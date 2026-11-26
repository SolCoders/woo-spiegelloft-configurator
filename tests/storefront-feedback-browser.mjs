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
check(await evaluate('jQuery(".wcs-custom-select__option[data-value=brass] > span").first().text()==="Klemmprofil + Stabilisationsstange 900 mm, kürzbar, hell verchromt"'), 'Dropdown retains the full option name including its plus sign without appending the price to the label');
await evaluate('window.mirrorField=jQuery(".wcs-customer-field-select").filter(function(){return String(jQuery(this).data("key")).endsWith(".make-up-mirror")});window.litButton=mirrorField.next(".wcs-custom-select").find("[data-value=beleuchtet]");window.widthField=jQuery(".wcs-dimension-input").first();window.heightField=jQuery(".wcs-dimension-input").eq(1);void 0');
check(await evaluate('mirrorField.find("option[value=beleuchtet]").prop("disabled") && litButton.prop("disabled") && litButton.hasClass("is-disabled") && litButton.attr("aria-disabled")==="true"'), 'Unavailable mirror option is visibly and natively disabled on first load');
await evaluate('litButton[0].click();litButton.trigger("click");void 0');
check(await evaluate('mirrorField.val()==="" && mirrorField.next(".wcs-custom-select").find(".wcs-custom-select__value").text()==="Keine Auswahl"'), 'Clicking an unavailable option cannot select it or change the placeholder');
await evaluate('widthField.val(600).trigger("change");void 0');
check(await evaluate('litButton.prop("disabled")'), 'Valid width cannot override an invalid height restriction');
await evaluate('heightField.val(600).trigger("change");void 0');
check(await evaluate('!litButton.prop("disabled") && !litButton.hasClass("is-disabled")'), 'Option becomes available once both minimum dimensions are satisfied');
await evaluate('litButton[0].click();void 0');
check(await evaluate('mirrorField.val()==="beleuchtet"'), 'An available mirror option can be selected');
await evaluate('widthField.val(400).trigger("change");void 0');
check(await evaluate('mirrorField.val()==="" && litButton.prop("disabled")'), 'Reducing width clears an unavailable selection and keeps it disabled');
await evaluate('widthField.val(600).trigger("change");heightField.val(400).trigger("change");void 0');
check(await evaluate('litButton.prop("disabled")'), 'Height restriction also works after changing the width');
await evaluate('widthField.val(400).trigger("change");void 0');
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
await evaluate('window.popupInput=jQuery(".wcs-dimension-input").first();popupInput.val(Number(popupInput.attr("max"))+1).trigger("change");void 0');
check(await evaluate('(()=>{const p=popupInput.closest(".wcs-size-control").find(".wcs-range-popup")[0],r=p.getBoundingClientRect(),c=document.querySelector(".wcs-configurator__content").getBoundingClientRect();return p.classList.contains("wcs-range-popup--below")&&r.top>=c.top&&r.bottom<=c.bottom&&p.contains(document.elementFromPoint(r.left+r.width/2,r.top+r.height/2))})()'), 'First-row popup opens below and is visible inside the scrolling container');
await evaluate('document.querySelector(".wcs-configurator__content").style.paddingTop="100px";popupInput.val(Number(popupInput.attr("max"))+1).trigger("change");void 0');
check(await evaluate('!popupInput.closest(".wcs-size-control").find(".wcs-range-popup").hasClass("wcs-range-popup--below")'), 'Popup opens above when there is space above and insufficient space below');
await evaluate('document.querySelector(".wcs-configurator__content").scrollTop=80');
await waitFor('popupInput.closest(".wcs-size-control").find(".wcs-range-popup").hasClass("wcs-range-popup--below")');
check(true,'Scrolling recalculates popup placement near the header');
await evaluate('document.querySelector(".wcs-configurator__content").style.paddingTop="";document.querySelector(".wcs-configurator__content").scrollTop=0;document.body.style.padding="8px";document.querySelector(".wcs-configurator").style.width="350px"');
await send('Emulation.setDeviceMetricsOverride',{width:375,height:800,deviceScaleFactor:1,mobile:true});
await evaluate('popupInput.val(Number(popupInput.attr("max"))+1).trigger("change");void 0');
check(await evaluate('(()=>{const p=popupInput.closest(".wcs-size-control").find(".wcs-range-popup")[0],r=p.getBoundingClientRect(),c=document.querySelector(".wcs-configurator__content").getBoundingClientRect();return r.left>=c.left&&r.right<=c.right&&r.top>=c.top&&r.bottom<=c.bottom})()'), 'Popup stays inside the scrolling area on mobile');
await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
await evaluate('document.body.style.padding="40px";document.querySelector(".wcs-configurator").style.width="620px"');
await evaluate('scrollStyle.remove()');
await evaluate('jQuery(".wcs-dimension-input").first().val("");jQuery(".wcs-configurator").trigger("submit");void 0');
check(await evaluate('!!document.querySelector(".wcs-range-popup:not([hidden])") && !!window.feedbackTestMarker && !document.querySelector(".woocommerce-error")'), 'Missing measurement shows a popup without page reload');
await evaluate('jQuery(".wcs-dimension-input").each(function(){jQuery(this).val(this.min||400).trigger("change")});jQuery(".wcs-choice-select").each(function(){this.selectedIndex=this.options.length-1;jQuery(this).trigger("change")});jQuery(".wcs-customer-field-select").each(function(){this.selectedIndex=this.options.length-1;jQuery(this).trigger("change")});void 0');
// Exercise server-only rejection through the real AJAX transport.
await evaluate('jQuery(".wcs-configurator").trigger("submit");void 0');
check(await evaluate('jQuery(".wcs-range-popup:not([hidden])").text().includes("Switch/sensor position is required")'), 'Missing nested switch position produces its required warning');
await evaluate('window.positionField=jQuery(".wcs-customer-field-select").filter(function(){return String(jQuery(this).data("key")).endsWith("sensor-position")});positionField.val("bottom-left").trigger("change");void 0');
check(await evaluate('!positionField.closest(".wcs-customer-field").hasClass("is-empty")'), 'Selected switch position clears required state');
check(await evaluate('jQuery(".wcs-customer-field-select").filter(function(){return String(jQuery(this).data("key")).endsWith(".finish")}).next(".wcs-custom-select").find(".wcs-custom-select__value").text()==="Klemmprofil + Stabilisationsstange 900 mm, kürzbar, hell verchromt"'), 'Selected dropdown value also retains the complete name including plus');
check(await evaluate('positionField.val()==="bottom-left" && JSON.parse(jQuery(".wcs-selections-input").val())[positionField.data("key")]==="bottom-left"'), 'Clear-on-parent-change rule preserves a newly selected position in the submitted data');
await evaluate('jQuery(".wcs-configurator").data("wcsRuleEngine").evaluate();void 0');
check(await evaluate('positionField.val()==="bottom-left"'), 'Repeated rule evaluation preserves the selected position');
await evaluate('window.finishField=jQuery(".wcs-customer-field-select").filter(function(){return String(jQuery(this).data("key")).endsWith(".finish")});finishField.val("");jQuery(".wcs-configurator").data("wcsRuleEngine").evaluate();finishField.val("brass");jQuery(".wcs-configurator").data("wcsRuleEngine").evaluate();void 0');
check(await evaluate('positionField.val()==="" && positionField.next(".wcs-custom-select").find(".wcs-custom-select__value").text()==="Keine Auswahl"'), 'Changing the source condition still clears the position and its visible dropdown label');
await evaluate('positionField.val("bottom-left").trigger("change");void 0');
await evaluate('window.testEngine=jQuery(".wcs-configurator").data("wcsRuleEngine");testEngine.rules.push({conditions:[{source:"nested",path:finishField.data("key"),operator:"equals",value:"brass"}],actions:[{action:"clear",target:finishField.data("key")+".brass.inactive-position"}],match:"all"});testEngine.evaluate();testEngine.rules.pop();void 0');
check(await evaluate('finishField.val()==="brass" && positionField.val()==="bottom-left"'), 'A clear rule for a missing nested field cannot clear its parent dropdown');
check(await evaluate('jQuery(".wcs-customer-number-input").filter(function(){return String(jQuery(this).data("key")).endsWith("distance-to-the-side-mm")}).closest(".wcs-customer-field").hasClass("is-required")'), 'A rule using the older distance path marks the visible nested distance field as required');
await evaluate('jQuery(".wcs-configurator input[name=nonce]").val("expired");jQuery(".wcs-configurator").trigger("submit");void 0');
await waitFor('!jQuery(".wcs-configurator").data("wcsSubmitting") && jQuery(".wcs-configurator__footer .wcs-range-popup:not([hidden])").length');
check(await evaluate('jQuery(".wcs-configurator__footer .wcs-range-popup").text().includes("Sitzung") && !!window.feedbackTestMarker'), 'Expired nonce appears in German without navigation');
// Restore the nonce rendered by the real shortcode.
await evaluate('jQuery(".wcs-configurator input[name=nonce]").val(new DOMParser().parseFromString('+JSON.stringify(fixture.html)+',"text/html").querySelector("input[name=nonce]").value);void 0');
const serverError = await evaluate('(async()=>{const w=jQuery(".wcs-configurator");const d=new URLSearchParams(w.serialize());d.set("wcs_selections",JSON.stringify({width:99999,height:400,side_width:99999,side_height:400}));const r=await fetch(w.find(".wcs-cart-endpoint").val(),{method:"POST",body:d});return await r.json()})()');
check(serverError.success===false && serverError.data.messages.length>0, 'Server dimension validation returns JSON messages');
check(serverError.data.messages[0].includes('Bitte geben Sie'), 'Server measurement error is German');
check(await evaluate('jQuery(".wcs-customer-number-input").filter(function(){return String(jQuery(this).data("key")).endsWith("empty_distance")}).val()==="0" && jQuery(".wcs-customer-number-input").filter(function(){return String(jQuery(this).data("key")).endsWith("empty_distance")}).attr("min")==="0"'), 'Number without a configured minimum starts at zero with a zero lower bound');
check(await evaluate('jQuery(".wcs-customer-number-input").filter(function(){return String(jQuery(this).data("key")).endsWith("minimum_distance")}).val()==="150"'), 'Numeric field starts at its configured minimum');
check(await evaluate('jQuery(".wcs-customer-number-input").filter(function(){return String(jQuery(this).data("key")).endsWith("zero_minimum")}).val()==="0"'), 'A zero minimum also initializes the field');
await evaluate('window.ruleTarget=jQuery(".wcs-customer-number-input").filter(function(){return String(jQuery(this).data("key")).endsWith("minimum_distance")});ruleTarget.removeAttr("max").val(450);jQuery(".wcs-configurator").data("wcsRuleEngine").evaluate();void 0');
check(await evaluate('ruleTarget.val()==="300" && ruleTarget.closest(".wcs-size-control").find(".wcs-range-popup:not([hidden])").text()==="Glass shelf length must fit inside the mirror width."'), 'Formula rule correction shows its configured popup');
await pause(3500);
check(await evaluate('ruleTarget.closest(".wcs-size-control").find(".wcs-range-popup:not([hidden])").length===1'), 'Range popup honors the configured five seconds instead of the default timeout');
await pause(1700);
check(await evaluate('ruleTarget.closest(".wcs-size-control").find(".wcs-range-popup:not([hidden])").length===0'), 'Rule popup closes after its configured duration');
await evaluate('ruleTarget.removeAttr("min").val(450).trigger("input");void 0');
await waitFor('ruleTarget.val()==="300"');
check(await evaluate('ruleTarget.closest(".wcs-size-control").find(".wcs-range-popup:not([hidden])").text()==="Glass shelf length must fit inside the mirror width."'), 'Typing above a max-only rule displays the configured warning');
await evaluate('ruleTarget.trigger("change");void 0');
check(await evaluate('ruleTarget.closest(".wcs-size-control").find(".wcs-range-popup:not([hidden])").length===1'), 'Committing the corrected value does not cancel the rule warning');
await evaluate('ruleTarget.val(450).trigger("change");void 0');
check(await evaluate('ruleTarget.val()==="300" && ruleTarget.closest(".wcs-size-control").find(".wcs-range-popup:not([hidden])").length===1'), 'Required-field validation does not hide the warning after correction');
await evaluate('window.singleBound=jQuery(".wcs-customer-number-input").filter(function(){return String(jQuery(this).data("key")).endsWith("empty_distance")});singleBound.attr("max",300).val(450).trigger("change");void 0');
check(await evaluate('singleBound.val()==="300" && singleBound.closest(".wcs-size-control").find(".wcs-range-popup:not([hidden])").text().includes("300")'), 'Static maximum without a minimum also produces a popup');
await evaluate('singleBound.removeAttr("max").attr("min",150).val(100).trigger("change");void 0');
check(await evaluate('singleBound.val()==="150" && singleBound.closest(".wcs-size-control").find(".wcs-range-popup:not([hidden])").text().includes("150")'), 'Static minimum without a maximum also produces a popup');
await evaluate('singleBound.removeAttr("min max").val(0);singleBound.closest(".wcs-size-control").find(".wcs-size-step--minus").trigger("click");void 0');
check(await evaluate('singleBound.val()==="0"'), 'Minus control cannot go below zero when no minimum is configured');
await evaluate('singleBound.val(-5).trigger("input");void 0');
check(await evaluate('singleBound.val()==="0"'), 'Typing a negative number is corrected immediately');
await evaluate('singleBound.attr("min",-20).val(-5).trigger("change");void 0');
check(await evaluate('singleBound.val()==="0"'), 'A negative minimum cannot allow a negative field value');
await evaluate('singleBound.removeAttr("min max").val("").trigger("change");ruleTarget.attr("min",150).val(150).trigger("change");ruleTarget.closest(".wcs-size-control").find(".wcs-size-step--minus").trigger("click");void 0');
check(await evaluate('singleBound.val()==="0" && ruleTarget.val()==="150"'), 'Cleared number resets to zero and a configured positive minimum remains enforced');
await evaluate('window.addedEvents=0;jQuery(document.body).on("added_to_cart.feedback",()=>window.addedEvents++);jQuery(".wcs-configurator").trigger("submit");jQuery(".wcs-configurator").trigger("submit");void 0');
await waitFor('!jQuery(".wcs-configurator").data("wcsSubmitting")');
if (!await evaluate('window.addedEvents===1')) { console.log('Cart diagnostic:',await evaluate('({message:jQuery(".wcs-configurator__footer .wcs-range-popup").text(),invalid:Array.from(document.querySelectorAll("[aria-invalid=true]")).map(el=>el.dataset.key),selections:jQuery(".wcs-selections-input").val()})')); }
check(await evaluate('window.addedEvents===1'), 'Valid configuration adds once despite repeated submit');
const cart = await evaluate('(async()=>{const r=await fetch('+JSON.stringify(fixture.base+'wp-json/wc/store/v1/cart')+');return await r.json()})()');
check(cart.items?.some(item=>item.id===fixture.product_id && item.quantity===1), 'Real guest cart contains the configured product');
const attributes = cart.items.find(item=>item.id===fixture.product_id).item_data;
check(attributes.some(row=>row.key==='Abstand zur Seitenkante des Spiegels (mm)' && row.value==='150'), 'Real cart accepts and retains the visible distance despite the older rule path');
check(await evaluate('jQuery(".wcs-configurator").data("current-total")===133'), 'Product page shows 83 plus the selected 50 option');
const itemPrice = cart.items.find(item=>item.id===fixture.product_id).prices;
check(Number(itemPrice.price) / Math.pow(10, itemPrice.currency_minor_unit)===133, 'Real guest cart charges 133 despite an identical option in another template: '+JSON.stringify(itemPrice));
check(attributes.some(row=>row.key==='Breite') && attributes.some(row=>row.key==='Höhe'), 'Real cart measurement attributes are German');
check(!attributes.some(row=>row.key==='Zusätzlicher Hinweis') && attributes.some(row=>row.key==='Zusätzlicher Abstand' && row.value==='0'), 'Real cart omits blank text and retains numeric fields initialized to zero');
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
