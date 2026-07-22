<?php
/**
 * Styles configuration.
 *
 * Merged into the HivePress `styles` config, so the stylesheet is enqueued
 * by the core asset component. The scope is limited to the routes that
 * render gallery markup, keeping the CSS off every other page.
 *
 * @package AdditionalGalleryForHivePress\Configs
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

return [
	'gallery_frontend' => [
		'handle'  => 'hivepress-gallery-frontend',
		'src'     => HP_AGL_URL . '/assets/css/frontend.css',
		'version' => HP_AGL_VERSION,

		'scope'   => [
			'gallery_view_page',
			'gallery_folder_view_page',
			'gallery_edit_page',
			'gallery_folder_edit_page',
			'listing_view_page',
			'vendor_view_page',
		],
	],
];
