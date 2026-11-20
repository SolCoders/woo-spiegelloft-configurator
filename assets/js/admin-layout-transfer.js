/* global wcsLayoutTransfer */
( function () {
	'use strict';
	const heading = document.querySelector( '.wrap .wp-heading-inline' );
	if ( ! heading || document.getElementById( 'wcs-import-layout' ) ) {
		return;
	}
	const link = document.createElement( 'a' );
	link.id = 'wcs-import-layout';
	link.className = 'page-title-action';
	link.href = wcsLayoutTransfer.url;
	link.textContent = wcsLayoutTransfer.label;
	const addNew = heading.nextElementSibling;
	( addNew && addNew.classList.contains( 'page-title-action' ) ? addNew : heading ).after( link );
}() );
