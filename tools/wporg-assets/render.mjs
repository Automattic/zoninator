/**
 * Renders the WordPress.org icon and banner PNGs from their SVG sources.
 *
 * Run from the plugin root: node tools/wporg-assets/render.mjs
 * Then compress: oxipng -o max --strip safe .wordpress-org/*.png
 */
import { readFileSync } from 'node:fs';
import { chromium } from 'playwright';

const icon = readFileSync( '.wordpress-org/icon.svg', 'utf8' );
const banner = readFileSync( 'tools/wporg-assets/banner.svg', 'utf8' );

const jobs = [
	{ svg: icon, width: 128, height: 128, scale: 1, out: 'icon-128x128.png' },
	{ svg: icon, width: 128, height: 128, scale: 2, out: 'icon-256x256.png' },
	{ svg: banner, width: 772, height: 250, scale: 1, out: 'banner-772x250.png' },
	{ svg: banner, width: 772, height: 250, scale: 2, out: 'banner-1544x500.png' },
	// The banner has a direction (phone, then the page with its zone), so RTL mirrors it.
	{ svg: banner, width: 772, height: 250, scale: 1, out: 'banner-772x250-rtl.png', rtl: true },
	{ svg: banner, width: 772, height: 250, scale: 2, out: 'banner-1544x500-rtl.png', rtl: true },
];

const browser = await chromium.launch();
for ( const { svg, width, height, scale, out, rtl } of jobs ) {
	const page = await browser.newPage( { viewport: { width, height }, deviceScaleFactor: scale } );
	const style = `display:block;width:${ width }px;height:${ height }px${ rtl ? ';transform:scaleX(-1)' : '' }`;
	await page.setContent( `<body style="margin:0">${ svg.replace( '<svg ', `<svg style="${ style }" ` ) }</body>` );
	await page.screenshot( { path: `.wordpress-org/${ out }` } );
	await page.close();
}
await browser.close();
