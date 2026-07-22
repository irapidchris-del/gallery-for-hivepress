<?php
/**
 * Gallery component.
 *
 * @package AdditionalGalleryForHivePress\Components
 */

namespace HivePress\Components;

use HivePress\Helpers as hp;
use HivePress\Models;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Wires the gallery into HivePress: account menu, page links, access
 * control and assets.
 *
 * Accessible via `hivepress()->gallery`.
 */
final class Gallery extends Component {

	/**
	 * Current vendor cache.
	 *
	 * @var mixed
	 */
	protected $current_vendor;

	/**
	 * Class constructor.
	 *
	 * @param array $args Component arguments.
	 */
	public function __construct( $args = [] ) {

		// Add the account menu item.
		add_filter( 'hivepress/v1/menus/user_account', [ $this, 'alter_account_menu' ] );

		// Add gallery links to vendor and listing pages.
		add_filter( 'hivepress/v1/templates/vendor_view_page', [ $this, 'alter_vendor_view_page' ] );
		add_filter( 'hivepress/v1/templates/listing_view_page', [ $this, 'alter_listing_view_page' ] );

		// Allow video uploads when enabled.
		add_filter( 'hivepress/v1/models/gallery_folder', [ $this, 'alter_model_fields' ] );

		// Register the shared OpenAI settings.
		add_filter( 'hivepress/v1/settings', [ $this, 'add_shared_settings' ] );

		// Populate the admin images meta box.
		add_filter( 'hivepress/v1/meta_boxes/gallery_folder_images', [ $this, 'alter_folder_images_meta_box' ] );

		// Add admin list table columns.
		add_filter( 'manage_hp_gallery_folder_posts_columns', [ $this, 'add_admin_columns' ] );
		add_action( 'manage_hp_gallery_folder_posts_custom_column', [ $this, 'render_admin_column' ], 10, 2 );

		// Delete cached previews when attachments are edited or deleted.
		add_action( 'edit_attachment', [ $this, 'delete_teaser_image' ] );
		add_action( 'delete_attachment', [ $this, 'delete_teaser_image' ] );

		// Shield gallery images from media APIs and attachment pages.
		add_filter( 'rest_attachment_query', [ $this, 'alter_rest_attachment_query' ], 10, 2 );
		add_action( 'template_redirect', [ $this, 'redirect_attachment_page' ] );

		parent::__construct( $args );
	}

	/**
	 * Gets the current user's vendor.
	 *
	 * @return \HivePress\Models\Vendor|null Vendor object or null.
	 */
	public function get_current_vendor() {
		if ( ! isset( $this->current_vendor ) ) {
			$vendor = null;

			if ( is_user_logged_in() ) {
				$vendor = Models\Vendor::query()->filter(
					[
						'status' => 'publish',
						'user'   => get_current_user_id(),
					]
				)->get_first();
			}

			$this->current_vendor = $vendor ? $vendor : false;
		}

		return $this->current_vendor ? $this->current_vendor : null;
	}

	/**
	 * Checks if the HivePress Memberships extension is active.
	 *
	 * @return bool
	 */
	public function is_memberships_active() {
		return post_type_exists( 'hp_membership' );
	}

	/**
	 * Checks if a user has an active membership in any of the given plans.
	 *
	 * Memberships are `hp_membership` posts where the user is the post
	 * author, the plan is the post parent, and the `publish` status means
	 * the membership is active.
	 *
	 * @param int   $user_id User ID.
	 * @param array $plan_ids Membership plan IDs.
	 * @return bool
	 */
	public function user_has_active_membership( $user_id, $plan_ids ) {
		static $cache = [];

		$user_id  = absint( $user_id );
		$plan_ids = array_filter( array_map( 'absint', (array) $plan_ids ) );

		if ( ! $user_id || ! $plan_ids || ! $this->is_memberships_active() ) {
			return false;
		}

		// Check the request-level cache.
		$cache_key = $user_id . '_' . implode( '_', $plan_ids );

		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$membership_ids = get_posts(
			[
				'post_type'       => 'hp_membership',
				'post_status'     => 'publish',
				'author'          => $user_id,
				'post_parent__in' => $plan_ids,
				'fields'          => 'ids',
				'numberposts'     => 1,
			]
		);

		$cache[ $cache_key ] = ! empty( $membership_ids );

		return $cache[ $cache_key ];
	}

