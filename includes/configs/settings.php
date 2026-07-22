<?php
/**
 * Settings configuration.
 *
 * Adds a "Gallery" section to the HivePress Vendors settings tab. Field
 * names are automatically prefixed, so options are saved as
 * `hp_gallery_hide_vendor_link`, `hp_gallery_manage_plans`, etc.
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

					'gallery_manage_plans'      => [
						'label'       => esc_html__( 'Vendor Access Plans', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Select the membership plans that allow vendors to use the gallery feature. Leave empty to allow all vendors. Requires the HivePress Memberships extension.', 'additional-gallery-for-hivepress' ),
						'type'        => 'select',
						'options'     => 'posts',
						'option_args' => [ 'post_type' => hp_agl_get_plan_post_type() ? hp_agl_get_plan_post_type() : 'hp_membership_plan' ],
						'multiple'    => true,
						'_order'      => 50,
					],

					'gallery_view_plans'        => [
						'label'       => esc_html__( 'Viewer Access Plans', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Select the membership plans that allow users to view members-only folders. If empty, members-only folders are visible to their owners only. Requires the HivePress Memberships extension.', 'additional-gallery-for-hivepress' ),
						'type'        => 'select',
						'options'     => 'posts',
						'option_args' => [ 'post_type' => hp_agl_get_plan_post_type() ? hp_agl_get_plan_post_type() : 'hp_membership_plan' ],
						'multiple'    => true,
						'_order'      => 60,
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
						'caption'     => esc_html__( 'Store new uploads with unguessable file names', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Appends a random suffix to newly uploaded file names (the HivePress protection mechanism), which prevents URL guessing and enumeration. It does not restrict access for anyone who already has a direct file URL, and it does not rename existing files.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 90,
					],
				],
			],
		],
	],
];
