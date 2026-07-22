<?php
/**
 * Scripts configuration.
 *
 * Merged into the HivePress `scripts` config, so the script is enqueued and
 * localized by the core asset component (which exposes the `data` array as
 * `hivepressGalleryFrontendData`). Only the account routes with the copy
 * button and the delete form need it.
 *
 * @package AdditionalGalleryForHivePress\Configs
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

return [
	'gallery_frontend' => [
		'handle'  => 'hivepress-gallery-frontend',
		'src'     => HP_AGL_URL . '/assets/js/frontend.js',
		'version' => HP_AGL_VERSION,
		'deps'    => [ 'jquery', 'jquery-ui-sortable' ],

		'scope'   => [
			'gallery_edit_page',
			'gallery_folder_edit_page',
		],

		'data'    => [
			'restNonce'     => wp_create_nonce( 'wp_rest' ),
			'deleteConfirm' => esc_html__( 'Are you sure you want to delete this folder? All of its images will be permanently removed.', 'additional-gallery-for-hivepress' ),
			'copied'        => esc_html__( 'Link copied!', 'additional-gallery-for-hivepress' ),
			'saved'         => esc_html__( 'Descriptions saved.', 'additional-gallery-for-hivepress' ),
			'saveError'     => esc_html__( 'Something went wrong. Please try again.', 'additional-gallery-for-hivepress' ),
		],
	],
];
