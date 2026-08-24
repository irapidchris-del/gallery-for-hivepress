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
 * Accessible via `hivepress()->agl_gallery`. The class and file name carry an
 * `Agl_` prefix because HivePress autoloads exactly one file per class name
 * across every registered extension: were core to ship its own
 * `components/class-gallery.php`, one of the two would silently never load.
 */
final class Agl_Gallery extends Component {

	/**
	 * Path prefix marking an attachment as protected.
	 *
	 * Historic and deliberately unchanged: it is what every existing install already has in
	 * `_wp_attached_file`, so where the files physically live can change without a single row of
	 * attachment metadata being rewritten.
	 *
	 * @var string
	 */
	const PROTECTED_PREFIX = 'hp-agl-protected/';

	/**
	 * Where a folder's chosen cover photo is kept.
	 *
	 * @var string
	 */
	const COVER_META = 'hp_agl_cover';

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

		// Register the photo page widget area. Registered from the plugin, not
		// a theme config, so it exists on any theme and appears under
		// Appearance > Widgets.
		add_action( 'widgets_init', [ $this, 'register_widget_area' ] );

		// Add gallery links to vendor and listing pages.
		add_filter( 'hivepress/v1/templates/vendor_view_page', [ $this, 'alter_vendor_view_page' ] );
		add_filter( 'hivepress/v1/templates/listing_view_page', [ $this, 'alter_listing_view_page' ] );

		// Register the shared OpenAI settings, and keep the key masked on screen
		// with a reveal toggle (see print_secret_toggles()).
		add_filter( 'hivepress/v1/settings', [ $this, 'add_shared_settings' ] );
		add_action( 'admin_head', [ $this, 'print_secret_styles' ] );
		add_action( 'admin_footer', [ $this, 'print_secret_toggles' ] );

		// Integrate gallery access with HivePress Memberships plans. The feature
		// toggle sits in the plan's Settings box beside the product link, while
		// the viewing permission and the per-plan limits sit in its general
		// Restrictions box, which is where Memberships keeps everything a
		// membership lets someone see or exceed.
		add_filter( 'hivepress/v1/models/membership_plan', [ $this, 'add_plan_fields' ] );
		add_filter( 'hivepress/v1/meta_boxes/membership_plan_settings', [ $this, 'add_plan_settings' ] );
		add_filter( 'hivepress/v1/meta_boxes/membership_plan_page_restrictions', [ $this, 'add_plan_restrictions' ] );

		// Keep the fail-closed gating flags in sync when a plan is saved or
		// removed. The HivePress model action fires after the plan meta is
		// persisted; the late save_post is a fallback (both are idempotent).
		add_action( 'hivepress/v1/models/membership_plan/update', [ $this, 'refresh_gating_flags' ] );
		add_action( 'save_post', [ $this, 'refresh_gating_flags_for_post' ], 999, 2 );
		add_action( 'deleted_post', [ $this, 'refresh_gating_flags_for_post' ], 10, 2 );
		add_action( 'trashed_post', [ $this, 'refresh_gating_flags_for_post' ] );
		add_action( 'untrashed_post', [ $this, 'refresh_gating_flags_for_post' ] );

		// Populate the admin images meta box.
		add_filter( 'hivepress/v1/meta_boxes/gallery_folder_images', [ $this, 'alter_folder_images_meta_box' ] );

		// Keep gallery uploads to a sane size: reject oversized files, and scale
		// down anything larger than the configured dimensions. Compression,
		// format conversion and bulk work on existing images are deliberately
		// left to dedicated image plugins, which do them far better.
		add_filter( 'wp_handle_upload_prefilter', [ $this, 'limit_upload_size' ] );
		add_filter( 'wp_handle_upload', [ $this, 'resize_upload' ], 10, 2 );

		// Add admin list table columns.
		add_filter( 'manage_hp_gallery_folder_posts_columns', [ $this, 'add_admin_columns' ] );
		add_action( 'manage_hp_gallery_folder_posts_custom_column', [ $this, 'render_admin_column' ], 10, 2 );

		// Background photo review, queued by the folder update controller so
		// that a slow moderation service can never hold a vendor's request.
		add_action( 'hp_agl_review_folder_images', [ $this, 'review_folder_images' ] );

		// Delete cached previews when attachments are edited or deleted, and
		// clear a deleted photo's likes and comments with it.
		add_action( 'edit_attachment', [ $this, 'delete_teaser_image' ] );
		add_action( 'delete_attachment', [ $this, 'delete_teaser_image' ] );
		add_action( 'delete_attachment', [ $this, 'delete_photo_engagement' ] );

		/*
		 * The gallery shown in place, rather than as a link away. Both are off by default and are
		 * independent of the sidebar buttons.
		 */
		add_filter( 'hivepress/v1/templates/vendor_view_page', [ $this, 'add_vendor_gallery_section' ] );
		add_filter( 'hivepress/v1/templates/listing_view_page', [ $this, 'add_listing_gallery_section' ] );

		/*
		 * Keep a basket to one seller, so Marketplace pays the right person. Three layers, because
		 * WooCommerce assembles carts three ways and only the first is covered by add-time
		 * validation. See validate_single_seller_cart() for the rule itself.
		 *
		 * Add time catches the ordinary case. It does NOT catch a cart WooCommerce built by
		 * merging: logging in merges the saved cart into the session cart with no validation
		 * (class-wc-cart-session.php:118-121, array_merge with the saved lines first), and the
		 * order-again rebuild validates each line against a cart that reads as empty (see the
		 * checkout method's docblock). So the whole basket is re-checked where every route to
		 * payment converges: `woocommerce_check_cart_items` for the classic cart, checkout and
		 * WC_Checkout::process_checkout(), and `woocommerce_store_api_cart_errors` for the blocks
		 * checkout (CartController::validate_cart(), src/StoreApi/Utilities/CartController.php:496).
		 */
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_single_seller_cart' ], 10, 2 );
		add_action( 'woocommerce_check_cart_items', [ $this, 'enforce_single_seller_cart' ] );
		add_action( 'woocommerce_store_api_cart_errors', [ $this, 'enforce_single_seller_cart_blocks' ], 10, 2 );

		/*
		 * Never sell gallery access to a visitor who is not signed in. Access attaches to the
		 * order's ACCOUNT - grant_paid_access() reads the order's customer ID - so a guest order
		 * has nobody to grant to: the money is taken and nothing is ever delivered. The unlock
		 * buttons already send signed-out visitors to sign in, but that only changes which URL is
		 * PRINTED; the buy URL itself (checkout plus add-to-cart) is public and shareable, so with
		 * WooCommerce guest checkout on, a shared link paid without an account. The refusal has to
		 * live in the cart, through the same three layers as the single-seller rule above and for
		 * the same reason: merged and rebuilt carts never pass add-time validation.
		 */
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_signed_in_access_purchase' ], 10, 2 );
		add_action( 'woocommerce_check_cart_items', [ $this, 'enforce_signed_in_access_purchase' ] );
		add_action( 'woocommerce_store_api_cart_errors', [ $this, 'enforce_signed_in_access_purchase_blocks' ], 10, 2 );

		// Land the buyer on the checkout they pressed a Buy button to reach. See the method.
		add_filter( 'woocommerce_add_to_cart_redirect', [ $this, 'redirect_access_purchase_to_checkout' ], 10, 2 );

