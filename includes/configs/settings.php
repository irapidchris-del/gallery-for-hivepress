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
				'description' => esc_html__( 'Vendors get a photo gallery in their account, linked from their profile and listings. Everything below applies to every vendor. A limit left empty is not applied, unless its own instruction names a built-in default.', 'additional-gallery-for-hivepress' ),
				'_order'      => 100,

				'fields'      => [
					'gallery_hide_vendor_link'         => [
						'label'   => esc_html__( 'Vendor Pages', 'additional-gallery-for-hivepress' ),
						'caption' => esc_html__( 'Hide the gallery link on vendor profiles', 'additional-gallery-for-hivepress' ),
						'type'    => 'checkbox',
						'_order'  => 10,
					],

					'gallery_hide_listing_link'        => [
						'label'   => esc_html__( 'Listing Pages', 'additional-gallery-for-hivepress' ),
						'caption' => esc_html__( 'Hide the gallery link on listing pages', 'additional-gallery-for-hivepress' ),
						'type'    => 'checkbox',
						'_order'  => 20,
					],

					'gallery_show_on_vendors'          => [
						'label'       => esc_html__( 'Gallery on Vendor Profiles', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Show the gallery on vendor profiles', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Adds a Gallery section to vendor profiles, below their listings, showing folders in place rather than only linking to them. Vendors with no gallery get no section. Independent of the sidebar button, so you can have either, both or neither.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 26,
					],

					'gallery_show_on_listings'         => [
						'label'       => esc_html__( 'Gallery on Listings', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Show the gallery on listing pages', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Adds a Gallery section to the foot of every listing by that vendor, before the reviews. Vendors with no gallery get no section.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 27,
					],

					'gallery_show_button_count'        => [
						'label'       => esc_html__( 'Gallery Button', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Show the photo count on the View Gallery button', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'With this off, the sidebar button simply reads "View Gallery".', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'default'     => true,
						'_order'      => 22,
					],

					/*
					 * One box per page type, not one shared box, because the two sidebars are
					 * numbered on different scales and the same figure lands somewhere different in
					 * each. On a vendor profile: summary 10, details 20, social links 25, action
					 * buttons 30. On a listing: details 10, social links 15, action buttons 20,
					 * vendor card 30. So 24 clears social links on a profile and lands BELOW both
					 * social links and the action buttons on a listing. 1.9.1 shipped a single box
					 * for both and could not express the same relative position on the two pages.
					 *
					 * A number rather than a placement choice plus a position box: a second box that
					 * applies to only one of the choices is a box that does nothing on most screens,
					 * and the settings notes are explicit that a setting which silently does nothing
					 * is the shape of bug to avoid. Empty has a stated meaning here and is the
					 * default, so every state of both fields is live.
					 *
					 * The vendor box keeps the option name 1.9.1 gave it, so a site that had already
					 * set one keeps its profiles exactly as they were.
					 */
					'gallery_button_position'          => [
						'label'       => esc_html__( 'Gallery Button Position (Vendor Pages)', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Where the View Gallery button sits in a vendor profile sidebar. Leave empty to group it with the action buttons beside Send Message, which suits most sites. Enter a number for a place of its own; lower sits higher. The summary card is 10, details 20, social links 25 and action buttons 30, so 24 sits just above social links.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 1,
						'max_value'   => 1000,
						'_order'      => 23,
					],

					'gallery_button_position_listings' => [
						'label'       => esc_html__( 'Gallery Button Position (Listing Pages)', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'The same choice for listing pages, which are numbered differently. Leave empty to group the button with the action buttons. The details are 10, social links 15, action buttons 20 and the vendor card 30, so 14 sits just above social links.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 1,
						'max_value'   => 1000,
						'_order'      => 24,
					],

					'gallery_max_folders'              => [
						'label'       => esc_html__( 'Maximum Folders', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Set the maximum number of gallery folders per vendor. Leave empty for no limit. A membership plan can raise or lower this per plan.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 1,
						'_order'      => 30,
					],

					'gallery_max_images'               => [
						'label'       => esc_html__( 'Maximum Images per Folder', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Set the maximum number of images allowed in each folder. Leave empty for the default of 30. A membership plan can raise or lower this per plan.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 1,
						'_order'      => 40,
					],

					'gallery_allow_video'              => [
						'label'   => esc_html__( 'Videos', 'additional-gallery-for-hivepress' ),
						'caption' => esc_html__( 'Allow uploading videos', 'additional-gallery-for-hivepress' ),
						'type'    => 'checkbox',
						'_order'  => 45,
					],

					'gallery_enable_likes'             => [
						'label'       => esc_html__( 'Likes', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Let visitors like individual photos', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Adds a heart button with a public count to every photo. Only signed-in visitors can like, once per photo.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'default'     => true,
						'_order'      => 46,
					],

					'gallery_ai_moderation'            => [
						'label'       => esc_html__( 'AI Moderation', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Review the first ten photos in each folder with AI', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Checks a folder\'s photos with the free OpenAI Moderation endpoint when the vendor saves the folder, using the API key from the Integrations settings. Each save reviews up to ten photos not reviewed before; set the photo limit below to guarantee full coverage. OpenAI fetches each photo from your site, so only public folders on a publicly reachable site can be checked, and when the service is unavailable saving simply proceeds unchecked.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 47,
					],

					/*
					 * The completeness guarantee for AI Moderation. A review pass covers at most ten
					 * photos (prepare_moderation_urls()), so a folder holding more can be published with
					 * photos nobody looked at. Capped at ten and applied through get_image_limit(), so
					 * the same figure governs the upload endpoint, the front-end form, the admin meta
					 * box and photo moves alike. Only shown while AI Moderation is ticked, because
					 * without the review the cap would just be a stricter Maximum Images box.
					 */
					'gallery_moderation_max_images'    => [
						'label'       => esc_html__( 'AI Moderation Photo Limit', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Cap each folder at this many photos, from 1 to 10, so one review covers the whole folder. Overrides higher limits, including per-plan ones. Folders already over the cap keep their photos but cannot take new ones. Leave empty for no cap.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 1,
						'max_value'   => 10,
						'_parent'     => 'gallery_ai_moderation',
						'_order'      => 48,
					],

					'gallery_enable_comments'          => [
						'label'       => esc_html__( 'Comments', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Let visitors comment on individual photos', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Every photo gets its own page with a comment thread, where signed-in visitors can comment, reply and like comments. People can delete their own comments, and the folder owner can delete any comment on their photos.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'default'     => true,
						'_order'      => 49,
					],

					'gallery_protect_files'            => [
						'label'       => esc_html__( 'Protect Files', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Protect private and members-only image files', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Moves private and members-only files out of the folder your web server publishes and serves them through an access-checked link, with unguessable names for new uploads. Recommended. If your hosting prevents the move, a notice above explains what to do.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'default'     => true,
						'_order'      => 90,
					],

					'gallery_button_radius'            => [
						'label'       => esc_html__( 'Button Corner Rounding (px)', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Rounds the corners of every button this plugin adds, so they match your site. Leave empty to keep the theme\'s shape, which suits most sites. 0 is square; larger is rounder.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 0,
						'max_value'   => 40,
						'_order'      => 46,
					],

					'gallery_max_filesize'             => [
						'label'       => esc_html__( 'Maximum File Size (MB)', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Reject gallery uploads larger than this many megabytes. Leave empty for no gallery-specific limit (the server upload limit still applies).', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 1,
						'_order'      => 100,
					],

					'gallery_storage_limit'            => [
						'label'       => esc_html__( 'Storage Limit (MB)', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Cap the total disk space each vendor\'s gallery may use, including thumbnails. Vendors see their usage on their Gallery page. Leave empty for no cap. A membership plan can raise or lower this per plan.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 1,
						'_order'      => 105,
					],

					'gallery_image_formats'            => [
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

					'gallery_max_dimensions'           => [
						'label'       => esc_html__( 'Maximum Image Dimensions (pixels)', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Resize uploads so neither side exceeds this many pixels; around 2000 suits most galleries. Leave empty to keep the original dimensions. For compression, WebP conversion and bulk work on existing images, use a dedicated image plugin.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 320,
						'_order'      => 120,
					],
				],
			],

			/*
			 * How the gallery LOOKS, kept apart from what it allows. Layout, the lightbox and the
			 * photo page sidebar used to sit among the storage caps and upload formats in General,
			 * which had grown past twenty settings; an owner looking for "how many folders across"
			 * had to read the maximum file size to find it. Moving a field between sections changes
			 * no option name, because HivePress prefixes from the field name and not from the tab or
			 * the section (components/class-admin.php:297), so nothing an owner has configured
			 * moves or resets.
			 */
			'gallery_display'      => [
				'title'       => esc_html__( 'Gallery Pages', 'additional-gallery-for-hivepress' ),
				'description' => esc_html__( 'How galleries look to visitors. These apply to the gallery page, to each folder page, and to the gallery sections you can add to vendor profiles and listing pages.', 'additional-gallery-for-hivepress' ),
				'_order'      => 101,

				'fields'      => [
					'gallery_layout'          => [
						'label'       => esc_html__( 'Gallery Layout', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Folder covers shows a grid of cover pictures, each opening the folder\'s own page. Single page puts every folder\'s photos on the gallery page one after another.', 'additional-gallery-for-hivepress' ),
						'type'        => 'radio',
						'default'     => 'folders',
						'_order'      => 10,

						'options'     => [
							'folders' => esc_html__( 'Folder covers', 'additional-gallery-for-hivepress' ),
							'single'  => esc_html__( 'Single page', 'additional-gallery-for-hivepress' ),
						],
					],

					'gallery_columns'         => [
						'label'       => esc_html__( 'Folder Columns', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'How many folder covers sit side by side on a full-width screen. Leave empty to fit as many as the screen allows. Narrow screens always show fewer.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 1,
						'max_value'   => 6,
						'_order'      => 20,
					],

					/*
					 * `_parent` on the columns box, so this only appears once a column count exists.
					 * Rows times columns is what gives a number of folders, and with the columns left
					 * to the screen there is no such number: the setting could be filled in and would
					 * then do nothing at all, which is precisely the shape of bug the settings notes
					 * warn about ("a select's stored values must match what the code branches on").
					 * Hiding it is honest; a filled-in box that is quietly ignored is not.
					 */
					'gallery_rows'            => [
						'label'       => esc_html__( 'Maximum Rows', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Caps the folder grid in the Gallery sections on vendor profiles and listing pages, linking to the full gallery for the rest. Leave empty to show every folder. Needs a column count above. The gallery page itself always shows all folders, as it is the only route to them.', 'additional-gallery-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 1,
						'_parent'     => 'gallery_columns',
						'_order'      => 30,
					],

					'gallery_cover_ratio'     => [
						'label'       => esc_html__( 'Cover Shape', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'The shape each folder cover is cropped to. Horizontal suits landscapes and interiors; vertical suits portraits and fashion. Choose the one your vendors mostly shoot.', 'additional-gallery-for-hivepress' ),
						'type'        => 'radio',
						'default'     => 'horizontal',
						'_order'      => 40,

						'options'     => [
							'horizontal' => esc_html__( 'Horizontal', 'additional-gallery-for-hivepress' ),
							'vertical'   => esc_html__( 'Vertical', 'additional-gallery-for-hivepress' ),
							'square'     => esc_html__( 'Square', 'additional-gallery-for-hivepress' ),
						],
					],

					'gallery_enable_lightbox' => [
						'label'       => esc_html__( 'Lightbox', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Let visitors enlarge the photo on its page', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'With this on, clicking the photo on its own page enlarges it in a pop-up viewer. Photos in the gallery always open their own page, where the comments live.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'default'     => true,
						'_order'      => 50,
					],

					'gallery_page_sidebar'    => [
						'label'       => esc_html__( 'Gallery Page Sidebar', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Adds a sidebar to the gallery page, showing the vendor\'s profile card. You can add your own widgets to it in the "Gallery Page (sidebar)" area under Appearance, then Widgets.', 'additional-gallery-for-hivepress' ),
						'type'        => 'radio',
						'default'     => 'none',
						'_order'      => 60,

						'options'     => [
							'none'  => esc_html__( 'No sidebar', 'additional-gallery-for-hivepress' ),
							'left'  => esc_html__( 'Left', 'additional-gallery-for-hivepress' ),
							'right' => esc_html__( 'Right', 'additional-gallery-for-hivepress' ),
						],
					],

					'gallery_folder_sidebar'  => [
						'label'       => esc_html__( 'Folder Page Sidebar', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Adds a sidebar to each folder page, showing the vendor\'s profile card. You can add your own widgets to it in the "Folder Page (sidebar)" area under Appearance, then Widgets.', 'additional-gallery-for-hivepress' ),
						'type'        => 'radio',
						'default'     => 'none',
						'_order'      => 70,

						'options'     => [
							'none'  => esc_html__( 'No sidebar', 'additional-gallery-for-hivepress' ),
							'left'  => esc_html__( 'Left', 'additional-gallery-for-hivepress' ),
							'right' => esc_html__( 'Right', 'additional-gallery-for-hivepress' ),
						],
					],

					/*
					 * Radio buttons rather than a drop-down, here and above. HivePress puts an empty
					 * "-" option at the top of every single-choice select it renders
					 * (fields/class-select.php:170-177), and on a left-or-right question that dash
					 * reads as a third position rather than as "unset" - which is what it was
					 * reported as. A radio set has no such entry (fields/class-radio.php:62-69) and
					 * the stored values are unchanged, so no site's saved choice moves.
					 *
					 * The photo page is the one sidebar with no "No sidebar" choice, because it holds
					 * the Manage Photo card: switching it off would leave a vendor no way at all to
					 * retitle, move or delete a photo.
					 */
					'gallery_photo_sidebar'   => [
						'label'       => esc_html__( 'Photo Page Sidebar', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Which side of the photo page the sidebar sits on. It holds the vendor\'s profile card and the owner\'s editing options, so it cannot be switched off. Add your own widgets in the "Photo Page (sidebar)" area under Appearance, then Widgets.', 'additional-gallery-for-hivepress' ),
						'type'        => 'radio',
						'default'     => 'right',
						'_order'      => 80,

						'options'     => [
							'left'  => esc_html__( 'Left', 'additional-gallery-for-hivepress' ),
							'right' => esc_html__( 'Right', 'additional-gallery-for-hivepress' ),
						],
					],
				],
			],

			'gallery_monetisation' => [
				'title'       => esc_html__( 'Gallery Monetisation', 'additional-gallery-for-hivepress' ),
				'description' => __( 'Members-only folders are how galleries make money: visitors must unlock them before seeing inside. <strong>To charge through memberships</strong>, add the gallery privileges to a paid HivePress Memberships plan, or to a free plan to simply require an account. <strong>To let vendors charge instead</strong>, enable paid access below: locked folders then offer that vendor\'s one-off purchase, falling back to your upgrade page link where no price is set.', 'additional-gallery-for-hivepress' ),
				'_order'      => 102,

				'fields'      => [
					'gallery_enable_members'     => [
						'label'       => esc_html__( 'Members-Only Folders', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Let vendors mark folders as members-only', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Members-only folders appear locked until a visitor gains access through a membership plan or a purchase. With this off, vendors choose only public or private, and existing members-only folders behave as private.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'default'     => true,
						'_order'      => 10,
					],

					/*
					 * Radio, for the same reason as the sidebar settings: this has three named
					 * choices and a default, so HivePress's automatic empty "-" entry was a fourth
					 * option that meant nothing. Found by sweeping every closed-set select in the
					 * plugin rather than only fixing the one that was reported.
					 */
					'gallery_locked_display'     => [
						'label'       => esc_html__( 'Locked Folder Display', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Choose how members-only folders appear to visitors without access.', 'additional-gallery-for-hivepress' ),
						'type'        => 'radio',
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
						'description' => esc_html__( 'Vendors set their own prices and lengths, from a day to permanent, up to three offers at once. Buying is a normal WooCommerce checkout, so WooCommerce is required. While a vendor sells access, their locked folders show the purchase button rather than the upgrade page link. With HivePress Marketplace, these sales count towards vendor earnings.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 30,
					],

					/*
					 * What one purchase actually buys. Vendor-wide is how paid access has always
					 * worked and stays the default, so no site's existing prices or sold accesses
					 * change on upgrade. Per folder is for sites whose vendors sell distinct sets of
					 * work - one shoot, one course, one collection - where charging once for all of
					 * them at once is the wrong offer.
					 *
					 * Switching between them does not move anybody's prices or take anybody's access
					 * away: prices are stored against the vendor and against each folder separately,
					 * and a grant is keyed to whichever it was bought for. Switching back finds both
					 * exactly as they were.
					 */
					'gallery_access_scope'       => [
						'label'       => esc_html__( 'What Access Buys', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Whole gallery: one set of prices unlocks all of a vendor\'s members-only folders at once. Each folder separately: prices are set per folder and a buyer unlocks only the folder they paid for. Prices set under one choice are kept if you switch.', 'additional-gallery-for-hivepress' ),
						'type'        => 'radio',
						'default'     => 'vendor',
						'_parent'     => 'gallery_enable_paid_access',
						'_order'      => 40,

						'options'     => [
							'vendor' => esc_html__( 'The vendor\'s whole gallery', 'additional-gallery-for-hivepress' ),
							'folder' => esc_html__( 'Each folder separately', 'additional-gallery-for-hivepress' ),
						],
					],

					'gallery_commission_rate'    => [
						'label'       => esc_html__( 'Commission Rate', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Your percentage of a gallery access sale, added at checkout as a separate Platform fee line, so the vendor still receives their price. Leave both commission boxes empty to take none. It only reaches you if your gateway settles gallery orders into the site\'s own account, which is the normal case; a gateway that charges each vendor\'s connected account directly pays the whole order to the vendor, so take your cut through that gateway instead.', 'additional-gallery-for-hivepress' ),
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
				'description' => esc_html__( 'Your galleries are kept if you ever delete this plugin. WordPress\'s own warning that deleting a plugin also deletes its data is generic and does not describe this one: nothing is removed unless you tick the box first.', 'additional-gallery-for-hivepress' ),
				'_order'      => 103,

				'fields'      => [
					'gallery_delete_data' => [
						'label'       => esc_html__( 'Delete All Data', 'additional-gallery-for-hivepress' ),
						'caption'     => esc_html__( 'Delete folders, likes, comments and settings when this plugin is deleted (photos stay in your media library)', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Unticked, deleting the plugin keeps every folder, photo, like, comment, purchased access and setting, so reinstalling restores your galleries exactly. Ticked, deleting the plugin permanently removes all of that; photos always stay in your media library. This cannot be undone.', 'additional-gallery-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 10,
					],
				],
			],
		],
	],
];
