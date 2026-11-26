/** Local development smoke test using Chromium's native DevTools protocol. */
import { spawn, spawnSync } from 'node:child_process';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const [ wpLoad, browserPath ] = process.argv.slice( 2 );
if ( ! wpLoad || ! browserPath ) {
	throw new Error( 'Usage: node tests/storefront-feedback-browser.mjs /path/to/wp-load.php /path/to/chrome' );
}
const helper = join( dirname( fileURLToPath( import.meta.url ) ), 'sensor-position-fixture.php' );
const php = ( mode, input ) => {
	const result = spawnSync( process.env.WCS_TEST_PHP || 'php', [ helper, wpLoad, mode, process.argv[4] ], { input: input ? JSON.stringify( input ) : '', encoding: 'utf8', windowsHide: true } );
	if ( result.status !== 0 ) { throw new Error( result.stderr || result.error?.message || 'Fixture helper failed' ); }
	return JSON.parse( result.stdout );
};
const pause = ( ms ) => new Promise( ( resolve ) => setTimeout( resolve, ms ) );
let fixture;
let browser;
let socket;
let directory;
let checks = 0;
const check = ( valid, label ) => {
	if ( ! valid ) { throw new Error( 'FAIL: ' + label ); }
	console.log( 'PASS: ' + label );
	checks++;
};
try {
	fixture = php( 'setup' );
	fixture.imports = [];
	if ( fixture.config_error ) { throw new Error( 'Fixture configuration: ' + fixture.config_error ); }
	directory = await mkdtemp( join( tmpdir(), 'wcs-layout-browser-' ) );
	browser = spawn( browserPath, [ '--headless=new', '--remote-debugging-port=0', '--no-first-run', '--no-default-browser-check', '--user-data-dir=' + join( directory, 'profile' ), 'about:blank' ], { windowsHide: true, stdio: 'ignore' } );
	let port;
	for ( let i = 0; i < 100; i++ ) {
		try { port = ( await readFile( join( directory, 'profile', 'DevToolsActivePort' ), 'utf8' ) ).split( '\n' )[0]; break; } catch { await pause( 100 ); }
	}
	if ( ! port ) { throw new Error( 'Browser did not start' ); }
	const targets = await ( await fetch( 'http://127.0.0.1:' + port + '/json/list' ) ).json();
	socket = new WebSocket( targets.find( ( target ) => target.type === 'page' ).webSocketDebuggerUrl );
	await new Promise( ( resolve, reject ) => { socket.addEventListener( 'open', resolve, { once: true } ); socket.addEventListener( 'error', reject, { once: true } ); } );
	let sequence = 0;
	const pending = new Map();
	const exceptions = [];
	socket.addEventListener( 'message', ( event ) => {
		const message = JSON.parse( event.data );
		if ( message.method === 'Page.javascriptDialogOpening' ) {
			socket.send( JSON.stringify( { id: ++sequence, method: 'Page.handleJavaScriptDialog', params: { accept: true } } ) );
		}
		if ( message.method === 'Runtime.exceptionThrown' ) { exceptions.push( message.params.exceptionDetails.text ); }
		const request = pending.get( message.id );
		if ( request ) { pending.delete( message.id ); message.error ? request.reject( new Error( message.error.message ) ) : request.resolve( message.result ); }
	} );
	const send = ( method, params = {} ) => new Promise( ( resolve, reject ) => {
		const id = ++sequence;
		pending.set( id, { resolve, reject } );
		socket.send( JSON.stringify( { id, method, params } ) );
	} );
	const evaluate = async ( expression ) => {
		const result = await send( 'Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true, userGesture: true } );
		if ( result.exceptionDetails ) { throw new Error( result.exceptionDetails.exception?.description || result.exceptionDetails.text ); }
		return result.result.value;
	};
	const waitFor = async ( expression ) => {
		for ( let i = 0; i < 450; i++ ) {
			try { const value = await evaluate( expression ); if ( value ) { return value; } }
			catch ( error ) { if ( ! /context|navigat/i.test( error.message ) ) { throw error; } }
			await pause( 100 );
		}
		const diagnostic = await evaluate( '({path:location.pathname,ready:document.readyState,forms:document.querySelectorAll(".wcs-configurator").length,text:document.body.innerText.slice(0,500)})' );
		throw new Error( 'Timed out waiting for page state: ' + JSON.stringify( diagnostic ) );
	};
	const navigate = async ( url ) => {
		await send( 'Page.navigate', { url } );
		await waitFor( 'document.readyState === "complete" && location.href === ' + JSON.stringify( url ) );
	};
	await send( 'Page.enable' );
	await send( 'Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false } );
	await send( 'Runtime.enable' );
	await send( 'Network.enable' );
	// Use a fresh guest cart for this smoke test.

await navigate(fixture.base);
await evaluate('document.body.innerHTML='+JSON.stringify(fixture.html));
await evaluate('(async()=>{const base='+JSON.stringify(fixture.base)+';const link=document.createElement("link");link.rel="stylesheet";link.href=base+"wp-content/plugins/woo-spiegelloft-configurator/assets/css/storefront.css";document.head.append(link);for(const src of ["wp-includes/js/jquery/jquery.min.js","wp-content/plugins/woo-spiegelloft-configurator/assets/js/storefront-rules.js","wp-content/plugins/woo-spiegelloft-configurator/assets/js/pricing.js","wp-content/plugins/woo-spiegelloft-configurator/assets/js/storefront.js"]){await new Promise((resolve,reject)=>{const s=document.createElement("script");s.src=base+src;s.onload=resolve;s.onerror=reject;document.head.append(s)})}})()');
await waitFor('!!window.jQuery && !!jQuery(".wcs-cart-endpoint").length');
await evaluate('document.body.style.padding="40px";document.querySelector(".wcs-configurator").style.width="620px"');
check(await evaluate('document.querySelector(".wcs-configurator").noValidate'), 'Native validation uses popup handling');
// Optional earlier script for confirming this regression fails before the fix.
if (process.argv[5]) {
 await evaluate(await readFile(process.argv[5], 'utf8'));
 await evaluate('jQuery(".wcs-configurator").each(function(){jQuery(this).data("wcsRuleEngine",new WCSRuleEngine(jQuery(this)));jQuery(this).data("wcsRuleEngine").evaluate()});void 0');
}
await evaluate('window.feedbackTestMarker = true');
for (const colour of ['neutral','warm','cold']) {
 await evaluate('jQuery(".wcs-customer-field-select").filter(function(){return String(jQuery(this).data("key")).endsWith(".light-color")}).val('+JSON.stringify(colour)+').trigger("change");void 0');
 for (const sensorType of ['touch-sensor','toggle-switch']) {
  await evaluate('window.sensor=jQuery(".wcs-customer-field-select").filter(function(){return String(jQuery(this).data("key")).endsWith(".switch-sensor")});sensor.next(".wcs-custom-select").find(".wcs-custom-select__option[data-value='+sensorType+']").trigger("click");window.beforeTotal=jQuery(".wcs-configurator").data("current-total");void 0');
  for (const position of ['unten-links','unten-mitte','unten-rechts']) {
   await evaluate('window.pos=jQuery(".wcs-customer-field-select").filter(function(){return String(jQuery(this).data("key")).endsWith(".position-of-switch-sensor")});pos.next(".wcs-custom-select").find(".wcs-custom-select__option[data-value='+position+']").trigger("click");void 0');
   check(await evaluate('sensor.val()==='+JSON.stringify(sensorType)+' && pos.val()==='+JSON.stringify(position)+' && jQuery(".wcs-configurator").data("current-total")===beforeTotal && JSON.parse(jQuery(".wcs-selections-input").val())[sensor.data("key")]==='+JSON.stringify(sensorType)),colour+' / '+sensorType+' / '+position+' preserves sensor charge and submitted selection');
  }
 }
}
await evaluate('sensor.next(".wcs-custom-select").find(".wcs-custom-select__option[data-value=touch-sensor]").trigger("click");jQuery(".wcs-customer-field-select").filter(function(){return String(jQuery(this).data("key")).endsWith(".position-of-switch-sensor")}).next(".wcs-custom-select").find(".wcs-custom-select__option[data-value=unten-mitte]").trigger("click");void 0');
await evaluate('window.addedEvents=0;jQuery(document.body).on("added_to_cart.sensor",()=>window.addedEvents++);jQuery(".wcs-configurator").trigger("submit");void 0');
await waitFor('!jQuery(".wcs-configurator").data("wcsSubmitting")');
check(await evaluate('window.addedEvents===1'),'Touch Sensor and its position successfully add to the real guest cart');
const cart=await evaluate('(async()=>await(await fetch('+JSON.stringify(fixture.base+'wp-json/wc/store/v1/cart')+')).json())()');
const item=cart.items.find(row=>row.id===fixture.product_id);
check(Number(item.prices.price)/Math.pow(10,item.prices.currency_minor_unit)===122.99,'Real guest cart retains the Touch Sensor cents: 83 + 39.99 = 122.99');
check(await evaluate('jQuery(".wcs-configurator").data("current-total")===122.99'),'Configurator retains the same 122.99 total');
await pause(1300);
check(await evaluate('jQuery(".wcs-configurator__footer-price").text().includes("122.99")'),'Visible configurator total includes both decimal places');
check(exceptions.length===0,'No uncaught browser exceptions');
console.log('Completed '+checks+' exported sensor position browser checks.');
} finally {
	if ( fixture ) { php( 'cleanup', fixture ); }
	if ( socket ) { socket.close(); }
	if ( browser ) { browser.kill(); }
	if ( directory ) {
		const profile = join( directory, 'profile' );
		if ( dirname( profile ) !== directory || !directory.startsWith( tmpdir() ) ) { throw new Error( 'Unexpected temporary browser path' ); }
		await pause( 500 );
		await rm( profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 } );
	}
}
