<?php
/**
 * Plugin Name: Additional Gallery for HivePress
 * Description: Gives vendors a front-end photo gallery with public, members-only and private folders, accessible from the account menu and linked from vendor profiles and listings.
 * Version: 1.3.0
 * Author: Chris B @ HivePress Community
 * Author URI: https://community.hivepress.io/u/chrisb
 * Text Domain: additional-gallery-for-hivepress
 * Domain Path: /languages/
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package AdditionalGalleryForHivePress
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Plugin constants.
if ( ! defined( 'HP_AGL_VERSION' ) ) {
	define( 'HP_AGL_VERSION', '1.3.0' );
}

if ( ! defined( 'HP_AGL_FILE' ) ) {
	define( 'HP_AGL_FILE', __FILE__ );
}

if ( ! defined( 'HP_AGL_DIR' ) ) {
	define( 'HP_AGL_DIR', __DIR__ );
}

if ( ! defined( 'HP_AGL_URL' ) ) {
	define( 'HP_AGL_URL', rtrim( plugin_dir_url( __FILE__ ), '/' ) );
}

/**
 * Casts an unknown value to a non-negative integer.
 *
 * Used to narrow values from untyped sources (options, request parameters,
 * filtered arrays) safely: non-scalar values become zero instead of raising
 * warnings or casting to unexpected numbers.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function hp_agl_int( $value ): int {
	return is_scalar( $value ) ? absint( $value ) : 0;
}

/**
 * Casts an unknown value to a string.
 *
 * Non-string scalars are cast; arrays, objects and null become an empty
 * string instead of the PHP "Array" artefact.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function hp_agl_string( $value ): string {
	if ( is_string( $value ) ) {
		return $value;
	}

	return is_scalar( $value ) ? (string) $value : '';
}

/**
 * Gets the upload formats accepted by the gallery, honouring the allowed
 * image formats setting and the video setting.
 *
 * Shared by the gallery folder model and the admin images meta box so both
 * enforce the same rules.
 *
 * @return array Lower-case file extensions.
 */
function hp_agl_get_upload_formats() {
	$allowed = array_filter( (array) get_option( 'hp_gallery_image_formats' ) );

	if ( ! $allowed ) {
		$allowed = [ 'jpg', 'png', 'webp', 'gif' ];
	}

	$map = [
		'jpg'  => [ 'jpg', 'jpeg' ],
		'png'  => [ 'png' ],
		'webp' => [ 'webp' ],
		'gif'  => [ 'gif' ],
	];

	$formats = [];

	foreach ( $allowed as $format ) {
		if ( isset( $map[ $format ] ) ) {
			$formats = array_merge( $formats, $map[ $format ] );
		}
	}

	// Fall back to the full image set if the option held only unknown values.
	if ( ! $formats ) {
		$formats = [ 'jpg', 'jpeg', 'png', 'webp', 'gif' ];
	}

	if ( get_option( 'hp_gallery_allow_video' ) ) {
		$formats = array_merge( $formats, [ 'mp4', 'webm', 'ogv' ] );
	}

	return array_values( array_unique( $formats ) );
}

/**
 * Registers this plugin as a HivePress extension.
 *
 * HivePress scans the registered directory for `includes/{configs,models,
 * controllers,components,forms,blocks,templates}` and autoloads classes in
 * the `HivePress\` namespace from there. The main plugin file must be named
 * after the plugin directory for HivePress to register the extension.
 */
add_filter(
	'hivepress/v1/extensions',
	function ( $extensions ) {
		$extensions[] = __DIR__;

		return $extensions;
	}
);

/**
 * Gets the HivePress Memberships plan post type.
 *
 * The Memberships extension is premium (closed source), so instead of
 * hard-coding its plan post type this resolves it at runtime: first via the
 * conventional HivePress name, then by inspecting the parent of an existing
 * membership record (memberships store the plan as `post_parent`).
 *
 * @return string|null Post type name, or null if it cannot be determined.
 */