	/**
	 * Checks if a vendor is allowed to use the gallery feature.
	 *
	 * If no plans are selected in the settings, all vendors can use it.
	 * If plans are selected, the vendor's user needs an active membership
	 * in one of them (this also fails closed if Memberships is inactive,
	 * so paid access is never given away by deactivating the extension).
	 *
	 * @param \HivePress\Models\Vendor|null $vendor Vendor object.
	 * @return bool
	 */
	public function vendor_can_use_gallery( $vendor ) {
		$can = true;

		$plan_ids = array_filter( (array) get_option( 'hp_gallery_manage_plans' ) );

		if ( $plan_ids ) {
			$can = $vendor && $this->user_has_active_membership( $vendor->get_user__id(), $plan_ids );
		}

		/**
		 * Filters whether a vendor can use the gallery feature.
		 *
		 * @param bool  $can Whether the vendor has access.
		 * @param mixed $vendor Vendor object.
		 */
		return (bool) apply_filters( 'hp_agl/vendor_can_use_gallery', $can, $vendor );
	}

	/**
	 * Checks if the current user can view a vendor's members-only folders.
	 *
	 * Folder owners and users with `edit_others_posts` always can. Other
	 * users need an active membership in one of the plans selected in the
	 * settings; if no plans are selected, members-only folders stay locked.
	 *
	 * @param \HivePress\Models\Vendor|null $vendor Vendor object.
	 * @return bool
	 */
	public function user_can_view_member_folders( $vendor ) {
		$can     = false;
		$user_id = get_current_user_id();

		if ( $user_id ) {
			if ( current_user_can( 'edit_others_posts' ) || ( $vendor && $user_id === $vendor->get_user__id() ) ) {
				$can = true;
			} else {
				$plan_ids = array_filter( (array) get_option( 'hp_gallery_view_plans' ) );

				$can = $plan_ids && $this->user_has_active_membership( $user_id, $plan_ids );
			}
		}

		/**
		 * Filters whether the current user can view a vendor's members-only
		 * folders. Hook here to grant access from other systems, e.g.
		 * per-vendor purchases.
		 *
		 * @param bool  $can Whether the user has access.
		 * @param int   $user_id Current user ID.
		 * @param mixed $vendor Vendor object.
		 */
		return (bool) apply_filters( 'hp_agl/user_can_view_member_folders', $can, $user_id, $vendor );
	}

	/**
	 * Gets the display mode for locked members-only folders.
	 *
	 * @return string Either 'blur', 'tiles' or 'hide'.
	 */
	public function get_locked_display() {
		$display = get_option( 'hp_gallery_locked_display' );

		return in_array( $display, [ 'blur', 'tiles', 'hide' ], true ) ? $display : 'blur';
	}

	/**
	 * Gets the URL locked visitors are sent to.
	 *
	 * @return mixed URL string or null.
	 */
	public function get_upgrade_url() {
		$page_id = hp_agl_int( get_option( 'hp_gallery_upgrade_page' ) );

		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			return get_permalink( $page_id );
		}

		// Fall back to the Memberships plan selection page.
		if ( hivepress()->router->get_route( 'membership_plans_view_page' ) ) {
			return hivepress()->router->get_url( 'membership_plans_view_page' );
		}

		if ( ! is_user_logged_in() ) {
			return hivepress()->router->get_return_url( 'user_login_page' );
		}

