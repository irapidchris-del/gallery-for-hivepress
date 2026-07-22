<?php
/**
 * Meta boxes configuration.
 *
 * Adds admin meta boxes to the gallery folder edit screen. Fields without an
 * explicit alias are stored as meta by HivePress (`visibility` maps to the
 * `hp_visibility` meta), while the vendor field is aliased to the post
 * parent. The images field renders the same drag-and-drop manager used for
 * listings; its upload rules (limits, formats, protection) come from the
 * gallery folder model, which the attachments endpoint reads.
 *
 * @package AdditionalGalleryForHivePress\Configs
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Get the per-folder image limit.
$hp_agl_max_images = hp_agl_int( get_option( 'hp_gallery_max_images' ) );

if ( ! $hp_agl_max_images ) {
	$hp_agl_max_images = 30;
}

// Get the accepted formats, mirroring the gallery folder model.
$hp_agl_formats = [ 'jpg', 'jpeg', 'png', 'webp', 'gif' ];

if ( get_option( 'hp_gallery_allow_video' ) ) {
	$hp_agl_formats = array_merge( $hp_agl_formats, [ 'mp4', 'webm', 'ogv' ] );
}

return [
	'gallery_folder_images'   => [
		'title'  => hivepress()->translator->get_string( 'images' ),
		'screen' => 'gallery_folder',
		'model'  => 'gallery_folder',

		'fields' => [
			'images' => [
				'caption'   => hivepress()->translator->get_string( 'select_images' ),
				'type'      => 'attachment_upload',
				'multiple'  => true,
				'max_files' => $hp_agl_max_images,
				'formats'   => $hp_agl_formats,
				'_order'    => 10,
			],
		],
	],

	'gallery_folder_settings' => [
		'title'  => hivepress()->translator->get_string( 'settings' ),
		'screen' => 'gallery_folder',
		'model'  => 'gallery_folder',

		'fields' => [
			'vendor'     => [
				'label'       => hivepress()->translator->get_string( 'vendor' ),
				'type'        => 'select',
				'options'     => 'posts',
				'option_args' => [ 'post_type' => 'hp_vendor' ],
				'source'      => hivepress()->router->get_url( 'vendors_resource' ),
				'_alias'      => 'post_parent',
				'_order'      => 10,
			],

			'visibility' => [
				'label'    => esc_html__( 'Visibility', 'additional-gallery-for-hivepress' ),
				'type'     => 'select',
				'required' => true,
				'default'  => 'public',
				'_order'   => 20,

				'options'  => [
					'public'  => esc_html__( 'Public', 'additional-gallery-for-hivepress' ),
					'members' => esc_html__( 'Members only', 'additional-gallery-for-hivepress' ),
					'private' => esc_html__( 'Private', 'additional-gallery-for-hivepress' ),
				],
			],
		],
	],
];
