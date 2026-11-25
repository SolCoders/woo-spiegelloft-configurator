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
	const upload = async (document) => {
		await navigate(fixture.base+'wp-admin/admin.php?page=wcs-layout-transfer');
		await evaluate('(()=>{const transfer=new DataTransfer();transfer.items.add(new File(['+JSON.stringify(JSON.stringify(document))+'],"layout.json",{type:"application/json"}));const input=document.querySelector("#wcs-layout-json");input.files=transfer.files;input.form.requestSubmit()})()');
		return await waitFor('document.readyState==="complete" && (document.querySelector("#wcs-layout-transfer-result.notice-success") ? "success" : document.querySelector("#wcs-layout-target") ? "choose" : false)');
	};
	const document = JSON.parse(download.body);
	document.options[0].meta._wcs_option_data.price=45;
	document.options[0].meta._wcs_price=45;
	document.options[0].meta._wcs_option_data.customer_fields[0].options[0].preselected=true;
	check(await upload(document)==='success','Real multipart upload updates the sole matching template');
	const importedUrl = await evaluate('document.querySelector("#wcs-layout-transfer-result a").href');
	const importedId = Number(new URL(importedUrl).searchParams.get('post'));
	check(importedId===fixture.layout_id,'Update keeps the existing template ID');
	check(await evaluate('document.querySelector("#wcs-layout-transfer-result").textContent.includes("Layout updated")'),'Success notice identifies an update');
	let state = php('inspect',fixture);
	check(state.option.price===45 && state.assigned===fixture.layout_id,'Saved option price changes while product assignment stays connected');
	const originalOption=state.option_id;
	check(await upload(document)==='success' && php('inspect',fixture).option_id===originalOption,'Repeated multipart upload reuses the existing option');
	const duplicate=php('duplicate',fixture).id;
	fixture.imports.push(duplicate);
	document.options[0].meta._wcs_option_data.price=62;
	document.options[0].meta._wcs_price=62;
	check(await upload(document)==='choose','Duplicate names require target selection');
	check(await evaluate('document.querySelectorAll("#wcs-layout-target option").length===3'),'Target selector lists both matching templates and their IDs');
	check(php('inspect',fixture).option.price===45,'Pending duplicate-name upload has not changed the template');
	const pendingToken = await evaluate('document.querySelector("input[name=pending_token]").value');
	const nonce = await evaluate('document.querySelector("#wcs-layout-target").form.querySelector("input[name=_wpnonce]").value');
	await evaluate('(()=>{const s=document.querySelector("#wcs-layout-target");s.value='+fixture.layout_id+';s.form.requestSubmit()})()');
	await waitFor('document.readyState==="complete" && !!document.querySelector("#wcs-layout-transfer-result.notice-success")');
	state=php('inspect',fixture);
	const other=php('inspect',{...fixture,template_id:duplicate});
	check(state.option.price===62 && other.option.price===45 && state.option_id!==other.option_id,'Selected template receives a private updated option; duplicate stays unchanged');
	const replay = await evaluate('(async()=>{const f=new FormData();f.set("action","wcs_import_layout");f.set("_wpnonce",'+JSON.stringify(nonce)+');f.set("pending_token",'+JSON.stringify(pendingToken)+');f.set("target_template_id",'+JSON.stringify(String(fixture.layout_id))+');const r=await fetch('+JSON.stringify(fixture.base+'wp-admin/admin-post.php')+',{method:"POST",body:f});const d=new DOMParser().parseFromString(await r.text(),"text/html");return d.querySelector("#wcs-layout-transfer-result")?.textContent||""})()');
	check(replay.includes('expired'),'Consumed pending upload cannot be replayed');
	// Actual server-rendered configurator, independent of the local product theme.
	await navigate(fixture.base);
	await evaluate('document.body.innerHTML='+JSON.stringify(state.html));
	await evaluate('(async()=>{const base='+JSON.stringify(fixture.base)+';for(const src of ["wp-includes/js/jquery/jquery.min.js","wp-content/plugins/woo-spiegelloft-configurator/assets/js/storefront-rules.js","wp-content/plugins/woo-spiegelloft-configurator/assets/js/pricing.js","wp-content/plugins/woo-spiegelloft-configurator/assets/js/storefront.js"]){await new Promise((resolve,reject)=>{const s=document.createElement("script");s.src=base+src;s.onload=resolve;s.onerror=reject;document.head.append(s)})}})()');
	await waitFor('jQuery(".wcs-configurator").data("current-total")>=162');
	check(await evaluate('jQuery(".wcs-customer-field-select").first().val()==="brass"'),'Assigned product renders the imported default immediately');
	check(await evaluate('Object.values(JSON.parse(jQuery(".wcs-selections-input").val())).includes("browser-fixture")'),'Updated option is included in the actual storefront selections');
	document.template.title=fixture.marker+' New';
	check(await upload(document)==='success','Unmatched name still creates a template through real upload');
	const newId=Number(new URL(await evaluate('document.querySelector("#wcs-layout-transfer-result a").href')).searchParams.get('post'));
	fixture.imports.push(newId);
	check(newId!==fixture.layout_id && newId!==duplicate,'New-name import creates an independent layout');
	check(await evaluate('document.querySelector("#wcs-layout-transfer-result").textContent.includes("Layout created")'),'Success notice distinguishes creation from update');
	check(exceptions.length===0,'No uncaught browser exceptions');
	console.log('Completed '+checks+' transfer browser checks.');
} finally {
	if ( fixture ) { php( 'cleanup', fixture ); }
	if ( socket ) { socket.close(); }
	if ( browser ) { browser.kill(); }
	// Retain the screenshot for inspection; remove the disposable browser profile.
	if ( directory ) {
		const profile=join(directory,'profile');
		if(dirname(profile)!==directory || !directory.startsWith(tmpdir())) { throw new Error('Unexpected temporary browser path'); }
		await pause(500);
		await rm(profile,{recursive:true,force:true,maxRetries:5,retryDelay:200});
	}
}
