<?php
/**
 * Post types configuration.
 *
 * Merged into the HivePress `post_types` config. The `gallery_folder` key is
 * automatically prefixed, registering the `hp_gallery_folder` post type.
 *
 * @package AdditionalGalleryForHivePress\Configs
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

return [
	'gallery_folder' => [
		'public'           => false,
		'show_ui'          => true,
		'show_in_menu'     => 'hp_settings',
		'delete_with_user' => false,
		'supports'         => [ 'title', 'editor', 'author' ],

		'labels'           => [
			'name'               => esc_html__( 'Gallery Folders', 'additional-gallery-for-hivepress' ),
			'singular_name'      => esc_html__( 'Gallery Folder', 'additional-gallery-for-hivepress' ),
			'add_new'            => esc_html_x( 'Add New', 'gallery folder', 'additional-gallery-for-hivepress' ),
			'add_new_item'       => esc_html__( 'Add Gallery Folder', 'additional-gallery-for-hivepress' ),
			'edit_item'          => esc_html__( 'Edit Gallery Folder', 'additional-gallery-for-hivepress' ),
			'new_item'           => esc_html__( 'Add Gallery Folder', 'additional-gallery-for-hivepress' ),
			'all_items'          => esc_html__( 'Gallery Folders', 'additional-gallery-for-hivepress' ),
			'search_items'       => esc_html__( 'Search Gallery Folders', 'additional-gallery-for-hivepress' ),
			'not_found'          => esc_html__( 'No gallery folders found.', 'additional-gallery-for-hivepress' ),
			'not_found_in_trash' => esc_html__( 'No gallery folders found.', 'additional-gallery-for-hivepress' ),
		],
	],
];
