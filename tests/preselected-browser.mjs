/** Local development smoke test using Chromium's native DevTools protocol. */
import { spawn, spawnSync } from 'node:child_process';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const [ wpLoad, browserPath ] = process.argv.slice( 2 );
if ( ! wpLoad || ! browserPath ) {
	throw new Error( 'Usage: node tests/preselected-browser.mjs /path/to/wp-load.php /path/to/chrome' );
}
const helper = join( dirname( fileURLToPath( import.meta.url ) ), 'preselected-fixture.php' );
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

await send('Network.setCookies',{cookies:fixture.cookies.map(cookie=>({...cookie,url:fixture.base,path:new URL(fixture.base).pathname,httpOnly:true}))});
await navigate(fixture.base+'wp-admin/post.php?post='+fixture.option_id+'&action=edit');
await waitFor('!!window.jQuery && jQuery(".wcs-option-preselected").length>=7');
check(await evaluate('jQuery(".wcs-option-preselected").first().attr("name").endsWith("[preselected]")'), 'Default toggles have indexed save names');
await evaluate('window.optionRows=jQuery(".wcs-customer-fields > .wcs-customer-field-list > .wcs-customer-field-row").first().find("> .wcs-customer-field-box > .wcs-customer-field-options > .wcs-customer-field-option");optionRows.eq(0).children(".wcs-option-default-control").find("input").prop("checked",true).trigger("change");optionRows.eq(1).children(".wcs-option-default-control").find("input").prop("checked",true).trigger("change");void 0');
check(await evaluate('!optionRows.eq(0).find("> .wcs-option-default-control input").prop("checked") && optionRows.eq(1).find("> .wcs-option-default-control input").prop("checked")'), 'Selecting a default unchecks the previous option in the same dropdown');
check(await evaluate('optionRows.eq(1).find(".wcs-customer-option-position-fields .wcs-option-preselected:checked").length===1'), 'Selecting a parent default preserves the independent nested default');
await evaluate('document.querySelector("#publish").click()');
await pause(1500);
await waitFor('document.readyState==="complete" && jQuery("#message.updated, .notice-success").length>0');
await navigate(fixture.base+'wp-admin/post.php?post='+fixture.option_id+'&action=edit');
check(await evaluate('jQuery(".wcs-customer-fields > .wcs-customer-field-list > .wcs-customer-field-row").first().find("> .wcs-customer-field-box > .wcs-customer-field-options > .wcs-customer-field-option").eq(1).find("> .wcs-option-default-control input").prop("checked")'), 'Preselection persists after a real admin save and fresh page load');
const render = php('render',fixture);
check(render.option.customer_fields[0].options[1].preselected===true && render.option.customer_fields[0].options[0].preselected===false, 'Stored metadata contains exactly the chosen default');
await send('Network.clearBrowserCookies');
await navigate(fixture.base);
await evaluate('document.body.innerHTML='+JSON.stringify(render.html));
await evaluate('(async()=>{const base='+JSON.stringify(fixture.base)+';for(const src of ["wp-includes/js/jquery/jquery.min.js","wp-content/plugins/woo-spiegelloft-configurator/assets/js/storefront-rules.js","wp-content/plugins/woo-spiegelloft-configurator/assets/js/pricing.js","wp-content/plugins/woo-spiegelloft-configurator/assets/js/storefront.js"]){await new Promise((resolve,reject)=>{const s=document.createElement("script");s.src=base+src;s.onload=resolve;s.onerror=reject;document.head.append(s)})}})()');
await waitFor('jQuery(".wcs-customer-field-select").length>=3');
check(await evaluate('jQuery(".wcs-customer-field-select").filter(function(){return String(jQuery(this).data("key")).endsWith(".finish")}).val()==="mitte"'), 'Saved option is selected when the storefront opens');
check(await evaluate('jQuery(".wcs-customer-field-select").filter(function(){return String(jQuery(this).data("key")).endsWith(".finish")}).find("option").filter(function(){return this.value===""}).length===0'), 'Configured default has no empty placeholder option');
check(await evaluate('jQuery(".wcs-customer-field-select").filter(function(){return String(jQuery(this).data("key")).endsWith(".optional")}).val()==="" && jQuery(".wcs-customer-field-select").filter(function(){return String(jQuery(this).data("key")).endsWith(".optional")}).find("option").first().text()==="Keine Auswahl"'), 'Dropdown with no default starts at Keine Auswahl');
check(await evaluate('jQuery(".wcs-customer-field-select").filter(function(){return String(jQuery(this).data("key")).endsWith(".height")}).val()==="oben"'), 'Nested dropdown initializes its own default');
check(await evaluate('Object.values(JSON.parse(jQuery(".wcs-selections-input").val())).includes("mitte") && Object.values(JSON.parse(jQuery(".wcs-selections-input").val())).includes("oben") && jQuery(".wcs-configurator").data("current-total")===127'), 'Initial selections and price include the preselected option and nested value');
check(exceptions.length===0,'No uncaught browser exceptions');
console.log('Completed '+checks+' preselected browser checks.');
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
