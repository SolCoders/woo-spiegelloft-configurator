/** Local development smoke test using Chromium's native DevTools protocol. */
import { spawn, spawnSync } from 'node:child_process';
import { mkdtemp, readFile, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const [ wpLoad, browserPath ] = process.argv.slice( 2 );
if ( ! wpLoad || ! browserPath ) {
	throw new Error( 'Usage: node tests/layout-transfer-browser.mjs /path/to/wp-load.php /path/to/chrome' );
}
const helper = join( dirname( fileURLToPath( import.meta.url ) ), 'layout-transfer-browser-fixture.php' );
const php = ( mode, input ) => {
	const result = spawnSync( 'php', [ helper, wpLoad, mode ], { input: input ? JSON.stringify( input ) : '', encoding: 'utf8', windowsHide: true } );
	if ( result.status !== 0 ) { throw new Error( result.stderr || 'Fixture helper failed' ); }
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
		const diagnostic = await evaluate( '({path:location.pathname,query:location.search,publish:document.querySelector("#publish")?.disabled,form:document.querySelector("#publish")?.form?.id,submitted:window.wcsTestSubmitted,prevented:window.wcsTestPrevented,invalid:Array.from(document.querySelectorAll("input:invalid,select:invalid")).map(x=>({name:x.name,reason:x.validationMessage})),errors:Array.from(document.querySelectorAll(".notice-error")).map(x=>x.textContent.trim())})' );
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
	await send( 'Network.setCookies', { cookies: fixture.cookies.map( ( cookie ) => ( { ...cookie, url: fixture.base, path: new URL( fixture.base ).pathname, httpOnly: true } ) ) } );
	await navigate( fixture.base + 'wp-admin/edit.php?post_type=wcs_template' );
	check( await evaluate( '!!document.querySelector("#wcs-import-layout.page-title-action")' ), 'Listing has Import Layout beside Add New' );
	const exportUrl = await evaluate( 'document.querySelector("#post-' + fixture.layout_id + ' .wcs_export a")?.href' );
	check( !! exportUrl, 'Listing offers Export JSON for the fixture layout' );
	const download = await evaluate( '(async () => {const r=await fetch(' + JSON.stringify( exportUrl ) + ');return {status:r.status, disposition:r.headers.get("content-disposition"), body:await r.text()};})()' );
	check( download.status === 200 && download.disposition?.includes( '.json' ) && JSON.parse( download.body ).format === 'wcs-mirror-layout', 'Authenticated export downloads a valid JSON package' );
	check( JSON.parse( download.body ).version === 2 && ! Object.hasOwn( JSON.parse( download.body ), 'media' ), 'JSON uses the settings-only format without a media section' );
	const invalidExport = await evaluate( '(async () => {const u=new URL(' + JSON.stringify( exportUrl ) + ');u.searchParams.set("_wpnonce","invalid");return (await fetch(u)).status;})()' );
	check( invalidExport === 403, 'Export with an invalid nonce is rejected' );
	await navigate( fixture.base + 'wp-admin/post.php?post=' + fixture.layout_id + '&action=edit' );
	check( await evaluate( 'document.querySelectorAll("#wcs_layout_transfer a.button").length === 2' ), 'Individual layout editor has both transfer buttons' );
	const screenshot = await send( 'Page.captureScreenshot', { format: 'png' } );
	await writeFile( join( directory, 'layout-editor.png' ), Buffer.from( screenshot.data, 'base64' ) );
	await navigate( fixture.base + 'wp-admin/admin.php?page=wcs-layout-transfer' );
	check( await evaluate( '!!document.querySelector("label[for=wcs-layout-json]") && !!document.querySelector("input[type=file][required]")' ), 'Shared import page has a labelled required JSON upload' );
	const invalidImport = await evaluate( '(async () => {const f=new FormData();f.set("action","wcs_import_layout");f.set("_wpnonce","invalid");return (await fetch(' + JSON.stringify( fixture.base + 'wp-admin/admin-post.php' ) + ',{method:"POST",body:f})).status;})()' );
	check( invalidImport === 403, 'Import with an invalid nonce is rejected' );
	await evaluate( '(() => {document.querySelector("#wcs-layout-transfer-result")?.remove();const transfer=new DataTransfer();transfer.items.add(new File([' + JSON.stringify( download.body ) + '],"layout.json",{type:"application/json"}));const input=document.querySelector("#wcs-layout-json");input.files=transfer.files;input.form.requestSubmit();})()' );
	const importedUrl = await waitFor( 'document.readyState === "complete" && document.querySelector("#wcs-layout-transfer-result.notice-success a")?.href' );
	const importedId = Number( new URL( importedUrl ).searchParams.get( 'post' ) );
	fixture.imports.push( importedId );
	check( importedId && importedId !== fixture.layout_id, 'Real multipart upload creates a new layout and redirects to a success notice' );
	check( await evaluate( 'document.querySelector("#wcs-layout-transfer-result").textContent.includes("Upload the images separately")' ), 'Import feedback asks the client to upload images separately' );
	await navigate( importedUrl );
	check( await evaluate( 'document.querySelector("#title").value === ' + JSON.stringify( fixture.marker ) ), 'Success link opens the imported layout editor' );
	const saveUrl = await evaluate( 'document.querySelector("#post").action' );
	await evaluate( 'document.querySelector("#post").addEventListener("submit",e=>{window.wcsTestSubmitted=true;setTimeout(()=>window.wcsTestPrevented=e.defaultPrevented,0)});document.querySelector("#publish").click()' );
	await waitFor( 'document.readyState === "complete" && !!document.querySelector("#message.updated")' );
	check( !! saveUrl, 'Imported layout saves through the real WordPress editor' );
	const importedOptionId = await evaluate( 'Number(document.querySelector(".wcs-option-table input[type=checkbox]:checked")?.value)' );
	await navigate( fixture.base + 'wp-admin/post.php?post=' + importedOptionId + '&action=edit' );
	check( await evaluate( 'document.querySelector(".wcs-customer-field-key")?.value === "finish" && document.querySelector(".wcs-customer-field-option-value")?.value === "brass"' ), 'Imported option editor preserves semantic keys independently of labels' );
	await evaluate( 'document.querySelector("#publish").click()' );
	await waitFor( 'document.readyState === "complete" && !!document.querySelector("#message.updated")' );
	check( await evaluate( 'document.querySelector(".wcs-customer-field-key")?.value === "finish" && document.querySelector(".wcs-customer-field-option-value")?.value === "brass"' ), 'Real option editor save retains imported field keys and dropdown values' );
	php( 'assign', fixture );
	await send( 'Page.navigate', { url: fixture.product_url } );
	await waitFor( 'document.readyState === "complete" && !!document.querySelector(".wcs-configurator")' );
	check( await evaluate( '!!document.querySelector(".wcs-configurator") && document.body.textContent.includes("Surface finish")' ), 'Assigned product renders the imported configurator in the browser' );
	check( exceptions.length === 0, 'No uncaught JavaScript exceptions in transfer and storefront flow' );
	console.log( 'Completed ' + checks + ' browser checks. Screenshot: ' + join( directory, 'layout-editor.png' ) );
} finally {
	if ( fixture ) { php( 'cleanup', fixture ); }
	if ( socket ) { socket.close(); }
	if ( browser ) { browser.kill(); }
	// Retain the screenshot for inspection; remove the disposable browser profile.
	if ( directory ) { await pause( 500 ); await rm( join( directory, 'profile' ), { recursive: true, force: true, maxRetries: 5, retryDelay: 200 } ); }
}