		/*
		 * The site's cut of a gallery access sale. `woocommerce_cart_calculate_fees` is the one hook
		 * the Store API honours as well as the classic cart, so the block checkout and the old one
		 * show the same total; adding the fee anywhere else makes the two disagree.
		 */
		add_action( 'woocommerce_cart_calculate_fees', [ $this, 'add_commission_fee' ] );
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'set_commission_item_meta' ], 10, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'set_access_item_meta' ], 10, 3 );

		// Paid gallery access: grant on payment, revoke when the money comes
		// back. Orders settle on `processing` for normal items and go straight
		// to `completed` for downloadable-only ones (and Marketplace forces the
		// same split), so both grant hooks are needed; the handler is
		// idempotent so an order passing through both statuses grants once.
		add_action( 'woocommerce_order_status_processing', [ $this, 'grant_paid_access' ] );
		add_action( 'woocommerce_order_status_completed', [ $this, 'grant_paid_access' ] );
		add_action( 'woocommerce_order_status_refunded', [ $this, 'revoke_paid_access' ] );
		add_action( 'woocommerce_order_status_cancelled', [ $this, 'revoke_paid_access' ] );

		/*
		 * Keep cached pages honest about likes and comments.
		 *
		 * A page cache serves signed-out visitors the HTML it stored, counts and all, so a new like
		 * or comment is invisible to everyone but the person who left it until the cache expires.
		 * Measured on a real host: still reading "0 likes, 0 comments" more than eighty minutes
		 * later, and surviving two manual purges.
		 *
		 * These are the raw comment hooks rather than the model events on purpose. Cache
		 * invalidation must never be missed, and the model events are skipped during an import and
		 * only fire while the owning extension is active; the raw hooks fire on every path,
		 * including a delete from the wp-admin comments screen.
		 */
		add_action( 'wp_insert_comment', [ $this, 'queue_engagement_purge' ], 10, 2 );
		add_action( 'deleted_comment', [ $this, 'queue_engagement_purge' ], 10, 2 );
		add_action( 'transition_comment_status', [ $this, 'queue_engagement_purge_on_status' ], 10, 3 );

		add_action( 'hp_agl_purge_photo_cache', [ $this, 'purge_photo_cache' ] );

		// Warn buyers before timed access lapses. `hp_agl/access_expired` only fires on the first
		// read after the expiry, so somebody who stops visiting is never told at all, and by the
		// time anyone is told it is already too late to renew without a gap.
		add_action( 'hivepress/v1/events/daily', [ $this, 'warn_expiring_access' ] );

		// Shield gallery images from media APIs and attachment pages.
		add_filter( 'rest_attachment_query', [ $this, 'alter_rest_attachment_query' ], 10, 2 );
		add_action( 'template_redirect', [ $this, 'redirect_attachment_page' ] );

		// Relocate private and members-only files behind the protected proxy.
		add_action( 'hivepress/v1/models/gallery_folder/update_images', [ $this, 'sync_folder_protection' ] );
		add_action( 'hivepress/v1/models/gallery_folder/update', [ $this, 'sync_folder_protection' ], 20 );

		// Apply the owner's chosen button rounding, if they have set one.
		add_action( 'wp_enqueue_scripts', [ $this, 'add_button_radius_style' ], 30 );

		// Say so when file protection cannot actually be delivered on this hosting.
		add_action( 'admin_notices', [ $this, 'render_protection_notice' ] );

		// Point WordPress at protected files, which no longer live under the uploads folder.
		add_filter( 'get_attached_file', [ $this, 'filter_attached_file' ], 10, 2 );

		// Delete them ourselves, because WordPress refuses to delete outside the uploads folder.
		add_action( 'delete_attachment', [ $this, 'delete_protected_files' ], 5 );

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
			} elseif ( $vendor && $this->has_paid_access( $user_id, $vendor->get_id() ) ) {

				// A purchased unlock covers this one vendor's locked folders.
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

		$model['fields']['gallery_folder_limit'] = [
			'type'      => 'number',
			'min_value' => 0,
			'_external' => true,
		];

		$model['fields']['gallery_image_limit'] = [
			'type'      => 'number',
			'min_value' => 1,
			'_external' => true,
		];

		$model['fields']['gallery_storage_limit'] = [
			'type'      => 'number',
			'min_value' => 1,
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
			'label'       => esc_html__( 'Gallery', 'additional-gallery-for-hivepress' ),
			'caption'     => esc_html__( 'Allow using the photo gallery', 'additional-gallery-for-hivepress' ),
			'description' => esc_html__( 'Vendors on this plan get the Gallery page in their account. Leave this unticked on every plan to keep the gallery open to all vendors.', 'additional-gallery-for-hivepress' ),
			'type'        => 'checkbox',
			'_order'      => 210,
		];

		return $meta_box;
	}

	/**
	 * Adds the gallery restrictions to the membership plan's general
	 * Restrictions meta box.
	 *
	 * Viewing members-only folders and the per-plan gallery limits belong here
	 * rather than in the Settings box: this is the box Memberships uses for
	 * everything a membership lets someone see or exceed. They are deliberately
	 * not added to the per-model Restrictions boxes (Listings, Vendors), whose
	 * fields are scoped to listing categories and per-entry limits; a gallery
	 * belongs to a vendor as a whole, so a category-scoped gallery limit would
	 * promise behaviour this plugin does not implement.
	 *
	 * @param array $meta_box Meta box arguments.
	 * @return array
	 */
	public function add_plan_restrictions( $meta_box ) {
		$meta_box['fields']['gallery_view'] = [
			'label'       => esc_html__( 'Gallery', 'additional-gallery-for-hivepress' ),
			'caption'     => esc_html__( 'Allow viewing members-only gallery folders', 'additional-gallery-for-hivepress' ),
			'description' => esc_html__( 'Members on this plan can open folders that vendors marked as members-only. Leave this unticked on every plan to keep those folders locked for everyone but their owner.', 'additional-gallery-for-hivepress' ),
			'type'        => 'checkbox',
			'_order'      => 210,
		];

		$meta_box['fields']['gallery_folder_limit'] = [
			'label'       => esc_html__( 'Gallery Folder Limit', 'additional-gallery-for-hivepress' ),
			'description' => esc_html__( 'Set how many gallery folders a vendor on this plan may create. Leave empty to use the site-wide limit from the Gallery settings. A vendor on more than one plan gets the highest limit.', 'additional-gallery-for-hivepress' ),
			'type'        => 'number',
			'min_value'   => 1,
			'_external'   => true,
			'_order'      => 220,
		];

		$meta_box['fields']['gallery_image_limit'] = [
			'label'       => esc_html__( 'Gallery Photo Limit (per folder)', 'additional-gallery-for-hivepress' ),
			'description' => esc_html__( 'Set how many photos a vendor on this plan may put in each folder. Leave empty to use the site-wide limit from the Gallery settings. A vendor on more than one plan gets the highest limit.', 'additional-gallery-for-hivepress' ),
			'type'        => 'number',
			'min_value'   => 1,
			'_external'   => true,
			'_order'      => 230,
		];

		$meta_box['fields']['gallery_storage_limit'] = [
			'label'       => esc_html__( 'Gallery Storage Limit (MB)', 'additional-gallery-for-hivepress' ),
			'description' => esc_html__( 'Cap the total disk space a vendor on this plan may use, counting every photo and its thumbnails. Leave empty to use the site-wide limit from the Gallery settings. A vendor on more than one plan gets the highest limit.', 'additional-gallery-for-hivepress' ),
			'type'        => 'number',
			'min_value'   => 1,
			'_external'   => true,
			'_order'      => 240,
		];

		return $meta_box;
	}

	/**
	 * Gets a per-plan numeric limit for a user, taking the most generous value
	 * across every plan they hold an active membership in.
	 *
	 * Returns null when Memberships is inactive, the user holds no membership,
	 * or no plan they hold sets the limit, so the caller falls back to the
	 * site-wide setting.
	 *
	 * @param int    $user_id User ID.
	 * @param string $meta_key Plan meta key.
	 * @return int|null
	 */
	public function get_plan_limit( $user_id, $meta_key ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! $this->is_memberships_active() ) {
			return null;
		}

		$plan_ids = $this->get_user_plan_ids( $user_id );

		if ( ! $plan_ids ) {
			return null;
		}

		$limit = null;

		foreach ( $plan_ids as $plan_id ) {
			$value = get_post_meta( $plan_id, $meta_key, true );

			// Anything below 1 means "no opinion", and falls through to the
			// site-wide limit. A stored 0 must never be treated as a real limit:
			// every consumer tests the result for truthiness, so a plan reading
			// "0 folders" would silently grant UNLIMITED folders instead. The
			// fields are bounded at 1 so a 0 can only arrive from an older
			// version of this plugin, when 0 was accepted. Denying the gallery
			// outright is what the plan's "Allow using the photo gallery"
			// checkbox is for.
			if ( ! is_numeric( $value ) || (int) $value < 1 ) {
				continue;
			}

			$value = (int) $value;

			if ( is_null( $limit ) || $value > $limit ) {
				$limit = $value;
			}
		}

		return $limit;
	}

	/**
	 * Gets the plan IDs a user holds an active membership in.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_user_plan_ids( $user_id ) {
		static $cache = [];

		$user_id = absint( $user_id );

		if ( isset( $cache[ $user_id ] ) ) {
			return $cache[ $user_id ];
		}

		$cache[ $user_id ] = [];

		if ( $user_id && $this->is_memberships_active() ) {
			$membership_ids = get_posts(
				[
					'post_type'   => 'hp_membership',
					'post_status' => 'publish',
					'author'      => $user_id,
					'numberposts' => -1,
					'fields'      => 'ids',
				]
			);

			foreach ( $membership_ids as $membership_id ) {
				$plan_id = wp_get_post_parent_id( $membership_id );

				if ( $plan_id ) {
					$cache[ $user_id ][] = $plan_id;
				}
			}

			$cache[ $user_id ] = array_values( array_unique( $cache[ $user_id ] ) );
		}

		return $cache[ $user_id ];
	}

	/**
	 * Gets the number of folders a vendor may create.
	 *
	 * @param \HivePress\Models\Vendor|null $vendor Vendor object.
	 * @return int Maximum folders, or 0 for no limit.
	 */
	public function get_folder_limit( $vendor ) {
		$limit = null;

		if ( $vendor ) {
			$limit = $this->get_plan_limit( $vendor->get_user__id(), 'hp_gallery_folder_limit' );
		}

		if ( is_null( $limit ) ) {
			$limit = hp_agl_int( get_option( 'hp_gallery_max_folders' ) );
		}

		/**
		 * Filters the number of gallery folders a vendor may create.
		 *
		 * @param int   $limit Maximum folders, 0 for no limit.
		 * @param mixed $vendor Vendor object.
		 */
		return absint( apply_filters( 'hp_agl/folder_limit', $limit, $vendor ) );
	}

	/**
	 * Gets the number of photos allowed in a folder, for a given owner.
	 *
	 * @param int $user_id Folder owner ID.
	 * @return int
	 */
	public function get_image_limit( $user_id = 0 ) {
		$limit = $this->get_plan_limit( $user_id, 'hp_gallery_image_limit' );

		if ( is_null( $limit ) ) {
			$limit = hp_agl_int( get_option( 'hp_gallery_max_images' ) );
		}

		if ( ! $limit ) {
			$limit = 30;
		}

		/**
		 * Filters the number of photos allowed in one gallery folder.
		 *
		 * @param int $limit Maximum photos.
		 * @param int $user_id Folder owner ID.
		 */
		return absint( apply_filters( 'hp_agl/image_limit', $limit, $user_id ) );
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
	 * Refreshes the gating flags when a membership plan is saved, deleted or
	 * trashed. Runs on the generic save_post at a late priority so the plan
	 * meta is already persisted, and reads the type from the passed post so it
	 * still works from `deleted_post` (after the row is gone).
	 *
	 * @param int           $post_id Post ID.
	 * @param \WP_Post|null $post Post object, when provided by the hook.
	 * @return void
	 */
	public function refresh_gating_flags_for_post( $post_id, $post = null ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$type       = $post instanceof \WP_Post ? $post->post_type : get_post_type( $post_id );
		$plan_types = array_filter( [ hp_agl_get_plan_post_type(), 'hp_membership_plan' ] );

		if ( in_array( $type, $plan_types, true ) ) {
			$this->refresh_gating_flags();
		}
	}

	/**
	 * Checks whether members-only folders are enabled on this site.
	 *
	 * @return bool
	 */
	public function are_members_folders_enabled() {
		return (bool) get_option( 'hp_gallery_enable_members' );
	}

	/**
	 * Gets a folder's effective visibility.
	 *
	 * With members-only folders switched off site-wide, an existing
	 * members-only folder behaves as private: fail closed, never reveal
	 * content the vendor marked as gated.
	 *
	 * @param \HivePress\Models\Gallery_Folder $folder Folder object.
	 * @return string
	 */
	public function get_effective_visibility( $folder ) {
		$visibility = $folder->get_visibility();

		if ( ! in_array( $visibility, [ 'public', 'members' ], true ) ) {
			return 'private';
		}

		if ( 'members' === $visibility && ! $this->are_members_folders_enabled() ) {
			return 'private';
		}

		return $visibility;
	}

	/**
	 * Gets the visibility choices vendors may pick from.
	 *
	 * @return array
	 */
	public function get_visibility_options() {
		$options = [
			'public' => esc_html__( 'Public', 'additional-gallery-for-hivepress' ),
		];

		if ( $this->are_members_folders_enabled() ) {
			$options['members'] = esc_html__( 'Members only', 'additional-gallery-for-hivepress' );
		}

		$options['private'] = esc_html__( 'Private', 'additional-gallery-for-hivepress' );

		return $options;
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

		// With members-only folders disabled site-wide, existing members
		// folders drop out of every public listing (they behave as private).
		$visibilities = $this->are_members_folders_enabled() ? [ 'public', 'members' ] : [ 'public' ];

		return Models\Gallery_Folder::query()->filter(
			[
				'status'         => 'publish',
				'vendor'         => absint( $vendor_id ),
				'visibility__in' => $visibilities,
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

			if ( 'members' === $this->get_effective_visibility( $folder ) && ! $member_view && 'hide' === $display ) {
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
	 * Uses `merge_blocks()` rather than `merge_trees()`: it takes a flat map and
	 * finds the target block wherever a theme has moved it, which matters here
	 * because the six official themes restructure these sidebars. It is also
	 * the replacement HivePress intends for `merge_trees`.
	 *
	 * @param array $template Template arguments.
	 * @return array
	 */
	public function alter_vendor_view_page( $template ) {
		return hivepress()->template->merge_blocks(
			$template,
			[
				'page_sidebar' => [
					'blocks' => [
						'gallery_link' => [
							'type'   => 'agl_gallery_link',
							'_order' => 15,
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
		return hivepress()->template->merge_blocks(
			$template,
			[
				'page_sidebar' => [
					'blocks' => [
						'gallery_link' => [
							'type'   => 'agl_gallery_link',
							'_order' => 35,
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
	 * Registers the photo page widget area.
	 *
	 * The wrapper markup matches what the official HivePress themes register
	 * for their own sidebars (see hivetheme's widget-areas config), so widgets
	 * dropped in here pick up the theme's sidebar-widget styling.
	 *
	 * @return void
	 */
	public function register_widget_area() {
		register_sidebar(
			[
				'id'            => 'hp_agl_photo_sidebar',
				'name'          => esc_html__( 'Photo Page (sidebar)', 'additional-gallery-for-hivepress' ),
				'description'   => esc_html__( 'Shown in the sidebar of every gallery photo page.', 'additional-gallery-for-hivepress' ),
				'before_widget' => '<div id="%1$s" class="hp-widget widget widget--sidebar %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<h3 class="widget__title">',
				'after_title'   => '</h3>',
			]
		);
	}

	/**
	 * Gets the photo page sidebar position.
	 *
	 * Unset and legacy-empty values fall back to the right, matching the core
	 * sidebar pages.
	 *
	 * @return string Either `left` or `right`.
	 */
	public function get_photo_sidebar_position() {
		return 'left' === get_option( 'hp_gallery_photo_sidebar' ) ? 'left' : 'right';
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

		/*
		 * The vendor's own choice wins, but only while it is still one of this folder's images.
		 * A cover that has since been deleted, or moved to another folder, quietly falls back to
		 * the first image rather than leaving a hole where the cover used to be.
		 */
		$chosen = absint( get_post_meta( $folder->get_id(), self::COVER_META, true ) );

		if ( $chosen && in_array( $chosen, array_map( 'absint', $image_ids ), true )
			&& 0 === strpos( (string) get_post_mime_type( $chosen ), 'image' ) ) {
			return $chosen;
		}

		foreach ( $image_ids as $image_id ) {
			if ( 0 === strpos( (string) get_post_mime_type( $image_id ), 'image' ) ) {
				return $image_id;
			}
		}

		return $image_ids ? hp\get_first_array_value( $image_ids ) : null;
	}

	/**
	 * Whether a photo is its folder's chosen cover.
	 *
	 * Asks the same question the gallery grid asks, so the control on the photo page can never
	 * disagree with the picture actually being shown - including for a folder where nobody has
	 * chosen, where the first image is the cover by default.
	 *
	 * @param object $folder Folder object.
	 * @param int    $photo_id Attachment ID.
	 * @return bool
	 */
	public function is_folder_cover( $folder, $photo_id ) {
		return absint( $photo_id ) && absint( $this->get_folder_cover_id( $folder ) ) === absint( $photo_id );
	}

	/**
	 * Sets a folder's cover photo.
	 *
	 * @param object $folder Folder object.
	 * @param int    $photo_id Attachment ID, 0 to go back to the first image.
	 * @return bool
	 */
	public function set_folder_cover( $folder, $photo_id ) {
		$photo_id = absint( $photo_id );

		if ( ! $photo_id ) {
			delete_post_meta( $folder->get_id(), self::COVER_META );

			return true;
		}

		if ( ! in_array( $photo_id, array_map( 'absint', (array) $folder->get_images__id() ), true ) ) {
			return false;
		}

		update_post_meta( $folder->get_id(), self::COVER_META, $photo_id );

		/*
		 * The gallery index and the folder page both show the cover, and a page cache would go on
		 * serving the old one. Same queued purge the like and comment counts use.
		 */
		$scheduler = hivepress()->scheduler;

		if ( $scheduler ) {
			$scheduler->add_action( 'hp_agl_purge_photo_cache', [ $photo_id ] );
		}

		return true;
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
		global $wpdb;

		$counts = [
			'images' => 0,
			'videos' => 0,
		];

		$folder_id = hp_agl_int( $folder->get_id() );

		if ( ! $folder_id ) {
			return $counts;
		}

		/*
		 * Cached beside the image IDs, in the same group, so whatever clears those clears these too
		 * and the two can never disagree about a folder's contents.
		 */
		$cached = hivepress()->cache->get_post_cache( $folder_id, 'media_counts', 'models/attachment' );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// The IDs themselves are already cached by the model's own lazy loader.
		$image_ids = array_filter( array_map( 'hp_agl_int', (array) $folder->get_images__id() ) );

		if ( $image_ids ) {
			/*
			 * One query for the whole folder, reading nothing but the mime types.
			 *
			 * This used to call get_images(), which turns every ID into a full attachment model
			 * purely to read one string off each. That is a handful of queries per folder, and it
			 * scales with folders AND with photos: a vendor with thirty folders paid for it on every
			 * render of their gallery page, on a table that is one of the largest on a busy site.
			 */
			$placeholders = implode( ',', array_fill( 0, count( $image_ids ), '%d' ) );

			$mime_types = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no core API returns mime types for a set of IDs without loading every post; the result is cached immediately below.
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a generated run of %d, which the sniff cannot see inside the interpolation; every value is passed through prepare().
					"SELECT post_mime_type FROM {$wpdb->posts} WHERE ID IN ( {$placeholders} )",
					$image_ids
				)
			);

			foreach ( (array) $mime_types as $mime_type ) {
				if ( 0 === strpos( (string) $mime_type, 'video/' ) ) {
					++$counts['videos'];
				} else {
					++$counts['images'];
				}
			}
		}

		hivepress()->cache->set_post_cache( $folder_id, 'media_counts', 'models/attachment', $counts );

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

		// Each half is pluralised on its own count before they are joined: a
		// single combined string cannot be, so a folder holding one of each
		// used to read "1 photos, 1 videos".
		if ( $videos && $images ) {
			return sprintf(
				/* translators: 1: photos count phrase e.g. "3 photos", 2: videos count phrase e.g. "1 video". */
				esc_html__( '%1$s, %2$s', 'additional-gallery-for-hivepress' ),
				/* translators: %s: photos number. */
				sprintf( _n( '%s photo', '%s photos', $images, 'additional-gallery-for-hivepress' ), number_format_i18n( $images ) ),
				/* translators: %s: videos number. */
				sprintf( _n( '%s video', '%s videos', $videos, 'additional-gallery-for-hivepress' ), number_format_i18n( $videos ) )
			);
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
	 * @param bool                             $locked Whether the folder is locked for the current user.
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

		// Full rendering. Engagement counts are fetched for the whole folder in
		// one go, rather than per tile.
		$photo_ids = [];

		foreach ( $attachments as $attachment ) {
			$photo_ids[] = $attachment->get_id();
		}

		$engagement = ( $this->are_likes_enabled() || $this->are_comments_enabled() ) ? $this->get_engagement_counts( $photo_ids ) : [];
		$liked_ids  = $this->are_likes_enabled() ? $this->get_liked_photo_ids( $photo_ids ) : [];

		$output .= '<div class="hp-agl-grid">';

		foreach ( $attachments as $attachment ) {
			$caption = get_post_field( 'post_excerpt', $attachment->get_id() );
			$caption = is_string( $caption ) ? trim( $caption ) : '';

			$photo_counts = isset( $engagement[ $attachment->get_id() ] ) ? $engagement[ $attachment->get_id() ] : [
				'likes'    => 0,
				'comments' => 0,
			];

			$photo_actions = $engagement ? $this->render_photo_actions( $folder, $attachment->get_id(), $photo_counts, in_array( $attachment->get_id(), $liked_ids, true ) ) : '';
			$photo_url     = $this->get_photo_url( $folder, $attachment->get_id() );

			if ( 0 === strpos( (string) $attachment->get_mime_type(), 'video/' ) ) {

				// Render the video inline, mirroring the core listing gallery.
				$output .= '<span class="hp-agl-grid__item hp-agl-grid__item--video">';
				$output .= '<video controls preload="metadata" playsinline><source src="' . esc_url( $attachment->get_url() . '#t=0.001' ) . '" type="' . esc_attr( $attachment->get_mime_type() ) . '"></video>';

				if ( $caption ) {
					$output .= '<a class="hp-agl-grid__caption hp-meta hp-link" href="' . esc_url( $photo_url ) . '">' . esc_html( $caption ) . '</a>';
				}

				$output .= $photo_actions;
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

				// A tile always opens the photo's own page; the lightbox
				// setting only affects enlarging the photo once there. The
				// media link carries its own class because the caption link
				// below is also a direct <a> child of the tile, and a shared
				// selector once gave captions the square image box.
				$output .= '<a class="hp-agl-grid__media" href="' . esc_url( $photo_url ) . '">' . $thumbnail . '</a>';

				if ( $caption ) {
					$output .= '<a class="hp-agl-grid__caption hp-meta hp-link" href="' . esc_url( $photo_url ) . '">' . esc_html( $caption ) . '</a>';
				}

				$output .= $photo_actions;
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

		// The costs and sign-up guidance live in the section description, which
		// renders as a normal paragraph under the section title, because a
		// field description is a hover tooltip and a link inside one cannot be
		// clicked before it disappears. Guarded like the section and the key
		// field, so however many OpenAI-based extensions are installed the
		// copy appears exactly once.
		if ( empty( $settings['integrations']['sections']['openai']['description'] ) ) {
			$settings['integrations']['sections']['openai']['description'] = sprintf(
				/* translators: %s: OpenAI sign-up URL. */
				__( 'Connects features that check content with OpenAI, such as gallery photo moderation. Moderation uses OpenAI\'s free Moderation endpoint: checks cost nothing and no paid credit is needed, though new accounts have modest rate limits. You only need an API key. <a href="%s" target="_blank">Sign up and create one here</a>.', 'additional-gallery-for-hivepress' ),
				'https://platform.openai.com/signup'
			);
		}

		if ( ! isset( $settings['integrations']['sections']['openai']['fields']['openai_api_key'] ) ) {

			// A Text field that *displays* as a password: `display_type` becomes
			// the input's type attribute, so the key stays masked on a screen
			// that gets screenshotted or shared while keeping Text validation.
			// `type => 'password'` would instead give a dead front-end-only
			// data-component, since core's eye handler never loads in wp-admin.
			// The reveal toggle is printed by print_secret_toggles() below.
			$settings['integrations']['sections']['openai']['fields']['openai_api_key'] = [
				'label'        => esc_html__( 'API Key', 'additional-gallery-for-hivepress' ),
				'description'  => __( 'Your OpenAI API key, shared by any installed extension that uses OpenAI\'s free Moderation endpoint. Checks are free and each folder save sends up to ten photos in a single request, so rate limits are rarely met.', 'additional-gallery-for-hivepress' ),
				'type'         => 'text',
				'display_type' => 'password',
				'max_length'   => 256,
				'attributes'   => [ 'autocomplete' => 'new-password' ],
				'_order'       => 10,
			];
		}

		// Mask the key whoever registered it. The field is shared, so on a site
		// where a sibling OpenAI extension's filter ran first its definition
		// wins, and an older sibling declares a plain text field. Setting
		// display_type here means the key renders masked on any load order
		// rather than relying on the reveal script to hide it after paint.
		$openai_key = &$settings['integrations']['sections']['openai']['fields']['openai_api_key'];

		if ( isset( $openai_key ) && empty( $openai_key['display_type'] ) ) {
			$openai_key['display_type']               = 'password';
			$openai_key['attributes']['autocomplete'] = 'new-password';
		}

		unset( $openai_key );

		return $settings;
	}

	/**
	 * Checks whether the current screen is the HivePress Integrations tab.
	 *
	 * @return bool
	 */
	protected function is_integrations_screen() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen check that changes nothing; the capability test below is the gate.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return 'hp_settings' === $page && 'integrations' === $tab && current_user_can( 'manage_options' );
	}

	/**
	 * Prints the stylesheet for the masked API key field.
	 *
	 * Core's admin stylesheet gives text, email, tel and url inputs a 25em
	 * width but omits `input[type=password]`, so a password-display field
	 * falls through to the 100% width that common.min.css puts on
	 * `.hp-field--password` and stretches across the whole screen. The width
	 * therefore lives on a wrapper span: the input fills it in both states of
	 * the toggle, so nothing moves when the type flips. The first rule sizes
	 * the bare input before the script wraps it, so the field never flashes
	 * full width while the page loads.
	 *
	 * @return void
	 */
	public function print_secret_styles() {
		if ( ! $this->is_integrations_screen() ) {
			return;
		}
		?>
		<style>
			.hp-form--table input[name="hp_openai_api_key"] {
				width: 25em;
				max-width: 100%;
				box-sizing: border-box;
			}

			.hp-form--table .hp-agl-secret-wrap {
				position: relative;
				display: inline-block;
				width: 25em;
				max-width: 100%;
			}

			.hp-form--table .hp-agl-secret-wrap input {
				width: 100%;
				box-sizing: border-box;
				padding-right: 2.2em;
			}

			/* Match core, which widens every form-table input to the full row
				below the wp-admin mobile breakpoint. */
			@media screen and (max-width: 782px) {

				.hp-form--table input[name="hp_openai_api_key"],
				.hp-form--table .hp-agl-secret-wrap {
					width: 100%;
				}
			}
		</style>
		<?php
	}

	/**
	 * Adds a show/hide toggle to the shared OpenAI API key field.
	 *
	 * Dashicons rather than Font Awesome, because WordPress loads them on every
	 * admin screen while HivePress's own eye button will not work here at all:
	 * its handler ships in the front-end bundle, which wp-admin never enqueues.
	 *
	 * The field is masked defensively rather than assumed masked. It is shared
	 * with any other OpenAI-based extension, and whichever plugin's settings
	 * filter runs first defines it, so on a site where a sibling plugin
	 * registered it as a plain text field the key would otherwise sit revealed.
	 * The `hpSecretToggle` marker on the input is the cross-plugin contract
	 * that stops a second extension adding a second eye to the same field.
	 *
	 * @return void
	 */
	public function print_secret_toggles() {
		if ( ! $this->is_integrations_screen() ) {
			return;
		}
		?>
		<script>
		( function() {
			var labels = {
				show: <?php echo wp_json_encode( __( 'Show', 'additional-gallery-for-hivepress' ) ); ?>,
				hide: <?php echo wp_json_encode( __( 'Hide', 'additional-gallery-for-hivepress' ) ); ?>
			};

			var input = document.querySelector( 'input[name="hp_openai_api_key"]' );

			if ( ! input || input.dataset.hpSecretToggle ) {
				return;
			}

			input.dataset.hpSecretToggle = '1';
			input.type = 'password';
			input.setAttribute( 'autocomplete', 'new-password' );

			// The wrapper carries the field's width and position rules; see
			// the stylesheet printed by print_secret_styles().
			var wrap = document.createElement( 'span' );

			wrap.className = 'hp-agl-secret-wrap';

			input.parentNode.insertBefore( wrap, input );
			wrap.appendChild( input );

			// A plain icon, deliberately without button chrome.
			var button = document.createElement( 'button' );

			// Explicitly not a submit button: it sits inside the settings form,
			// where a typeless button defaults to submit and would save the page.
			button.type = 'button';
			button.setAttribute( 'aria-label', labels.show );
			button.title = labels.show;
			button.style.position = 'absolute';
			button.style.right = '0.4em';
			button.style.top = '50%';
			button.style.transform = 'translateY(-50%)';
			button.style.background = 'none';
			button.style.border = '0';
			button.style.padding = '0';
			button.style.margin = '0';
			button.style.cursor = 'pointer';
			button.style.color = '#787c82';
			button.style.lineHeight = '1';

			var icon = document.createElement( 'span' );

			icon.className = 'dashicons dashicons-visibility';

			button.appendChild( icon );

			button.addEventListener( 'click', function() {
				var hidden = 'password' === input.type;

				input.type = hidden ? 'text' : 'password';
				icon.className = 'dashicons ' + ( hidden ? 'dashicons-hidden' : 'dashicons-visibility' );
				button.title = hidden ? labels.hide : labels.show;
				button.setAttribute( 'aria-label', hidden ? labels.hide : labels.show );
			} );

			wrap.appendChild( button );
		} )();
		</script>
		<?php
	}

	/**
	 * Prepares the image URLs of a folder save for moderation.
	 *
	 * Only public http(s) URLs are usable, because OpenAI fetches each
	 * image server-side. The list is de-duplicated and capped, and keyed by
	 * attachment ID so the caller can tell which photos were actually put in
	 * front of the service - marking anything else as checked would exempt
	 * photos nobody ever looked at.
	 *
	 * @param array $attachment_ids Attachment IDs.
	 * @return array Image URLs keyed by attachment ID.
	 */
	public function prepare_moderation_urls( $attachment_ids ) {
		$urls = [];

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = hp_agl_int( $attachment_id );

			if ( ! $attachment_id ) {
				continue;
			}

			/*
			 * Only images. The moderation endpoint takes image_url inputs and nothing else, so a
			 * video (folders hold them when hp_gallery_allow_video is on) can never get a verdict
			 * - and one dud in the batch used to poison the WHOLE run: moderate_image_urls() read
			 * the refusal as "no verdict", answered null, nothing was marked as checked, and every
			 * later save of the folder re-bought a full review of photos that had already passed.
			 * Videos are simply not part of this check; leaving them out is what lets the run
			 * finish and the checked marks stick.
			 */
			if ( ! wp_attachment_is_image( $attachment_id ) ) {
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

			$urls[ $attachment_id ] = $url;
		}

		/**
		 * Filters the maximum number of images sent for moderation in a
		 * single request.
		 *
		 * @param int $cap Maximum number of images.
		 */
		$cap = apply_filters( 'hp_agl/moderation_image_cap', 10 );

		$cap = max( 0, is_scalar( $cap ) ? (int) $cap : 0 );

		// Keys survive the cap: they are the attachment IDs the caller marks as checked.
		return array_slice( $urls, 0, $cap, true );
	}

	/**
	 * Moderates a set of image URLs with the OpenAI Moderation endpoint.
	 *
	 * ONE REQUEST PER PHOTO, and that is not a style choice. The endpoint
	 * takes exactly one image per call: send two and it answers
	 * 400 too_many_images, "Number of images (2) exceeds maximum of 1"
	 * (measured against the live API by the moderation plugin, 2026-08-11).
	 * This method used to batch every photo into a single call, so on any
	 * folder with more than one photo the request failed, the check failed
	 * open, and the folder was accepted. It looked exactly like a clean pass,
	 * and a one-photo folder worked perfectly, which is why it survived
	 * unnoticed. Never batch these.
	 *
	 * Checking stops at the first photo that is flagged. A whole-run deadline
	 * bounds the total, and each request is given only the time actually left,
	 * so the budget is a real ceiling rather than a starting gun.
	 *
	 * Returns false ONLY when every photo came back with a definite verdict.
	 * If any photo could not be checked the answer is null, because "some of
	 * these photos were never looked at" is not "these photos are fine".
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

		/**
		 * Filters the total number of seconds photo review may spend.
		 *
		 * @hook hp_agl_moderation_budget
		 * @param {int} $seconds Whole-run deadline across every photo. Default 30.
		 * @return {int} Filtered deadline.
		 */
		$deadline = microtime( true ) + max( 5, (int) apply_filters( 'hp_agl_moderation_budget', 30 ) );

		$all_verdicts = true;

		foreach ( $urls as $url ) {
			$remaining = $deadline - microtime( true );

			if ( $remaining < 2 ) {
				return null;
			}

			$verdict = $this->moderate_one_image( hp_agl_string( $url ), $api_key, (int) min( 10, floor( $remaining ) ) );

			if ( true === $verdict ) {
				return true;
			}

			if ( null === $verdict ) {
				$all_verdicts = false;
			}
		}

		return $all_verdicts ? false : null;
	}

	/**
	 * Sends one photo to the OpenAI Moderation endpoint.
	 *
	 * Fails open: null on a missing key, a transport error, a timeout or a
	 * response that cannot be read, so a moderation outage never blocks a
	 * vendor.
	 *
	 * @param string $url     Image URL.
	 * @param string $api_key OpenAI API key.
	 * @param int    $timeout Request timeout in seconds.
	 * @return bool|null True if flagged, false if clean, null if unavailable.
	 */
	protected function moderate_one_image( $url, $api_key, $timeout ) {
		$body = wp_json_encode(
			[
				'model' => 'omni-moderation-latest',

				'input' => [
					[
						'type'      => 'image_url',

						'image_url' => [
							'url' => $url,
						],
					],
				],
			]
		);

		if ( false === $body ) {
			return null;
		}

		// OpenAI fetches the image server-side, so this is never quick. That
		// is exactly why the caller runs in the background and why the
		// timeout is bounded rather than generous - see "Never block a public
		// request on a third party" in security-standards.md.
		// The user agent is set explicitly, and is a top-level argument rather
		// than a header: left out, WordPress substitutes
		// "WordPress/{version}; {site url}", so every moderation request would
		// tell OpenAI the site's address and its exact WordPress version.
		$response = wp_remote_post(
			'https://api.openai.com/v1/moderations',
			[
				'timeout'    => $timeout,
				'user-agent' => 'additional-gallery-for-hivepress/' . HP_AGL_VERSION,

				'headers'    => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],

				'body'       => $body,
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
	 * Reviews a folder's photos in the background, and hides it if any is
	 * flagged.
	 *
	 * Queued from the folder update controller through the HivePress
	 * scheduler. Photo review is one remote call per photo against a service
	 * that downloads each image itself, so running it on the vendor's own
	 * request put the whole site's capacity behind OpenAI: the sibling
	 * moderation plugin measured 21-32 seconds of held request doing exactly
	 * this, which is enough to exhaust a small PHP worker pool and hand every
	 * other visitor a 504.
	 *
	 * A flagged folder is set to draft, so it stops being public just as a
	 * refused save would have prevented. What changes is when the vendor
	 * finds out.
	 *
	 * Settings and photos are re-read here rather than passed through the
	 * queue, since the owner may have changed them in the gap and the queue
	 * may retry.
	 *
	 * @param int $folder_id Gallery folder ID.
	 */
	public function review_folder_images( $folder_id ) {
		$folder_id = hp_agl_int( $folder_id );

		if ( ! $folder_id || ! get_option( 'hp_gallery_ai_moderation' ) ) {
			return;
		}

		$folder = Models\Gallery_Folder::query()->get_by_id( $folder_id );

		if ( empty( $folder ) || 'publish' !== $folder->get_status() ) {
			return;
		}

		/*
		 * Only photos that have not been checked before. Re-saving a folder used to send every photo
		 * in it to OpenAI again, so a vendor fixing a typo in the title paid for a full round trip
		 * and waited on it: measured at 4.9 seconds for a first save and 4.1 for a re-save with
		 * nothing added (2026-08-20). Checking is per photo and a photo does not change once
		 * uploaded, so a second look at the same file can only ever reach the same answer.
		 */
		$unchecked = $this->get_unchecked_images( $folder );

		if ( ! $unchecked ) {
			return;
		}

		$image_urls = $this->prepare_moderation_urls( $unchecked );

		if ( ! $image_urls ) {
			return;
		}

		$flagged = $this->moderate_image_urls( $image_urls );

		/*
		 * Marked as checked only on a definite answer, and only for the photos actually sent.
		 * moderate_image_urls() returns null when the service could not be reached, and treating
		 * that as "checked" would let a photo through for good on a single outage -- the one
		 * failure this must not have. Marking all of $unchecked had the mirror failure: entries
		 * prepare_moderation_urls() drops (videos, protected files, duplicates, anything past the
		 * cap) were stamped as reviewed without ever being looked at. The dropped ones stay
		 * unmarked on purpose - a capped-off photo gets its turn on the next save, and a video or
		 * protected file just falls out again up front, at the cost of a meta read, not a request.
		 */
		if ( null !== $flagged ) {
			foreach ( array_keys( $image_urls ) as $image_id ) {
				update_post_meta( $image_id, '_hp_agl_ai_checked', 1 );
			}
		}

		if ( true !== $flagged ) {
			return;
		}

		update_post_meta( $folder_id, '_hp_agl_flagged', 1 );

		$folder->set_status( 'draft' )->save();

		/**
		 * Fires when the photo review hides a published folder.
		 *
		 * The folder simply stops being visible, and nothing in the interface says so, which means
		 * the vendor finds out by noticing it has gone. Hook here to tell them.
		 *
		 * @hook hp_agl/folder_flagged
		 * @param {int} $folder_id Gallery folder ID.
		 * @param {object} $folder Gallery folder object.
		 */
		do_action( 'hp_agl/folder_flagged', $folder_id, $folder );
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
		$post_id = get_the_ID();
		$folder  = $post_id ? Models\Gallery_Folder::query()->get_by_id( $post_id ) : null;

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
		if ( ! $this->is_gallery_upload() || empty( $file['size'] ) ) {
			return $file;
		}

		$max_mb = hp_agl_int( get_option( 'hp_gallery_max_filesize' ) );

		if ( $max_mb && $file['size'] > $max_mb * MB_IN_BYTES ) {
			/* translators: %s: size in megabytes. */
			$file['error'] = sprintf( esc_html__( 'Each file must be smaller than %s MB.', 'additional-gallery-for-hivepress' ), number_format_i18n( $max_mb ) );

			return $file;
		}

		// Enforce the storage quota, when one applies to this vendor.
		$vendor = $this->get_current_vendor();

		if ( $vendor ) {
			$limit = $this->get_storage_limit( $vendor );

			if ( $limit && $this->get_storage_used( $vendor ) + (int) $file['size'] > $limit ) {

				// Only mention upgrading where a plan actually exists to
				// upgrade to: on a site without Memberships the advice is a
				// dead end, and the vendor is left with nothing to act on.
				if ( $this->is_memberships_active() ) {
					/* translators: %s: size limit, e.g. "500 MB". */
					$file['error'] = sprintf( esc_html__( 'This upload would take your gallery over its %s storage limit. Remove some photos to make room, or upgrade your plan for more space.', 'additional-gallery-for-hivepress' ), $this->format_size( $limit ) );
				} else {
					/* translators: %s: size limit, e.g. "500 MB". */
					$file['error'] = sprintf( esc_html__( 'This upload would take your gallery over its %s storage limit. Remove some photos to make room.', 'additional-gallery-for-hivepress' ), $this->format_size( $limit ) );
				}
			}
		}

		return $file;
	}

	/**
	 * Scales a gallery image down right after upload, before WordPress
	 * generates its thumbnails, so every derived size comes from the scaled
	 * original.
	 *
	 * @param array  $upload Upload data (file, url, type).
	 * @param string $context Upload context.
	 * @return array
	 */
	public function resize_upload( $upload, $context ) {
		if ( 'upload' !== $context || ! $this->is_gallery_upload() || empty( $upload['file'] ) ) {
			return $upload;
		}

		$result = $this->resize_image_file( $upload['file'], hp_agl_string( hp\get_array_value( $upload, 'type' ) ) );

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
	 * Scales an oversized gallery image down in place.
	 *
	 * Runs at upload time, before WordPress generates its thumbnails, so every
	 * derived size is made from the already-scaled original.
	 *
	 * Deliberately the only image processing this plugin does. Compression,
	 * metadata stripping, WebP conversion and re-processing existing libraries
	 * were removed in 1.5.0: dedicated image plugins do all of that better, with
	 * fallbacks and CDN support, and doing it here only risked their work.
	 * Resizing stays because it belongs to the upload itself, and an unscaled
	 * phone photo is the one problem a gallery really does create.
	 *
	 * @param string $file File path.
	 * @param string $mime MIME type.
	 * @return array|false New file data (file, url, type), or false if unchanged.
	 */
	public function resize_image_file( $file, $mime ) {
		if ( ! in_array( $mime, [ 'image/jpeg', 'image/png', 'image/webp' ], true ) || ! file_exists( $file ) ) {
			return false;
		}

		$max_dim = hp_agl_int( get_option( 'hp_gallery_max_dimensions' ) );

		if ( ! $max_dim ) {
			return false;
		}

		$editor = wp_get_image_editor( $file );

		if ( is_wp_error( $editor ) ) {
			return false;
		}

		$size = $editor->get_size();

		// Leave an image that is already within bounds completely untouched,
		// rather than re-encoding it and losing quality for nothing.
		if ( ! is_array( $size ) || ( $size['width'] <= $max_dim && $size['height'] <= $max_dim ) ) {
			return false;
		}

		$editor->resize( $max_dim, $max_dim, false );

		$saved = $editor->save( $file, $mime );

		if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			return false;
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

		// Annotated loosely on purpose: WordPress adds keys beyond the documented
		// shape, including `original_image` on any upload it scaled down.
		/** @var array<string,mixed>|false $metadata */
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( $file && is_array( $metadata ) ) {
			$dir = dirname( $file );

			if ( ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size ) {
					if ( ! empty( $size['file'] ) && file_exists( $dir . '/' . $size['file'] ) ) {
						$bytes += (int) filesize( $dir . '/' . $size['file'] );
					}
				}
			}

			// Include the scaled big-image original backup, if present.
			if ( ! empty( $metadata['original_image'] ) && file_exists( $dir . '/' . $metadata['original_image'] ) ) {
				$bytes += (int) filesize( $dir . '/' . $metadata['original_image'] );
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
		$storage = $this->get_protected_storage();

		return $storage ? $storage['dir'] : null;
	}

	/**
	 * Gets how protected files are actually being stored.
	 *
	 * Returns `external` when the files sit outside the folder the web server publishes, and
	 * `uploads` when the best available option is a deny rule inside the uploads folder.
	 *
	 * @return string Storage mode, or an empty string when no directory could be created.
	 */
	public function get_protected_storage_mode() {
		$storage = $this->get_protected_storage();

		return $storage ? $storage['mode'] : '';
	}

	/**
	 * Works out where protected files should live, and creates it.
	 *
	 * A deny rule in the uploads folder is not protection on every stack. Where a reverse proxy
	 * serves static files ahead of the web server -- Nginx in front of Apache is the common shared
	 * hosting arrangement -- nothing ever reads `.htaccess` for a request that matches a file on
	 * disk, so the rule is skipped and the file is returned in full. Measured on a real host
	 * 2026-08-20: every private photo was retrievable by URL while the directory listing and the
	 * `.htaccess` itself both correctly returned 403, which is exactly what makes it look like it
	 * works. The unguessable file name is obscurity, not access control.
	 *
	 * So the files are put somewhere the web server cannot publish at all, and the deny rule
	 * becomes a second line rather than the only one. Where that is impossible the uploads folder
	 * is still used, but the mode is reported honestly so the settings screen can say so instead of
	 * promising protection the stack cannot deliver.
	 *
	 * @return array|null `dir` and `mode`, or null when nothing could be created.
	 */
	protected function get_protected_storage() {
		static $storage = false;

		if ( false !== $storage ) {
			return $storage;
		}

		foreach ( $this->get_protected_dir_candidates() as $candidate ) {
			if ( ! wp_mkdir_p( $candidate['dir'] ) || ! wp_is_writable( $candidate['dir'] ) ) {
				continue;
			}

			// Belt and braces on both routes. Outside the published folder the rule is redundant on
			// a correctly configured host and free insurance on a misconfigured one; inside it, it
			// is all there is.
			$this->protect_directory( $candidate['dir'] );

			$storage = $candidate;

			return $storage;
		}

		$storage = null;

		return $storage;
	}

	/**
	 * Lists the places protected files could be stored, best first.
	 *
	 * @return array
	 */
	protected function get_protected_dir_candidates() {
		$candidates = [];

		/**
		 * Filters the directory protected gallery files are stored in.
		 *
		 * Set this to a path the web server does not publish. It is used ahead of everything else,
		 * so a host with an unusual layout can point the plugin at the right place.
		 *
		 * @hook hp_agl/protected_dir
		 * @param {string} $dir Absolute directory path, or an empty string to choose automatically.
		 * @return {string} Absolute directory path.
		 */
		$custom = (string) apply_filters( 'hp_agl/protected_dir', defined( 'HP_AGL_PROTECTED_DIR' ) ? (string) HP_AGL_PROTECTED_DIR : '' );

		if ( $custom ) {
			$candidates[] = [
				'dir'  => untrailingslashit( wp_normalize_path( $custom ) ),
				'mode' => 'external',
			];
		}

		// Beside the published folder rather than inside it. The name carries a hash of this
		// install's own path so two sites sharing a parent directory cannot collide.
		$published = $this->get_published_dir();
		$parent    = dirname( $published );

		if ( $parent && $parent !== $published && wp_is_writable( $parent ) ) {
			$candidates[] = [
				'dir'  => $parent . '/hp-agl-protected-' . substr( md5( $published ), 0, 8 ),
				'mode' => 'external',
			];
		}

		// Last resort. Correct on a plain Apache host, and reported as the weaker option everywhere
		// else rather than being presented as protection.
		$upload_dir = wp_get_upload_dir();

		$candidates[] = [
			'dir'  => wp_normalize_path( $upload_dir['basedir'] ) . '/hp-agl-protected',
			'mode' => 'uploads',
		];

		// Anything that would still sit inside the published folder is not protection, so it is
		// dropped rather than offered -- except the uploads fallback, which is honest about itself.
		return array_values(
			array_filter(
				$candidates,
				function( $candidate ) use ( $published ) {
					return 'uploads' === $candidate['mode'] || ! $this->is_inside( $candidate['dir'], $published );
				}
			)
		);
	}

	/**
	 * Gets the directory the web server publishes.
	 *
	 * `DOCUMENT_ROOT` is the honest answer and is what a reverse proxy serves from, but it is
	 * absent under cron and WP-CLI, so the last value seen during a real request is kept. WordPress
	 * living in a subdirectory is the case that makes this matter: there, the directory above
	 * ABSPATH is still published, and treating it as safe would move the files somewhere just as
	 * exposed while reporting them protected.
	 *
	 * @return string
	 */
	protected function get_published_dir() {
		$root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['DOCUMENT_ROOT'] ) ) : '';

		if ( $root ) {
			$real = realpath( $root );
			$root = untrailingslashit( wp_normalize_path( $real ? $real : $root ) );

			if ( hp_agl_string( get_option( 'hp_agl_document_root' ) ) !== $root ) {
				update_option( 'hp_agl_document_root', $root, false );
			}
		} else {
			$root = hp_agl_string( get_option( 'hp_agl_document_root' ) );
		}

		$abspath = untrailingslashit( wp_normalize_path( ABSPATH ) );

		// With no reliable answer, assume the whole WordPress folder is published, which is true on
		// an ordinary install and errs towards caution on any other.
		if ( ! $root ) {
			return $abspath;
		}

		// A document root that does not contain this install is not describing this site, so it is
		// ignored rather than trusted.
		return $this->is_inside( $abspath, $root ) ? $root : $abspath;
	}

	/**
	 * Checks whether one path sits inside another.
	 *
	 * @param string $path Path to test.
	 * @param string $directory Directory it might be inside.
	 * @return bool
	 */
	protected function is_inside( $path, $directory ) {
		$path      = untrailingslashit( wp_normalize_path( $path ) );
		$directory = untrailingslashit( wp_normalize_path( $directory ) );

		if ( ! $directory ) {
			return false;
		}

		return $path === $directory || 0 === strpos( $path . '/', $directory . '/' );
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
	 * @param array  $sources Srcset sources.
	 * @param array  $size_array Size array.
	 * @param string $image_src Image source.
	 * @param array  $image_meta Image metadata.
	 * @param int    $attachment_id Attachment ID.
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

		// Query the folder's attachments directly rather than through the
		// cached relation, so a just-uploaded file is never missed (and so a
		// stale cache can never leave a private file unprotected).
		foreach ( $this->get_folder_attachment_ids( $folder->get_id() ) as $attachment_id ) {
			if ( $protect ) {
				$this->protect_attachment( $attachment_id );
			} else {
				$this->unprotect_attachment( $attachment_id );
			}
		}
	}

	/**
	 * Gets a folder's attachment IDs directly from the database (bypassing the
	 * cached relation), for the protection sync.
	 *
	 * @param int $folder_id Folder ID.
	 * @return array
	 */
	protected function get_folder_attachment_ids( $folder_id ) {
		return get_posts(
			[
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'post_parent' => absint( $folder_id ),
				'numberposts' => -1,
				'fields'      => 'ids',

				'meta_query'  => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded per-folder lookup that must not depend on cache freshness for a security-relevant relocation.
					[
						'key'   => 'hp_parent_field',
						'value' => 'images',
					],
				],
			]
		);
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
				'post_status' => 'any',
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
		$upload_dir = wp_get_upload_dir();
		$basedir    = untrailingslashit( wp_normalize_path( $upload_dir['basedir'] ) );

		// Get the current stored path, which may be relative to the uploads folder or, since the
		// files moved outside the published folder, absolute.
		$stored = hp_agl_string( get_post_meta( $attachment_id, '_wp_attached_file', true ) );

		if ( ! $stored ) {
			return false;
		}

		$base_relative = $this->get_base_relative( $stored );

		if ( ! $base_relative ) {
			return false;
		}

		/*
		 * The stored value keeps its historic shape either way: `hp-agl-protected/2026/08/x.jpg`
		 * while protected, the plain relative path otherwise. Only the physical location changes.
		 *
		 * Storing an absolute path instead was tried and abandoned. WordPress decides whether
		 * `_wp_attached_file` is absolute with `! str_starts_with( $file, '/' ) && ! preg_match(
		 * '|^.:\\|', $file )` (wp-includes/post.php:849), which requires a BACKSLASH after the
		 * drive letter -- while wp_normalize_path() turns every separator into a forward slash. On
		 * Windows the two disagree, WordPress prepends the uploads path to an already-absolute one,
		 * and every file belonging to the attachment stops resolving. Measured 2026-08-20: 0 of 15
		 * image sizes findable. Keeping the value relative sidesteps the disagreement completely,
		 * and means an existing install needs no meta rewritten at all.
		 */
		$dest_stored = $to_protected ? self::PROTECTED_PREFIX . $base_relative : $base_relative;

		if ( $to_protected && ! $this->get_protected_dir() ) {
			return false;
		}

		$from_dir = dirname( $this->get_physical_path( $stored ) );
		$to_dir   = dirname( $this->get_physical_path( $dest_stored ) );

		if ( $dest_stored === $stored && $from_dir === $to_dir ) {
			return true;
		}

		$relative      = $stored;
		$dest_relative = $dest_stored;

		if ( ! wp_mkdir_p( $to_dir ) ) {
			return false;
		}

		// Collect every file that belongs to this attachment. The metadata is
		// annotated loosely because WordPress adds keys beyond the documented
		// shape, including `original_image` on any upload it scaled down.
		$files = [ basename( $relative ) ];

		/** @var array<string,mixed>|false $metadata */
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

		$files = array_unique( $files );
		$main  = basename( $relative );

		// Move the main file first; abort if it cannot move (nothing has
		// changed yet, so the attachment stays consistent).
		if ( ! $this->move_file( $from_dir . '/' . $main, $to_dir . '/' . $main ) ) {
			return false;
		}

		// Move the remaining variants best-effort. A failed variant must not
		// leave the attachment pointing at a half-moved main file.
		foreach ( $files as $file ) {
			if ( $file !== $main ) {
				$this->move_file( $from_dir . '/' . $file, $to_dir . '/' . $file );
			}
		}

		// Point the attachment at the new location, keeping both `_wp_attached_file`
		// and the metadata `file` key in sync. WordPress deletes intermediate
		// sizes and the scaled original relative to `dirname($meta['file'])`, so
		// a stale value there would orphan those files on deletion.
		update_post_meta( $attachment_id, '_wp_attached_file', $dest_relative );

		if ( is_array( $metadata ) ) {
			$metadata['file'] = $dest_relative;

			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		return true;
	}

	/**
	 * Gets a folder's photos that have not been through the AI photo check.
	 *
	 * @param object $folder Gallery folder object.
	 * @return array Attachment IDs.
	 */
	public function get_unchecked_images( $folder ) {
		$unchecked = [];

		foreach ( (array) $folder->get_images__id() as $image_id ) {
			$image_id = hp_agl_int( $image_id );

			if ( $image_id && ! get_post_meta( $image_id, '_hp_agl_ai_checked', true ) ) {
				$unchecked[] = $image_id;
			}
		}

		return $unchecked;
	}

	/**
	 * Applies the chosen corner rounding to this plugin's buttons.
	 *
	 * Added to the plugin's own stylesheet rather than printed into the head, so it inherits that
	 * sheet's scope and never loads on a page with no gallery on it. An empty setting emits nothing
	 * at all, which is the default and leaves every button exactly as the theme drew it.
	 *
	 * @return void
	 */
	public function add_button_radius_style() {
		$radius = get_option( 'hp_gallery_button_radius' );

		// A cleared number field stores as an empty string, which is a deliberate "leave it alone",
		// while a stored 0 is a deliberate "square". Only a numeric value counts.
		if ( ! is_numeric( $radius ) ) {
			return;
		}

		$radius = max( 0, min( 40, (int) $radius ) );

		$selectors = implode(
			', ',
			[
				'.hp-agl-link__button',
				'.hp-agl-account__share-row .button',
				'.hp-agl-account__price-row .hp-form__button',
				'.hp-agl-folder__copy',
				'.hp-agl-photo-manage .hp-form__button',
				'.hp-agl-comments__form .hp-form__button',
				'.hp-agl-gallery__folder-unlock .button',
			]
		);

		wp_add_inline_style( 'hivepress-gallery-frontend', $selectors . '{border-radius:' . $radius . 'px;}' );
	}

	/**
	 * Warns the site owner when protected files could not leave the published folder.
	 *
	 * This exists because the alternative is worse than no feature: the setting used to promise that
	 * these files "cannot be reached from outside", and on a host that serves static files ahead of
	 * the web server that was simply untrue -- every private photo was retrievable by anyone with
	 * the address. An owner who knows can move the files themselves; an owner who is told they are
	 * safe cannot.
	 *
	 * Shown only on the settings screen that carries the setting, and only to somebody who could act
	 * on it, so it is not another notice following people around the dashboard.
	 *
	 * @return void
	 */
	public function render_protection_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! $this->is_file_protection_enabled() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which admin screen is being shown, not acting on a request.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'hp_settings' !== $page || 'gallery' !== $tab ) {
			return;
		}

		$mode = $this->get_protected_storage_mode();

		if ( 'external' === $mode ) {
			return;
		}

		$message = 'uploads' === $mode
			? esc_html__( 'Protect Files is on, but this site could not move the files out of the folder your web server publishes, so they are guarded by a deny rule instead. That rule works on Apache on its own. It is ignored where another web server, usually Nginx, hands out files before Apache sees the request, which is common on shared hosting, and private photos can then be opened by anyone with the address.', 'additional-gallery-for-hivepress' )
			: esc_html__( 'Protect Files is on, but no protected folder could be created, so private and members-only files stay where they are and can be opened by anyone with the address.', 'additional-gallery-for-hivepress' );

		$help = esc_html__( 'To fix it, ask your host for a folder outside the published one that PHP can write to, then add a line to wp-config.php defining HP_AGL_PROTECTED_DIR as that path.', 'additional-gallery-for-hivepress' );

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s %s</p></div>',
			esc_html__( 'Additional Gallery:', 'additional-gallery-for-hivepress' ),
			esc_html( $message ),
			esc_html( $help )
		);
	}

	/**
	 * Moves already-protected files out of the uploads folder.
	 *
	 * Runs once, on the update that relocates protected storage. Only the files move: the stored
	 * `_wp_attached_file` keeps the same `hp-agl-protected/` prefix it has always had, so there is
	 * no attachment metadata to rewrite and nothing to undo if the move is interrupted -- a file
	 * that has moved and one that has not are both found, because get_physical_path() resolves the
	 * prefix through whichever directory is in use and falls back to the old one.
	 *
	 * Does nothing where the files cannot leave the uploads folder, which is the case this update
	 * cannot fix and the settings screen now says so instead of promising otherwise.
	 *
	 * @return int Number of files moved.
	 */
	public function migrate_protected_files() {
		$storage = $this->get_protected_storage();

		if ( ! $storage || 'external' !== $storage['mode'] ) {
			return 0;
		}

		$upload_dir = wp_get_upload_dir();
		$legacy     = untrailingslashit( wp_normalize_path( $upload_dir['basedir'] ) ) . '/' . untrailingslashit( self::PROTECTED_PREFIX );

		if ( ! is_dir( $legacy ) ) {
			return 0;
		}

		$target = untrailingslashit( wp_normalize_path( $storage['dir'] ) );
		$moved  = 0;

		try {
			$items = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $legacy, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::CHILD_FIRST
			);
		} catch ( \Exception $exception ) {
			return 0;
		}

		$directories = [];

		foreach ( $items as $item ) {
			$path     = wp_normalize_path( $item->getPathname() );
			$relative = ltrim( substr( $path, strlen( $legacy ) ), '/' );

			if ( '' === $relative ) {
				continue;
			}

			if ( $item->isDir() ) {
				$directories[] = $path;

				continue;
			}

			// The two guards are recreated in the new location by protect_directory(), so the old
			// copies are dropped rather than carried across.
			if ( in_array( $relative, [ '.htaccess', 'index.php' ], true ) ) {
				wp_delete_file( $path );

				continue;
			}

			$destination = $target . '/' . $relative;

			if ( ! wp_mkdir_p( dirname( $destination ) ) ) {
				continue;
			}

			if ( $this->move_file( $path, $destination ) ) {
				++$moved;
			}
		}

		// Tidy up behind the move, deepest first. Anything still holding a file is left alone.
		foreach ( $directories as $directory ) {
			@rmdir( $directory ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- only succeeds on an empty directory, and a failure means something is still there and must stay.
		}

		@rmdir( $legacy ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- same reasoning.

		return $moved;
	}

	/**
	 * Reduces a stored attachment path to its plain uploads-relative form.
	 *
	 * @param string $stored Value of `_wp_attached_file`.
	 * @return string
	 */
	protected function get_base_relative( $stored ) {
		$stored = wp_normalize_path( hp_agl_string( $stored ) );

		return (string) preg_replace( '#^' . preg_quote( self::PROTECTED_PREFIX, '#' ) . '#', '', $stored );
	}

	/**
	 * Resolves a stored attachment path to the file's real location on disk.
	 *
	 * This is the only place that knows protected files may not live under the uploads folder, so
	 * everything else -- moving, deleting, serving -- can keep working in stored paths.
	 *
	 * @param string $stored Value of `_wp_attached_file`.
	 * @return string
	 */
	public function get_physical_path( $stored ) {
		$stored = wp_normalize_path( hp_agl_string( $stored ) );

		if ( ! $stored ) {
			return '';
		}

		$upload_dir = wp_get_upload_dir();
		$basedir    = untrailingslashit( wp_normalize_path( $upload_dir['basedir'] ) );

		if ( 0 !== strpos( $stored, self::PROTECTED_PREFIX ) ) {
			return $basedir . '/' . $stored;
		}

		$base_relative = substr( $stored, strlen( self::PROTECTED_PREFIX ) );

		$storage = $this->get_protected_storage();
		$legacy  = $basedir . '/' . untrailingslashit( self::PROTECTED_PREFIX ) . '/' . $base_relative;

		// With no protected directory available at all, the historic in-uploads location is the
		// only place it can be.
		if ( ! $storage ) {
			return $legacy;
		}

		$path = untrailingslashit( wp_normalize_path( $storage['dir'] ) ) . '/' . $base_relative;

		/*
		 * Between the update landing and the migration running -- or if that migration was
		 * interrupted part way -- a file may still be sitting in the old place. Falling back to it
		 * when the new path holds nothing means those files keep working instead of disappearing,
		 * and costs one file_exists() on a path that is about to be opened anyway.
		 */
		if ( $legacy !== $path && ! file_exists( $path ) && file_exists( $legacy ) ) {
			return $legacy;
		}

		return $path;
	}

	/**
	 * Points WordPress at the real location of a protected file.
	 *
	 * Everything that reads an attachment from disk goes through get_attached_file(), so one filter
	 * covers image editing, regenerating sizes and the media library alike.
	 *
	 * @param string $file Resolved file path.
	 * @param int    $attachment_id Attachment ID.
	 * @return string
	 */
	public function filter_attached_file( $file, $attachment_id ) {
		$stored = hp_agl_string( get_post_meta( $attachment_id, '_wp_attached_file', true ) );

		if ( 0 !== strpos( wp_normalize_path( $stored ), self::PROTECTED_PREFIX ) ) {
			return $file;
		}

		return $this->get_physical_path( $stored );
	}

	/**
	 * Deletes a protected attachment's files.
	 *
	 * WordPress cannot do this itself once the files leave the uploads folder: it deletes each one
	 * through wp_delete_file_from_directory( $file, $uploads['basedir'] ), which refuses anything
	 * outside that directory (wp-includes/post.php, verified). Without this the originals and every
	 * generated size would survive the attachment and sit on disk for good.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function delete_protected_files( $attachment_id ) {
		$stored = hp_agl_string( get_post_meta( $attachment_id, '_wp_attached_file', true ) );

		if ( 0 !== strpos( wp_normalize_path( $stored ), self::PROTECTED_PREFIX ) ) {
			return;
		}

		$path = $this->get_physical_path( $stored );

		if ( ! $path ) {
			return;
		}

		$dir   = dirname( $path );
		$files = [ basename( $path ) ];

		/** @var array<string,mixed>|false $metadata */
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

		foreach ( array_unique( $files ) as $file ) {
			$target = $dir . '/' . basename( $file );

			// Never step outside the directory this plugin owns, whatever the metadata says.
			if ( $this->is_inside( $target, $dir ) && file_exists( $target ) ) {
				wp_delete_file( $target );
			}
		}
	}

	/**
	 * Moves a single file, tolerating an already-moved source and falling back
	 * to copy+unlink across volumes.
	 *
	 * @param string $from Source path.
	 * @param string $to Destination path.
	 * @return bool
	 */
	protected function move_file( $from, $to ) {
		if ( $from === $to ) {
			return true;
		}

		if ( ! file_exists( $from ) ) {
			// Treat an already-relocated file as success.
			return file_exists( $to );
		}

		if ( @rename( $from, $to ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- direct move of a local upload; falls back to copy+unlink across volumes.
			return true;
		}

		if ( @copy( $from, $to ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			wp_delete_file( $from );

			return true;
		}

		return false;
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

		// Owners and editors always have access. The user ID must be non-zero,
		// so a logged-out visitor never matches an orphaned (author 0) folder.
		$user_id = get_current_user_id();

		if ( ( $user_id && $user_id === $folder->get_user__id() ) || current_user_can( 'edit_others_posts' ) ) {
			return true;
		}

		$visibility = $this->get_effective_visibility( $folder );

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

		/*
		 * Guard against path traversal: the file must live somewhere this plugin owns. Both roots
		 * are allowed because a protected file now sits outside the uploads folder, which is the
		 * whole point of protecting it -- checking only uploads would refuse to serve exactly the
		 * files this route exists for.
		 */
		$upload_dir = wp_get_upload_dir();
		$real_path  = $path ? realpath( $path ) : false;

		$roots = [];

		foreach ( [ $upload_dir['basedir'], $this->get_protected_dir() ] as $root ) {
			$real_root = $root ? realpath( $root ) : false;

			if ( $real_root ) {
				$roots[] = $real_root;
			}
		}

		$allowed = false;

		foreach ( $roots as $root ) {
			if ( $real_path && 0 === strpos( $real_path, $root . DIRECTORY_SEPARATOR ) ) {
				$allowed = true;

				break;
			}
		}

		if ( ! $allowed || ! is_file( $real_path ) ) {
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
			if ( '' === $matches[1] && '' !== $matches[2] ) {

				// Suffix range: the final N bytes.
				$start = max( 0, $size - (int) $matches[2] );
				$end   = $size - 1;
			} else {
				if ( '' !== $matches[1] ) {
					$start = (int) $matches[1];
				}

				if ( '' !== $matches[2] ) {
					$end = (int) $matches[2];
				}
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
		$visibility = $this->get_effective_visibility( $folder );
		$allowed    = false;
		$vendor     = null;
		$user_id    = get_current_user_id();
		$owner      = ( $user_id && $user_id === $folder->get_user__id() ) || current_user_can( 'edit_others_posts' );

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

	/**
	 * Checks whether photo likes are switched on.
	 *
	 * @return bool
	 */
	public function are_likes_enabled() {
		return (bool) get_option( 'hp_gallery_enable_likes' );
	}

	/**
	 * Checks whether photo comments are switched on.
	 *
	 * @return bool
	 */
	public function are_comments_enabled() {
		return (bool) get_option( 'hp_gallery_enable_comments' );
	}

	/**
	 * Counts likes and comments for a set of photos in one query each.
	 *
	 * A folder page renders up to a few dozen photos, and asking for each
	 * photo's counts separately would mean two queries per tile. The counts are
	 * grouped in a single query per type instead, and cached for the request.
	 *
	 * @param array $photo_ids Attachment IDs.
	 * @param bool  $force Whether to bypass the per-request cache, needed after
	 *                     a like is added or removed in the same request.
	 * @return array Keyed by attachment ID, each `[ 'likes' => int, 'comments' => int ]`.
	 */
	public function get_engagement_counts( $photo_ids, $force = false ) {
		global $wpdb;

		static $cache = [];

		$photo_ids = array_filter( array_map( 'absint', (array) $photo_ids ) );
		$counts    = [];
		$missing   = [];

		foreach ( $photo_ids as $photo_id ) {
			if ( ! $force && isset( $cache[ $photo_id ] ) ) {
				$counts[ $photo_id ] = $cache[ $photo_id ];
			} else {
				$missing[] = $photo_id;

				$counts[ $photo_id ] = [
					'likes'    => 0,
					'comments' => 0,
				];
			}
		}

		if ( ! $missing ) {
			return $counts;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $missing ), '%d' ) );

		// Grouped counts for both types at once. Written directly because the
		// comment API has no grouped-count form, and asking per photo would be
		// one query per tile. `$placeholders` is a generated run of %d and every
		// value is bound by prepare(); the only interpolation is that run and
		// the table name.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- see above; generated placeholders only.
		$sql = "SELECT comment_post_ID, comment_type, COUNT(*) AS total FROM {$wpdb->comments} WHERE comment_approved = '1' AND comment_type IN ( 'hp_agl_like', 'hp_agl_comment' ) AND comment_post_ID IN ( {$placeholders} ) GROUP BY comment_post_ID, comment_type";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- aggregate with no API equivalent, cached per request below.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $missing ) );

		foreach ( (array) $rows as $row ) {
			$photo_id = absint( $row->comment_post_ID );
			$key      = 'hp_agl_like' === $row->comment_type ? 'likes' : 'comments';

			if ( isset( $counts[ $photo_id ] ) ) {
				$counts[ $photo_id ][ $key ] = absint( $row->total );
			}
		}

		foreach ( $missing as $photo_id ) {
			$cache[ $photo_id ] = $counts[ $photo_id ];
		}

		return $counts;
	}

	/**
	 * Gets the photo IDs the current user has liked, out of a given set.
	 *
	 * @param array $photo_ids Attachment IDs.
	 * @return array Attachment IDs.
	 */
	public function get_liked_photo_ids( $photo_ids ) {
		$photo_ids = array_filter( array_map( 'absint', (array) $photo_ids ) );
		$user_id   = get_current_user_id();

		if ( ! $user_id || ! $photo_ids ) {
			return [];
		}

		$likes = get_comments(
			[
				'type'     => 'hp_agl_like',
				'user_id'  => $user_id,
				'post__in' => $photo_ids,
				'status'   => 'approve',
				'fields'   => 'ids',
				'number'   => 0,
				'orderby'  => 'comment_ID',
			]
		);

		$liked = [];

		foreach ( (array) $likes as $like_id ) {
			$liked[] = absint( get_comment( $like_id )->comment_post_ID );
		}

		return array_values( array_unique( $liked ) );
	}

	/**
	 * Gets the top-level comments on a photo, oldest first.
	 *
	 * @param int $photo_id Attachment ID.
	 * @return array Comment objects.
	 */
	public function get_photo_comments( $photo_id ) {
		return get_comments(
			[
				'type'    => 'hp_agl_comment',
				'post_id' => absint( $photo_id ),
				'status'  => 'approve',
				'parent'  => 0,
				'orderby' => 'comment_date_gmt',
				'order'   => 'ASC',
				'number'  => 100,
			]
		);
	}

	/**
	 * Gets the replies to a photo's comments, grouped by parent.
	 *
	 * One query for the whole thread rather than one per comment.
	 *
	 * @param int $photo_id Attachment ID.
	 * @return array Reply comment objects keyed by parent comment ID.
	 */
	public function get_photo_comment_replies( $photo_id ) {
		$replies = get_comments(
			[
				'type'           => 'hp_agl_comment',
				'post_id'        => absint( $photo_id ),
				'status'         => 'approve',
				'parent__not_in' => [ 0 ],
				'orderby'        => 'comment_date_gmt',
				'order'          => 'ASC',
				'number'         => 500,
			]
		);

		$grouped = [];

		foreach ( (array) $replies as $reply ) {
			$grouped[ absint( $reply->comment_parent ) ][] = $reply;
		}

		return $grouped;
	}

	/**
	 * Gets like counts for a photo's comments, plus which of them the current
	 * user has liked, in one query.
	 *
	 * Comment likes are `hp_agl_clike` rows whose `comment_parent` is the liked
	 * comment and whose `comment_post_ID` is the photo, so one person can like
	 * each comment once and everything for a thread loads together.
	 *
	 * @param int $photo_id Attachment ID.
	 * @return array `counts` keyed by comment ID, and `liked` comment IDs.
	 */
	public function get_comment_like_data( $photo_id ) {
		$data = [
			'counts' => [],
			'liked'  => [],
		];

		$likes = get_comments(
			[
				'type'    => 'hp_agl_clike',
				'post_id' => absint( $photo_id ),
				'status'  => 'approve',
				'number'  => 0,
			]
		);

		$user_id = get_current_user_id();

		foreach ( (array) $likes as $like ) {
			$parent = absint( $like->comment_parent );

			$data['counts'][ $parent ] = isset( $data['counts'][ $parent ] ) ? $data['counts'][ $parent ] + 1 : 1;

			if ( $user_id && absint( $like->user_id ) === $user_id ) {
				$data['liked'][] = $parent;
			}
		}

		return $data;
	}

	/**
	 * Checks whether the current user may delete a photo comment.
	 *
	 * The comment's author may remove their own, the owner of the folder the
	 * photo belongs to may remove any comment on their own photos, and
	 * moderators may remove anything.
	 *
	 * @param \WP_Comment $comment Comment object.
	 * @return bool
	 */
	public function can_delete_photo_comment( $comment ) {
		if ( ! $comment instanceof \WP_Comment ) {
			return false;
		}

		if ( current_user_can( 'moderate_comments' ) ) {
			return true;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		if ( absint( $comment->user_id ) === $user_id ) {
			return true;
		}

		// Fall back to folder ownership.
		$folder_id = wp_get_post_parent_id( absint( $comment->comment_post_ID ) );

		if ( ! $folder_id ) {
			return false;
		}

		return absint( get_post_field( 'post_author', $folder_id ) ) === $user_id;
	}

	/**
	 * Removes a deleted photo's likes, comments, replies and comment likes.
	 *
	 * WordPress deletes a post's comments with the post, but an attachment is
	 * deleted through `wp_delete_attachment()`, which does not, so the rows
	 * would otherwise be orphaned.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function delete_photo_engagement( $attachment_id ) {
		/*
		 * 'status' => 'any' is DELIBERATE here and must stay. In WP_Comment_Query it drops the
		 * status clause altogether (class-wp-comment-query.php:568-591), which is what makes a
		 * trashed or spammed row visible - and a deletion sweep that cannot see the trash leaves
		 * exactly the orphans this method exists to prevent. It is the opposite of the reading
		 * bug fixed in Notifications 1.3.4, where 'any' let the trash into a LOOKUP. Rule of
		 * thumb: deleting wants 'any', reading never does.
		 */
		$comments = get_comments(
			[
				'post_id'  => absint( $attachment_id ),
				'type__in' => [ 'hp_agl_like', 'hp_agl_comment', 'hp_agl_clike' ],
				'status'   => 'any',
				'fields'   => 'ids',
				'number'   => 0,
			]
		);

		foreach ( (array) $comments as $comment_id ) {
			wp_delete_comment( absint( $comment_id ), true );
		}
	}

	/**
	 * Removes a comment's replies and likes along with it.
	 *
	 * `wp_delete_comment()` re-parents children instead of deleting them, which
	 * would turn replies into top-level comments and leave like rows pointing at
	 * nothing.
	 *
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	public function delete_comment_children( $comment_id ) {

		// 'status' => 'any' is deliberate, for the same reason as delete_photo_engagement()
		// above: a deletion sweep has to see trashed rows or it leaves them orphaned.
		$children = get_comments(
			[
				'type__in' => [ 'hp_agl_comment', 'hp_agl_clike' ],
				'parent'   => absint( $comment_id ),
				'status'   => 'any',
				'fields'   => 'ids',
				'number'   => 0,
			]
		);

		foreach ( (array) $children as $child_id ) {
			wp_delete_comment( absint( $child_id ), true );
		}
	}

	/**
	 * Gets the public URL of a photo's own page.
	 *
	 * @param \HivePress\Models\Gallery_Folder $folder Folder object.
	 * @param int                              $photo_id Attachment ID.
	 * @return string
	 */
	public function get_photo_url( $folder, $photo_id ) {
		return hivepress()->router->get_url(
			'gallery_photo_view_page',
			[
				'vendor_id'         => $folder->get_vendor__id(),
				'gallery_folder_id' => $folder->get_id(),
				'attachment_id'     => absint( $photo_id ),
			]
		);
	}

	/**
	 * Gets the previous and next photo IDs within a folder's own order.
	 *
	 * @param \HivePress\Models\Gallery_Folder $folder Folder object.
	 * @param int                              $photo_id Attachment ID.
	 * @return array `previous` and `next` attachment IDs, either possibly null.
	 */
	public function get_photo_siblings( $folder, $photo_id ) {
		$ids      = array_map( 'absint', (array) $folder->get_images__id() );
		$position = array_search( absint( $photo_id ), $ids, true );

		return [
			'previous' => false !== $position && $position > 0 ? $ids[ $position - 1 ] : null,
			'next'     => false !== $position && $position < count( $ids ) - 1 ? $ids[ $position + 1 ] : null,
		];
	}

	/**
	 * Gets a photo's display title: its own title when it has a real one, else
	 * its position in the folder.
	 *
	 * WordPress titles attachments after the file name, and protected uploads
	 * get randomised names, so a title that still looks like a file name is not
	 * worth showing.
	 *
	 * @param \HivePress\Models\Gallery_Folder $folder Folder object.
	 * @param int                              $photo_id Attachment ID.
	 * @return string
	 */
	public function get_photo_title( $folder, $photo_id ) {
		$title = trim( (string) get_the_title( $photo_id ) );
		$file  = pathinfo( (string) get_post_meta( absint( $photo_id ), '_wp_attached_file', true ), PATHINFO_FILENAME );

		if ( $title && sanitize_title( $title ) !== sanitize_title( $file ) ) {
			return $title;
		}

		$ids      = array_map( 'absint', (array) $folder->get_images__id() );
		$position = array_search( absint( $photo_id ), $ids, true );

		/* translators: 1: photo number, 2: folder name. */
		return sprintf( esc_html__( 'Photo %1$s in %2$s', 'additional-gallery-for-hivepress' ), number_format_i18n( false !== $position ? $position + 1 : 1 ), $folder->get_title() );
	}

	/**
	 * Renders the like and comment bar for one photo.
	 *
	 * @param \HivePress\Models\Gallery_Folder $folder Folder object.
	 * @param int                              $photo_id Attachment ID.
	 * @param array                            $counts Counts for this photo.
	 * @param bool                             $liked Whether the current user has liked it.
	 * @return string
	 */
	public function render_photo_actions( $folder, $photo_id, $counts, $liked ) {
		$likes    = $this->are_likes_enabled();
		$comments = $this->are_comments_enabled();

		if ( ! $likes && ! $comments ) {
			return '';
		}

		$output = '<span class="hp-agl-actions">';

		if ( $likes ) {
			$label = $liked ? esc_html__( 'Remove your like', 'additional-gallery-for-hivepress' ) : esc_html__( 'Like this photo', 'additional-gallery-for-hivepress' );

			$output .= '<button type="button" class="hp-agl-action hp-agl-action--like' . ( $liked ? ' is-liked' : '' ) . '"'
				. ' data-agl-like="' . esc_attr( (string) $photo_id ) . '"'
				. ' aria-pressed="' . ( $liked ? 'true' : 'false' ) . '"'
				. ' title="' . esc_attr( $label ) . '">'
				. '<i class="hp-icon fas fa-heart"></i>'
				. '<span class="hp-agl-action__count">' . esc_html( number_format_i18n( hp_agl_int( hp\get_array_value( $counts, 'likes' ) ) ) ) . '</span>'
				. '</button>';
		}

		if ( $comments ) {

			// The count links to the photo's own page, where the conversation
			// lives.
			$output .= '<a href="' . esc_url( $this->get_photo_url( $folder, $photo_id ) . '#hp-agl-comments' ) . '" class="hp-agl-action hp-agl-action--comment hp-link"'
				. ' title="' . esc_attr__( 'Comments on this photo', 'additional-gallery-for-hivepress' ) . '">'
				. '<i class="hp-icon fas fa-comment"></i>'

				// Named after the photo so the script can find this exact badge. Posting a comment
				// used to leave it reading zero while the heading below already said "Comments (1)",
				// because the badge sits outside the comments section the script was updating.
				. '<span class="hp-agl-action__count" data-agl-comment-count="' . esc_attr( (string) $photo_id ) . '">' . esc_html( number_format_i18n( hp_agl_int( hp\get_array_value( $counts, 'comments' ) ) ) ) . '</span>'
				. '</a>';
		}

		$output .= '</span>';

		return $output;
	}

	/**
	 * Renders one comment (or reply) in the thread.
	 *
	 * The markup mirrors the Reviews extension's card: avatar, author, date,
	 * text, then an action row.
	 *
	 * @param \WP_Comment $comment Comment object.
	 * @param array       $like_data Comment like counts and liked IDs.
	 * @param bool        $is_reply Whether this is a nested reply.
	 * @return string
	 */
	protected function render_photo_comment( $comment, $like_data, $is_reply = false ) {
		$comment_id = absint( $comment->comment_ID );
		$like_count = isset( $like_data['counts'][ $comment_id ] ) ? hp_agl_int( $like_data['counts'][ $comment_id ] ) : 0;
		$liked      = in_array( $comment_id, $like_data['liked'], true );

		/*
		 * The id makes every comment a link target, so a "reply to your comment" notification can
		 * land the reader ON the comment rather than at the top of the photo page. The sticky
		 * header's scroll-padding-top (set by the Notifications plugin exactly for fragment jumps
		 * like this) keeps the target clear of the pinned header, the same as review deep links.
		 */
		$output = '<article id="agl-comment-' . esc_attr( (string) $comment_id ) . '" class="hp-agl-comment' . ( $is_reply ? ' hp-agl-comment--reply' : '' ) . '" data-agl-comment-id="' . esc_attr( (string) $comment_id ) . '">';

		$output .= '<div class="hp-agl-comment__image">' . get_avatar( absint( $comment->user_id ), 48 ) . '</div>';

		$output .= '<div class="hp-agl-comment__body">';
		$output .= '<div class="hp-agl-comment__header">';
		$output .= '<strong class="hp-agl-comment__author">' . esc_html( $comment->comment_author ) . '</strong>';

		// date_i18n(), not wp_date(): the stored date is already on the site's
		// clock, so wp_date() would add the offset a second time.
		$output .= '<time class="hp-agl-comment__date hp-meta" datetime="' . esc_attr( $comment->comment_date ) . '">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $comment->comment_date ) ) ) . '</time>';
		$output .= '</div>';

		$output .= '<div class="hp-agl-comment__text">' . esc_html( $comment->comment_content ) . '</div>';

		$output .= '<div class="hp-agl-comment__actions">';

		if ( $this->are_likes_enabled() ) {
			$like_label = $liked ? esc_html__( 'Remove your like', 'additional-gallery-for-hivepress' ) : esc_html__( 'Like this comment', 'additional-gallery-for-hivepress' );

			$output .= '<button type="button" class="hp-agl-action hp-agl-action--like' . ( $liked ? ' is-liked' : '' ) . '" data-agl-clike="' . esc_attr( (string) $comment_id ) . '" aria-pressed="' . ( $liked ? 'true' : 'false' ) . '" title="' . esc_attr( $like_label ) . '">'
				. '<i class="hp-icon fas fa-heart"></i>'
				. '<span class="hp-agl-action__count">' . esc_html( number_format_i18n( $like_count ) ) . '</span>'
				. '</button>';
		}

		if ( ! $is_reply && is_user_logged_in() ) {
			$output .= '<button type="button" class="hp-agl-action hp-link" data-agl-reply="' . esc_attr( (string) $comment_id ) . '"><i class="hp-icon fas fa-share"></i><span>' . esc_html__( 'Reply', 'additional-gallery-for-hivepress' ) . '</span></button>';
		}

		if ( $this->can_delete_photo_comment( $comment ) ) {
			$output .= '<button type="button" class="hp-agl-action hp-link" data-agl-comment-delete="' . esc_attr( (string) $comment_id ) . '"><i class="hp-icon fas fa-times"></i><span>' . esc_html__( 'Delete', 'additional-gallery-for-hivepress' ) . '</span></button>';
		}

		$output .= '</div>';
		$output .= '</div>';
		$output .= '</article>';

		return $output;
	}

	/**
	 * Renders the full comment thread for a photo.
	 *
	 * @param int $photo_id Attachment ID.
	 * @return string
	 */
	public function render_photo_comment_thread( $photo_id ) {
		$photo_id  = absint( $photo_id );
		$comments  = $this->get_photo_comments( $photo_id );
		$replies   = $this->get_photo_comment_replies( $photo_id );
		$like_data = $this->get_comment_like_data( $photo_id );

		if ( ! $comments ) {
			return '<p class="hp-agl-comments__empty hp-meta">' . esc_html__( 'No comments yet. Be the first!', 'additional-gallery-for-hivepress' ) . '</p>';
		}

		$output = '';

		foreach ( $comments as $comment ) {
			$output .= $this->render_photo_comment( $comment, $like_data );

			$comment_id = absint( $comment->comment_ID );

			if ( ! empty( $replies[ $comment_id ] ) ) {
				$output .= '<div class="hp-agl-comment__replies">';

				foreach ( $replies[ $comment_id ] as $reply ) {
					$output .= $this->render_photo_comment( $reply, $like_data, true );
				}

				$output .= '</div>';
			}
		}

		return $output;
	}

	/**
	 * Renders the inline comment form for a photo page.
	 *
	 * @param int $photo_id Attachment ID.
	 * @return string
	 */
	public function render_photo_comment_form( $photo_id ) {
		if ( ! is_user_logged_in() ) {
			$login_url = hivepress()->router->get_return_url( 'user_login_page' );

			return '<p class="hp-agl-comments__signin hp-meta"><a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Sign in to join the conversation', 'additional-gallery-for-hivepress' ) . '</a></p>';
		}

		$output  = '<form class="hp-form hp-agl-comments__form" data-agl-comment-form="' . esc_attr( (string) absint( $photo_id ) ) . '">';
		$output .= '<div class="hp-agl-comments__composer">';
		$output .= '<div class="hp-agl-comment__image">' . get_avatar( get_current_user_id(), 48 ) . '</div>';
		$output .= '<div class="hp-form__field hp-form__field--textarea">';
		$output .= '<textarea name="text" class="hp-field hp-field--textarea" rows="2" maxlength="1000" required placeholder="' . esc_attr__( 'Share your thoughts on this photo...', 'additional-gallery-for-hivepress' ) . '"></textarea>';
		$output .= '</div>';
		$output .= '</div>';
		$output .= '<div class="hp-form__footer">';
		$output .= '<button type="submit" class="hp-form__button button button--primary alt"><span>' . esc_html__( 'Post Comment', 'additional-gallery-for-hivepress' ) . '</span></button>';
		$output .= '</div>';
		$output .= '<div class="hp-form__messages" data-agl-comment-message></div>';
		$output .= '</form>';

		return $output;
	}

	/**
	 * Gets the site's cut of a gallery access sale.
	 *
	 * Shaped like HivePress's own commission settings: a percentage, a flat amount, or both. Both
	 * boxes empty means no commission, and this returns null so every caller can leave well alone.
	 *
	 * Every caller reads the commission through here, so the filter below is the one place a
	 * payment gateway can switch it off. It has to exist because the whole feature rests on an
	 * assumption the site owner cannot see: that the order is charged to the SITE's payment
	 * account, which is what makes a "Platform fee" line the site's money. A gateway that charges
	 * each vendor's own connected account directly reverses that. The buyer is charged the price
	 * plus the fee, the whole amount is created on the vendor's account, and the site collects
	 * nothing but whatever cut the gateway itself takes - so the fee is paid to the vendor while
	 * the buyer is told it is the platform's. Measured on a 100.00 sale at 10%: buyer charged
	 * 110.00, all 110.00 on the vendor's account. A gateway of that shape must return null here.
	 *
	 * @return array|null `rate` percentage and `fee` amount.
	 */
	public function get_commission() {
		$rate = round( floatval( get_option( 'hp_gallery_commission_rate' ) ), 2 );
		$fee  = round( floatval( get_option( 'hp_gallery_commission_fee' ) ), 2 );

		$commission = null;

		if ( $rate > 0 || $fee > 0 ) {
			$commission = [
				'rate' => min( 100, max( 0, $rate ) ),
				'fee'  => max( 0, $fee ),
			];
		}

		/**
		 * Filters the site's cut of a gallery access sale.
		 *
		 * Return null to take no commission at all. A payment gateway that charges each vendor's
		 * own account directly must do so: under that model the site never receives the fee it
		 * would be charging the buyer for.
		 *
		 * @hook hp_agl/commission
		 * @param {array|null} $commission Commission with `rate` percentage and `fee` amount, or null for none.
		 * @return {array|null} Commission.
		 */
		$commission = apply_filters( 'hp_agl/commission', $commission );

		if ( ! is_array( $commission ) ) {
			return null;
		}

		$rate = round( floatval( hp\get_array_value( $commission, 'rate' ) ), 2 );
		$fee  = round( floatval( hp\get_array_value( $commission, 'fee' ) ), 2 );

		if ( $rate <= 0 && $fee <= 0 ) {
			return null;
		}

		return [
			'rate' => min( 100, max( 0, $rate ) ),
			'fee'  => max( 0, $fee ),
		];
	}

	/**
	 * Sends somebody who has just added gallery access straight to the checkout.
	 *
	 * The unlock button links to `?add-to-cart=N` on the checkout URL, which reads as though it
	 * settles where the buyer ends up. It does not. WooCommerce adds the product and then decides the
	 * destination for itself, and on a default site that is not the checkout - the buyer is bounced
	 * back to wherever WooCommerce prefers, with the pass sitting in a basket they were never shown.
	 * They see a page they did not ask for, no confirmation, and no charge, so the reasonable
	 * conclusion is that the button is broken.
	 *
	 * This went unnoticed for a long time because the site it was built against runs a third-party
	 * "direct checkout" plugin that forces the redirect globally. That plugin was doing the work, and
	 * every site without one got the bounce. Turning it off on staging is what exposed it.
	 *
	 * Only our own products are redirected, identified by the marker written when the product is
	 * created. A basket that also holds somebody else's goods is left to whatever that seller's own
	 * flow wants; this decides nothing on their behalf.
	 *
	 * @param string               $url Redirect URL chosen so far.
	 * @param \WC_Product|int|null $product The product being added. WooCommerce passes an OBJECT.
	 * @return string
	 */
	public function redirect_access_purchase_to_checkout( $url, $product = null ) {
		if ( ! function_exists( 'wc_get_checkout_url' ) ) {
			return $url;
		}

		/*
		 * The second argument is a WC_Product, not an ID.
		 *
		 * `WC_Form_Handler::add_to_cart_action()` builds it with `wc_get_product( $product_id )` and
		 * passes that object straight into the filter (class-wc-form-handler.php:856 and :889,
		 * verified). Every other add-to-cart filter nearby hands over an ID, so an ID is the natural
		 * assumption - and a wrong one. `absint()` on an object yields 1, which quietly looked up
		 * post 1, found no marker, and returned early: the redirect simply never happened and there
		 * was nothing in the logs to say why. An ID is still accepted, because a plugin re-applying
		 * the filter by hand may well pass one.
		 */
		if ( $product instanceof \WC_Product ) {
			$product_id = absint( $product->get_id() );
		} else {
			$product_id = is_scalar( $product ) ? absint( $product ) : 0;
		}

		// Without a product there is nothing to identify, and guessing from the basket would redirect
		// somebody who was adding something else entirely.
		if ( ! $product_id || ! get_post_meta( $product_id, 'hp_agl_vendor', true ) ) {
			return $url;
		}

		// Anything already set by something else is left alone: an earlier filter is a deliberate
		// choice by the site, and a checkout redirect is not worth overruling it for.
		if ( $url ) {
			return $url;
		}

		return wc_get_checkout_url();
	}

	/**
	 * Says whether a set of cart lines holds gallery access alongside another seller's goods.
	 *
	 * The same author test the add-time guard applies, but over a finished basket rather than one
	 * incoming item, so it can judge a cart however it was assembled - added normally, merged on
	 * login, or rebuilt by order-again.
	 *
	 * @param array $items Cart contents, as WC_Cart::get_cart() returns them.
	 * @return bool
	 */
	protected function cart_holds_mixed_sellers( $items ) {
		$has_access = false;
		$authors    = [];

		foreach ( (array) $items as $item ) {
			$product_id = absint( hp\get_array_value( (array) $item, 'product_id' ) );

			if ( ! $product_id ) {
				continue;
			}

			if ( get_post_meta( $product_id, 'hp_agl_vendor', true ) ) {
				$has_access = true;
			}

			$authors[ absint( get_post_field( 'post_author', $product_id ) ) ] = true;
		}

		return $has_access && count( $authors ) > 1;
	}

	/**
	 * The message shown when a basket mixes gallery access with another seller's goods.
	 *
	 * One string, used by all three enforcement layers, so the buyer reads the same explanation
	 * whichever door they came through.
	 *
	 * @return string
	 */
	protected function get_single_seller_message() {
		return esc_html__( 'Gallery access has to be bought on its own, because the payment goes to the person whose gallery it is. Please check out or empty your basket first, then add it again.', 'additional-gallery-for-hivepress' );
	}

	/**
	 * Blocks checkout while the basket mixes gallery access with another seller's goods.
	 *
	 * The add-time guard cannot be the only layer. WooCommerce also builds carts by MERGING, and
	 * neither merge path validates: logging in merges the saved cart into the session cart
	 * (class-wc-cart-session.php:118-121 - a buyer who abandoned vendor A's pass, added vendor B's
	 * as a guest and then signed in at checkout ends up with both, vendor A's line first), and
	 * order-again re-validates each line against a cart the guard sees as empty, because the
	 * rebuilt lines accumulate in a local variable until set_cart_contents() runs at the end
	 * (class-wc-cart-session.php:615 firing before :264). Marketplace would then pay the whole
	 * order to the first line's vendor. This hook fires on the cart page, the checkout page and
	 * inside WC_Checkout::process_checkout(), so however the basket was put together, it cannot
	 * reach payment mixed.
	 *
	 * Refusing, not repairing: silently deleting a line the buyer may have paid attention to is
	 * worse than telling them why they must choose.
	 *
	 * @return void
	 */
	public function enforce_single_seller_cart() {
		if ( ! class_exists( '\HivePress\Components\Marketplace' ) || ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
			return;
		}

		if ( ! $this->cart_holds_mixed_sellers( WC()->cart->get_cart() ) ) {
			return;
		}

		$message = $this->get_single_seller_message();

		// The hook fires on both the cart and checkout render, so guard against saying it twice.
		if ( function_exists( 'wc_add_notice' ) && ( ! function_exists( 'wc_has_notice' ) || ! wc_has_notice( $message, 'error' ) ) ) {
			wc_add_notice( $message, 'error' );
		}
	}

	/**
	 * The same backstop for the blocks checkout, which never runs `woocommerce_check_cart_items`.
	 *
	 * CartController::validate_cart() passes a WP_Error for hooks to fill and turns any entry into
	 * an InvalidCartException, which the block renders against the basket.
	 *
	 * @param \WP_Error $errors Errors to add to.
	 * @param \WC_Cart  $cart Cart object.
	 * @return void
	 */
	public function enforce_single_seller_cart_blocks( $errors, $cart ) {
		if ( ! class_exists( '\HivePress\Components\Marketplace' ) || ! $errors instanceof \WP_Error || ! $cart instanceof \WC_Cart ) {
			return;
		}

		if ( $this->cart_holds_mixed_sellers( $cart->get_cart() ) ) {
			$errors->add( 'hp_agl_mixed_sellers', $this->get_single_seller_message() );
		}
	}

	/**
	 * Says whether a signed-out visitor's cart holds gallery access.
	 *
	 * Always false for a signed-in buyer: the rule only exists because access has nowhere to go
	 * without an account, so the moment there is one, the cart is somebody else's business.
	 *
	 * @param array $items Cart contents, as WC_Cart::get_cart() returns them.
	 * @return bool
	 */
	protected function cart_holds_guest_access( $items ) {
		if ( is_user_logged_in() ) {
			return false;
		}

		foreach ( (array) $items as $item ) {
			$product_id = absint( hp\get_array_value( (array) $item, 'product_id' ) );

			if ( $product_id && get_post_meta( $product_id, 'hp_agl_vendor', true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The message shown when a signed-out visitor tries to buy gallery access.
	 *
	 * One string for all three enforcement layers, matching how the single-seller rule speaks.
	 *
	 * @return string
	 */
	protected function get_signed_in_access_message() {
		return esc_html__( 'Gallery access has to be bought while signed in, because it is added to your account. Please sign in or register first, then buy it again.', 'additional-gallery-for-hivepress' );
	}

	/**
	 * Refuses to add gallery access to a signed-out visitor's cart.
	 *
	 * Access is granted to the order's customer account and to nothing else: grant_paid_access()
	 * reads `$order->get_customer_id()` and returns when it is 0, deliberately, because there is
	 * no guest identity worth trusting a grant to. A guest order therefore pays and receives
	 * nothing, with no trace anywhere - so the purchase must be refused up front, not repaired
	 * after the money moved. Whether guest checkout is even on is not consulted: an owner can
	 * switch it on at any time, and this product simply cannot be sold to a guest either way.
	 *
	 * @param bool $passed Whether the item may be added.
	 * @param int  $product_id Product being added.
	 * @return bool
	 */
	public function validate_signed_in_access_purchase( $passed, $product_id ) {
		if ( ! $passed || is_user_logged_in() ) {
			return $passed;
		}

		$product_id = absint( $product_id );

		if ( ! $product_id || ! get_post_meta( $product_id, 'hp_agl_vendor', true ) ) {
			return $passed;
		}

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $this->get_signed_in_access_message(), 'error' );
		}

		return false;
	}

	/**
	 * Blocks checkout while a signed-out visitor's basket holds gallery access.
	 *
	 * The whole-cart backstop behind validate_signed_in_access_purchase(), for the same reason the
	 * single-seller rule has one: WooCommerce also assembles carts without add-time validation
	 * (login-merge, order-again), and a cart persisted from before this rule existed reaches
	 * checkout without ever being validated again. Signing in clears the refusal by itself, which
	 * is exactly the resolution the message asks for.
	 *
	 * @return void
	 */
	public function enforce_signed_in_access_purchase() {
		if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
			return;
		}

		if ( ! $this->cart_holds_guest_access( WC()->cart->get_cart() ) ) {
			return;
		}

		$message = $this->get_signed_in_access_message();

		// The hook fires on both the cart and checkout render, so guard against saying it twice.
		if ( function_exists( 'wc_add_notice' ) && ( ! function_exists( 'wc_has_notice' ) || ! wc_has_notice( $message, 'error' ) ) ) {
			wc_add_notice( $message, 'error' );
		}
	}

	/**
	 * The same backstop for the blocks checkout, which never runs `woocommerce_check_cart_items`.
	 *
	 * @param \WP_Error $errors Errors to add to.
	 * @param \WC_Cart  $cart Cart object.
	 * @return void
	 */
	public function enforce_signed_in_access_purchase_blocks( $errors, $cart ) {
		if ( ! $errors instanceof \WP_Error || ! $cart instanceof \WC_Cart ) {
			return;
		}

		if ( $this->cart_holds_guest_access( $cart->get_cart() ) ) {
			$errors->add( 'hp_agl_guest_access', $this->get_signed_in_access_message() );
		}
	}

	/**
	 * Stops a basket holding gallery access alongside another seller's goods.
	 *
	 * HivePress Marketplace decides who earned an order by reading the post author of its FIRST line
	 * and nothing else (`Marketplace::create_order()`, which calls
	 * `hp\get_first_array_value( $order->get_items() )`). Every line after that is paid to whoever
	 * sold the first one. Marketplace never hits this itself because its own buy button calls
	 * `WC()->cart->empty_cart()` before adding, so one of its orders can only ever hold one seller.
	 *
	 * This plugin does not empty the basket, and deliberately so: a buyer is meant to be able to add
	 * 7 days and then 90 days from the same vendor and pay once. That is safe, because both lines
	 * have the same author. Two DIFFERENT vendors in one basket is not safe, and it was verified
	 * rather than assumed - an order holding one product from each of two vendors was put through
	 * `woocommerce_new_order` and the whole 21.00 was credited to the first vendor, with the second
	 * receiving nothing while the buyer was correctly granted access to both galleries.
	 *
	 * So the basket is held to one author whenever gallery access is involved, which is the same
	 * guarantee Marketplace gives itself, arrived at by refusing the addition rather than by silently
	 * throwing away what the buyer had already chosen.
	 *
	 * Nothing is enforced when Marketplace is inactive. Without it there are no vendor earnings to
	 * misroute - every payment belongs to the site - and refusing a mixed basket would be a
	 * restriction with no purpose behind it.
	 *
	 * @param bool $passed Whether the item may be added.
	 * @param int  $product_id Product being added.
	 * @return bool
	 */
	public function validate_single_seller_cart( $passed, $product_id ) {
		/*
		 * Note the class check rather than `hp\is_plugin_active()`. That helper takes a CLASS or
		 * FUNCTION name and simply calls `class_exists()` on it, despite reading like a slug test -
		 * `is_plugin_active( 'woocommerce' )` only works because PHP class names are case-insensitive
		 * and WooCommerce's class happens to be `WooCommerce`. There is no class called `marketplace`,
		 * so the slug spelling returned false, this guard never ran, and a two-vendor basket sailed
		 * through. Name the class.
		 */
		if ( ! $passed || ! class_exists( '\HivePress\Components\Marketplace' ) || ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
			return $passed;
		}

		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return $passed;
		}

		$incoming_is_access = (bool) get_post_meta( $product_id, 'hp_agl_vendor', true );
		$incoming_author    = absint( get_post_field( 'post_author', $product_id ) );

		foreach ( WC()->cart->get_cart() as $item ) {
			$existing_id = absint( hp\get_array_value( $item, 'product_id' ) );

			if ( ! $existing_id || $existing_id === $product_id ) {
				continue;
			}

			// Only step in where gallery access is one of the two. A basket of somebody else's
			// products is somebody else's business, and their plugin may well handle it already.
			if ( ! $incoming_is_access && ! get_post_meta( $existing_id, 'hp_agl_vendor', true ) ) {
				continue;
			}

			if ( absint( get_post_field( 'post_author', $existing_id ) ) === $incoming_author ) {
				continue;
			}

			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( $this->get_single_seller_message(), 'error' );
			}

			return false;
		}

		return $passed;
	}

	/**
	 * Works out the commission due on the gallery access lines of a cart.
	 *
	 * Only those lines count. A cart holding a booking as well must not have the site's gallery cut
	 * charged on the booking too, and the flat part is charged once for the cart rather than once
	 * per line, which is how HivePress charges its own.
	 *
	 * @param \WC_Cart $cart Cart object.
	 * @return array Vendor IDs keyed by cart item key, plus a `total` amount.
	 */
	protected function get_cart_commission( $cart ) {
		$empty = [
			'total' => 0.0,
			'items' => [],
		];

		$commission = $this->get_commission();

		if ( ! $commission || ! $this->is_paid_access_enabled() ) {
			return $empty;
		}

		$subtotal = 0.0;
		$items    = [];

		foreach ( $cart->get_cart() as $item_key => $item ) {
			$product_id = hp_agl_int( hp\get_array_value( $item, 'product_id' ) );
			$vendor_id  = $product_id ? absint( get_post_meta( $product_id, 'hp_agl_vendor', true ) ) : 0;

			if ( ! $vendor_id ) {
				continue;
			}

			$items[ $item_key ] = $vendor_id;

			// line_total is already worked out by the time fees are calculated; the product price
			// is the fallback for any caller that gets here earlier.
			$line = hp\get_array_value( $item, 'line_total' );

			if ( is_null( $line ) && isset( $item['data'] ) && is_object( $item['data'] ) ) {
				$line = floatval( $item['data']->get_price() ) * max( 1, hp_agl_int( hp\get_array_value( $item, 'quantity' ) ) );
			}

			$subtotal += floatval( $line );
		}

		if ( ! $items || $subtotal <= 0 ) {
			return $empty;
		}

		$total = $subtotal * ( $commission['rate'] / 100 ) + $commission['fee'];

		return [
			'total' => round( max( 0, $total ), wc_get_price_decimals() ),
			'items' => $items,
		];
	}

	/**
	 * Adds the site's commission to the cart as its own line.
	 *
	 * @param \WC_Cart $cart Cart object.
	 * @return void
	 */
	public function add_commission_fee( $cart ) {
		if ( ! $cart instanceof \WC_Cart ) {
			return;
		}

		// Nothing configured is the default, and this hook runs on every cart calculation on the
		// site, so that case has to cost a single autoloaded option read and no more.
		if ( ! $this->get_commission() ) {
			return;
		}

		$commission = $this->get_cart_commission( $cart );

		if ( $commission['total'] <= 0 ) {
			return;
		}

		/**
		 * Filters the label shown against the site's gallery commission at checkout.
		 *
		 * @hook hp_agl/commission_label
		 * @param {string} $label Label.
		 * @return {string} Label.
		 */
		$label = apply_filters( 'hp_agl/commission_label', esc_html__( 'Platform fee', 'additional-gallery-for-hivepress' ) );

		/**
		 * Filters whether the site's gallery commission is taxable.
		 *
		 * @hook hp_agl/commission_taxable
		 * @param {bool} $taxable Taxable.
		 * @return {bool} Taxable.
		 */
		$taxable = (bool) apply_filters( 'hp_agl/commission_taxable', false );

		$cart->add_fee( $label, $commission['total'], $taxable );
	}

	/**
	 * Records the commission against the order line, so vendor earnings exclude it.
	 *
	 * HivePress Marketplace works a vendor's profit out from the order total, and the order total
	 * now includes the site's own fee. Marketplace already has a contract for exactly this, used by
	 * its own service fee: `hp_commission_fee` on the order item is subtracted from the vendor's
	 * share. Scaling by the vendor's share first is what makes the two cancel out exactly, rather
	 * than leaving the vendor a slice of the site's fee.
	 *
	 * Without Marketplace there are no vendor earnings to keep straight and nothing to record.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @param string                 $item_key Cart item key.
	 * @param array                  $values Cart item.
	 * @return void
	 */
	public function set_commission_item_meta( $item, $item_key, $values ) {
		$marketplace = hivepress()->marketplace;

		if ( ! $marketplace || ! method_exists( $marketplace, 'get_commission_rate' ) || ! function_exists( 'WC' ) ) {
			return;
		}

		if ( ! $this->get_commission() || ! WC()->cart ) {
			return;
		}

		$commission = $this->get_cart_commission( WC()->cart );

		if ( $commission['total'] <= 0 || ! $commission['items'] ) {
			return;
		}

		/*
		 * Charged once for the cart, so it is recorded against ONE line - and that line has to be
		 * the FIRST line of the whole cart, not the first gallery line. Marketplace's
		 * create_order() reads `hp_commission_fee` from the order's first item of any kind
		 * (class-marketplace.php:793, via get_first_array_value) and never looks further. A basket
		 * whose first line is the same vendor's Marketplace listing with the gallery pass second -
		 * which the single-seller rule deliberately allows - would otherwise have the fee stamped
		 * on a line nobody reads, and the fee would be paid out to the vendor as earnings instead
		 * of staying with the site.
		 */
		if ( array_key_first( WC()->cart->get_cart() ) !== $item_key ) {
			return;
		}

		$vendor = Models\Vendor::query()->get_by_id( hp\get_first_array_value( $commission['items'] ) );

		if ( ! $vendor ) {
			return;
		}

		$share = floatval( $marketplace->get_commission_rate( $vendor ) );
		$fee   = round( $commission['total'] * $share, 2 );

		if ( $fee > 0 ) {

			// Added to anything already recorded, never overwritten: if another integration has
			// stamped its own figure on this line, both cuts must survive.
			$fee += floatval( $item->get_meta( 'hp_commission_fee' ) );

			$item->update_meta_data( 'hp_commission_fee', round( $fee, 2 ) );
		}
	}

	/**
	 * Checks whether paid gallery access is available on this site.
	 *
	 * @return bool
	 */
	public function is_paid_access_enabled() {
		return (bool) get_option( 'hp_gallery_enable_paid_access' ) && class_exists( 'WooCommerce' );
	}

	/**
	 * How many lengths one vendor may sell at once.
	 *
	 * @var int
	 */
	const MAX_TIERS = 3;

	/**
	 * Adds the gallery section to a vendor profile, below their listings.
	 *
	 * Order 30 puts it after the listings container, which core places at 20
	 * (hivepress/includes/templates/class-vendor-view-page.php, verified).
	 *
	 * @param array $template Template arguments.
	 * @return array
	 */
	public function add_vendor_gallery_section( $template ) {
		if ( ! get_option( 'hp_gallery_show_on_vendors' ) ) {
			return $template;
		}

		return hp\merge_trees(
			$template,
			[
				'blocks' => [
					'page_content' => [
						'blocks' => [
							'agl_gallery_section' => [
								'type'   => 'section',
								'title'  => esc_html__( 'Gallery', 'additional-gallery-for-hivepress' ),
								'_order' => 30,

								'blocks' => [
									'agl_gallery_section_content' => [
										'type'   => 'agl_gallery_section',
										'_label' => esc_html__( 'Gallery', 'additional-gallery-for-hivepress' ),
										'_order' => 10,
									],
								],
							],
						],
					],
				],
			]
		);
	}

	/**
	 * Adds the gallery section to the foot of a listing.
	 *
	 * Order 85 lands it after the tags, which the Tags extension places at 70, and before the
	 * reviews, which the Reviews extension places at 100 - both verified in their own
	 * `alter_listing_view_page()`. Sitting between them means the section keeps its place whether
	 * either extension is active or not, without this plugin having to know which are installed.
	 *
	 * @param array $template Template arguments.
	 * @return array
	 */
	public function add_listing_gallery_section( $template ) {
		if ( ! get_option( 'hp_gallery_show_on_listings' ) ) {
			return $template;
		}

		return hp\merge_trees(
			$template,
			[
				'blocks' => [
					'page_content' => [
						'blocks' => [
							'agl_gallery_section' => [
								'type'   => 'section',
								'title'  => esc_html__( 'Gallery', 'additional-gallery-for-hivepress' ),
								'_order' => 85,

								'blocks' => [
									'agl_gallery_section_content' => [
										'type'   => 'agl_gallery_section',
										'_label' => esc_html__( 'Gallery', 'additional-gallery-for-hivepress' ),
										'_order' => 10,
									],
								],
							],
						],
					],
				],
			]
		);
	}

	/**
	 * Gets the lengths of access a vendor may choose from.
	 *
	 * A fixed list rather than a free number box. A vendor pricing their own work should be choosing
	 * between recognisable offers, not inventing 43 days, and a buyer comparing two vendors can only
	 * do so if they are offering comparable things. Zero is permanent access.
	 *
	 * @return array Days mapped to the wording shown to vendors and buyers.
	 */
	public function get_access_durations() {
		$durations = [
			1  => esc_html__( '1 day', 'additional-gallery-for-hivepress' ),
			7  => esc_html__( '7 days', 'additional-gallery-for-hivepress' ),
			30 => esc_html__( '30 days', 'additional-gallery-for-hivepress' ),
			90 => esc_html__( '90 days', 'additional-gallery-for-hivepress' ),
			0  => esc_html__( 'Permanent', 'additional-gallery-for-hivepress' ),
		];

		/**
		 * Filters the lengths of access a vendor may choose from.
		 *
		 * Keys are whole days and zero means permanent. A length removed here stays honoured for
		 * anybody who already bought it, and for any vendor already selling it, until that vendor
		 * next saves - taking an option away must never silently rewrite what somebody is selling.
		 *
		 * @hook hp_agl/access_durations
		 * @param {array} $durations Days mapped to their wording.
		 * @return {array} Days mapped to their wording.
		 */
		$durations = (array) apply_filters( 'hp_agl/access_durations', $durations );

		$clean = [];

		foreach ( $durations as $days => $label ) {
			$clean[ absint( $days ) ] = (string) $label;
		}

		return $clean;
	}

	/**
	 * Puts a length of access into words.
	 *
	 * @param int $days Days, 0 for permanent.
	 * @return string
	 */
	public function get_duration_label( $days ) {
		$days = absint( $days );

		$known = hp\get_array_value( $this->get_access_durations(), $days );

		if ( $known ) {
			return $known;
		}

		// A length an owner has since filtered away is still described properly for whoever is
		// already selling or holding it.
		if ( ! $days ) {
			return esc_html__( 'Permanent', 'additional-gallery-for-hivepress' );
		}

		/* translators: %s: number of days. */
		return sprintf( _n( '%s day', '%s days', $days, 'additional-gallery-for-hivepress' ), number_format_i18n( $days ) );
	}

	/**
	 * Gets the meta key holding the length a vendor sells in one slot.
	 *
	 * @param int $tier Slot number.
	 * @return string
	 */
	protected function get_days_meta_key( $tier ) {
		return 'hp_gallery_days' . ( $tier > 1 ? '_' . absint( $tier ) : '' );
	}

	/**
	 * Gets the length of access a vendor sells in one slot.
	 *
	 * Falls back to what the slot's product was stamped with, and then to the site-wide period that
	 * used to define it, so a vendor who priced a slot before vendors chose their own lengths keeps
	 * selling exactly what they were selling.
	 *
	 * @param int $vendor_id Vendor ID.
	 * @param int $tier Slot number.
	 * @return int Days, 0 for permanent.
	 */
	public function get_tier_days( $vendor_id, $tier ) {
		$stored = get_post_meta( absint( $vendor_id ), $this->get_days_meta_key( $tier ), true );

		if ( '' !== $stored && ! is_null( $stored ) ) {
			return absint( $stored );
		}

		$product_id = absint( get_post_meta( absint( $vendor_id ), $this->get_product_meta_key( $tier ), true ) );

		if ( $product_id ) {
			$stamped = get_post_meta( $product_id, 'hp_agl_period', true );

			if ( '' !== $stamped && ! is_null( $stamped ) ) {
				return absint( $stamped );
			}
		}

		/*
		 * Last resort: the site-wide Access Period boxes that used to live on the Gallery settings
		 * tab, before each vendor chose their own lengths. The boxes are gone from the settings
		 * screen and nothing writes these options any more, but a site that upgraded still HAS them,
		 * and they are the only record of what its vendors were selling. Reading them keeps those
		 * vendors selling the same lengths through the upgrade instead of silently dropping to zero.
		 *
		 * Do not delete this as dead code. It looks dead because no UI writes it; it is the upgrade
		 * path for every site that had paid access before per-vendor lengths existed.
		 */
		return absint( get_option( 'hp_gallery_access_period' . ( $tier > 1 ? '_' . absint( $tier ) : '' ) ) );
	}

	/**
	 * Gets the meta key holding a vendor's price for one access length.
	 *
	 * The first tier keeps the key it has always used, so no site's stored prices move.
	 *
	 * @param int $tier Tier number.
	 * @return string
	 */
	protected function get_price_meta_key( $tier ) {
		return 'hp_gallery_price' . ( $tier > 1 ? '_' . absint( $tier ) : '' );
	}

	/**
	 * Gets the meta key holding the product that sells one access length.
	 *
	 * @param int $tier Tier number.
	 * @return string
	 */
	protected function get_product_meta_key( $tier ) {
		return 'hp_gallery_product' . ( $tier > 1 ? '_' . absint( $tier ) : '' );
	}

	/**
	 * Gets a vendor's gallery access price.
	 *
	 * @param int $vendor_id Vendor ID.
	 * @param int $tier Access length to price, defaulting to the first.
	 * @return float Zero when unset.
	 */
	public function get_access_price( $vendor_id, $tier = 1 ) {
		$price = get_post_meta( absint( $vendor_id ), $this->get_price_meta_key( $tier ), true );

		return is_numeric( $price ) ? max( 0, (float) $price ) : 0.0;
	}

	/**
	 * Gets every length a vendor is actually selling.
	 *
	 * Shortest first, with permanent access last: it is the largest offer however small its number.
	 *
	 * @param int $vendor_id Vendor ID.
	 * @return array Slots with `tier`, `period`, `price` and `product` keys, in the order a buyer
	 *               should see them.
	 */
	public function get_priced_access_tiers( $vendor_id ) {
		$priced = [];

		for ( $tier = 1; $tier <= self::MAX_TIERS; $tier++ ) {
			$price      = $this->get_access_price( $vendor_id, $tier );
			$product_id = absint( get_post_meta( absint( $vendor_id ), $this->get_product_meta_key( $tier ), true ) );

			if ( $price && $product_id && 'publish' === get_post_status( $product_id ) ) {
				$priced[ $tier ] = [
					'tier'    => $tier,
					'period'  => $this->get_tier_days( $vendor_id, $tier ),
					'price'   => $price,
					'product' => $product_id,
				];
			}
		}

		uasort(
			$priced,
			function ( $a, $b ) {
				if ( ! $a['period'] || ! $b['period'] ) {
					return $a['period'] ? -1 : ( $b['period'] ? 1 : 0 );
				}

				return $a['period'] - $b['period'];
			}
		);

		return $priced;
	}

	/**
	 * Sets a vendor's gallery access price and keeps the linked WooCommerce
	 * product in step.
	 *
	 * The product is authored by the vendor's user on purpose: HivePress
	 * Marketplace resolves an order's vendor from the product author, so these
	 * sales flow into vendor earnings and commission with no extra wiring.
	 *
	 * Each access length is sold by its own product, so the length a buyer paid for is recorded on
	 * the thing they bought rather than read back from a site setting that may since have changed.
	 *
	 * @param \HivePress\Models\Vendor $vendor Vendor object.
	 * @param int                      $tier Slot number, 1 to MAX_TIERS.
	 * @param int                      $days Length in days, 0 for permanent.
	 * @param float                    $price Price, 0 to stop selling this length.
	 * @return bool
	 */
	public function set_access_tier( $vendor, $tier, $days, $price ) {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return false;
		}

		$vendor_id = $vendor->get_id();
		$price     = max( 0, (float) $price );
		$tier      = max( 1, absint( $tier ) );
		$period    = absint( $days );

		update_post_meta( $vendor_id, $this->get_price_meta_key( $tier ), $price ? wc_format_decimal( $price ) : '' );
		update_post_meta( $vendor_id, $this->get_days_meta_key( $tier ), $period );

		// Get or create the product.
		$product_id = absint( get_post_meta( $vendor_id, $this->get_product_meta_key( $tier ), true ) );
		$product    = $product_id ? wc_get_product( $product_id ) : null;

		if ( ! $product && ! $price ) {
			return true;
		}

		if ( ! $product ) {
			$product = new \WC_Product_Simple();
		}

		$product->set_name( $this->get_access_product_name( $vendor, $period ) );
		$product->set_regular_price( (string) $price );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_status( $price ? 'publish' : 'draft' );

		$product_id = $product->save();

		if ( ! $product_id ) {
			return false;
		}

		// Author = vendor user (Marketplace's vendor resolution), plus our own
		// marker for the grant handler.
		wp_update_post(
			[
				'ID'          => $product_id,
				'post_author' => $vendor->get_user__id(),
			]
		);

		update_post_meta( $product_id, 'hp_agl_vendor', $vendor_id );

		/*
		 * Also the standard `hp_vendor` product meta, which is how the wider HivePress payment
		 * ecosystem finds a product's vendor. A gallery access product has no listing behind it -
		 * it is a bare WooCommerce product with our own `hp_agl_vendor` marker - so any gateway
		 * that resolves the vendor by walking product -> listing -> vendor comes up empty and, if
		 * it is a per-vendor gateway, hides itself at checkout. HivePress Marketplace's Stripe
		 * Connect direct-charges gateway does exactly that and checks `hp_vendor` product meta as
		 * its fallback, so without this a buyer using direct charges is left with no way to pay for
		 * gallery access (verified on staging: only the booking "pay on arrival" method showed).
		 * Stamping the same vendor id under the conventional key lets that resolution succeed, and
		 * is inert for anyone not using such a gateway.
		 */
		update_post_meta( $product_id, 'hp_vendor', $vendor_id );

		/*
		 * The period is stamped on the product, not looked up when the order is paid. An admin who
		 * shortens the access period between someone adding it to the cart and their payment
		 * clearing must not quietly hand them less than the page offered.
		 */
		update_post_meta( $product_id, 'hp_agl_period', $period );
		update_post_meta( $product_id, 'hp_agl_tier', $tier );

		update_post_meta( $vendor_id, $this->get_product_meta_key( $tier ), $product_id );

		$this->tag_access_product( $product_id );

		return true;
	}

	/**
	 * Names a vendor's access product for one length of access.
	 *
	 * The length is in the name because the products sit side by side in the order, the vendor's
	 * product list and every report, and "Gallery access: Ada" three times over tells nobody which
	 * was bought.
	 *
	 * @param \HivePress\Models\Vendor $vendor Vendor object.
	 * @param int                      $period Access period in days, 0 for lifetime.
	 * @return string
	 */
	protected function get_access_product_name( $vendor, $period ) {
		if ( ! $period ) {
			/* translators: %s: vendor name. */
			return sprintf( esc_html__( 'Gallery access: %s', 'additional-gallery-for-hivepress' ), $vendor->get_name() );
		}

		return sprintf(
			/* translators: 1: vendor name, 2: number of days. */
			_n( 'Gallery access: %1$s (%2$s day)', 'Gallery access: %1$s (%2$s days)', $period, 'additional-gallery-for-hivepress' ),
			$vendor->get_name(),
			number_format_i18n( $period )
		);
	}

	/**
	 * Records the length of access on the order line at checkout.
	 *
	 * Underscored so WooCommerce keeps it out of the customer's sight, and read back when the
	 * payment clears. An offline payment can take days to clear, and whatever the site offers by
	 * then, the buyer is owed what it offered when they paid.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @param string                 $item_key Cart item key.
	 * @param array                  $values Cart item.
	 * @return void
	 */
	public function set_access_item_meta( $item, $item_key, $values ) {
		$product_id = $item->get_product_id();

		if ( ! $product_id || ! get_post_meta( $product_id, 'hp_agl_vendor', true ) ) {
			return;
		}

		$period = get_post_meta( $product_id, 'hp_agl_period', true );

		if ( '' !== $period ) {
			$item->update_meta_data( '_hp_agl_period', hp_agl_int( $period ) );
		}
	}

	/**
	 * Puts every gallery access product under one product tag.
	 *
	 * The products are hidden from the catalogue, so without a tag there is no way to address them
	 * as a group: a site owner setting up a conditional checkout, a fee rule or a report has to name
	 * each vendor's product one at a time and remember to add the next one.
	 *
	 * The tag is created on first use and then reused. Its name is filterable, because a site with an
	 * established taxonomy may already have one it would rather use.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	protected function tag_access_product( $product_id ) {
		if ( ! taxonomy_exists( 'product_tag' ) ) {
			return;
		}

		/**
		 * Filters the product tag put on every gallery access product.
		 *
		 * @hook hp_agl/access_product_tag
		 * @param {string} $tag Tag name. An empty string switches the tagging off.
		 * @return {string} Tag name.
		 */
		$tag = trim( (string) apply_filters( 'hp_agl/access_product_tag', 'additional gallery' ) );

		if ( ! $tag ) {
			return;
		}

		// Appended rather than set, so a tag the owner has added themselves is never removed.
		wp_set_object_terms( $product_id, $tag, 'product_tag', true );
	}

	/**
	 * Gets a user's active access grant for a vendor, if any.
	 *
	 * Grants store the order that paid for them and, since timed access
	 * arrived, an expiry timestamp (zero for lifetime). The expiry uses the
	 * period configured at purchase time, so changing the setting never
	 * shortens or extends access someone already bought. Expired grants are
	 * removed on first read.
	 *
	 * @param int $user_id User ID.
	 * @param int $vendor_id Vendor ID.
	 * @return array|null `order` and `expires` keys, or null without one.
	 */
	public function get_access_grant( $user_id, $vendor_id ) {
		$user_id   = absint( $user_id );
		$vendor_id = absint( $vendor_id );
		$grant     = $this->read_access_grant( $user_id, $vendor_id );

		if ( ! $grant ) {
			return null;
		}

		if ( $grant['expires'] && $grant['expires'] < time() ) {
			delete_user_meta( $user_id, 'hp_agl_access_' . $vendor_id );

			/**
			 * Fires when a purchased gallery access lapses because its access
			 * period has passed. Fired on the first check after the expiry.
			 *
			 * @hook hp_agl/access_expired
			 * @param {int} $user_id Buyer user ID.
			 * @param {int} $vendor_id Vendor ID.
			 * @param {int} $order_id WooCommerce order ID that granted the access.
			 */
			do_action( 'hp_agl/access_expired', $user_id, $vendor_id, $grant['order'] );

			return null;
		}

		return $grant;
	}

	/**
	 * Queues a cache purge when a like or comment on a gallery photo changes.
	 *
	 * @param int    $comment_id Comment ID.
	 * @param object $comment Comment object.
	 * @return void
	 */
	public function queue_engagement_purge( $comment_id, $comment = null ) {
		if ( ! is_object( $comment ) ) {
			$comment = get_comment( $comment_id );
		}

		if ( ! $comment || ! in_array( $comment->comment_type, [ 'hp_agl_like', 'hp_agl_comment', 'hp_agl_clike' ], true ) ) {
			return;
		}

		$photo_id = hp_agl_int( $comment->comment_post_ID );

		if ( ! $photo_id ) {
			return;
		}

		/*
		 * Queued, never done here. SiteGround's purge calls out to Site Tools
		 * (Supercacher::flush_dynamic_cache, verified in its source), so doing this inline would put
		 * a third-party round trip on the request of whoever pressed Like. That is the shape that
		 * took a customer's site down with 504s on 2026-08-19, and a like is exactly the kind of
		 * thing a busy page does many times at once.
		 *
		 * The scheduler drops a job whose hook and arguments are already queued, so a burst of likes
		 * on one photo collapses into a single purge.
		 */
		$scheduler = hivepress()->scheduler;

		if ( $scheduler ) {
			$scheduler->add_action( 'hp_agl_purge_photo_cache', [ $photo_id ] );
		}
	}

	/**
	 * Queues the same purge when a comment is approved or unapproved.
	 *
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 * @param object $comment Comment object.
	 * @return void
	 */
	public function queue_engagement_purge_on_status( $new_status, $old_status, $comment ) {
		if ( $new_status !== $old_status && is_object( $comment ) ) {
			$this->queue_engagement_purge( hp_agl_int( $comment->comment_ID ), $comment );
		}
	}

	/**
	 * Purges the cached pages that show a photo's like and comment counts.
	 *
	 * Runs from the scheduler. Three pages carry those numbers: the photo itself, the folder it sits
	 * in, and the vendor's gallery index.
	 *
	 * @param int $photo_id Attachment ID.
	 * @return void
	 */
	public function purge_photo_cache( $photo_id ) {
		$photo_id = hp_agl_int( $photo_id );
		$folder   = $photo_id ? $this->get_photo_folder_for_purge( $photo_id ) : null;

		if ( ! $folder ) {
			return;
		}

		$vendor_id = hp_agl_int( $folder->get_vendor__id() );

		$urls = array_filter(
			[
				$this->get_photo_url( $folder, $photo_id ),
				(string) hivepress()->router->get_url(
					'gallery_folder_view_page',
					[
						'vendor_id'         => $vendor_id,
						'gallery_folder_id' => $folder->get_id(),
					]
				),
				(string) hivepress()->router->get_url( 'gallery_view_page', [ 'vendor_id' => $vendor_id ] ),
			]
		);

		if ( ! $urls ) {
			return;
		}

		/**
		 * Fires with the pages whose cached copy is now out of date.
		 *
		 * Hook this for any caching layer not handled below, including a CDN.
		 *
		 * @hook hp_agl/purge_urls
		 * @param {array} $urls Page URLs.
		 * @param {int} $photo_id Attachment ID.
		 */
		do_action( 'hp_agl/purge_urls', $urls, $photo_id );

		// FlyingPress. Takes the whole set in one call.
		if ( class_exists( '\FlyingPress\Purge' ) && method_exists( '\FlyingPress\Purge', 'purge_urls' ) ) {
			\FlyingPress\Purge::purge_urls( $urls );
		}

		// SiteGround Optimizer.
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			foreach ( $urls as $url ) {
				sg_cachepress_purge_cache( $url );
			}
		}

		// WP Rocket, which a fair number of HivePress sites run.
		if ( function_exists( 'rocket_clean_files' ) ) {
			rocket_clean_files( $urls );
		}

		// LiteSpeed, which announces itself through an action rather than a function.
		if ( has_action( 'litespeed_purge_url' ) ) {
			foreach ( $urls as $url ) {
				do_action( 'litespeed_purge_url', $url );
			}
		}
	}

	/**
	 * Gets the folder a photo belongs to, for the purge job.
	 *
	 * @param int $photo_id Attachment ID.
	 * @return object|null
	 */
	protected function get_photo_folder_for_purge( $photo_id ) {
		if ( ! class_exists( '\HivePress\Models\Gallery_Folder' ) ) {
			return null;
		}

		$folder_id = hp_agl_int( wp_get_post_parent_id( $photo_id ) );

		if ( ! $folder_id || 'hp_gallery_folder' !== get_post_type( $folder_id ) ) {
			return null;
		}

		return Models\Gallery_Folder::query()->get_by_id( $folder_id );
	}

	/**
	 * Warns buyers whose timed gallery access is about to lapse.
	 *
	 * Runs once a day on HivePress's own daily event, so no scheduling code is needed here. Each
	 * grant is warned about once: the flag is written into the grant itself, which means a repeat
	 * purchase clears it automatically, because grant_paid_access() overwrites the whole array.
	 *
	 * Lifetime grants store an expiry of zero and are skipped. Grants live one per buyer per vendor
	 * as `hp_agl_access_{vendor_id}` user meta, so the vendor is named in the key rather than the
	 * value and there is nothing to narrow on in SQL but the key prefix. `meta_key` is indexed, so
	 * that prefix match uses the index, and the expiry is compared in PHP because it sits inside a
	 * serialised array where SQL cannot reach it.
	 *
	 * @return void
	 */
	public function warn_expiring_access() {
		global $wpdb;

		if ( ! $this->is_paid_access_enabled() ) {
			return;
		}

		/**
		 * Filters how many days before it lapses a buyer is warned about their gallery access.
		 *
		 * @hook hp_agl/access_warning_days
		 * @param {int} $days Days of notice. Default 7. Zero switches the warning off.
		 * @return {int} Days of notice.
		 */
		$days = (int) apply_filters( 'hp_agl/access_warning_days', 7 );

		if ( $days < 1 ) {
			return;
		}

		/**
		 * Filters how many grants one daily pass examines.
		 *
		 * The cap is a safety valve rather than a tuned number: the query is bounded by how many
		 * people have ever bought access, which on any ordinary site is far below this. Raising it
		 * only matters on a site that sells more than this many accesses in a day.
		 *
		 * @hook hp_agl/access_warning_limit
		 * @param {int} $limit Maximum grants examined per run. Default 2000.
		 * @return {int} Maximum grants examined per run.
		 */
		$limit = max( 1, (int) apply_filters( 'hp_agl/access_warning_limit', 2000 ) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no core API can select by meta-key prefix across all users; runs once a day on cron, so there is nothing to cache.
			$wpdb->prepare(
				"SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta}
				 WHERE meta_key LIKE %s
				 ORDER BY umeta_id ASC
				 LIMIT %d",
				$wpdb->esc_like( 'hp_agl_access_' ) . '%',
				$limit
			)
		);

		if ( ! $rows ) {
			return;
		}

		$now     = time();
		$horizon = $now + $days * DAY_IN_SECONDS;

		foreach ( $rows as $row ) {
			$grant = maybe_unserialize( $row->meta_value );

			// Grants from before timed access stored the bare order ID and are lifetime, so there
			// is nothing to warn about.
			if ( ! is_array( $grant ) ) {
				continue;
			}

			$expires = hp_agl_int( hp\get_array_value( $grant, 'expires' ) );

			// Zero is lifetime; anything already past is left to get_access_grant(), which clears
			// it and fires the expiry action.
			if ( $expires < 1 || $expires <= $now || $expires > $horizon ) {
				continue;
			}

			if ( hp\get_array_value( $grant, 'warned' ) ) {
				continue;
			}

			$user_id   = absint( $row->user_id );
			$vendor_id = absint( substr( $row->meta_key, strlen( 'hp_agl_access_' ) ) );

			if ( ! $user_id || ! $vendor_id ) {
				continue;
			}

			// The flag goes in before the action fires, so a listener that throws cannot leave the
			// grant unflagged and warn again tomorrow.
			$grant['warned'] = 1;

			update_user_meta( $user_id, $row->meta_key, $grant );

			/**
			 * Fires once, ahead of a purchased gallery access lapsing.
			 *
			 * @hook hp_agl/access_expiring
			 * @param {int} $user_id Buyer user ID.
			 * @param {int} $vendor_id Vendor ID.
			 * @param {int} $expires Expiry timestamp.
			 * @param {int} $days_left Whole days remaining, at least one.
			 */
			do_action( 'hp_agl/access_expiring', $user_id, $vendor_id, $expires, max( 1, (int) ceil( ( $expires - $now ) / DAY_IN_SECONDS ) ) );
		}
	}

	/**
	 * Checks whether a user has bought access to a vendor's locked folders.
	 *
	 * @param int $user_id User ID.
	 * @param int $vendor_id Vendor ID.
	 * @return bool
	 */
	public function has_paid_access( $user_id, $vendor_id ) {
		return $this->is_paid_access_enabled() && null !== $this->get_access_grant( $user_id, $vendor_id );
	}

	/**
	 * Reads a stored access grant into its full shape.
	 *
	 * Three shapes exist in the wild and all of them stay readable. The oldest is a bare order ID
	 * from before access could expire; then came an `order` and `expires` pair; now there is also a
	 * `parts` ledger, so that access built up from more than one purchase can have one of those
	 * purchases refunded without taking away the rest.
	 *
	 * @param int $user_id User ID.
	 * @param int $vendor_id Vendor ID.
	 * @return array|null
	 */
	protected function read_access_grant( $user_id, $vendor_id ) {
		$value = get_user_meta( absint( $user_id ), 'hp_agl_access_' . absint( $vendor_id ), true );

		if ( ! $value ) {
			return null;
		}

		if ( ! is_array( $value ) ) {
			return [
				'order'   => hp_agl_int( $value ),
				'expires' => 0,
				'started' => 0,
				'parts'   => [],
			];
		}

		$parts = hp\get_array_value( $value, 'parts' );

		return [
			'order'   => hp_agl_int( hp\get_array_value( $value, 'order' ) ),
			'expires' => hp_agl_int( hp\get_array_value( $value, 'expires' ) ),
			'started' => hp_agl_int( hp\get_array_value( $value, 'started' ) ),
			'parts'   => is_array( $parts ) ? $parts : [],
		];
	}

	/**
	 * Works out when a grant runs out from the purchases that built it.
	 *
	 * Days are added to the moment access first began rather than to whatever the expiry happens to
	 * be, so the answer is the same however many times it is recalculated. One lifetime purchase in
	 * the ledger makes the whole grant lifetime.
	 *
	 * @param array $grant Grant.
	 * @return int Expiry timestamp, 0 for lifetime.
	 */
	protected function calculate_grant_expiry( $grant ) {
		if ( ! $grant['parts'] ) {
			return $grant['expires'];
		}

		$days = 0;

		foreach ( $grant['parts'] as $part_days ) {
			if ( ! hp_agl_int( $part_days ) ) {
				return 0;
			}

			$days += hp_agl_int( $part_days );
		}

		return $grant['started'] + $days * DAY_IN_SECONDS;
	}

	/**
	 * Adds a purchase to a user's access to one vendor.
	 *
	 * Buying again while access is still running extends it rather than being ignored. Before
	 * access came in more than one length there was nothing to buy twice, so ignoring a second
	 * purchase was harmless; now someone can quite reasonably buy a week and then a year, and
	 * taking their money for nothing would not be.
	 *
	 * @param int $user_id User ID.
	 * @param int $vendor_id Vendor ID.
	 * @param int $order_id Order that paid for it.
	 * @param int $product_id Product that was bought, so two lengths in one order both count.
	 * @param int $period Days bought, 0 for lifetime.
	 * @return array|null The stored grant, or null when this line had already been counted.
	 */
	protected function add_access_time( $user_id, $vendor_id, $order_id, $product_id, $period ) {
		$grant  = $this->read_access_grant( $user_id, $vendor_id );
		$period = max( 0, absint( $period ) );

		/*
		 * Keyed by the line, not by the order. One order can hold two different lengths for the
		 * same vendor - the unlock page links straight to add-to-cart and WooCommerce keeps the
		 * cart, so somebody can press "7 days" and then "90 days" and pay once. Keyed by the order
		 * alone, the second line was silently dropped and they were charged for both and given the
		 * shorter one. The same line still yields the same key on the processing and completed
		 * passes, which is what stops a single payment counting twice.
		 */
		$part_key = $order_id . ':' . absint( $product_id );

		if ( $grant && $grant['expires'] && $grant['expires'] < time() ) {

			// Lapsed. Buying again starts over rather than topping up something already gone.
			$grant = null;
		}

		if ( ! $grant ) {
			$grant = [
				'order'   => $order_id,
				'expires' => 0,
				'started' => time(),
				'parts'   => [],
			];
		} else {
			if ( isset( $grant['parts'][ $part_key ] ) ) {

				// Already counted. An order moves to processing and then to completed, and both of
				// those grant access, so this runs twice for a single payment as a matter of course.
				return null;
			}

			/*
			 * A grant made before the ledger existed has an expiry but nothing to explain it. Its
			 * remaining days become the opening entry, keyed by the order that paid for them, so
			 * the recalculation below lands back on the same date. A lifetime grant contributes a
			 * lifetime entry and stays lifetime.
			 */
			if ( ! $grant['parts'] ) {
				$grant['started'] = time();

				$grant['parts'][ (string) $grant['order'] ] = $grant['expires']
					? max( 1, (int) ceil( ( $grant['expires'] - time() ) / DAY_IN_SECONDS ) )
					: 0;
			}
		}

		/*
		 * Any `warned` flag a previous expiry left behind is dropped here on purpose: the new
		 * date has not been warned about, and the buyer should hear about that one too.
		 */
		$grant['parts'][ $part_key ] = $period;
		$grant['order']              = $order_id;
		$grant['expires']            = $this->calculate_grant_expiry( $grant );

		update_user_meta( $user_id, 'hp_agl_access_' . $vendor_id, $grant );

		return $grant;
	}

	/**
	 * Removes one order's contribution from a user's access to a vendor.
	 *
	 * @param int $user_id User ID.
	 * @param int $vendor_id Vendor ID.
	 * @param int $order_id Order being refunded or cancelled.
	 * @return bool Whether anything was taken away.
	 */
	protected function remove_access_time( $user_id, $vendor_id, $order_id ) {
		$grant = $this->read_access_grant( $user_id, $vendor_id );

		if ( ! $grant ) {
			return false;
		}

		/*
		 * Without a ledger there is nothing to subtract, so the old rule stands: the order that
		 * granted the access is the only one that can take it away.
		 */
		if ( ! $grant['parts'] ) {
			if ( $grant['order'] !== $order_id ) {
				return false;
			}

			delete_user_meta( $user_id, 'hp_agl_access_' . $vendor_id );

			return true;
		}

		/*
		 * Every line that order paid for goes, not one. Grants written before the ledger was keyed
		 * by line hold a bare order ID, so both shapes are matched.
		 */
		$prefix  = $order_id . ':';
		$removed = false;

		foreach ( array_keys( $grant['parts'] ) as $key ) {
			if ( (string) $key === (string) $order_id || 0 === strpos( (string) $key, $prefix ) ) {
				unset( $grant['parts'][ $key ] );

				$removed = true;
			}
		}

		if ( ! $removed ) {
			return false;
		}

		if ( ! $grant['parts'] ) {
			delete_user_meta( $user_id, 'hp_agl_access_' . $vendor_id );

			return true;
		}

		$grant['expires'] = $this->calculate_grant_expiry( $grant );

		// Refunding the long purchase can leave less time than has already passed.
		if ( $grant['expires'] && $grant['expires'] <= time() ) {
			delete_user_meta( $user_id, 'hp_agl_access_' . $vendor_id );

			return true;
		}

		// Keys may be "order:product" or a bare order ID, so take the order half either way.
		$grant['order'] = hp_agl_int( strtok( (string) hp\get_last_array_value( array_keys( $grant['parts'] ) ), ':' ) );

		update_user_meta( $user_id, 'hp_agl_access_' . $vendor_id, $grant );

		return true;
	}

	/**
	 * Grants gallery access for every access product in a paid order.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function grant_paid_access( $order_id ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;

		if ( ! $order ) {
			return;
		}

		$user_id = absint( $order->get_customer_id() );

		if ( ! $user_id ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$product_id = $item->get_product_id();
			$vendor_id  = absint( get_post_meta( $product_id, 'hp_agl_vendor', true ) );

			if ( ! $vendor_id ) {
				continue;
			}

			/*
			 * What the site offered when they paid, recorded on the order line at checkout. Falling
			 * back through the product to the site setting covers an order made before this stamp
			 * existed and one an admin has built by hand in wp-admin.
			 */
			$period = $item->get_meta( '_hp_agl_period' );

			if ( '' === $period || is_null( $period ) ) {
				$period = get_post_meta( $product_id, 'hp_agl_period', true );
			}

			$period = '' === $period ? absint( get_option( 'hp_gallery_access_period' ) ) : hp_agl_int( $period );

			$grant = $this->add_access_time( $user_id, $vendor_id, absint( $order_id ), $product_id, $period );

			if ( ! $grant ) {
				continue;
			}

			$expires = $grant['expires'];

			/**
			 * Fires when someone buys access to a vendor's locked gallery
			 * folders. Hook here to notify the vendor or the buyer.
			 *
			 * @hook hp_agl/access_purchased
			 * @param {int} $user_id Buyer user ID.
			 * @param {int} $vendor_id Vendor ID.
			 * @param {int} $order_id WooCommerce order ID.
			 * @param {int} $expires Expiry timestamp, 0 for lifetime access.
			 */
			do_action( 'hp_agl/access_purchased', $user_id, $vendor_id, absint( $order_id ), $expires );
		}
	}

	/**
	 * Revokes gallery access when the order that granted it is refunded or
	 * cancelled.
	 *
	 * Only the granting order revokes, so a failed duplicate order cannot take
	 * away access someone paid for.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function revoke_paid_access( $order_id ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;

		if ( ! $order ) {
			return;
		}

		$user_id = absint( $order->get_customer_id() );

		if ( ! $user_id ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$vendor_id = absint( get_post_meta( $item->get_product_id(), 'hp_agl_vendor', true ) );

			if ( ! $vendor_id ) {
				continue;
			}

			/*
			 * Read straight from storage rather than through get_access_grant(), which clears an
			 * expired grant before answering: a refund must still tidy away what it left behind.
			 * Only this order's own contribution goes; access paid for by another order stays.
			 */
			if ( $this->remove_access_time( $user_id, $vendor_id, absint( $order_id ) ) ) {

				/**
				 * Fires when a purchased gallery access is revoked because the
				 * order was refunded or cancelled.
				 *
				 * @hook hp_agl/access_revoked
				 * @param {int} $user_id Buyer user ID.
				 * @param {int} $vendor_id Vendor ID.
				 * @param {int} $order_id WooCommerce order ID.
				 */
				do_action( 'hp_agl/access_revoked', $user_id, $vendor_id, absint( $order_id ) );
			}
		}
	}

	/**
	 * Renders the unlock action shown with a locked folder.
	 *
	 * Exactly one button appears, so the visitor is never asked to choose
	 * between paying the vendor and paying the site: while the vendor sells
	 * access, their purchase is the unlock path; otherwise the site's upgrade
	 * page link is.
	 *
	 * @param \HivePress\Models\Vendor $vendor Vendor object.
	 * @return string
	 */
	public function render_unlock_actions( $vendor ) {
		$output = '';

		// Purchase buttons, one per length of access the vendor has priced.
		if ( $this->is_paid_access_enabled() ) {
			$tiers = $this->get_priced_access_tiers( $vendor->get_id() );

			/*
			 * A signed-out visitor is sent to sign in rather than to the checkout whichever length
			 * they pick, because the access has to attach to an account. Working out which one they
			 * wanted and resuming it afterwards is a login round trip this does not attempt; they
			 * land back on this page with the same choice in front of them.
			 */
			$login_url = is_user_logged_in() ? '' : hivepress()->router->get_return_url( 'user_login_page' );

			foreach ( $tiers as $tier ) {
				$buy_url = $login_url ? $login_url : add_query_arg( 'add-to-cart', $tier['product'], wc_get_checkout_url() );
				$price   = esc_html( wp_strip_all_tags( wc_price( $tier['price'] ) ) );

				/*
				 * The length leads and the price follows in brackets, so a column of buttons lines
				 * up on the thing the buyer is actually choosing between. The old wording put the
				 * price first and buried the length at the end, which read as three near-identical
				 * sentences. Changed in 1.8.15.
				 */
				if ( $tier['period'] ) {
					$label = sprintf(
						/* translators: 1: number of days, 2: price. */
						_n( 'Unlock for %1$s Day (%2$s)', 'Unlock for %1$s Days (%2$s)', $tier['period'], 'additional-gallery-for-hivepress' ),
						esc_html( number_format_i18n( $tier['period'] ) ),
						$price
					);
				} else {
					/* translators: %s: price. */
					$label = sprintf( esc_html__( 'Unlock Permanently (%s)', 'additional-gallery-for-hivepress' ), $price );
				}

				$output .= '<a href="' . esc_url( $buy_url ) . '" class="hp-agl-unlock__buy hp-button button button--primary"><i class="hp-icon fas fa-unlock"></i><span>' . $label . '</span></a>';
			}
		}

		// Membership upgrade link, only when no purchase is offered.
		if ( ! $output ) {
			$upgrade_url = $this->get_upgrade_url();

			if ( $upgrade_url ) {
				$output .= '<a href="' . esc_url( $upgrade_url ) . '" class="hp-agl-unlock__upgrade hp-button button button--primary"><i class="hp-icon fas fa-unlock"></i><span>' . esc_html__( 'Unlock Access', 'additional-gallery-for-hivepress' ) . '</span></a>';
			}
		}

		if ( $output ) {
			$output = '<p class="hp-agl-gallery__folder-unlock">' . $output . '</p>';
		}

		return $output;
	}

	/**
	 * Gets a vendor's storage limit in bytes.
	 *
	 * @param \HivePress\Models\Vendor|null $vendor Vendor object.
	 * @return int Zero for no limit.
	 */
	public function get_storage_limit( $vendor ) {
		$limit = null;

		if ( $vendor ) {
			$limit = $this->get_plan_limit( $vendor->get_user__id(), 'hp_gallery_storage_limit' );
		}

		if ( is_null( $limit ) ) {
			$limit = hp_agl_int( get_option( 'hp_gallery_storage_limit' ) );
		}

		/**
		 * Filters a vendor's gallery storage limit in megabytes.
		 *
		 * @hook hp_agl/storage_limit
		 * @param {int} $limit Limit in megabytes, 0 for none.
		 * @param {mixed} $vendor Vendor object.
		 * @return {int} Limit in megabytes.
		 */
		return absint( apply_filters( 'hp_agl/storage_limit', $limit, $vendor ) ) * MB_IN_BYTES;
	}

	/**
	 * Gets the total bytes a vendor's gallery currently uses.
	 *
	 * @param \HivePress\Models\Vendor $vendor Vendor object.
	 * @return int
	 */
	public function get_storage_used( $vendor ) {
		$bytes = 0;

		$folder_ids = get_posts(
			[
				'post_type'   => 'hp_gallery_folder',
				'post_status' => 'any',
				'post_parent' => $vendor->get_id(),
				'numberposts' => -1,
				'fields'      => 'ids',
			]
		);

		foreach ( $folder_ids as $folder_id ) {
			$bytes += $this->get_folder_size( $folder_id );
		}

		return $bytes;
	}
}
