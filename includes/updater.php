<?php
/**
 * GitHub release updater.
 *
 * Serves plugin updates straight from this repository's GitHub releases using
 * the native WordPress 5.8+ update API (the `Update URI` header routes update
 * checks to the `update_plugins_github.com` filter). Self-contained: no
 * external library or update service is involved.
 *
 * @package AdditionalGalleryForHivePress
 */

namespace AdditionalGalleryForHivePress\Updater;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

const UPDATE_REPO = 'irapidchris-del/gallery-for-hivepress';

const UPDATE_SLUG = 'additional-gallery-for-hivepress';

const UPDATE_CACHE_KEY = 'hp_agl_github_release';

/**
 * Gets the latest release, cached in a site transient.
 *
 * @param bool $force Whether to bypass the cache.
 * @return array|null
 */
function get_latest_release( $force = false ) {
	$release = $force ? false : get_site_transient( UPDATE_CACHE_KEY );

	if ( ! is_array( $release ) ) {
		$release = fetch_latest_release();

		set_site_transient( UPDATE_CACHE_KEY, $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	}

	return $release ? $release : null;
}

/**
 * Fetches the latest release from the GitHub API.
 *
 * @return array
 */
function fetch_latest_release() {
	$response = wp_remote_get(
		'https://api.github.com/repos/' . UPDATE_REPO . '/releases/latest',
		[
			'timeout' => 10,
			'headers' => [ 'Accept' => 'application/vnd.github+json' ],
		]
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return [];
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $data ) ) {
		return [];
	}

	$version = ltrim( (string) ( isset( $data['tag_name'] ) ? $data['tag_name'] : '' ), 'vV' );

	if ( ! $version ) {
		return [];
	}

	$package = '';

	foreach ( (array) ( isset( $data['assets'] ) ? $data['assets'] : [] ) as $asset ) {
		$name = strtolower( (string) ( isset( $asset['name'] ) ? $asset['name'] : '' ) );

		if ( '.zip' === substr( $name, -4 ) && ! empty( $asset['browser_download_url'] ) ) {
			$package = (string) $asset['browser_download_url'];

			break;
		}
	}

	if ( ! $package ) {
		return [];
	}

	return [
		'version'   => $version,
		'package'   => $package,
		'url'       => (string) ( isset( $data['html_url'] ) ? $data['html_url'] : 'https://github.com/' . UPDATE_REPO ),
		'notes'     => (string) ( isset( $data['body'] ) ? $data['body'] : '' ),
		'published' => (string) ( isset( $data['published_at'] ) ? $data['published_at'] : '' ),
	];
}

/**
 * Provides the update data to WordPress via the native `Update URI` API.
 *
 * @param mixed  $update Update data (false by default).
 * @param array  $plugin_data Plugin headers.
 * @param string $plugin_file Plugin file.
 * @return mixed
 */
function check_for_update( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( HP_AGL_FILE ) !== $plugin_file ) {
		return $update;
	}

	$release = get_latest_release();

	if ( ! $release ) {
		return $update;
	}

	return [
		'id'      => 'https://github.com/' . UPDATE_REPO,
		'slug'    => UPDATE_SLUG,
		'plugin'  => $plugin_file,
		'version' => $release['version'],
		'url'     => $release['url'],
		'package' => $release['package'],
	];
}

add_filter( 'update_plugins_github.com', __NAMESPACE__ . '\\check_for_update', 10, 3 );

/**
 * Supplies the "View version details" popup information.
 *
 * @param mixed  $result Result.
 * @param string $action API action.
 * @param object $args API arguments.
 * @return mixed
 */
function get_plugin_information( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! is_object( $args ) || UPDATE_SLUG !== ( isset( $args->slug ) ? $args->slug : '' ) ) {
		return $result;
	}

	$release = get_latest_release();

	if ( ! $release ) {
		return $result;
	}

	$plugin_data = get_file_data(
		HP_AGL_FILE,
		[
			'Name'        => 'Plugin Name',
			'Description' => 'Description',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'RequiresWP'  => 'Requires at least',
			'RequiresPHP' => 'Requires PHP',
		]
	);

	return (object) [
		'name'          => $plugin_data['Name'],
		'slug'          => UPDATE_SLUG,
		'version'       => $release['version'],
		'author'        => '<a href="' . esc_url( $plugin_data['AuthorURI'] ) . '">' . esc_html( $plugin_data['Author'] ) . '</a>',
		'homepage'      => 'https://github.com/' . UPDATE_REPO,
		'requires'      => $plugin_data['RequiresWP'],
		'requires_php'  => $plugin_data['RequiresPHP'],
		'last_updated'  => $release['published'],
		'download_link' => $release['package'],

		'sections'      => [
			'description' => wpautop( esc_html( $plugin_data['Description'] ) ),
			'changelog'   => $release['notes'] ? wpautop( esc_html( $release['notes'] ) ) : '<p>' . esc_html__( 'See the GitHub releases page for the changelog.', 'additional-gallery-for-hivepress' ) . '</p>',
		],
	];
}

