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
const helper = join( dirname( fileURLToPath( import.meta.url ) ), 'storefront-feedback-fixture.php' );
const php = ( mode, input ) => {
	const result = spawnSync( process.env.WCS_TEST_PHP || 'php', [ helper, wpLoad, mode ], { input: input ? JSON.stringify( input ) : '', encoding: 'utf8', windowsHide: true } );
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
check(await evaluate('jQuery(".wcs-size-box,.wcs-size-sidewise").text().includes("Breite") && jQuery(".wcs-size-box,.wcs-size-sidewise").text().includes("Höhe")'), 'Stored English measurement labels display in German');
check(await evaluate('!Array.from(document.querySelectorAll(".wcs-custom-select__value")).some(el=>el.textContent==="None")'), 'Empty dropdowns use German text');
await evaluate('window.feedbackTestMarker = true');
// Ensure the content actually overflows for checking scrollbar visibility.
await evaluate('window.scrollStyle=document.createElement("style");scrollStyle.textContent=".wcs-configurator__content{height:160px!important;flex:none!important;overflow-y:auto!important}";document.head.append(scrollStyle)');
await send('Input.dispatchMouseEvent', {type:'mouseMoved', x:1, y:1});
check(await evaluate('getComputedStyle(document.querySelector(".wcs-configurator__content")).scrollbarColor === "rgba(0, 0, 0, 0) rgba(0, 0, 0, 0)"'), 'Desktop scrollbar is transparent outside the main container');
const hover = await evaluate('(()=>{const r=document.querySelector(".wcs-configurator").getBoundingClientRect();return{x:r.x+20,y:r.y+20}})()');
await send('Input.dispatchMouseEvent', {type:'mouseMoved',...hover});
check(await evaluate('getComputedStyle(document.querySelector(".wcs-configurator__content")).scrollbarColor.includes("rgb(10, 135, 140)")'), 'Desktop scrollbar appears on container hover');
await send('Input.dispatchMouseEvent', {type:'mouseMoved',x:1,y:1});
check(await evaluate('getComputedStyle(document.querySelector(".wcs-configurator__content")).scrollbarColor === "rgba(0, 0, 0, 0) rgba(0, 0, 0, 0)"'), 'Desktop scrollbar disappears on mouse leave');
await evaluate('scrollStyle.remove()');
await evaluate('jQuery(".wcs-dimension-input").first().val("");jQuery(".wcs-configurator").trigger("submit");void 0');
check(await evaluate('!!document.querySelector(".wcs-range-popup:not([hidden])") && !!window.feedbackTestMarker && !document.querySelector(".woocommerce-error")'), 'Missing measurement shows a popup without page reload');
await evaluate('jQuery(".wcs-dimension-input").each(function(){jQuery(this).val(this.min||400).trigger("change")});jQuery(".wcs-choice-select").each(function(){this.selectedIndex=this.options.length-1;jQuery(this).trigger("change")});jQuery(".wcs-customer-field-select").each(function(){this.selectedIndex=this.options.length-1;jQuery(this).trigger("change")});void 0');
// Exercise server-only rejection through the real AJAX transport.
await evaluate('jQuery(".wcs-configurator input[name=nonce]").val("expired");jQuery(".wcs-configurator").trigger("submit");void 0');
await waitFor('!jQuery(".wcs-configurator").data("wcsSubmitting") && jQuery(".wcs-configurator__footer .wcs-range-popup:not([hidden])").length');
check(await evaluate('jQuery(".wcs-configurator__footer .wcs-range-popup").text().includes("Sitzung") && !!window.feedbackTestMarker'), 'Expired nonce appears in German without navigation');
// Restore the nonce rendered by the real shortcode.
await evaluate('jQuery(".wcs-configurator input[name=nonce]").val(new DOMParser().parseFromString('+JSON.stringify(fixture.html)+',"text/html").querySelector("input[name=nonce]").value);void 0');
const serverError = await evaluate('(async()=>{const w=jQuery(".wcs-configurator");const d=new URLSearchParams(w.serialize());d.set("wcs_selections",JSON.stringify({width:99999,height:400,side_width:99999,side_height:400}));const r=await fetch(w.find(".wcs-cart-endpoint").val(),{method:"POST",body:d});return await r.json()})()');
check(serverError.success===false && serverError.data.messages.length>0, 'Server dimension validation returns JSON messages');
check(serverError.data.messages[0].includes('Bitte geben Sie'), 'Server measurement error is German');
check(await evaluate('jQuery(".wcs-customer-number-input").filter(function(){return String(jQuery(this).data("key")).endsWith("empty_distance")}).val()===""'), 'Optional number begins empty');
check(await evaluate('jQuery(".wcs-customer-number-input").filter(function(){return String(jQuery(this).data("key")).endsWith("minimum_distance")}).val()==="150"'), 'Numeric field starts at its configured minimum');
check(await evaluate('jQuery(".wcs-customer-number-input").filter(function(){return String(jQuery(this).data("key")).endsWith("zero_minimum")}).val()==="0"'), 'A zero minimum also initializes the field');
await evaluate('window.addedEvents=0;jQuery(document.body).on("added_to_cart.feedback",()=>window.addedEvents++);jQuery(".wcs-configurator").trigger("submit");jQuery(".wcs-configurator").trigger("submit");void 0');
await waitFor('!jQuery(".wcs-configurator").data("wcsSubmitting")');
check(await evaluate('window.addedEvents===1'), 'Valid configuration adds once despite repeated submit');
const cart = await evaluate('(async()=>{const r=await fetch('+JSON.stringify(fixture.base+'wp-json/wc/store/v1/cart')+');return await r.json()})()');
check(cart.items?.some(item=>item.id===fixture.product_id && item.quantity===1), 'Real guest cart contains the configured product');
const attributes = cart.items.find(item=>item.id===fixture.product_id).item_data;
check(attributes.some(row=>row.key==='Breite') && attributes.some(row=>row.key==='Höhe'), 'Real cart measurement attributes are German');
check(!attributes.some(row=>row.key==='Zusätzlicher Hinweis'||row.key==='Zusätzlicher Abstand'), 'Real cart omits blank text and numeric options');
check(attributes.some(row=>row.key==='Seitenabstand' && row.value==='150') && attributes.some(row=>row.key==='Nullwert' && row.value==='0'), 'Real cart retains initialized minimum values');
check(!attributes.some(row=>String(row.value).includes('Zusatzoption')), 'Real cart omits the internal parent option and keeps the visible field labels');
await navigate(fixture.product_url);
check(await evaluate('!document.querySelector(".woocommerce-error")'), 'Handled errors are not queued for the next page load');
check(exceptions.length===0, 'No uncaught browser exceptions');
console.log('Completed '+checks+' storefront feedback browser checks.');
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
