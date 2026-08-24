<?php
/**
 * Settings configuration.
 *
 * Adds a "Gallery" tab to the HivePress settings screen. Field names are
 * automatically prefixed, so options are saved as
 * `hp_gallery_hide_vendor_link`, `hp_gallery_max_folders`, etc.
 *
 * These sections used to hang off the Vendors tab. The prefix comes from the
 * field name rather than the tab, so moving them changed no option name and
 * no stored value.
 *
 * Two sections rather than one: section descriptions render through
 * hp\sanitize_html(), which keeps only strong/a/i tags, so a long description
 * cannot contain line breaks. Splitting the monetisation copy into its own
 * section gives it a heading and a readable paragraph of its own.
 *
 * @package AdditionalGalleryForHivePress\Configs
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

return [
	'gallery' => [
		'title'    => esc_html__( 'Gallery', 'additional-gallery-for-hivepress' ),

		/*
		 * A tab of its own rather than three more sections bolted onto Vendors, which is where these
		 * lived and where they had grown to twenty-odd settings, pushing HivePress's own vendor
		 * options off the bottom of the screen. It also matches how the rest of this range is
		 * arranged.
		 *
		 * The option names are untouched: HivePress prefixes from the FIELD name, not the tab, so
		 * every setting is still stored as `hp_gallery_...` and nothing an owner has configured
		 * moves or resets. Only where the screen draws them changes.
		 */
		'_order'   => 130,

		'sections' => [
			'gallery'              => [
				'title'       => esc_html__( 'General', 'additional-gallery-for-hivepress' ),
				'description' => esc_html__( 'Vendors get a photo gallery in their account, reachable from their profile and listings. Everything below applies to every vendor on your site. Most limits left empty are simply not applied; where a limit has a built-in default instead, its own instruction says so.', 'additional-gallery-for-hivepress' ),
				'_order'      => 100,

				'fields'      => [
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

					'gallery_show_on_vendors'   => [
						'label'       => esc_html__( 'Gallery on Vendor Profiles', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Show the gallery on vendor profiles', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Adds a Gallery section to a vendor profile, below their listings, showing their folders in place rather than only linking to them. Vendors with no gallery get no section at all. This is separate from the sidebar button above, so you can have either, both or neither.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 26,
					],

					'gallery_show_on_listings'  => [
						'label'       => esc_html__( 'Gallery on Listings', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Show the gallery on listing pages', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Adds a Gallery section to the foot of every listing by that vendor, after the tags and before the reviews. Useful where the work itself is what sells the listing. Vendors with no gallery get no section at all.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 27,
					],

					'gallery_show_button_count' => [
						'label'       => esc_html__( 'Gallery Button', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Show the photo count on the View Gallery button', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'With this off, the sidebar button simply reads "View Gallery".', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'default'     => true,
						'_order'      => 22,
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

					'gallery_enable_lightbox'   => [
						'label'       => esc_html__( 'Lightbox', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Let visitors enlarge the photo on its page', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'With this on, clicking the photo on its own page enlarges it in a pop-up viewer. Photos in the gallery always open their own page, where the comments live.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'default'     => true,
						'_order'      => 26,
					],

					'gallery_photo_sidebar'     => [
						'label'       => esc_html__( 'Photo Page Sidebar', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Choose which side of the photo page the sidebar sits on. It shows the vendor\'s profile card and, for the photo\'s owner, the editing options, and you can add your own widgets to it in the "Photo Page (sidebar)" area under Appearance, then Widgets.', 'additional-gallery-for-hivepress' ),
						'type'        => 'select',
						'default'     => 'right',
						'_order'      => 27,

						'options'     => [
							'right' => esc_html__( 'Right', 'additional-gallery-for-hivepress' ),
							'left'  => esc_html__( 'Left', 'additional-gallery-for-hivepress' ),
						],
					],

					'gallery_max_folders'       => [
						'label'       => esc_html__( 'Maximum Folders', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Set the maximum number of gallery folders per vendor. Leave empty for no limit. A membership plan can raise or lower this per plan.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 1,
						'_order'      => 30,
					],

					'gallery_max_images'        => [
						'label'       => esc_html__( 'Maximum Images per Folder', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Set the maximum number of images allowed in each folder. Leave empty for the default of 30. A membership plan can raise or lower this per plan.', 'additional-gallery-for-hivepress' ),
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

					'gallery_enable_likes'      => [
						'label'       => esc_html__( 'Likes', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Let visitors like individual photos', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Adds a heart button to every photo, with a count everyone can see. Only signed-in visitors can like, and each person can like a photo once.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'default'     => true,
						'_order'      => 46,
					],

					'gallery_ai_moderation'     => [
						'label'       => esc_html__( 'AI Moderation', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Review the first ten photos in each folder with AI', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Checks a folder\'s photos with OpenAI when the vendor saves the folder, using the API key from the HivePress Integrations settings. The first ten photos in the folder are checked together in one free request; any beyond the tenth are not checked. OpenAI fetches each photo from your site, so this only covers public folders: photos in private and members-only folders are skipped while Protect Files is on, because their files are not served at a public address. Your site must also be publicly reachable, so on local or private sites, and whenever the service is unavailable, saving simply proceeds unchecked.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 47,
					],

					'gallery_enable_comments'   => [
						'label'       => esc_html__( 'Comments', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Let visitors comment on individual photos', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Every photo gets its own page with a comment thread beneath it, where signed-in visitors can comment, reply and like comments. People can delete their own comments, and the folder owner can delete any comment on their photos.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'default'     => true,
						'_order'      => 48,
					],

					'gallery_protect_files'     => [
						'label'       => esc_html__( 'Protect Files', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Protect private and members-only image files', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Moves the files in private and members-only folders out of the folder your web server publishes, and serves them through an access-checked link instead, so there is no direct address to open. New uploads also get unguessable file names, and gallery images are excluded from public media API results. Recommended. If the files cannot be moved out on your hosting, a notice above says so and explains what to do.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'default'     => true,
						'_order'      => 90,
					],

					'gallery_button_radius'     => [
						'label'       => esc_html__( 'Button Corner Rounding (px)', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Rounds the corners of every button this plugin adds, so they match the rest of your site. Leave it empty and each button keeps whatever shape your theme gives it, which is right on most sites. Set 0 for square corners, or a larger number for rounder ones.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 0,
						'max_value'   => 40,
						'_order'      => 46,
					],

					'gallery_max_filesize'      => [
						'label'       => esc_html__( 'Maximum File Size (MB)', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Reject gallery uploads larger than this many megabytes. Leave empty for no gallery-specific limit (the server upload limit still applies).', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 1,
						'_order'      => 100,
					],

					'gallery_storage_limit'     => [
						'label'       => esc_html__( 'Storage Limit (MB)', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Cap the total disk space each vendor\'s gallery may use, counting every photo and its thumbnails. Vendors see their usage on their Gallery page. Leave empty for no cap. A membership plan can raise or lower this per plan.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 1,
						'_order'      => 105,
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
						'label'       => esc_html__( 'Maximum Image Dimensions (pixels)', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Resize uploaded images so their width and height do not exceed this many pixels. Photos straight from a phone or camera are far bigger than any screen needs, so this is the single biggest saving; around 2000 suits most galleries. Leave empty to keep the original dimensions. For compression, WebP conversion and bulk work on images you already have, use a dedicated image plugin alongside this one.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 320,
						'_order'      => 120,
					],
				],
			],

			'gallery_monetisation' => [
				'title'       => esc_html__( 'Gallery Monetisation', 'additional-gallery-for-hivepress' ),
				'description' => __( 'Members-only folders are how galleries make money: visitors must unlock them before seeing inside. <strong>To charge through memberships</strong>, add the gallery privileges to a paid HivePress Memberships plan, or add them to a free plan to simply require an account. <strong>To let vendors charge instead</strong>, enable paid access below: a locked folder then offers that vendor\'s one-off purchase, and only vendors who have not set a price fall back to your upgrade page link.', 'additional-gallery-for-hivepress' ),
				'_order'      => 101,

				'fields'      => [
					'gallery_enable_members'     => [
						'label'       => esc_html__( 'Members-Only Folders', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Let vendors mark folders as members-only', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Members-only folders appear locked until a visitor gains access through a membership plan or a purchase. With this off, vendors only choose between public and private, and any existing members-only folders behave as private. Turn it off if your site has no plans to monetise galleries.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'default'     => true,
						'_order'      => 10,
					],

					'gallery_locked_display'     => [
						'label'       => esc_html__( 'Locked Folder Display', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Choose how members-only folders appear to visitors without access.', 'additional-gallery-for-hivepress' ),
						'type'        => 'select',
						'default'     => 'blur',
						'_order'      => 20,

						'options'     => [
							'blur'  => esc_html__( 'Show blurred previews', 'additional-gallery-for-hivepress' ),
							'tiles' => esc_html__( 'Show locked placeholders', 'additional-gallery-for-hivepress' ),
							'hide'  => esc_html__( 'Hide completely', 'additional-gallery-for-hivepress' ),
						],
					],

					'gallery_enable_paid_access' => [
						'label'       => esc_html__( 'Paid Access', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Let vendors sell access to their members-only folders', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Each vendor sets their own lengths and prices on their Gallery page, choosing from a day, a week, a month, three months or permanent access, and may offer up to three of them at once. Setting any is optional. Buying is a normal WooCommerce checkout and the unlock applies to that one vendor\'s locked folders. While a vendor sells access, their locked folders show the purchase button rather than the upgrade page link. Requires WooCommerce. With HivePress Marketplace active, these sales count towards vendor earnings and your usual commission applies.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 30,
					],

					'gallery_commission_rate'    => [
						'label'       => esc_html__( 'Commission Rate', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Your percentage of a gallery access sale. Leave both commission boxes empty to take none. The amount is added on top at checkout and shown to the buyer as a separate line called Platform fee, so the vendor still receives the price they set. This only reaches you if your payment gateway settles gallery orders into the site\'s own payment account, which is the normal case. If you use a gateway that charges each vendor\'s connected account directly, the whole order including this fee is paid to the vendor and none of it reaches the site, so leave both boxes empty and take your cut through that gateway instead.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'decimals'    => 2,
						'min_value'   => 0,
						'max_value'   => 100,
						'_order'      => 60,
					],

					'gallery_commission_fee'     => [
						'label'       => esc_html__( 'Commission Fee', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'A flat amount on top of the rate, charged once per gallery access sale. Use either box on its own or both together.', 'additional-gallery-for-hivepress' ),
						'type'        => 'currency',
						'min_value'   => 0,
						'_order'      => 70,
					],

					'gallery_upgrade_page'       => [
						'label'       => esc_html__( 'Upgrade Page', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Choose the page locked visitors are sent to, e.g. your membership pricing page.', 'additional-gallery-for-hivepress' ),
						'type'        => 'select',
						'options'     => 'posts',
						'option_args' => [ 'post_type' => 'page' ],
						'_order'      => 50,
					],
				],
			],

			'gallery_removal'      => [
				'title'       => esc_html__( 'Removing the Plugin', 'additional-gallery-for-hivepress' ),
				'description' => esc_html__( 'Your galleries are kept if you ever delete this plugin. WordPress shows its own warning saying that deleting a plugin also deletes its data, but that warning is generic: it appears for every plugin and does not describe what this one does. Nothing below is removed unless you tick the box first.', 'additional-gallery-for-hivepress' ),
				'_order'      => 102,

				'fields'      => [
					'gallery_delete_data' => [
						'label'       => esc_html__( 'Delete All Data', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Delete folders, likes, comments and settings when this plugin is deleted (photos stay in your media library)', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Leave this unticked, and deleting the plugin keeps every gallery folder, photo, like, comment, purchased access and setting, so reinstalling brings your galleries back exactly as they were. Tick it, and deleting the plugin permanently removes all of that. Photos themselves always stay in your media library either way. This cannot be undone, so only tick it if you are sure you are finished with the gallery.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 10,
					],
				],
			],
		],
	],
];