		return null;
	}

	/**
	 * Gets a vendor's publicly listed gallery folders (public and
	 * members-only; private folders are never listed).
	 *
	 * @param int $vendor_id Vendor ID.
	 * @return \HivePress\Queries\Post Folder query results (iterable of `Gallery_Folder` models).
	 */
	public function get_listed_folders( $vendor_id ) {
		return Models\Gallery_Folder::query()->filter(
			[
				'status'         => 'publish',
				'vendor'         => absint( $vendor_id ),
				'visibility__in' => [ 'public', 'members' ],
			]
		)->order(
			[
				'sort_order'   => 'asc',
				'created_date' => 'asc',
			]
		)->get();
	}

	/**
	 * Gets the image and video counts a visitor would see for a vendor,
	 * including locked previews unless they are hidden by the settings.
	 * Both counts are zero when the vendor has no gallery access.
	 *
	 * @param \HivePress\Models\Vendor|null $vendor Vendor object.
	 * @return array
	 */
	public function get_visible_media_counts( $vendor ) {
		$counts = [
			'images' => 0,
			'videos' => 0,
		];

		if ( empty( $vendor ) || ! $this->vendor_can_use_gallery( $vendor ) ) {
			return $counts;
		}

		$member_view = $this->user_can_view_member_folders( $vendor );
		$display     = $this->get_locked_display();

		foreach ( $this->get_listed_folders( $vendor->get_id() ) as $folder ) {
			if ( ! $folder instanceof \HivePress\Models\Gallery_Folder ) {
				continue;
			}

			if ( 'members' === $folder->get_visibility() && ! $member_view && 'hide' === $display ) {
				continue;
			}

			$folder_counts = $this->get_media_counts( $folder );

			$counts['images'] += $folder_counts['images'];
			$counts['videos'] += $folder_counts['videos'];
		}

		return $counts;
	}

	/**
	 * Adds the gallery item to the account menu.
	 *
	 * @param array $menu Menu arguments.
	 * @return array
	 */
	public function alter_account_menu( $menu ) {
		$vendor = $this->get_current_vendor();

		if ( $vendor && $this->vendor_can_use_gallery( $vendor ) ) {
			$menu['items']['gallery'] = [
				'route'  => 'gallery_edit_page',
				'_order' => 40,
			];
		}

		return $menu;
	}

	/**
	 * Adds the gallery link to vendor pages.
	 *
	 * @param array $template Template arguments.
	 * @return array
	 */
	public function alter_vendor_view_page( $template ) {
		return hp\merge_trees(
			$template,
			[
				'blocks' => [
					'page_sidebar' => [
						'blocks' => [
							'gallery_link' => [
								'type'   => 'gallery_link',
								'_order' => 15,
							],
						],
					],
				],
			]
		);
	}

	/**
	 * Adds the gallery link to listing pages.
	 *
	 * @param array $template Template arguments.
	 * @return array
	 */
	public function alter_listing_view_page( $template ) {
		return hp\merge_trees(
			$template,
			[
				'blocks' => [
					'page_sidebar' => [
						'blocks' => [
							'gallery_link' => [
								'type'   => 'gallery_link',
								'_order' => 35,
							],
						],
					],
				],
			]
		);
	}

	/**
	 * Gets the gallery layout.
	 *
	 * @return string
	 */
	public function get_layout() {
		$layout = get_option( 'hp_gallery_layout' );

		if ( ! in_array( $layout, [ 'folders', 'single' ], true ) ) {
			$layout = 'folders';
		}

		return $layout;
	}

	/**
	 * Gets the cover image ID of a folder.
	 *
	 * Mirrors the core listing behaviour with videos enabled: the first
	 * image is preferred, falling back to the first item of any type.
	 *
	 * @param \HivePress\Models\Gallery_Folder $folder Folder object.
	 * @return mixed Attachment ID or null.
	 */
	public function get_folder_cover_id( $folder ) {
		$image_ids = (array) $folder->get_images__id();

		foreach ( $image_ids as $image_id ) {
			if ( 0 === strpos( (string) get_post_mime_type( $image_id ), 'image' ) ) {
				return $image_id;
			}
		}

		return $image_ids ? hp\get_first_array_value( $image_ids ) : null;
	}

	/**
	 * Gets the last update time of the given folders.
	 *
	 * This is the upload time of the newest gallery item in the folders,
	 * so it reflects new work rather than edits to folder details.
	 *
	 * @param array $folder_ids Folder IDs.
	 * @return mixed Unix timestamp or null.
	 */
	public function get_updated_time( $folder_ids ) {
		$folder_ids = array_filter( array_map( 'absint', (array) $folder_ids ) );

		if ( ! $folder_ids ) {
			return null;
		}

		// Get the newest gallery item.
		$attachment_ids = get_posts(
			[
				'post_type'       => 'attachment',
				'post_status'     => 'inherit',
				'post_parent__in' => $folder_ids,
				'numberposts'     => 1,
				'orderby'         => 'date',
				'order'           => 'DESC',
				'fields'          => 'ids',

				'meta_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded per-vendor lookup for a single newest item.
					[
						'key'   => 'hp_parent_field',
						'value' => 'images',
					],
				],
			]
		);

		if ( ! $attachment_ids ) {
			return null;
		}

		$time = get_post_time( 'U', true, hp_agl_int( hp\get_first_array_value( $attachment_ids ) ) );

		return $time ? $time : null;
	}

	/**
	 * Adds video formats to the images field when video uploads are enabled.
	 *
	 * Mirrors the core listing pattern: the extra extensions are merged into
	 * the model field, so the upload endpoint and both the front-end and
	 * admin upload managers accept them.
	 *
	 * @param array $model Model arguments.
	 * @return array
	 */
	public function alter_model_fields( $model ) {
		if ( get_option( 'hp_gallery_allow_video' ) ) {
			$model['fields']['images'] = hp\merge_arrays(
				$model['fields']['images'],
				[
					'formats' => [ 'mp4', 'webm', 'ogv' ],
				]
			);
		}

		return $model;
	}

	/**
	 * Gets the public URL of a folder.
	 *
	 * @param \HivePress\Models\Gallery_Folder $folder Folder object.
	 * @return string
	 */
	public function get_folder_url( $folder ) {
		return hivepress()->router->get_url(
			'gallery_folder_view_page',
			[
				'vendor_id'         => $folder->get_vendor__id(),
				'gallery_folder_id' => $folder->get_id(),
			]
		);
	}

	/**
	 * Gets the image and video counts of a folder.
	 *
	 * @param \HivePress\Models\Gallery_Folder $folder Folder object.
	 * @return array
	 */
	public function get_media_counts( $folder ) {
		$counts = [
			'images' => 0,
			'videos' => 0,
		];

		// Prime the relation field; it is only populated by the lazy
		// loader, mirroring the core listing images template.
		$folder->get_images__id();

		foreach ( (array) $folder->get_images() as $attachment ) {
			if ( 0 === strpos( (string) $attachment->get_mime_type(), 'video/' ) ) {
				++$counts['videos'];
			} else {
				++$counts['images'];
			}
		}

		return $counts;
	}

	/**
	 * Gets the media count label of a folder.
	 *
	 * @param array $counts Image and video counts.
	 * @return string
	 */
	public function get_media_count_label( $counts ) {
		$images = hp_agl_int( hp\get_array_value( $counts, 'images' ) );
		$videos = hp_agl_int( hp\get_array_value( $counts, 'videos' ) );

		if ( $videos && $images ) {
			/* translators: 1: photos number, 2: videos number. */
			return sprintf( esc_html__( '%1$s photos, %2$s videos', 'additional-gallery-for-hivepress' ), number_format_i18n( $images ), number_format_i18n( $videos ) );
		}

		if ( $videos ) {
			/* translators: %s: videos number. */
			return sprintf( _n( '%s video', '%s videos', $videos, 'additional-gallery-for-hivepress' ), number_format_i18n( $videos ) );
		}

		/* translators: %s: photos number. */
		return sprintf( _n( '%s photo', '%s photos', $images, 'additional-gallery-for-hivepress' ), number_format_i18n( $images ) );
	}

	/**
	 * Renders the media grid of a folder.
	 *
	 * Shared by the public gallery layouts and the folder page. When the
	 * folder is locked, only blurred previews or placeholders are rendered
	 * and the original file URLs never appear in the markup. Videos are
	 * rendered as inline players, mirroring the core listing gallery.
	 *
	 * @param \HivePress\Models\Gallery_Folder $folder Folder object.
	 * @param bool   $locked Whether the folder is locked for the current user.
	 * @return string
	 */
	public function render_folder_media( $folder, $locked ) {
		$output = '';

		// Prime the relation field (see get_media_counts()).
		$folder->get_images__id();

		// Get attachments.
		$attachments = (array) $folder->get_images();

		if ( ! $attachments ) {
			return $output;
		}

		if ( $locked ) {

			// Locked rendering.
			$locked_display = $this->get_locked_display();

			$output .= '<div class="hp-agl-grid hp-agl-grid--locked">';

			foreach ( $attachments as $attachment ) {

				// Get the blurred preview, if previews are enabled and one can be generated.
				$teaser_url = null;

				if ( 'blur' === $locked_display ) {
					$teaser_url = $this->get_teaser_url( $attachment->get_id() );
				}

				if ( $teaser_url ) {
					$output .= '<span class="hp-agl-grid__item hp-agl-grid__item--locked"><img src="' . esc_url( $teaser_url ) . '" alt="" loading="lazy"><i class="hp-icon fas fa-lock"></i></span>';
				} else {
					$output .= '<span class="hp-agl-grid__item hp-agl-grid__item--locked hp-agl-grid__item--placeholder"><i class="hp-icon fas fa-lock"></i></span>';
				}
			}

			$output .= '</div>';

			return $output;
		}

		// Full rendering.
		$output .= '<div class="hp-agl-grid">';

		foreach ( $attachments as $attachment ) {
			$caption = get_post_field( 'post_excerpt', $attachment->get_id() );
			$caption = is_string( $caption ) ? trim( $caption ) : '';

			if ( 0 === strpos( (string) $attachment->get_mime_type(), 'video/' ) ) {

				// Render the video inline, mirroring the core listing gallery.
				$output .= '<span class="hp-agl-grid__item hp-agl-grid__item--video">';
				$output .= '<video controls preload="metadata" playsinline><source src="' . esc_url( $attachment->get_url() . '#t=0.001' ) . '" type="' . esc_attr( $attachment->get_mime_type() ) . '"></video>';

				if ( $caption ) {
					$output .= '<span class="hp-agl-grid__caption">' . esc_html( $caption ) . '</span>';
				}

				$output .= '</span>';
			} else {
				$full_url = wp_get_attachment_image_url( $attachment->get_id(), 'large' );

				if ( ! $full_url ) {
					$full_url = wp_get_attachment_image_url( $attachment->get_id(), 'full' );
				}

				if ( ! $full_url ) {
					continue;
				}

				$thumbnail = wp_get_attachment_image(
					$attachment->get_id(),
					'medium_large',
					false,
					[
						'loading' => 'lazy',
						'alt'     => $caption ? $caption : $folder->get_title(),
					]
				);

				$output .= '<span class="hp-agl-grid__item">';
				$output .= '<a href="' . esc_url( $full_url ) . '" data-fancybox="hp-agl-folder-' . esc_attr( (string) $folder->get_id() ) . '" data-caption="' . esc_attr( $caption ? $caption : $folder->get_title() ) . '">' . $thumbnail . '</a>';

				if ( $caption ) {
					$output .= '<span class="hp-agl-grid__caption">' . esc_html( $caption ) . '</span>';
				}

				$output .= '</span>';
			}
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Registers the shared OpenAI section and API key field.
	 *
	 * The key is shared with any other extension that talks to OpenAI
	 * (for example Automated Listing Moderation for HivePress), so both
	 * the section and the field are added only when another plugin has
	 * not registered them already. Whichever plugin runs first creates
	 * them; the field then appears exactly once.
	 *
	 * @param array $settings Settings configuration.
	 * @return array
	 */
	public function add_shared_settings( $settings ) {
		if ( ! isset( $settings['integrations']['sections']['openai'] ) ) {
			$settings['integrations']['sections']['openai'] = [
				'title'  => 'OpenAI',
				'_order' => 40,
				'fields' => [],
			];
		}

		if ( ! isset( $settings['integrations']['sections']['openai']['fields']['openai_api_key'] ) ) {
			$settings['integrations']['sections']['openai']['fields']['openai_api_key'] = [
				'label'       => esc_html__( 'API Key', 'additional-gallery-for-hivepress' ),
				'description' => __( 'Your OpenAI API key, shared by any installed extension that uses OpenAI\'s free Moderation endpoint. Moderation calls are free, but an OpenAI API account is required to obtain a key.', 'additional-gallery-for-hivepress' ),
				'type'        => 'text',
				'max_length'  => 256,
				'_order'      => 10,
			];
		}

		return $settings;
	}

	/**
	 * Prepares the image URLs of a folder save for moderation.
	 *
	 * Only public http(s) URLs are usable, because OpenAI fetches each
	 * image server-side. The list is de-duplicated and capped.
	 *
	 * @param array $attachment_ids Attachment IDs.
	 * @return array Image URLs.
	 */
	public function prepare_moderation_urls( $attachment_ids ) {
		$urls = [];

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = hp_agl_int( $attachment_id );

			if ( ! $attachment_id ) {
				continue;
			}

			// Get the image URL.
			$url = wp_get_attachment_image_url( $attachment_id, 'large' );

			if ( ! $url ) {
				$url = wp_get_attachment_url( $attachment_id );
			}

			if ( ! $url || ! preg_match( '#^https?://#i', $url ) || in_array( $url, $urls, true ) ) {
				continue;
			}

			$urls[] = $url;
		}

		/**
		 * Filters the maximum number of images sent for moderation in a
		 * single request.
		 *
		 * @param int $cap Maximum number of images.
		 */
		$cap = apply_filters( 'hp_agl/moderation_image_cap', 10 );

		$cap = max( 0, is_scalar( $cap ) ? (int) $cap : 0 );

		return array_slice( $urls, 0, $cap );
	}

	/**
	 * Moderates a set of image URLs with the OpenAI Moderation endpoint.
	 *
	 * All URLs are sent together in one free request. The check fails
	 * open: on any failure (missing key, transport error, unexpected
	 * response) null is returned and the caller treats the service as
	 * unavailable rather than blocking the vendor.
	 *
	 * @param array $urls Image URLs.
	 * @return bool|null True if flagged, false if clean, null if unavailable.
	 */
	public function moderate_image_urls( $urls ) {

		// Get the shared API key.
		$api_key = trim( hp_agl_string( get_option( 'hp_openai_api_key' ) ) );

		if ( ! $api_key || ! $urls ) {
			return null;
		}

		// Build the request body.
		$input = [];

		foreach ( $urls as $url ) {
			$input[] = [
				'type'      => 'image_url',

				'image_url' => [
					'url' => hp_agl_string( $url ),
				],
			];
		}

		$body = wp_json_encode(
			[
				'model' => 'omni-moderation-latest',
				'input' => $input,
			]
		);

		if ( false === $body ) {
			return null;
		}

		// Send the request. OpenAI fetches each image server-side, so a
		// generous timeout is required.
		$response = wp_remote_post(
			'https://api.openai.com/v1/moderations',
			[
				'timeout' => 15,

				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],

				'body'    => $body,
			]
		);

		if ( is_wp_error( $response ) || 200 !== hp_agl_int( wp_remote_retrieve_response_code( $response ) ) ) {
			return null;
		}

		// Narrow the decoded response step by step.
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		$results = $data['results'] ?? null;

		if ( ! is_array( $results ) ) {
			return null;
		}

		$result = $results[0] ?? null;

		if ( ! is_array( $result ) ) {
			return null;
		}

		$flagged = $result['flagged'] ?? null;

		return is_bool( $flagged ) ? $flagged : null;
	}

	/**
	 * Populates the admin images meta box with the folder's image IDs,
	 * mirroring the core listing images meta box.
	 *
	 * @param array $meta_box Meta box arguments.
	 * @return array
	 */
	public function alter_folder_images_meta_box( $meta_box ) {

		// Get folder.
		$folder = Models\Gallery_Folder::query()->get_by_id( get_post() );

		if ( $folder ) {

			// Set image IDs.
			$meta_box['fields']['images']['default'] = $folder->get_images__id();
		}

		return $meta_box;
	}

	/**
	 * Adds columns to the folders list table.
	 *
	 * @param array $columns Table columns.
	 * @return array
	 */
	public function add_admin_columns( $columns ) {
		$date = null;

		if ( isset( $columns['date'] ) ) {
			$date = $columns['date'];

			unset( $columns['date'] );
		}

		$columns['hp_agl_vendor']     = esc_html__( 'Vendor', 'additional-gallery-for-hivepress' );
		$columns['hp_agl_visibility'] = esc_html__( 'Visibility', 'additional-gallery-for-hivepress' );
		$columns['hp_agl_images']     = esc_html__( 'Images', 'additional-gallery-for-hivepress' );

		if ( $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Renders a folders list table column.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_admin_column( $column, $post_id ) {
		if ( 'hp_agl_vendor' === $column ) {
			$vendor_id = wp_get_post_parent_id( $post_id );

			if ( $vendor_id && 'hp_vendor' === get_post_type( $vendor_id ) ) {
				$edit_link = get_edit_post_link( $vendor_id );

				if ( $edit_link ) {
					echo '<a href="' . esc_url( $edit_link ) . '">' . esc_html( get_the_title( $vendor_id ) ) . '</a>';
				} else {
					echo esc_html( get_the_title( $vendor_id ) );
				}
			} else {
				echo '&mdash;';
			}
		} elseif ( 'hp_agl_visibility' === $column ) {
			$labels = [
				'public'  => esc_html__( 'Public', 'additional-gallery-for-hivepress' ),
				'members' => esc_html__( 'Members only', 'additional-gallery-for-hivepress' ),
				'private' => esc_html__( 'Private', 'additional-gallery-for-hivepress' ),
			];

			$visibility = get_post_meta( $post_id, 'hp_visibility', true );
			$visibility = is_string( $visibility ) ? $visibility : '';

			echo esc_html( hp_agl_string( hp\get_array_value( $labels, $visibility, $labels['private'] ) ) );
		} elseif ( 'hp_agl_images' === $column ) {
			$folder = Models\Gallery_Folder::query()->get_by_id( $post_id );

			echo esc_html( number_format_i18n( $folder ? count( (array) $folder->get_images__id() ) : 0 ) );
		}
	}

	/**
	 * Gets the blurred preview URL for an image.
	 *
	 * Locked folders must never expose original file URLs, so a heavily
	 * blurred derivative is generated once with GD (tiny downscale,
	 * repeated Gaussian passes, upscale) and cached in the uploads folder.
	 * If generation is impossible null is returned, and the caller renders
	 * a lock placeholder instead, so originals are never leaked.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|null Preview URL or null.
	 */
	public function get_teaser_url( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		// Only images can be blurred.
		if ( 0 !== strpos( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) {
			return null;
		}

		/**
		 * Filters the blurred preview settings.
		 *
		 * @param array $args Preview settings.
		 */
		$args = apply_filters(
			'hp_agl/teaser_args',
			[
				'width'   => 480,
				'passes'  => 15,
				'quality' => 65,
			]
		);

		// Get the cache location.
		$upload_dir = wp_get_upload_dir();

		$dir  = $upload_dir['basedir'] . '/hp-agl-teasers';
		$file = $dir . '/' . $attachment_id . '.jpg';
		$url  = $upload_dir['baseurl'] . '/hp-agl-teasers/' . $attachment_id . '.jpg';

		if ( file_exists( $file ) ) {
			return $url;
		}

		// Check GD availability.
		if ( ! function_exists( 'imagecreatefromstring' ) || ! function_exists( 'imagefilter' ) ) {
			return null;
		}

		// Prepare the cache directory.
		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}

		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', '<?php // Silence is golden.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- one-off index guard beside the direct GD writes; WP_Filesystem targets remote/credentialed setups.
		}

		// Get the source file, preferring the medium size to limit memory use.
		$source = get_attached_file( $attachment_id );

		if ( $source ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );

			if ( ! empty( $metadata['sizes']['medium']['file'] ) ) {
				$medium = path_join( dirname( $source ), $metadata['sizes']['medium']['file'] );

				if ( file_exists( $medium ) ) {
					$source = $medium;
				}
			}
		}

		if ( ! $source || ! file_exists( $source ) ) {
			return null;
		}

		// Load the image.
		$contents = file_get_contents( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a local upload for GD; WP_Filesystem is for remote/credentialed contexts.
		$image    = $contents ? @imagecreatefromstring( $contents ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- corrupt files must fail quietly to the placeholder, not raise warnings.

		if ( ! $image ) {
			return null;
		}

		$width  = imagesx( $image );
		$height = imagesy( $image );

		// Clamp the aspect ratio. Extreme ratios would allocate a huge
		// canvas (a fatal on constrained hosts), and the preview is
		// square-cropped in the grid anyway, so this is not visible.
		$ratio = min( 4, max( 0.25, $height / $width ) );

		// Downscale to a tiny image, blur it repeatedly, then upscale.
		$tiny_width  = 24;
		$tiny_height = max( 1, (int) round( $tiny_width * $ratio ) );

		$tiny = imagecreatetruecolor( $tiny_width, $tiny_height );

		if ( ! $tiny ) {
			imagedestroy( $image );

			return null;
		}

		// Fill with white so transparent areas do not blur to black.
		$white = imagecolorallocate( $tiny, 255, 255, 255 );

		if ( false !== $white ) {
			imagefill( $tiny, 0, 0, $white );
		}

		imagecopyresampled( $tiny, $image, 0, 0, 0, 0, $tiny_width, $tiny_height, $width, $height );
		imagedestroy( $image );

		$passes = max( 1, (int) $args['passes'] );

		for ( $pass = 0; $pass < $passes; $pass++ ) {
			imagefilter( $tiny, IMG_FILTER_GAUSSIAN_BLUR );
		}

		$out_width  = max( 100, (int) $args['width'] );
		$out_height = max( 1, (int) round( $out_width * $ratio ) );

		$output = imagecreatetruecolor( $out_width, $out_height );

		if ( ! $output ) {
			imagedestroy( $tiny );

			return null;
		}

		imagecopyresampled( $output, $tiny, 0, 0, 0, 0, $out_width, $out_height, $tiny_width, $tiny_height );
		imagedestroy( $tiny );

		// Save the preview.
		$saved = imagejpeg( $output, $file, min( 90, max( 10, absint( $args['quality'] ) ) ) );

		imagedestroy( $output );

		return $saved ? $url : null;
	}

	/**
	 * Deletes the cached blurred preview of a deleted attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function delete_teaser_image( $attachment_id ) {
		$upload_dir = wp_get_upload_dir();

		$file = $upload_dir['basedir'] . '/hp-agl-teasers/' . absint( $attachment_id ) . '.jpg';

		if ( file_exists( $file ) ) {
			wp_delete_file( $file );
		}
	}

	/**
	 * Hides gallery images from public REST API media queries when file
	 * protection is enabled.
	 *
	 * @param array            $args Query arguments.
	 * @param \WP_REST_Request $request API request.
	 * @return array
	 */
	public function alter_rest_attachment_query( $args, $request ) {
		if ( ! get_option( 'hp_gallery_protect_files' ) || current_user_can( 'edit_posts' ) ) {
			return $args;
		}

		$args['meta_query']   = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : []; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- required to exclude protected gallery files from public media API results.
		$args['meta_query'][] = [
			'relation' => 'OR',

			[
				'key'     => 'hp_parent_model',
				'compare' => 'NOT EXISTS',
			],

			[
				'key'     => 'hp_parent_model',
				'value'   => 'gallery_folder',
				'compare' => '!=',
			],
		];

		return $args;
	}

	/**
	 * Redirects attachment pages of images in members-only and private
	 * folders for visitors without access to them.
	 *
	 * @return void
	 */
	public function redirect_attachment_page() {
		if ( ! is_attachment() ) {
			return;
		}

		// Check the parent folder.
		$attachment = get_queried_object();

		if ( ! $attachment || empty( $attachment->post_parent ) || 'hp_gallery_folder' !== get_post_type( $attachment->post_parent ) ) {
			return;
		}

		// Get the folder.
		$folder = Models\Gallery_Folder::query()->get_by_id( $attachment->post_parent );

		if ( ! $folder ) {
			return;
		}

		// Check access.
		$visibility = $folder->get_visibility();
		$allowed    = false;
		$vendor     = null;
		$owner      = get_current_user_id() === $folder->get_user__id() || current_user_can( 'edit_others_posts' );

		if ( in_array( $visibility, [ 'public', 'members' ], true ) && ! $owner ) {

			// Listed folders require an unlocked gallery, matching the gallery pages.
			$vendor = Models\Vendor::query()->get_by_id( $folder->get_vendor__id() );

			if ( ! $this->vendor_can_use_gallery( $vendor ) ) {
				$visibility = 'private';
			}
		}

		if ( 'public' === $visibility ) {
			$allowed = true;
		} elseif ( 'members' === $visibility ) {
			$allowed = $this->user_can_view_member_folders( $vendor );
		} else {
			$allowed = $owner;
		}

		if ( ! $allowed ) {
			wp_safe_redirect( home_url( '/' ) );

			exit;
		}
	}
}