add_filter( 'plugins_api', __NAMESPACE__ . '\\get_plugin_information', 10, 3 );

/**
 * Adds a "Check for updates" plugin action link.
 *
 * @param array $links Action links.
 * @return array
 */
function add_update_check_link( $links ) {
	if ( current_user_can( 'update_plugins' ) ) {
		$links[] = '<a href="' . esc_url( wp_nonce_url( self_admin_url( 'plugins.php?hp_agl_check_updates=1' ), 'hp_agl_check_updates' ) ) . '">' . esc_html__( 'Check for updates', 'additional-gallery-for-hivepress' ) . '</a>';
	}

	return $links;
}

add_filter( 'plugin_action_links_' . plugin_basename( HP_AGL_FILE ), __NAMESPACE__ . '\\add_update_check_link' );
add_filter( 'network_admin_plugin_action_links_' . plugin_basename( HP_AGL_FILE ), __NAMESPACE__ . '\\add_update_check_link' );

/**
 * Handles the manual update check: refreshes the cache, forces a WordPress
 * recheck, and redirects back to the plugins screen with a status notice.
 *
 * @return void
 */
function handle_update_check() {
	if ( ! isset( $_GET['hp_agl_check_updates'] ) || ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	check_admin_referer( 'hp_agl_check_updates' );

	$release = get_latest_release( true );

	wp_clean_plugins_cache();
	wp_update_plugins();

	$status = 'none';

	if ( ! $release ) {
		$status = 'error';
	} elseif ( version_compare( $release['version'], HP_AGL_VERSION, '>' ) ) {
		$status = 'available';
	}

	wp_safe_redirect( add_query_arg( 'hp_agl_checked', $status, self_admin_url( 'plugins.php' ) ) );

	exit;
}

add_action( 'admin_init', __NAMESPACE__ . '\\handle_update_check' );

/**
 * Shows the result notice after a manual update check.
 *
 * @return void
 */
function show_update_notice() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice after our own nonce-checked redirect.
	if ( ! isset( $_GET['hp_agl_checked'] ) || ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$status = sanitize_key( wp_unslash( $_GET['hp_agl_checked'] ) );

	if ( 'available' === $status ) {
		$class   = 'notice-warning';
		$message = esc_html__( 'A new version of Additional Gallery for HivePress is available. Refresh this page to update.', 'additional-gallery-for-hivepress' );
	} elseif ( 'error' === $status ) {
		$class   = 'notice-error';
		$message = esc_html__( 'Additional Gallery for HivePress could not check for updates. Please try again later.', 'additional-gallery-for-hivepress' );
	} elseif ( 'none' === $status ) {
		$class   = 'notice-success';
		$message = esc_html__( 'Additional Gallery for HivePress is up to date.', 'additional-gallery-for-hivepress' );
	} else {
		return;
	}

	printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
}

add_action( 'admin_notices', __NAMESPACE__ . '\\show_update_notice' );

/**
 * Renames the extracted update folder to the installed plugin directory, so an
 * update never lands in a version-suffixed folder.
 *
 * @param string $source Extracted source.
 * @param string $remote_source Remote source.
 * @param object $upgrader Upgrader.
 * @param array  $hook_extra Hook arguments.
 * @return string|\WP_Error
 */
function fix_update_directory( $source, $remote_source, $upgrader, $hook_extra = [] ) {
	global $wp_filesystem;

	if ( plugin_basename( HP_AGL_FILE ) !== ( isset( $hook_extra['plugin'] ) ? $hook_extra['plugin'] : '' ) || ! $wp_filesystem ) {
		return $source;
	}

	$directory = dirname( plugin_basename( HP_AGL_FILE ) );

	if ( '.' === $directory ) {
		return $source;
	}

	$target = trailingslashit( $remote_source ) . $directory . '/';

	if ( trailingslashit( $source ) === $target ) {
		return $source;
	}

	if ( ! $wp_filesystem->move( untrailingslashit( $source ), untrailingslashit( $target ) ) ) {
		return new \WP_Error( 'hp_agl_rename_failed', esc_html__( 'Could not rename the update directory.', 'additional-gallery-for-hivepress' ) );
	}

	return $target;
}

add_filter( 'upgrader_source_selection', __NAMESPACE__ . '\\fix_update_directory', 10, 4 );
