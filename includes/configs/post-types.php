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

		/*
		 * Listed under Vendors rather than HivePress. A gallery folder belongs to exactly one vendor
		 * and is only ever reached by way of one, so it sits beside the vendors themselves rather
		 * than in the settings menu, where it was one unrelated entry among emails and templates.
		 * The value is the parent menu's own slug, which for a post type menu is its edit screen.
		 */
		'show_in_menu'     => 'edit.php?post_type=hp_vendor',
		'delete_with_user' => false,

		/*
		 * No `author` support, deliberately, and matching how core declares `hp_listing` and
		 * `hp_vendor` (hivepress/includes/configs/post-types.php:60, :86). WordPress's Author box and
		 * the Vendor field beside it are two controls for one thing, and an admin who set them to
		 * different people got a folder whose owner and whose vendor disagreed: the front-end owner
		 * checks read the post author, while every listing and count reads the vendor. The vendor is
		 * the single control now, and Agl_Gallery::sync_folder_author() writes the post author to
		 * match it on save, which is the same rule core applies to a listing
		 * (components/class-listing.php:122-128).
		 */
		'supports'         => [ 'title', 'editor' ],

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
