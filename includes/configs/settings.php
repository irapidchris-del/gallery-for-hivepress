<?php
/**
 * Settings configuration.
 *
 * Adds a "Gallery" section to the HivePress Vendors settings tab. Field
 * names are automatically prefixed, so options are saved as
 * `hp_gallery_hide_vendor_link`, `hp_gallery_max_folders`, etc.
 *
 * @package AdditionalGalleryForHivePress\Configs
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

return [
	'vendors' => [
		'sections' => [
			'gallery' => [
				'title'  => esc_html__( 'Gallery', 'additional-gallery-for-hivepress' ),
				'_order' => 100,

				'fields' => [
					'gallery_hide_vendor_link'  => [
						'label'   => esc_html__( 'Vendor Pages', 'additional-gallery-for-hivepress' ),
						'caption' => esc_html__( 'Hide the gallery link on vendor profiles', 'additional-gallery-for-hivepress' ),
						'type'    => 'checkbox',
						'_order'  => 10,
					],

					'gallery_hide_listing_link' => [
						'label'   => esc_html__( 'Listing Pages', 'additional-gallery-for-hivepress' ),
						'caption' => esc_html__( 'Hide the gallery link on listing pages', 'additional-gallery-for-hivepress' ),
						'type'    => 'checkbox',
						'_order'  => 20,
					],

					'gallery_layout'            => [
						'label'       => esc_html__( 'Gallery Layout', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Choose how the public gallery page displays folders.', 'additional-gallery-for-hivepress' ),
						'type'        => 'select',
						'default'     => 'folders',
						'_order'      => 25,

						'options'     => [
							'folders' => esc_html__( 'Folder covers', 'additional-gallery-for-hivepress' ),
							'single'  => esc_html__( 'Single page', 'additional-gallery-for-hivepress' ),
						],
					],

					'gallery_max_folders'       => [
						'label'       => esc_html__( 'Maximum Folders', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Set the maximum number of gallery folders per vendor. Leave empty for no limit.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 1,
						'_order'      => 30,
					],

					'gallery_max_images'        => [
						'label'       => esc_html__( 'Maximum Images per Folder', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Set the maximum number of images allowed in each folder. Leave empty for the default of 30.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 1,
						'_order'      => 40,
					],

					'gallery_allow_video'       => [
						'label'   => esc_html__( 'Videos', 'additional-gallery-for-hivepress' ),
						'caption' => esc_html__( 'Allow uploading videos', 'additional-gallery-for-hivepress' ),
						'type'    => 'checkbox',
						'_order'  => 45,
					],

					'gallery_ai_moderation'     => [
						'label'       => esc_html__( 'AI Moderation', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Review gallery photos with AI', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Checks a folder\'s photos with OpenAI when it is saved, using the API key from the HivePress Integrations settings. All photos are checked together in one free request. Your site must be publicly reachable for OpenAI to fetch the photos; on local or private sites, and whenever the service is unavailable, saving simply proceeds unchecked.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 47,
					],

					'gallery_locked_display'    => [
						'label'       => esc_html__( 'Locked Folder Display', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Choose how members-only folders appear to visitors without access.', 'additional-gallery-for-hivepress' ),
						'type'        => 'select',
						'default'     => 'blur',
						'_order'      => 70,

						'options'     => [
							'blur'  => esc_html__( 'Show blurred previews', 'additional-gallery-for-hivepress' ),
							'tiles' => esc_html__( 'Show locked placeholders', 'additional-gallery-for-hivepress' ),
							'hide'  => esc_html__( 'Hide completely', 'additional-gallery-for-hivepress' ),
						],
					],

					'gallery_upgrade_page'      => [
						'label'       => esc_html__( 'Upgrade Page', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Choose the page locked visitors are sent to, e.g. your membership pricing page.', 'additional-gallery-for-hivepress' ),
						'type'        => 'select',
						'options'     => 'posts',
						'option_args' => [ 'post_type' => 'page' ],
						'_order'      => 80,
					],

					'gallery_protect_files'     => [
						'label'       => esc_html__( 'Protect Files', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Protect private and members-only image files', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Moves the files in private and members-only folders to a protected location and serves them through an access-checked link, so their URLs cannot be opened directly. New uploads also get unguessable file names, and gallery images are excluded from public media API results. Recommended. On Nginx, add the server rule shown in the plugin readme; the access check applies regardless.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'default'     => true,
						'_order'      => 90,
					],

					'gallery_max_filesize'      => [
						'label'       => esc_html__( 'Maximum File Size', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Reject gallery uploads larger than this many megabytes. Leave empty for no gallery-specific limit (the server upload limit still applies).', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 1,
						'_order'      => 100,
					],

					'gallery_image_formats'     => [
						'label'       => esc_html__( 'Allowed Image Formats', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Choose which image formats vendors may upload. Leave empty to allow all supported formats.', 'additional-gallery-for-hivepress' ),
						'type'        => 'select',
						'multiple'    => true,
						'_order'      => 110,

						'options'     => [
							'jpg'  => 'JPG',
							'png'  => 'PNG',
							'webp' => 'WebP',
							'gif'  => 'GIF',
						],
					],

					'gallery_max_dimensions'    => [
						'label'       => esc_html__( 'Maximum Image Dimensions', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Resize uploaded images so their width and height do not exceed this many pixels. Leave empty to keep the original dimensions.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 320,
						'_order'      => 120,
					],

					'gallery_image_quality'     => [
						'label'       => esc_html__( 'Image Quality', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Re-compress uploaded JPG and WebP images at this quality (1-100). Lower values save more bandwidth. Leave empty to keep the WordPress default.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 10,
						'max_value'   => 100,
						'_order'      => 130,
					],

					'gallery_strip_metadata'    => [
						'label'   => esc_html__( 'Metadata', 'additional-gallery-for-hivepress' ),
						'caption' => esc_html__( 'Strip camera and location metadata from uploaded images', 'additional-gallery-for-hivepress' ),
						'type'    => 'checkbox',
						'_order'  => 140,
					],

					'gallery_convert_webp'      => [
						'label'       => esc_html__( 'Convert to WebP', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Convert uploaded JPG and PNG images to WebP', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'WebP files are typically much smaller. Requires WebP support on the server, and WebP must be an allowed format above.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 150,
					],

					'gallery_keep_originals'    => [
						'label'       => esc_html__( 'Keep Originals', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Back up original files before optimizing existing images', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'When using the "Optimize images" bulk action on existing folders, keep a copy of each original so the optimization can be undone with "Restore original images". Uses extra disk space.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 160,
					],
				],
			],
		],
	],
];
