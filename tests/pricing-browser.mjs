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
const helper = join( dirname( fileURLToPath( import.meta.url ) ), 'pricing-browser-fixture.php' );
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
		const diagnostic = await evaluate( '({path:location.pathname,query:location.search,publish:jQuery("#publish")[0]?.disabled,form:jQuery("#publish")[0]?.form?.id,submitted:window.wcsTestSubmitted,prevented:window.wcsTestPrevented,invalid:Array.from(document.querySelectorAll("input:invalid,select:invalid")).map(x=>({name:x.name,reason:x.validationMessage})),errors:Array.from(document.querySelectorAll(".notice-error")).map(x=>x.textContent.trim())})' );
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

// Pricing scenarios.
	await navigate(fixture.base + 'wp-admin/post.php?post=' + fixture.layout_id + '&action=edit');
	check(await evaluate('Array.from(document.querySelectorAll("#wcs-admin-css, #wcs-pricing-js, #wcs-admin-pricing-js, #wcs-admin-template-js")).length === 4 && Array.from(document.querySelectorAll("#wcs-admin-css, #wcs-pricing-js, #wcs-admin-pricing-js, #wcs-admin-template-js")).every(el => /^1\\.0\\.4\\.\\d+$/.test(new URL(el.src || el.href).searchParams.get("ver")))'), 'Pricing assets have file-specific cache versions');
	await navigate(fixture.base + 'wp-admin/post.php?post=' + fixture.layout_id + '&action=edit');
	check(await evaluate('!!jQuery(".wcs-configure-pricing")[0]'), 'Configure pricing button is present');
	check(await evaluate('(() => { const b=jQuery(".wcs-configure-pricing")[0].getBoundingClientRect(), c=jQuery(".wcs-dimensions-grid")[0].getBoundingClientRect(); return c.right-b.right < 24; })()'), 'Configure pricing aligns to the card right edge');
	await evaluate('jQuery(".wcs-configure-pricing")[0].click()');
	check(await evaluate('getComputedStyle(document.querySelector(".wcs-pricing-overlay")).position === "fixed" && document.querySelector(".wcs-pricing-dialog").getBoundingClientRect().height > 0'), 'Modal opens visibly after a cached page reload');
	check(await evaluate('document.querySelectorAll(".wcs-pricing-tabs button").length === 3'), 'Two dimension tabs and Shipping');
	check(await evaluate('document.querySelectorAll("[data-rule]").length === 43'), 'Complete default threshold table');
	await evaluate('jQuery("[data-prop=preview]").val(525).trigger("input")');
	check(await evaluate('jQuery(".wcs-pricing-result")[0].textContent.includes("32.50")'), 'Preview adds matching thresholds');
	await evaluate('jQuery("[data-rule=0] [data-prop=amount]").val(99).trigger("input"); jQuery(".wcs-pricing-cancel")[0].click(); jQuery(".wcs-configure-pricing")[0].click()');
	check(await evaluate('Number(jQuery("[data-rule=0] [data-prop=amount]")[0].value) === 26'), 'Cancel discards edits');
	await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'ArrowRight', code: 'ArrowRight' });
	check(await evaluate('jQuery("[aria-selected=true]").data("tab") === 1'), 'Arrow key changes pricing tab');
	await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Home', code: 'Home' });
	await evaluate('jQuery("[data-action=add-rule]").first().click()');
	check(await evaluate('jQuery("[data-rule]").length === 44'), 'Dimension rule can be added');
	await evaluate('jQuery("[data-rule]").eq(1).find("[data-action=remove-rule]").click()');
	check(await evaluate('jQuery("[data-rule]").length === 43'), 'Dimension rule can be removed');
	await send('Emulation.setDeviceMetricsOverride', { width: 375, height: 812, deviceScaleFactor: 1, mobile: true });
	check(await evaluate('jQuery(".wcs-pricing-dialog")[0].scrollWidth <= jQuery(".wcs-pricing-dialog")[0].clientWidth'), 'Modal fits narrow screens without horizontal overflow');
	await writeFile(join(directory, 'dimension-pricing-mobile.png'), Buffer.from((await send('Page.captureScreenshot', { format: 'png' })).data,'base64'));
	await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
	await evaluate('jQuery(".wcs-pricing-apply")[0].focus()');
	await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
	check(await evaluate('document.activeElement.matches(".wcs-pricing-close")'), 'Keyboard focus remains inside the modal');
	await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
	check(await evaluate('jQuery(".wcs-pricing-overlay")[0].hidden'), 'Escape dismisses the modal');
	await evaluate('jQuery(".wcs-configure-pricing").click()');
	await evaluate('jQuery("[data-tab=2]")[0].click(); jQuery("[data-prop=value-0]").val(1300).trigger("input"); jQuery("[data-prop=value-1]").val(1400).trigger("input")');
	check(await evaluate('jQuery(".wcs-pricing-result")[0].textContent.includes("103.00")'), 'Shipping preview charges both orientations only once');
	await evaluate('jQuery("[data-prop=base-price]").val(200).trigger("input")');
	check(await evaluate('jQuery(".wcs-pricing-result").text().includes("136.00")'), 'Admin shipping preview uses the manually entered test base price');
	await evaluate('jQuery("[data-prop=base-price]").val(100).trigger("input")');
	await writeFile(join(directory, 'shipping-pricing.png'), Buffer.from((await send('Page.captureScreenshot', { format: 'png' })).data,'base64'));
	await evaluate('jQuery("[data-prop=enabled]").prop("checked",false).trigger("change")');
	check(await evaluate('jQuery(".wcs-pricing-result")[0].textContent.includes("0.00")'), 'Disabled shipping removes fees and markup');
	await evaluate('jQuery("[data-prop=enabled]").prop("checked",true).trigger("change"); jQuery(".wcs-pricing-apply")[0].click(); jQuery(".wcs-side-remove")[0].click()');
	check(await evaluate('document.querySelectorAll(".wcs-side-row").length === 1 && jQuery(".wcs-side-remove")[0].disabled'), 'Final size field cannot be removed');
	await evaluate('jQuery(".wcs-side-label").val("Diameter").trigger("input"); jQuery(".wcs-side-key").val("diameter").trigger("input"); jQuery(".wcs-configure-pricing")[0].click()');
	check(await evaluate('jQuery("[data-tab=0]")[0].textContent === "Diameter"'), 'Tab follows entered size label');
	await evaluate('jQuery("[data-tab=1]")[0].click(); jQuery("[data-prop=value-0]").val(1300).trigger("input")');
	check(await evaluate('document.querySelectorAll(".wcs-pricing-preview [data-prop^=value-]").length === 1 && jQuery(".wcs-pricing-result")[0].textContent.includes("103.00")'), 'One preview field supplies both diameter dimensions');
	await evaluate('jQuery(".wcs-pricing-cancel")[0].click(); jQuery(".wcs-side-add")[0].click(); jQuery(".wcs-side-row").last().find(".wcs-side-label").val("Second").trigger("input"); jQuery(".wcs-side-row").last().find(".wcs-side-key").val("second").trigger("input"); jQuery(".wcs-side-add")[0].click(); jQuery(".wcs-side-row").eq(1).find(".wcs-side-label").val("Third").trigger("input"); jQuery(".wcs-side-row").eq(1).find(".wcs-side-key").val("third").trigger("input"); jQuery(".wcs-configure-pricing")[0].click(); jQuery("[data-tab=3]")[0].click()');
	check(await evaluate('jQuery("[data-prop=enabled]")[0].disabled && jQuery(".wcs-pricing-result")[0].textContent.includes("0.00")'), 'More than two fields disables shipping');
	await evaluate('jQuery(".wcs-pricing-cancel")[0].click(); jQuery(".wcs-side-remove").eq(1).click(); jQuery(".wcs-side-remove").eq(1).click(); jQuery(".wcs-configure-pricing")[0].click(); jQuery("[data-rule=0] [data-prop=amount]").val(30).trigger("input"); jQuery(".wcs-pricing-apply")[0].click(); jQuery(".wcs-side-label").val("Round mirror").trigger("input"); jQuery(".wcs-side-key").val("round").trigger("input"); jQuery(".wcs-configure-pricing")[0].click()');
	check(await evaluate('Number(jQuery("[data-rule=0] [data-prop=amount]")[0].value) === 30'), 'Rule association survives label and key edits');
	await evaluate('jQuery(".wcs-pricing-cancel").click(); jQuery(".wcs-side-key").val("round__mirror").trigger("input")');
	check(await evaluate('JSON.parse(jQuery("#wcs-pricing-settings").val()).dimensions.side_round__mirror[0].amount === 30'), 'Underscores in field keys retain their pricing association');
	await evaluate('jQuery(".wcs-side-key").val("round").trigger("input"); jQuery(".wcs-configure-pricing").click()');
	await writeFile(join(directory, 'dimension-pricing.png'), Buffer.from((await send('Page.captureScreenshot', { format: 'png' })).data,'base64'));
	await evaluate('jQuery(".wcs-pricing-close")[0].click()');
	check(await evaluate('document.activeElement.matches(".wcs-configure-pricing")'), 'Closing restores focus to the button');
	await evaluate('jQuery("#publish")[0].click()');
	await waitFor('document.readyState === "complete" && !!jQuery("#message.updated")[0]');
	check(await evaluate('JSON.parse(jQuery("#wcs-pricing-settings")[0].value).dimensions.side_round[0].amount === 30 && document.querySelectorAll(".wcs-side-row").length === 1'), 'Real template save retains pricing and one size field');
	await send('Page.navigate', { url: fixture.product_url });
	await waitFor('document.readyState === "complete" && !!jQuery(".wcs-configurator")[0]');
	await evaluate('jQuery(".wcs-dimension-input").val(525).trigger("change")');
	await waitFor('!jQuery(".wcs-configurator__price").hasClass("is-counting")');
	const storefront = await evaluate('(() => { const w=jQuery(".wcs-configurator");return {total:w.data("displayPrice"), text:w.find(".wcs-configurator__price").text(), selections:JSON.parse(w.find(".wcs-selections-input").val())}; })()');
	const response = await evaluate('(async()=>{const r=await fetch(' + JSON.stringify(fixture.base + 'wp-json/wcs/v1/price') + ',{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({product_id:' + fixture.product_id + ',selections:' + JSON.stringify(storefront.selections) + '})});return await r.json()})()');
	if (!(response.price > 100 && storefront.text.includes(Number(response.price).toFixed(2)))) console.log('Pricing diagnostic:', JSON.stringify({ storefront, response }));
	check(response.price > 100 && storefront.text.includes(Number(response.price).toFixed(2)), 'Storefront and real price API agree');
	const cartResult = await evaluate('(async()=>{const f=new FormData();f.set("add-to-cart",' + fixture.product_id + ');f.set("quantity","1");f.set("wcs_selections",' + JSON.stringify(JSON.stringify(storefront.selections)) + ');const r=await fetch(location.href,{method:"POST",body:f});const d=new DOMParser().parseFromString(await r.text(),"text/html");return {errors:d.querySelector(".woocommerce-error")?.textContent||""};})()');
	check(!cartResult.errors, 'Fresh configured product adds to a real cart');
	const cart = await evaluate('(async()=>{const r=await fetch(' + JSON.stringify(fixture.base + 'wp-json/wc/store/v1/cart') + ');return await r.json()})()');
	const line = cart.items?.find(item => item.id === fixture.product_id);
	const precision = Math.pow(10, line?.prices.currency_minor_unit || 0);
	check(response.price === Math.round((100 + 36.5 + 15 + 36 + 100 * 0.33) * precision) / precision, 'Live total marks up only the WooCommerce product price');
	check(!!line && Number(line.prices.price) / Math.pow(10,line.prices.currency_minor_unit) === response.price, 'Cart uses the same configured unit price');
	await send('Page.navigate', { url: fixture.base + 'checkout/' });
	await waitFor('document.readyState === "complete" && location.pathname.includes("checkout")');
	await waitFor('document.body.textContent.includes(' + JSON.stringify(fixture.marker) + ')');
	check(true, 'Configured product reaches checkout');
	check(await evaluate('Array.from(document.querySelectorAll(".wc-block-components-order-summary-item, .cart_item")).some(el => el.textContent.includes(' + JSON.stringify(fixture.marker) + ') && el.textContent.includes(' + JSON.stringify(String(response.price)) + '))'), 'Checkout order summary retains the configured price');
	check(exceptions.length === 0, 'No uncaught browser exceptions');
	console.log('Completed ' + checks + ' pricing browser checks. Screenshots: ' + directory);
} finally {
	if ( fixture ) { php( 'cleanup', fixture ); }
	if ( socket ) { socket.close(); }
	if ( browser ) { browser.kill(); }
	// Retain the screenshot for inspection; remove the disposable browser profile.
	if ( directory ) { await pause( 500 ); await rm( join( directory, 'profile' ), { recursive: true, force: true, maxRetries: 5, retryDelay: 200 } ); }
}
