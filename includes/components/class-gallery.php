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

		// Register the shared OpenAI settings.
		add_filter( 'hivepress/v1/settings', [ $this, 'add_shared_settings' ] );

		// Integrate gallery access with HivePress Memberships plans.
		add_filter( 'hivepress/v1/models/membership_plan', [ $this, 'add_plan_fields' ] );
		add_filter( 'hivepress/v1/meta_boxes/membership_plan_settings', [ $this, 'add_plan_settings' ] );

		// Keep the fail-closed gating flags in sync when a plan is saved or removed.
		add_action( 'save_post_hp_membership_plan', [ $this, 'refresh_gating_flags' ] );
		add_action( 'deleted_post', [ $this, 'refresh_gating_flags_for_post' ] );
		add_action( 'trashed_post', [ $this, 'refresh_gating_flags_for_post' ] );
		add_action( 'untrashed_post', [ $this, 'refresh_gating_flags_for_post' ] );

		// Populate the admin images meta box.
		add_filter( 'hivepress/v1/meta_boxes/gallery_folder_images', [ $this, 'alter_folder_images_meta_box' ] );

		// Optimize gallery uploads (size limit + resize/compress/convert).
		add_filter( 'wp_handle_upload_prefilter', [ $this, 'limit_upload_size' ] );
		add_filter( 'wp_handle_upload', [ $this, 'optimize_upload' ], 10, 2 );

		// Add admin list table columns.
		add_filter( 'manage_hp_gallery_folder_posts_columns', [ $this, 'add_admin_columns' ] );
		add_action( 'manage_hp_gallery_folder_posts_custom_column', [ $this, 'render_admin_column' ], 10, 2 );

		// Add bulk optimize/restore actions to the folders list.
		add_filter( 'bulk_actions-edit-hp_gallery_folder', [ $this, 'add_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-hp_gallery_folder', [ $this, 'handle_bulk_actions' ], 10, 3 );
		add_action( 'admin_notices', [ $this, 'render_bulk_action_notice' ] );

		// Delete cached previews when attachments are edited or deleted.
		add_action( 'edit_attachment', [ $this, 'delete_teaser_image' ] );
		add_action( 'delete_attachment', [ $this, 'delete_teaser_image' ] );

		// Shield gallery images from media APIs and attachment pages.
		add_filter( 'rest_attachment_query', [ $this, 'alter_rest_attachment_query' ], 10, 2 );
		add_action( 'template_redirect', [ $this, 'redirect_attachment_page' ] );

		// Relocate private and members-only files behind the protected proxy.
		add_action( 'hivepress/v1/models/gallery_folder/update_images', [ $this, 'sync_folder_protection' ] );
		add_action( 'hivepress/v1/models/gallery_folder/update', [ $this, 'sync_folder_protection' ], 20 );

		// Rewrite protected file URLs to the access-checked proxy.
		add_filter( 'wp_get_attachment_url', [ $this, 'filter_attachment_url' ], 10, 2 );
		add_filter( 'wp_get_attachment_image_src', [ $this, 'filter_attachment_image_src' ], 10, 3 );
		add_filter( 'wp_calculate_image_srcset', [ $this, 'filter_attachment_image_srcset' ], 10, 5 );

		// Secure existing files when protection is switched on.
		add_action( 'update_option_hp_gallery_protect_files', [ $this, 'sync_all_protection' ], 10, 2 );

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
	 * Gating is opt-in and configured natively on HivePress Memberships
	 * plans: if no plan enables gallery access, every vendor can use the
	 * feature. Once at least one plan enables it, the vendor's user needs an
	 * active membership in one of those plans. This fails closed if the
	 * Memberships extension is deactivated (the gating flag is persisted, so
	 * `user_has_active_membership()` returns false and access is denied),
	 * meaning paid access is never given away by accident.
	 *
	 * @param \HivePress\Models\Vendor|null $vendor Vendor object.
	 * @return bool
	 */
	public function vendor_can_use_gallery( $vendor ) {
		$can = true;

		if ( get_option( 'hp_gallery_access_gated' ) ) {
			$can = $vendor && $this->user_has_active_membership( $vendor->get_user__id(), $this->get_access_plan_ids() );
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
	 * users need an active membership in one of the plans that enable
	 * members-only viewing; if no plan enables it, members-only folders stay
	 * locked for everyone but their owner.
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
			} elseif ( get_option( 'hp_gallery_view_gated' ) ) {
				$can = $this->user_has_active_membership( $user_id, $this->get_view_plan_ids() );
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
	 * Adds gallery access options to the Membership Plan model.
	 *
	 * The two checkboxes are stored as plan meta (`hp_gallery_access` and
	 * `hp_gallery_view`), so gallery gating is configured natively on each
	 * plan rather than in a separate plan picker.
	 *
	 * @param array $model Model arguments.
	 * @return array
	 */
	public function add_plan_fields( $model ) {
		$model['fields']['gallery_access'] = [
			'type'      => 'checkbox',
			'_external' => true,
		];

		$model['fields']['gallery_view'] = [
			'type'      => 'checkbox',
			'_external' => true,
		];

		return $model;
	}

	/**
	 * Adds the gallery access fields to the Membership Plan settings meta box.
	 *
	 * @param array $meta_box Meta box arguments.
	 * @return array
	 */
	public function add_plan_settings( $meta_box ) {
		$meta_box['fields']['gallery_access'] = [
			'label'   => esc_html__( 'Gallery', 'additional-gallery-for-hivepress' ),
			'caption' => esc_html__( 'Allow using the photo gallery', 'additional-gallery-for-hivepress' ),
			'type'    => 'checkbox',
			'_order'  => 210,
		];

		$meta_box['fields']['gallery_view'] = [
			'label'   => esc_html__( 'Gallery Viewing', 'additional-gallery-for-hivepress' ),
			'caption' => esc_html__( 'Allow viewing members-only gallery folders', 'additional-gallery-for-hivepress' ),
			'type'    => 'checkbox',
			'_order'  => 220,
		];

		return $meta_box;
	}

	/**
	 * Gets the IDs of membership plans that enable the gallery feature.
	 *
	 * @return array
	 */
	public function get_access_plan_ids() {
		static $plan_ids;

		if ( ! isset( $plan_ids ) ) {
			$plan_ids = $this->query_plan_ids( 'hp_gallery_access' );
		}

		return $plan_ids;
	}

	/**
	 * Gets the IDs of membership plans that unlock members-only folders.
	 *
	 * @return array
	 */
	public function get_view_plan_ids() {
		static $plan_ids;

		if ( ! isset( $plan_ids ) ) {
			$plan_ids = $this->query_plan_ids( 'hp_gallery_view' );
		}

		return $plan_ids;
	}

	/**
	 * Queries published membership plans carrying the given gallery meta flag.
	 *
	 * @param string $meta_key Plan meta key.
	 * @return array Plan IDs.
	 */
	protected function query_plan_ids( $meta_key ) {
		$post_type = hp_agl_get_plan_post_type();

		return array_map(
			'absint',
			get_posts(
				[
					'post_type'   => $post_type ? $post_type : 'hp_membership_plan',
					'post_status' => 'publish',
					'numberposts' => -1,
					'fields'      => 'ids',

					'meta_query'  => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small, admin-defined plan set read behind a persisted gating flag.
						[
							'key'     => $meta_key,
							'value'   => '1',
							'compare' => '=',
						],
					],
				]
			)
		);
	}

	/**
	 * Recomputes and stores whether any plan gates gallery access or viewing.
	 *
	 * Persisting these flags lets the access checks fail closed even when the
	 * Memberships extension (and its plan post type) is later deactivated.
	 *
	 * @return void
	 */
	public function refresh_gating_flags() {
		update_option( 'hp_gallery_access_gated', $this->query_plan_ids( 'hp_gallery_access' ) ? '1' : '' );
		update_option( 'hp_gallery_view_gated', $this->query_plan_ids( 'hp_gallery_view' ) ? '1' : '' );
	}

	/**
	 * Refreshes the gating flags when a membership plan is deleted or trashed.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function refresh_gating_flags_for_post( $post_id ) {
		if ( 'hp_membership_plan' === get_post_type( $post_id ) ) {
			$this->refresh_gating_flags();
		}
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

			// Protected files have no externally fetchable URL, so OpenAI
			// cannot reach them; skip rather than send a dud URL.
			if ( get_post_meta( $attachment_id, 'hp_agl_protected', true ) ) {
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
			$count  = $folder ? count( (array) $folder->get_images__id() ) : 0;

			echo esc_html( number_format_i18n( $count ) );

			if ( $folder && $count ) {
				echo ' <span style="opacity:0.6;">(' . esc_html( $this->format_size( $this->get_folder_size( $folder ) ) ) . ')</span>';
			}
		}
	}

	/**
	 * Rejects gallery uploads over the configured file-size limit.
	 *
	 * @param array $file Uploaded file data.
	 * @return array
	 */
	public function limit_upload_size( $file ) {
		$max_mb = hp_agl_int( get_option( 'hp_gallery_max_filesize' ) );

		if ( $max_mb && $this->is_gallery_upload() && ! empty( $file['size'] ) && $file['size'] > $max_mb * MB_IN_BYTES ) {
			/* translators: %s: size in megabytes. */
			$file['error'] = sprintf( esc_html__( 'Each file must be smaller than %s MB.', 'additional-gallery-for-hivepress' ), number_format_i18n( $max_mb ) );
		}

		return $file;
	}

	/**
	 * Optimizes a gallery image right after upload, before WordPress generates
	 * its thumbnails, so every derived size comes from the optimized original.
	 *
	 * @param array  $upload Upload data (file, url, type).
	 * @param string $context Upload context.
	 * @return array
	 */
	public function optimize_upload( $upload, $context ) {
		if ( 'upload' !== $context || ! $this->is_gallery_upload() || empty( $upload['file'] ) ) {
			return $upload;
		}

		$result = $this->optimize_image_file( $upload['file'], hp_agl_string( hp\get_array_value( $upload, 'type' ) ) );

		if ( $result ) {
			$upload['file'] = $result['file'];
			$upload['url']  = $result['url'];
			$upload['type'] = $result['type'];
		}

		return $upload;
	}

	/**
	 * Checks whether the current request is a gallery folder upload.
	 *
	 * The HivePress attachments endpoint (which authorises the upload) posts
	 * the parent model in the request body, so gallery uploads can be
	 * recognised at the WordPress upload hooks.
	 *
	 * @return bool
	 */
	protected function is_gallery_upload() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the upload is authorised by the HivePress attachments endpoint; this only detects context.
		return isset( $_POST['parent_model'] ) && 'gallery_folder' === sanitize_key( wp_unslash( $_POST['parent_model'] ) );
	}

	/**
	 * Resizes, recompresses, strips metadata from and optionally converts an
	 * image file in place, according to the optimization settings.
	 *
	 * @param string $file File path.
	 * @param string $mime MIME type.
	 * @return array|false New file data (file, url, type), or false if unchanged.
	 */
	public function optimize_image_file( $file, $mime ) {
		if ( ! in_array( $mime, [ 'image/jpeg', 'image/png', 'image/webp' ], true ) || ! file_exists( $file ) ) {
			return false;
		}

		$max_dim = hp_agl_int( get_option( 'hp_gallery_max_dimensions' ) );
		$quality = hp_agl_int( get_option( 'hp_gallery_image_quality' ) );
		$strip   = (bool) get_option( 'hp_gallery_strip_metadata' );

		$convert = (bool) get_option( 'hp_gallery_convert_webp' )
			&& in_array( $mime, [ 'image/jpeg', 'image/png' ], true )
			&& in_array( 'webp', hp_agl_get_upload_formats(), true )
			&& wp_image_editor_supports( [ 'mime_type' => 'image/webp' ] );

		if ( ! $max_dim && ! $quality && ! $strip && ! $convert ) {
			return false;
		}

		$editor = wp_get_image_editor( $file );

		if ( is_wp_error( $editor ) ) {
			return false;
		}

		if ( $quality ) {
			$editor->set_quality( min( 100, max( 10, $quality ) ) );
		}

		if ( $max_dim ) {
			$size = $editor->get_size();

			if ( is_array( $size ) && ( $size['width'] > $max_dim || $size['height'] > $max_dim ) ) {
				$editor->resize( $max_dim, $max_dim, false );
			}
		}

		// Choose the output file (re-encoding always drops most metadata).
		$target_mime = $convert ? 'image/webp' : $mime;
		$target_file = $file;

		if ( $convert ) {
			$target_file = preg_replace( '/\.\w+$/', '.webp', $file );

			if ( ! $target_file || $target_file === $file ) {
				$target_file = $file . '.webp';
			}
		}

		$saved = $editor->save( $target_file, $target_mime );

		if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			return false;
		}

		// Remove the original source file when the format changed.
		if ( $saved['path'] !== $file && file_exists( $file ) ) {
			wp_delete_file( $file );
		}

		$upload_dir = wp_get_upload_dir();

		return [
			'file' => $saved['path'],
			'url'  => str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $saved['path'] ),
			'type' => $saved['mime-type'],
		];
	}

	/**
	 * Gets the total file weight (bytes on disk) of a folder's media.
	 *
	 * @param int|\HivePress\Models\Gallery_Folder $folder Folder ID or object.
	 * @return int
	 */
	public function get_folder_size( $folder ) {
		if ( ! $folder instanceof \HivePress\Models\Gallery_Folder ) {
			$folder = Models\Gallery_Folder::query()->get_by_id( $folder );
		}

		$bytes = 0;

		if ( $folder ) {
			foreach ( (array) $folder->get_images__id() as $attachment_id ) {
				$bytes += $this->get_attachment_size( $attachment_id );
			}
		}

		return $bytes;
	}

	/**
	 * Gets the total file weight of an attachment, including its resized files.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int
	 */
	protected function get_attachment_size( $attachment_id ) {
		$file  = get_attached_file( $attachment_id );
		$bytes = ( $file && file_exists( $file ) ) ? (int) filesize( $file ) : 0;

		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( $file && is_array( $metadata ) && ! empty( $metadata['sizes'] ) ) {
			$dir = dirname( $file );

			foreach ( $metadata['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) && file_exists( $dir . '/' . $size['file'] ) ) {
					$bytes += (int) filesize( $dir . '/' . $size['file'] );
				}
			}
		}

		return $bytes;
	}

	/**
	 * Formats a byte count for display.
	 *
	 * @param int $bytes Byte count.
	 * @return string
	 */
	public function format_size( $bytes ) {
		return $bytes ? hp_agl_string( size_format( $bytes, $bytes >= MB_IN_BYTES ? 1 : 0 ) ) : '0 B';
	}

	/**
	 * Adds the optimize/restore bulk actions to the folders list table.
	 *
	 * @param array $actions Bulk actions.
	 * @return array
	 */
	public function add_bulk_actions( $actions ) {
		$actions['hp_agl_optimize'] = esc_html__( 'Optimize images', 'additional-gallery-for-hivepress' );
		$actions['hp_agl_restore']  = esc_html__( 'Restore original images', 'additional-gallery-for-hivepress' );

		return $actions;
	}

	/**
	 * Runs the optimize/restore bulk actions on the selected folders.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action Bulk action.
	 * @param array  $post_ids Selected post IDs.
	 * @return string
	 */
	public function handle_bulk_actions( $redirect, $action, $post_ids ) {
		if ( ! in_array( $action, [ 'hp_agl_optimize', 'hp_agl_restore' ], true ) ) {
			return $redirect;
		}

		$count = 0;

		foreach ( (array) $post_ids as $post_id ) {
			if ( 'hp_gallery_folder' !== get_post_type( $post_id ) ) {
				continue;
			}

			$folder = Models\Gallery_Folder::query()->get_by_id( $post_id );

			if ( ! $folder ) {
				continue;
			}

			foreach ( (array) $folder->get_images__id() as $attachment_id ) {
				if ( 'hp_agl_optimize' === $action ) {
					if ( $this->optimize_attachment( $attachment_id ) ) {
						++$count;
					}
				} elseif ( $this->restore_attachment( $attachment_id ) ) {
					++$count;
				}
			}
		}

		return add_query_arg(
			[
				'hp_agl_bulk'  => $action,
				'hp_agl_count' => $count,
			],
			$redirect
		);
	}

	/**
	 * Shows a success notice after a bulk optimize/restore action.
	 *
	 * @return void
	 */
	public function render_bulk_action_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only notice after WordPress' own bulk-action redirect.
		if ( empty( $_GET['hp_agl_bulk'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_GET['hp_agl_bulk'] ) );
		$count  = isset( $_GET['hp_agl_count'] ) ? absint( $_GET['hp_agl_count'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 'hp_agl_restore' === $action ) {
			/* translators: %s: number of images. */
			$message = sprintf( _n( '%s image restored.', '%s images restored.', $count, 'additional-gallery-for-hivepress' ), number_format_i18n( $count ) );
		} elseif ( 'hp_agl_optimize' === $action ) {
			/* translators: %s: number of images. */
			$message = sprintf( _n( '%s image optimized.', '%s images optimized.', $count, 'additional-gallery-for-hivepress' ), number_format_i18n( $count ) );
		} else {
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Optimizes an existing attachment, backing up its original first when
	 * requested, then regenerating its thumbnails.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function optimize_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$file          = get_attached_file( $attachment_id );
		$mime          = hp_agl_string( get_post_mime_type( $attachment_id ) );

		if ( ! $file || ! file_exists( $file ) || ! in_array( $mime, [ 'image/jpeg', 'image/png', 'image/webp' ], true ) ) {
			return false;
		}

		if ( get_option( 'hp_gallery_keep_originals' ) ) {
			$this->backup_original( $attachment_id, $file, $mime );
		}

		$result = $this->optimize_image_file( $file, $mime );

		if ( ! $result ) {
			return false;
		}

		// Point the attachment at the optimized file when the format changed.
		if ( $result['file'] !== $file ) {
			update_attached_file( $attachment_id, $result['file'] );

			wp_update_post(
				[
					'ID'             => $attachment_id,
					'post_mime_type' => $result['type'],
				]
			);
		}

		$this->regenerate_attachment( $attachment_id, $result['file'] );

		return true;
	}

	/**
	 * Restores an attachment's backed-up original and regenerates it.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function restore_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$backup        = get_post_meta( $attachment_id, 'hp_agl_original', true );

		if ( ! is_array( $backup ) || empty( $backup['file'] ) || empty( $backup['name'] ) ) {
			return false;
		}

		$upload_dir  = wp_get_upload_dir();
		$backup_path = $upload_dir['basedir'] . '/' . $backup['file'];

		if ( ! file_exists( $backup_path ) ) {
			return false;
		}

		// Restore next to the current file, keeping the (possibly protected) location.
		$current  = get_attached_file( $attachment_id );
		$rel      = hp_agl_string( get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$dest_rel = trailingslashit( dirname( $rel ) ) . $backup['name'];
		$dest_rel = ltrim( str_replace( './', '', $dest_rel ), '/' );
		$dest     = $upload_dir['basedir'] . '/' . $dest_rel;

		if ( ! @copy( $backup_path, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return false;
		}

		// Remove the optimized file and its resized variants.
		if ( $current && $current !== $dest ) {
			$this->delete_attachment_files( $attachment_id );
		}

		update_attached_file( $attachment_id, $dest );

		$mime = hp_agl_string( hp\get_array_value( $backup, 'mime' ) );

		wp_update_post(
			[
				'ID'             => $attachment_id,
				'post_mime_type' => $mime ? $mime : hp_agl_string( get_post_mime_type( $attachment_id ) ),
			]
		);

		$this->regenerate_attachment( $attachment_id, $dest );

		// Clear the backup.
		wp_delete_file( $backup_path );
		delete_post_meta( $attachment_id, 'hp_agl_original' );

		return true;
	}

	/**
	 * Backs up an attachment's original file once, before optimization.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file File path.
	 * @param string $mime MIME type.
	 * @return void
	 */
	protected function backup_original( $attachment_id, $file, $mime ) {
		if ( get_post_meta( $attachment_id, 'hp_agl_original', true ) ) {
			return;
		}

		$upload_dir = wp_get_upload_dir();
		$dir        = $upload_dir['basedir'] . '/hp-agl-originals';

		if ( ! wp_mkdir_p( $dir ) ) {
			return;
		}

		$this->protect_directory( $dir );

		$name = basename( $file );
		$dest = $dir . '/' . $attachment_id . '-' . $name;

		if ( ! @copy( $file, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return;
		}

		update_post_meta(
			$attachment_id,
			'hp_agl_original',
			[
				'file' => 'hp-agl-originals/' . $attachment_id . '-' . $name,
				'name' => $name,
				'mime' => $mime,
			]
		);
	}

	/**
	 * Regenerates an attachment's thumbnails and metadata, clearing old files.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file File path.
	 * @return void
	 */
	protected function regenerate_attachment( $attachment_id, $file ) {
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$this->delete_attachment_files( $attachment_id, true );

		$metadata = wp_generate_attachment_metadata( $attachment_id, $file );

		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		// Refresh the blurred teaser so locked previews reflect the new file.
		$this->delete_teaser_image( $attachment_id );
	}

	/**
	 * Deletes an attachment's resized files (and optionally leaves the main
	 * file in place, when only clearing stale intermediates).
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $keep_main Whether to keep the main file.
	 * @return void
	 */
	protected function delete_attachment_files( $attachment_id, $keep_main = false ) {
		$file     = get_attached_file( $attachment_id );
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( ! $file ) {
			return;
		}

		$dir = dirname( $file );

		if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) && basename( $file ) !== $size['file'] && file_exists( $dir . '/' . $size['file'] ) ) {
					wp_delete_file( $dir . '/' . $size['file'] );
				}
			}
		}

		if ( ! $keep_main && file_exists( $file ) ) {
			wp_delete_file( $file );
		}
	}

	/**
	 * Writes deny-direct-access guards into a directory.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	protected function protect_directory( $dir ) {
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			$rules = "# Additional Gallery for HivePress - deny direct access.\n"
				. "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n"
				. "Options -Indexes\n";

			file_put_contents( $dir . '/.htaccess', $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- one-off deny-rule guard beside direct file handling.
		}

		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', '<?php // Silence is golden.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- one-off index guard.
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
	 * Checks whether file protection is enabled.
	 *
	 * When on, files in private and members-only folders are relocated to a
	 * protected directory (denied direct web access) and served through an
	 * access-checked proxy, so their URLs cannot be opened directly.
	 *
	 * @return bool
	 */
	public function is_file_protection_enabled() {
		return (bool) get_option( 'hp_gallery_protect_files' );
	}

	/**
	 * Gets the protected uploads directory, creating it (with a deny rule) on
	 * first use.
	 *
	 * @return string|null Absolute path, or null if it cannot be created.
	 */
	public function get_protected_dir() {
		$upload_dir = wp_get_upload_dir();
		$dir        = $upload_dir['basedir'] . '/hp-agl-protected';

		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}

		// Deny direct web access (Apache). Nginx needs a server rule; the
		// proxy re-checks access regardless, so this is defence in depth.
		$this->protect_directory( $dir );

		return $dir;
	}

	/**
	 * Rewrites a protected attachment URL to the access-checked proxy.
	 *
	 * @param string $url Attachment URL.
	 * @param int    $attachment_id Attachment ID.
	 * @return string
	 */
	public function filter_attachment_url( $url, $attachment_id ) {
		if ( $this->is_file_protection_enabled() && get_post_meta( $attachment_id, 'hp_agl_protected', true ) ) {
			return $this->get_protected_file_url( $attachment_id, 'full' );
		}

		return $url;
	}

	/**
	 * Rewrites a protected attachment image source to the proxy.
	 *
	 * @param array|false  $image Image data (url, width, height, is_intermediate).
	 * @param int          $attachment_id Attachment ID.
	 * @param string|array $size Requested size.
	 * @return array|false
	 */
	public function filter_attachment_image_src( $image, $attachment_id, $size ) {
		if ( is_array( $image ) && $this->is_file_protection_enabled() && get_post_meta( $attachment_id, 'hp_agl_protected', true ) ) {
			$size_key = is_string( $size ) ? $size : 'full';

			$image[0] = $this->get_protected_file_url( $attachment_id, $size_key );
		}

		return $image;
	}

	/**
	 * Drops the srcset for protected attachments, so no direct file URLs are
	 * generated by the responsive-image markup.
	 *
	 * @param array $sources Srcset sources.
	 * @param array $size_array Size array.
	 * @param string $image_src Image source.
	 * @param array $image_meta Image metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	public function filter_attachment_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		if ( $this->is_file_protection_enabled() && get_post_meta( $attachment_id, 'hp_agl_protected', true ) ) {
			return [];
		}

		return $sources;
	}

	/**
	 * Builds the proxy URL for a protected file.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size Image size name.
	 * @return string
	 */
	public function get_protected_file_url( $attachment_id, $size = 'full' ) {
		$url = hivepress()->router->get_url( 'gallery_file_view_page', [ 'attachment_id' => $attachment_id ] );

		if ( $size && 'full' !== $size ) {
			$url = add_query_arg( 'size', $size, $url );
		}

		return $url;
	}

	/**
	 * Synchronises the protection state of a folder's files with its
	 * visibility (private/members are protected; public are public).
	 *
	 * @param int|\HivePress\Models\Gallery_Folder $folder Folder ID or object.
	 * @return void
	 */
	public function sync_folder_protection( $folder ) {
		if ( ! $folder instanceof \HivePress\Models\Gallery_Folder ) {
			$folder = Models\Gallery_Folder::query()->get_by_id( $folder );
		}

		if ( ! $folder ) {
			return;
		}

		$protect = $this->is_file_protection_enabled() && in_array( $folder->get_visibility(), [ 'members', 'private' ], true );

		foreach ( (array) $folder->get_images__id() as $attachment_id ) {
			if ( $protect ) {
				$this->protect_attachment( $attachment_id );
			} else {
				$this->unprotect_attachment( $attachment_id );
			}
		}
	}

	/**
	 * Relocates all folders' files when the protection setting is toggled.
	 *
	 * @param mixed $old_value Old option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public function sync_all_protection( $old_value, $new_value ) {
		if ( (bool) $old_value === (bool) $new_value ) {
			return;
		}

		$folder_ids = get_posts(
			[
				'post_type'   => 'hp_gallery_folder',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
			]
		);

		foreach ( $folder_ids as $folder_id ) {
			$this->sync_folder_protection( $folder_id );
		}
	}

	/**
	 * Moves an attachment's files into the protected directory.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function protect_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id || get_post_meta( $attachment_id, 'hp_agl_protected', true ) ) {
			return;
		}

		if ( ! $this->get_protected_dir() ) {
			return;
		}

		if ( $this->move_attachment_files( $attachment_id, true ) ) {
			update_post_meta( $attachment_id, 'hp_agl_protected', '1' );
		}
	}

	/**
	 * Moves an attachment's files back out of the protected directory.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function unprotect_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id || ! get_post_meta( $attachment_id, 'hp_agl_protected', true ) ) {
			return;
		}

		if ( $this->move_attachment_files( $attachment_id, false ) ) {
			delete_post_meta( $attachment_id, 'hp_agl_protected' );
		}
	}

	/**
	 * Moves an attachment's original, resized and scaled files into or out of
	 * the protected directory, keeping the attachment metadata in sync.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $to_protected Whether to move into the protected directory.
	 * @return bool
	 */
	protected function move_attachment_files( $attachment_id, $to_protected ) {
		$prefix = 'hp-agl-protected/';

		$upload_dir = wp_get_upload_dir();
		$basedir    = $upload_dir['basedir'];

		// Get the current relative path.
		$relative = hp_agl_string( get_post_meta( $attachment_id, '_wp_attached_file', true ) );

		if ( ! $relative ) {
			return false;
		}

		// Compute the base (unprefixed) and destination relative paths.
		$base_relative = preg_replace( '#^' . preg_quote( $prefix, '#' ) . '#', '', $relative );
		$dest_relative = $to_protected ? $prefix . $base_relative : $base_relative;

		if ( $dest_relative === $relative ) {
			return true;
		}

		$from_dir = dirname( $basedir . '/' . $relative );
		$to_dir   = dirname( $basedir . '/' . $dest_relative );

		if ( ! wp_mkdir_p( $to_dir ) ) {
			return false;
		}

		// Collect every file that belongs to this attachment.
		$files    = [ basename( $relative ) ];
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( is_array( $metadata ) ) {
			if ( ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size ) {
					if ( ! empty( $size['file'] ) ) {
						$files[] = $size['file'];
					}
				}
			}

			if ( ! empty( $metadata['original_image'] ) ) {
				$files[] = $metadata['original_image'];
			}
		}

		// Move each file, tolerating already-moved or missing variants.
		foreach ( array_unique( $files ) as $file ) {
			$from = $from_dir . '/' . $file;
			$to   = $to_dir . '/' . $file;

			if ( $from === $to || ! file_exists( $from ) ) {
				continue;
			}

			if ( ! @rename( $from, $to ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- direct move of a local upload; falls back to copy+unlink across volumes.
				if ( @copy( $from, $to ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					wp_delete_file( $from );
				} else {
					return false;
				}
			}
		}

		// Point the attachment at the new location.
		update_post_meta( $attachment_id, '_wp_attached_file', $dest_relative );

		return true;
	}

	/**
	 * Checks whether the current user may access a protected attachment.
	 *
	 * Mirrors the gallery-page and attachment-page access rules.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function can_access_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}

		// Get the parent folder.
		$parent_id = wp_get_post_parent_id( $attachment_id );

		if ( ! $parent_id || 'hp_gallery_folder' !== get_post_type( $parent_id ) ) {
			return false;
		}

		$folder = Models\Gallery_Folder::query()->get_by_id( $parent_id );

		if ( ! $folder ) {
			return false;
		}

		// Owners and editors always have access.
		if ( get_current_user_id() === $folder->get_user__id() || current_user_can( 'edit_others_posts' ) ) {
			return true;
		}

		$visibility = $folder->get_visibility();

		// Listed folders require an unlocked gallery, matching the gallery pages.
		if ( in_array( $visibility, [ 'public', 'members' ], true ) ) {
			$vendor = Models\Vendor::query()->get_by_id( $folder->get_vendor__id() );

			if ( ! $vendor || 'publish' !== $folder->get_status() || ! $this->vendor_can_use_gallery( $vendor ) ) {
				return false;
			}

			if ( 'public' === $visibility ) {
				return true;
			}

			return $this->user_can_view_member_folders( $vendor );
		}

		return false;
	}

	/**
	 * Streams a protected file to an authorised visitor, or exits with the
	 * appropriate error status.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size Image size name.
	 * @return void
	 */
	public function serve_protected_file( $attachment_id, $size = 'full' ) {
		$attachment_id = absint( $attachment_id );

		// Check access.
		if ( ! $this->can_access_attachment( $attachment_id ) ) {
			nocache_headers();
			status_header( is_user_logged_in() ? 403 : 401 );

			exit;
		}

		// Resolve the requested file, defaulting to the original.
		$path = get_attached_file( $attachment_id );
		$size = sanitize_key( (string) $size );

		if ( $size && 'full' !== $size ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );

			if ( ! empty( $metadata['sizes'][ $size ]['file'] ) ) {
				$path = path_join( dirname( $path ), $metadata['sizes'][ $size ]['file'] );
			}
		}

		// Guard against path traversal: the file must live under uploads.
		$upload_dir = wp_get_upload_dir();
		$real_path  = $path ? realpath( $path ) : false;
		$real_base  = realpath( $upload_dir['basedir'] );

		if ( ! $real_path || ! $real_base || 0 !== strpos( $real_path, $real_base . DIRECTORY_SEPARATOR ) || ! is_file( $real_path ) ) {
			nocache_headers();
			status_header( 404 );

			exit;
		}

		$this->stream_file( $real_path, hp_agl_string( get_post_mime_type( $attachment_id ) ) );
	}

	/**
	 * Streams a local file with caching, conditional-GET and byte-range
	 * support (the latter needed for video seeking).
	 *
	 * @param string $path File path.
	 * @param string $mime MIME type.
	 * @return void
	 */
	protected function stream_file( $path, $mime ) {
		$size          = (int) filesize( $path );
		$last_modified = (int) filemtime( $path );
		$etag          = '"' . md5( $last_modified . '-' . $size ) . '"';

		if ( ! $mime ) {
			$mime = 'application/octet-stream';
		}

		// Discard any buffered output so the binary stream is clean.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// Conditional GET.
		$if_none_match     = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared as an opaque validator token.
		$if_modified_since = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? strtotime( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) : false; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ( $if_none_match && $if_none_match === $etag ) || ( $if_modified_since && $if_modified_since >= $last_modified ) ) {
			status_header( 304 );

			exit;
		}

		// Base headers.
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: inline; filename="' . basename( $path ) . '"' );
		header( 'Cache-Control: private, max-age=' . ( 6 * HOUR_IN_SECONDS ) );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_modified ) . ' GMT' );
		header( 'ETag: ' . $etag );
		header( 'Accept-Ranges: bytes' );
		header_remove( 'Expires' );
		header_remove( 'Pragma' );

		// Range handling for seeking.
		$start = 0;
		$end   = $size - 1;
		$range = isset( $_SERVER['HTTP_RANGE'] ) ? wp_unslash( $_SERVER['HTTP_RANGE'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed numerically below.

		if ( $range && preg_match( '/bytes=(\d*)-(\d*)/', $range, $matches ) ) {
			if ( '' !== $matches[1] ) {
				$start = (int) $matches[1];
			}

			if ( '' !== $matches[2] ) {
				$end = (int) $matches[2];
			}

			if ( $start > $end || $start >= $size ) {
				status_header( 416 );
				header( 'Content-Range: bytes */' . $size );

				exit;
			}

			$end = min( $end, $size - 1 );

			status_header( 206 );
			header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $size );
		}

		$length = $end - $start + 1;

		header( 'Content-Length: ' . $length );

		// Stream the requested byte range.
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming a local upload to the browser.

		if ( false === $handle ) {
			status_header( 500 );

			exit;
		}

		if ( $start > 0 ) {
			fseek( $handle, $start );
		}

		$remaining = $length;

		while ( $remaining > 0 && ! feof( $handle ) ) {
			$buffer = fread( $handle, (int) min( 8192, $remaining ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread

			if ( false === $buffer ) {
				break;
			}

			echo $buffer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw binary file stream.
			flush();

			$remaining -= strlen( $buffer );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		exit;
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
