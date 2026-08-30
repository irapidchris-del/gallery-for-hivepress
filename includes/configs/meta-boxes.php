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

// The AI moderation cap binds here too, so the admin box shows the same limit
// the attachments endpoint enforces (see Agl_Gallery::get_moderation_image_cap()).
$hp_agl_moderation_cap = hivepress()->agl_gallery ? hivepress()->agl_gallery->get_moderation_image_cap() : 0;

if ( $hp_agl_moderation_cap && $hp_agl_moderation_cap < $hp_agl_max_images ) {
	$hp_agl_max_images = $hp_agl_moderation_cap;
}

// Get the accepted formats (shared with the gallery folder model).
$hp_agl_formats = hp_agl_get_upload_formats();

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

		// Named rather than borrowing core's bare "Settings", which sat above a Vendor box and a
		// Visibility box with nothing on screen saying what it was the settings of.
		'title'  => esc_html__( 'Gallery Settings', 'additional-gallery-for-hivepress' ),
		'screen' => 'gallery_folder',
		'model'  => 'gallery_folder',

		'fields' => [
			'vendor'     => [
				'label'       => hivepress()->translator->get_string( 'vendor' ),
				'type'        => 'select',
				'options'     => 'posts',
				'option_args' => [ 'post_type' => 'hp_vendor' ],
				'source'      => hivepress()->router->get_url( 'vendors_resource' ),

				/*
				 * Required, so the label reads "Vendor" and not "Vendor (optional)". HivePress adds
				 * that suffix to every field that is not required (fields/class-field.php:228-230),
				 * and it was wrong here twice over: a folder with no vendor appears in nobody's
				 * gallery, and since the Author box went (see post-types.php) this is the only place
				 * a folder's owner is set at all.
				 */
				'required'    => true,
				'_alias'      => 'post_parent',
				'_order'      => 10,
			],

			'visibility' => [
				'label'    => esc_html__( 'Visibility', 'additional-gallery-for-hivepress' ),
				'type'     => 'select',
				'required' => true,
				'default'  => 'public',
				'_order'   => 20,

				// Same choices vendors get, so an admin cannot set a state the
				// site has switched off.
				'options'  => hivepress()->agl_gallery->get_visibility_options(),
			],
		],
	],
];
