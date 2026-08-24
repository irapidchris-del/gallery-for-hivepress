<?php
/**
 * Plugin Name: Additional Gallery for HivePress
 * Description: Gives vendors a front-end photo gallery with public, members-only and private folders, accessible from the account menu and linked from vendor profiles and listings.
 * Version: 1.8.15
 * Author: ChrisB @ HivePress Community
 * Author URI: https://community.hivepress.io/u/chrisb/summary
 * Text Domain: additional-gallery-for-hivepress
 * Domain Path: /languages/
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: hivepress
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Update URI: https://github.com/irapidchris-del/gallery-for-hivepress
 *
 * @package AdditionalGalleryForHivePress
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Plugin constants.
if ( ! defined( 'HP_AGL_VERSION' ) ) {
	define( 'HP_AGL_VERSION', '1.8.15' );
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
 * Enables automatic updates from GitHub releases via the native WordPress
 * 5.8+ update API (the `Update URI` header above routes update checks to the
 * `update_plugins_github.com` filter). Self-registers its hooks on load.
 */
require_once HP_AGL_DIR . '/includes/updater.php';

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
 * the `HivePress\` namespace from there.
 *
 * Two registration forms exist and each has a catch, so the right one is chosen
 * at runtime:
 *
 * - A bare directory *string* is what every official extension passes, but
 *   HivePress then derives the main file as `{dirname}/{dirname}.php`
 *   (`class-core.php:267`) and silently registers nothing when the folder name
 *   and the file name disagree. A zip that unpacks to a suffixed folder (say
 *   `additional-gallery-for-hivepress-main`) leaves the plugin active but
 *   completely inert, with no error anywhere.
 * - An *array* is used as-is (`:260`) and so is immune to the folder name, but
 *   HivePress' updater probe just above it concatenates every entry as a string
 *   (`:249-250`), which raises "Array to string conversion" for an array entry
 *   on every request under WP_DEBUG.
 *
 * So: use the string form whenever it actually resolves, which is the normal
 * install and behaves exactly like an official extension; fall back to the array
 * form only when the folder has been renamed, where a debug notice is a small
 * price for the plugin working at all. In that fallback, run the same updater
 * probe first over the string entries, so the `updates` key is already set and
 * core's loop, which is what emits the notice, never runs.
 */
add_filter(
	'hivepress/v1/extensions',
	function ( $extensions ) {
		$dirname = basename( HP_AGL_DIR );

		if ( file_exists( HP_AGL_DIR . '/' . $dirname . '.php' ) ) {
			$extensions[] = HP_AGL_DIR;

			return $extensions;
		}

		if ( ! isset( $extensions['updates'] ) ) {
			$package = '/vendor/hivepress/hivepress-updates';

			foreach ( $extensions as $dir ) {
				if ( is_string( $dir ) && file_exists( $dir . $package . '/hivepress-updates.php' ) ) {
					$extensions['updates'] = $dir . $package;

					break;
				}
			}
		}

		$extensions['additional_gallery'] = [
			'name'    => 'Additional Gallery for HivePress',
			'version' => HP_AGL_VERSION,
			'path'    => HP_AGL_DIR,
			'url'     => HP_AGL_URL,
		];

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
 *
 * Gated on the capability that could actually resolve it, and dismissible, so
 * it stays on the right side of the no-admin-hijacking rule: an editor or a
 * shop manager can do nothing about a missing plugin, so telling them is noise.
 */
add_action(
	'admin_notices',
	function () {
		if ( ! function_exists( 'hivepress' ) && current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Additional Gallery for HivePress requires the HivePress plugin to be installed and activated.', 'additional-gallery-for-hivepress' ) . '</p></div>';
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

		// Likes, comments, the lightbox, the button count and members-only
		// folders are on by default. `add_option` never overwrites, so a site
		// that switched any of them off keeps its choice across reactivation.
		add_option( 'hp_gallery_enable_likes', '1' );
		add_option( 'hp_gallery_enable_comments', '1' );
		add_option( 'hp_gallery_enable_lightbox', '1' );
		add_option( 'hp_gallery_show_button_count', '1' );
		add_option( 'hp_gallery_enable_members', '1' );
		add_option( 'hp_gallery_photo_sidebar', 'right' );
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

				// Recompute the persisted gating flags directly (not via the
				// component), so the fail-closed flags are correct even if
				// HivePress is inactive during the upgrade.
				$plan_post_type = hp_agl_get_plan_post_type() ? hp_agl_get_plan_post_type() : 'hp_membership_plan';

				foreach (
					[
						'hp_gallery_access' => 'hp_gallery_access_gated',
						'hp_gallery_view'   => 'hp_gallery_view_gated',
					] as $meta_key => $flag
				) {
					$gated = get_posts(
						[
							'post_type'   => $plan_post_type,
							'post_status' => 'publish',
							'numberposts' => 1,
							'fields'      => 'ids',

							'meta_query'  => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one-off upgrade check across a small plan set.
								[
									'key'   => $meta_key,
									'value' => '1',
								],
							],
						]
					);

					update_option( $flag, $gated ? '1' : '' );
				}

				// Enable file protection by default, and flush the new file route.
				add_option( 'hp_gallery_protect_files', '1' );
				update_option( 'hp_agl_flush_rewrite_rules', '1' );

				// Relocate existing private and members-only files behind the proxy.
				if ( function_exists( 'hivepress' ) && hivepress()->agl_gallery && get_option( 'hp_gallery_protect_files' ) ) {
					hivepress()->agl_gallery->sync_all_protection( '', '1' );
				}
			}

			// 1.4.x to 1.5.0: retire the image-processing settings this version
			// dropped, and clear the backups the removed "Keep Originals"
			// feature made. Compression, metadata stripping and WebP conversion
			// now belong to dedicated image plugins, which do them better.
			if ( ! $version || version_compare( $version, '1.5.0', '<' ) ) {
				foreach ( [ 'hp_gallery_image_quality', 'hp_gallery_strip_metadata', 'hp_gallery_convert_webp', 'hp_gallery_keep_originals' ] as $retired ) {
					delete_option( $retired );
				}

				// Remove the originals backup directory and the meta pointing
				// at it. Nothing references either once the bulk actions are
				// gone, so leaving them would just consume disk space.
				$upload_dir  = wp_get_upload_dir();
				$originals   = $upload_dir['basedir'] . '/hp-agl-originals';
				$backup_meta = 'hp_agl_original';

				if ( is_dir( $originals ) ) {
					$items = scandir( $originals );

					foreach ( (array) $items as $item ) {
						if ( '.' !== $item && '..' !== $item && is_file( $originals . '/' . $item ) ) {
							wp_delete_file( $originals . '/' . $item );
						}
					}

					// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rmdir_rmdir, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- removing our own directory during an upgrade; WP_Filesystem would need credentials we cannot prompt for here.
					@rmdir( $originals );
				}

				delete_metadata( 'post', 0, $backup_meta, '', true );

				// Likes and comments arrive switched on for existing sites too.
				add_option( 'hp_gallery_enable_likes', '1' );
				add_option( 'hp_gallery_enable_comments', '1' );
			}

			// 1.5.x to 1.6.0: the photo pages add rewrite rules, and the new
			// display toggles arrive switched on (their features match 1.5.x
			// behaviour when on).
			if ( ! $version || version_compare( $version, '1.6.0', '<' ) ) {
				add_option( 'hp_gallery_enable_lightbox', '1' );
				add_option( 'hp_gallery_show_button_count', '1' );
				add_option( 'hp_gallery_enable_members', '1' );

				update_option( 'hp_agl_flush_rewrite_rules', '1' );
			}

			// 1.6.x to 1.7.0: the separate photo edit route is gone (editing
			// lives in the photo page sidebar now), so its rewrite rule must be
			// dropped, and the sidebar position setting arrives.
			if ( ! $version || version_compare( $version, '1.7.0', '<' ) ) {
				add_option( 'hp_gallery_photo_sidebar', 'right' );

				update_option( 'hp_agl_flush_rewrite_rules', '1' );
			}

			// 1.8.2 to 1.8.3: protected files move out of the uploads folder, because a deny rule
			// inside it is not read at all where a reverse proxy serves static files ahead of the
			// web server. Only the files move; their stored paths are unchanged.
			if ( ! $version || version_compare( $version, '1.8.3', '<' ) ) {

				/*
				 * The move needs the gallery component, and this file runs even when HivePress is
				 * not there to provide it - WordPress before 6.5 ignores the `Requires Plugins`
				 * header, so the gallery can be active alone. Calling `hivepress()` unguarded here
				 * fataled EVERY request in that state, including the admin one that carries the
				 * "requires HivePress" notice meant for exactly this situation. Stopping before the
				 * version is recorded keeps the migration owed: every block above this point repeats
				 * safely (which also keeps the 1.3.0 block's skipped protection sync owed, not
				 * lost), and the whole routine simply runs again once HivePress is back, instead of
				 * stamping a version that would skip the file move for good.
				 */
				if ( ! function_exists( 'hivepress' ) ) {
					return;
				}

				$gallery = hivepress()->agl_gallery;

				if ( $gallery ) {
					$gallery->migrate_protected_files();
				}
			}

			update_option( 'hp_agl_version', HP_AGL_VERSION );
		}
	},
	1000
);

/**
 * Drops the cached rewrite rules on deactivation so the gallery URLs disappear.
 *
 * Deliberately not `flush_rewrite_rules()`: on the deactivation request this
 * plugin's routes are still registered, so a flush would write our now-dead
 * gallery rules straight back into the option. Deleting the option is what
 * core's own router does, and WordPress rebuilds it lazily on the next request,
 * by which time our routes are gone.
 */
register_deactivation_hook(
	__FILE__,
	function () {
		delete_option( 'rewrite_rules' );
	}
);