function hp_agl_get_plan_post_type() {
	static $post_type;

	if ( ! isset( $post_type ) ) {
		$post_type = null;

		if ( post_type_exists( 'hp_membership_plan' ) ) {
			$post_type = 'hp_membership_plan';
		} elseif ( post_type_exists( 'hp_membership' ) ) {

			// Derive the plan post type from an existing membership's parent.
			$membership_ids = get_posts(
				[
					'post_type'   => 'hp_membership',
					'post_status' => 'any',
					'numberposts' => 5,
					'fields'      => 'ids',
				]
			);

			foreach ( $membership_ids as $membership_id ) {
				$parent_id = wp_get_post_parent_id( $membership_id );

				if ( $parent_id && get_post_type( $parent_id ) ) {
					$post_type = get_post_type( $parent_id );

					break;
				}
			}
		}

		/**
		 * Filters the detected membership plan post type.
		 *
		 * @param string|null $post_type Post type name.
		 */
		$post_type = apply_filters( 'hp_agl_membership_plan_post_type', $post_type );
	}

	return $post_type;
}

/**
 * Shows an admin notice if HivePress is not active.
 */
add_action(
	'admin_notices',
	function () {
		if ( ! function_exists( 'hivepress' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Additional Gallery for HivePress requires the HivePress plugin to be installed and activated.', 'additional-gallery-for-hivepress' ) . '</p></div>';
		}
	}
);

/**
 * Flags rewrite rules for flushing on activation.
 *
 * The gallery routes register rewrite rules via the HivePress router on
 * `init`, so the flush must happen on the request after activation, once
 * those rules exist.
 */
register_activation_hook(
	__FILE__,
	function () {
		update_option( 'hp_agl_flush_rewrite_rules', '1' );

		// Enable file protection by default, without overriding an explicit choice.
		add_option( 'hp_gallery_protect_files', '1' );
	}
);

/**
 * Flushes rewrite rules after the HivePress router has added them (priority
 * 10 on `init`), and runs one-time upgrade routines when the plugin version
 * changes.
 */
add_action(
	'init',
	function () {
		if ( get_option( 'hp_agl_flush_rewrite_rules' ) ) {
			delete_option( 'hp_agl_flush_rewrite_rules' );

			flush_rewrite_rules();
		}

		// Run upgrade routines.
		$version = hp_agl_string( get_option( 'hp_agl_version' ) );

		if ( HP_AGL_VERSION !== $version ) {

			// 1.0.x to 1.1.0: migrate the public checkbox to the visibility select.
			if ( ! $version || version_compare( $version, '1.1.0', '<' ) ) {
				$folder_ids = get_posts(
					[
						'post_type'   => 'hp_gallery_folder',
						'post_status' => 'any',
						'numberposts' => -1,
						'fields'      => 'ids',
					]
				);

				foreach ( $folder_ids as $folder_id ) {
					if ( ! get_post_meta( $folder_id, 'hp_visibility', true ) ) {
						update_post_meta( $folder_id, 'hp_visibility', get_post_meta( $folder_id, 'hp_public', true ) ? 'public' : 'private' );
					}

					delete_post_meta( $folder_id, 'hp_public' );
				}
			}

			// 1.2.x to 1.3.0: migrate the plan-picker settings to per-plan meta.
			if ( ! $version || version_compare( $version, '1.3.0', '<' ) ) {
				$plan_flags = [
					'hp_gallery_manage_plans' => 'hp_gallery_access',
					'hp_gallery_view_plans'   => 'hp_gallery_view',
				];

				foreach ( $plan_flags as $option => $meta_key ) {
					$plan_ids = array_filter( array_map( 'absint', (array) get_option( $option ) ) );

					foreach ( $plan_ids as $plan_id ) {
						update_post_meta( $plan_id, $meta_key, '1' );
					}

					delete_option( $option );
				}

				// Recompute the persisted gating flags from the migrated plans.
				if ( function_exists( 'hivepress' ) && hivepress()->gallery ) {
					hivepress()->gallery->refresh_gating_flags();
				}

				// Enable file protection by default, and flush the new file route.
				add_option( 'hp_gallery_protect_files', '1' );
				update_option( 'hp_agl_flush_rewrite_rules', '1' );
			}

			update_option( 'hp_agl_version', HP_AGL_VERSION );
		}
	},
	1000
);

/**
 * Flushes rewrite rules on deactivation so stale gallery URLs are removed.
 */
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
