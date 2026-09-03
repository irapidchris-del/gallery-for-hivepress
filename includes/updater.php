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
 * When GitHub's hourly allowance for this server is expected back. While this is set the API is not
 * called at all, so a site that has run out does not spend the rest of the window making requests
 * that can only be refused.
 */
const UPDATE_RATE_LIMIT_KEY = 'hp_agl_github_release_rate_limit';

/**
 * Support link, shown only on the Plugins screen: once in this plugin's row
 * meta and once in its "View details" popup. Deliberately never inside the
 * plugin's own settings UI, which would edge towards admin hijacking.
 */
const DONATE_URL = 'https://ko-fi.com/chrisbathivepresscommunity';

/**
 * Gets the latest release, cached in a site transient.
 *
 * @param bool $force Whether to bypass the cache.
 * @return array|null Release data, `[ 'none' => true ]` when the repository has
 *                    no release, or null when the check could not be made.
 */
function get_latest_release( $force = false ) {
	$cached = get_site_transient( UPDATE_CACHE_KEY );

	if ( ! $force && is_array( $cached ) ) {
		/*
		 * A cached answer is served at once, but one older than an hour is refreshed behind the
		 * scenes when someone is on the Plugins screen. Two releases inside the six-hour cache
		 * life otherwise meant a site updated to the middle one first and only saw the newer one
		 * after the cache turned over.
		 */
		if ( $cached && isset( $GLOBALS['pagenow'] ) && in_array( $GLOBALS['pagenow'], [ 'plugins.php', 'update-core.php' ], true ) && time() - (int) ( isset( $cached['fetched_at'] ) ? $cached['fetched_at'] : 0 ) > HOUR_IN_SECONDS ) {
			schedule_release_refresh();
		}

		return $cached ? $cached : null;
	}

	/*
	 * A cold cache must not be filled from somebody's page load. WordPress asks every plugin for its
	 * update details while rendering an admin request, so with several of these installed one such
	 * request made one blocking call to GitHub after another, in series: a site with nine of them
	 * measured 18.6 seconds on a settings screen, once, and then behaved perfectly for six hours
	 * because the answers were cached again. That is the same shape as the listing-save incident, on
	 * the admin side rather than the public one.
	 *
	 * So the fetch moves to a background job and this answers with what is already known. The manual
	 * Check for updates link still fetches immediately, because there a person is waiting for it.
	 */
	if ( ! $force ) {
		schedule_release_refresh();

		return null;
	}

	$release = fetch_latest_release();

	/*
	 * A failed check must not erase what the last good one found. Overwriting the cache with an empty
	 * result took a genuinely pending update off the Plugins screen for an hour with nothing to say
	 * why, which is worse than showing an answer that is at most a few hours old.
	 */
	if ( ! $release && $cached ) {
		set_site_transient( UPDATE_CACHE_KEY, $cached, HOUR_IN_SECONDS );

		return $cached;
	}

	// A definitive "no release" answer is cached briefly rather than for the
	// full period, so the first published release is picked up promptly.
	if ( is_array( $release ) && $release ) {
		$release['fetched_at'] = time();
	}

	set_site_transient( UPDATE_CACHE_KEY, $release, has_release( $release ) ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );

	return $release ? $release : null;
}

/**
 * Queues a background refresh of the release cache.
 *
 * Prefers HivePress's scheduler, which is Action Scheduler and already refuses a duplicate of a job
 * with the same hook and arguments, so repeated admin requests coalesce into one fetch. WP-Cron is
 * the fallback for the same reason it exists: it also runs the work outside this request.
 *
 * Neither is blocking, so where cron itself is starved the cache simply stays cold and no update is
 * offered until somebody presses Check for updates, which always fetches at once.
 *
 * @return void
 */
function schedule_release_refresh() {
	$hook = UPDATE_CACHE_KEY . '_refresh';

	// Assigned and then tested: Core defines no __isset(), so isset( hivepress()->x ) is always
	// false even for a component that is present and working.
	$scheduler = function_exists( 'hivepress' ) ? hivepress()->scheduler : null;

	if ( $scheduler ) {
		$scheduler->add_action( $hook );

		return;
	}

	if ( ! wp_next_scheduled( $hook ) ) {
		wp_schedule_single_event( time(), $hook );
	}
}

/**
 * Fills the release cache. Runs from the scheduler, never from a page render.
 *
 * @return void
 */
function refresh_release() {
	get_latest_release( true );
}

add_action( UPDATE_CACHE_KEY . '_refresh', __NAMESPACE__ . '\\refresh_release' );

/**
 * Checks whether a release lookup produced an installable release.
 *
 * @param mixed $release Release data.
 * @return bool
 */
function has_release( $release ) {
	return is_array( $release ) && ! empty( $release['version'] ) && ! empty( $release['package'] );
}

/**
 * Fetches the latest release details from the GitHub API.
 *
 * Draft and pre-release entries are excluded by the endpoint itself, so publishing a pre-release
 * never triggers an update notice.
 *
 * @return array<string, string>
 */
function fetch_latest_release() {
	$data = fetch_release_data();

	// Passed straight through, because the gallery's manual-check notice tells "nothing published"
	// apart from "the check failed" and says something different for each.
	if ( 'none' === $data ) {
		return [ 'none' => true ];
	}

	if ( ! is_array( $data ) ) {
		return [];
	}

	// The version is read from the release tag, with or without a "v" prefix.
	$version = ltrim( (string) ( isset( $data['tag_name'] ) ? $data['tag_name'] : '' ), 'vV' );

	if ( ! $version ) {
		return [];
	}

	// The update package is the first release asset named `*.zip`.
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
 * Gets the latest release, from github.com in preference to the GitHub API.
 *
 * WHY THIS DOES NOT SIMPLY CALL THE API
 *
 * Without a token `api.github.com` allows **60 requests an hour per IP address**, and that
 * allowance is shared by every plugin on the site, by every other site on the same server, and by
 * anything else calling the API from that address. A site running several of these extensions,
 * plus a few clicks of "Check for updates" - which deliberately bypasses the cache - spends it
 * easily; on shared hosting a neighbouring site can spend it alone. GitHub then answers 403, and
 * reporting that as "could not reach GitHub" sends the owner hunting a network fault that does not
 * exist. That is the same family of bug as reporting a 404 as unreachable: a refusal is an answer,
 * not a failure to get one.
 *
 * Everything this lookup needs is also published on github.com itself, which carries no such
 * allowance:
 *
 *   - `/releases/latest` answers 302, and the Location header names the release GitHub considers
 *     latest, with drafts and pre-releases excluded exactly as the API excludes them;
 *   - `/releases/expanded_assets/{tag}` is the fragment the release page uses to list its own
 *     downloads, so it names the asset;
 *   - `/releases.atom` carries the release notes.
 *
 * Measured against GitHub's own rate-limit counter on 2026-08-19, thirteen full update checks
 * through this route moved it by zero. The API is kept as a fallback so that a change at github.com
 * cannot leave the plugin with no way to check at all.
 *
 * @return array<string, mixed>|null Release data in the API's own shape, or null.
 */
function fetch_release_data() {
	$site = fetch_release_from_site();

	if ( isset( $site['release'] ) ) {
		return $site['release'];
	}

	// github.com has given a definite answer that nothing is published. Asking the API would only
	// repeat it, at the cost of one of the sixty.
	if ( isset( $site['reason'] ) && 'no_release' === $site['reason'] ) {
		return 'none';
	}

	return fetch_release_from_api();
}

/**
 * Reads the latest release from github.com, without touching the API allowance.
 *
 * @return array<string, mixed> Either a `release` in the API's shape, a `reason`, or empty to fall
 *                              back to the API.
 */
function fetch_release_from_site() {
	$base = 'https://github.com/' . UPDATE_REPO;

	$response = request(
		$base . '/releases/latest',
		[
			// Do not follow it. The redirect target is the answer.
			'redirection' => 0,
		]
	);

	if ( ! $response ) {
		return [];
	}

	$code = (int) wp_remote_retrieve_response_code( $response );

	// A repository with nothing published answers 404 here, which is the normal state of a new
	// repository rather than a fault.
	if ( 404 === $code ) {
		return [ 'reason' => 'no_release' ];
	}

	if ( 301 !== $code && 302 !== $code ) {
		return [];
	}

	$location = wp_remote_retrieve_header( $response, 'location' );

	// WordPress hands back an array when a header repeats.
	if ( is_array( $location ) ) {
		$location = end( $location );
	}

	if ( ! preg_match( '#/releases/tag/(.+)$#', (string) $location, $matches ) ) {
		return [];
	}

	$tag = rawurldecode( trim( $matches[1] ) );

	$asset = fetch_release_asset( $base, $tag );

	// No downloadable asset means there is nothing the updater could install, so let the API have
	// its say rather than reporting a release that cannot be applied.
	if ( ! $asset ) {
		return [];
	}

	$notes = fetch_release_notes( $base, $tag );

	// Shaped exactly like the API's own answer, so everything downstream is identical either way.
	return [
		'release' => [
			'tag_name'     => $tag,
			'html_url'     => $base . '/releases/tag/' . rawurlencode( $tag ),
			'body'         => $notes['body'],
			'published_at' => $notes['published'],
			'assets'       => [
				[
					'name'                 => $asset['name'],
					'browser_download_url' => $asset['url'],
				],
			],
		],
	];
}

/**
 * Reads a release's asset from the fragment the release page uses to list its own downloads.
 *
 * @param string $base Repository URL.
 * @param string $tag Release tag.
 * @return array<string, string>|null
 */
function fetch_release_asset( $base, $tag ) {
	$response = request( $base . '/releases/expanded_assets/' . rawurlencode( $tag ) );

	if ( ! $response || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	if ( ! preg_match_all( '#href="(/[^"]*/releases/download/[^"]+\.zip)"#i', wp_remote_retrieve_body( $response ), $matches ) ) {
		return null;
	}

	// Take the first zip, matching what the API branch does with the assets list.
	$path = html_entity_decode( $matches[1][0], ENT_QUOTES, 'UTF-8' );

	return [
		'name' => rawurldecode( basename( $path ) ),
		'url'  => 'https://github.com' . $path,
	];
}

/**
 * Reads a release's notes and publication date from the releases feed.
 *
 * Only the changelog in the plugin details popup depends on this, so a failure here is not fatal.
 *
 * @param string $base Repository URL.
 * @param string $tag Release tag.
 * @return array<string, string>
 */
function fetch_release_notes( $base, $tag ) {
	$empty = [
		'body'      => '',
		'published' => '',
	];

	$response = request( $base . '/releases.atom' );

	if ( ! $response || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return $empty;
	}

	if ( ! preg_match_all( '#<entry>(.*?)</entry>#s', wp_remote_retrieve_body( $response ), $entries ) ) {
		return $empty;
	}

	foreach ( $entries[1] as $entry ) {

		// Match the tag rather than taking the newest entry: the feed also carries pre-releases,
		// which the latest-release redirect deliberately skips.
		if ( false === strpos( $entry, '/releases/tag/' . $tag ) ) {
			continue;
		}

		$notes = '';

		if ( preg_match( '#<content[^>]*>(.*?)</content>#s', $entry, $content ) ) {
			$notes = release_notes_to_text( $content[1] );
		}

		$published = '';

		if ( preg_match( '#<updated>(.*?)</updated>#s', $entry, $updated ) ) {
			$published = trim( $updated[1] );
		}

		return [
			'body'      => $notes,
			'published' => $published,
		];
	}

	return $empty;
}

/**
 * Turns the rendered notes in the feed back into the plain text the API would have returned.
 *
 * The API hands back the release body as it was written, in Markdown, and the details popup prints
 * that as text. The feed carries the rendered HTML instead, so headings, bold runs and list items
 * are put back into their Markdown spelling to keep the popup reading the same either way.
 *
 * @param string $html Rendered notes.
 * @return string
 */
function release_notes_to_text( $html ) {
	$text = html_entity_decode( $html, ENT_QUOTES, 'UTF-8' );

	$text = preg_replace( '#<h[1-6][^>]*>(.*?)</h[1-6]>#is', "\n**$1**\n", $text );
	$text = preg_replace( '#<(strong|b)[^>]*>(.*?)</\1>#is', '**$2**', $text );
	$text = preg_replace( '#<(em|i)[^>]*>(.*?)</\1>#is', '*$2*', $text );
	$text = preg_replace( '#<li[^>]*>#i', "\n- ", $text );
	$text = preg_replace( '#</(p|div|ul|ol|li|pre|blockquote)>#i', "\n", $text );
	$text = preg_replace( '#<br\s*/?>#i', "\n", $text );

	$text = wp_strip_all_tags( (string) $text );

	// Collapse the blank lines the substitutions leave behind.
	$text = preg_replace( '#\n{3,}#', "\n\n", (string) $text );

	return trim( (string) $text );
}

/**
 * Reads the latest release from the GitHub API.
 *
 * Kept as a fallback only. See `fetch_release_data()` for why it is not the first choice.
 *
 * @return array<string, mixed>|null
 */
function fetch_release_from_api() {

	// GitHub has already said the allowance is spent, so sit the window out rather than spending it
	// on requests that can only be refused.
	if ( get_site_transient( UPDATE_RATE_LIMIT_KEY ) ) {
		return null;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . UPDATE_REPO . '/releases/latest',
		[
			'timeout'    => 10,
			'headers'    => [ 'Accept' => 'application/vnd.github+json' ],

			// Our own User-Agent, because WordPress's default is "WordPress/{version}; {site url}"
			// (wp-includes/class-wp-http.php:211) and that puts the site's address and its exact
			// WordPress version into every release check. GitHub only requires that the header
			// identifies something, so this satisfies it while telling them nothing about the site.
			'user-agent' => UPDATE_SLUG . '/' . HP_AGL_VERSION,
		]
	);

	if ( is_wp_error( $response ) ) {
		return null;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );

	if ( 200 !== $code ) {

		// A 403 or 429 with nothing left on the counter means this server's hourly allowance is
		// spent. Nothing is wrong with the site, the plugin or the repository, so it must not be
		// reported as though something were.
		if ( ( 403 === $code || 429 === $code ) && '0' === (string) wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' ) ) {
			$reset = (int) wp_remote_retrieve_header( $response, 'x-ratelimit-reset' );
			$wait  = $reset > time() ? min( $reset - time(), HOUR_IN_SECONDS ) : 5 * MINUTE_IN_SECONDS;

			set_site_transient( UPDATE_RATE_LIMIT_KEY, $reset ? $reset : time() + $wait, $wait );
		}

		// A 404 is an answer, not a failure to get one: nothing is published yet.
		return 404 === $code ? 'none' : null;
	}

	delete_site_transient( UPDATE_RATE_LIMIT_KEY );

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	return is_array( $data ) ? $data : null;
}

/**
 * Makes a request to github.com.
 *
 * The User-Agent is set for the same reason as in the API branch: WordPress's default would put the
 * site's address and its exact WordPress version into every check.
 *
 * @param string               $url Request URL.
 * @param array<string, mixed> $args Extra request arguments.
 * @return array<string, mixed>|null
 */
function request( $url, $args = [] ) {
	$response = wp_remote_get(
		$url,
		array_merge(
			[
				'timeout'    => 10,
				'headers'    => [ 'Accept' => 'text/html, application/xml;q=0.9, */*;q=0.8' ],
				'user-agent' => UPDATE_SLUG . '/' . HP_AGL_VERSION,
			],
			$args
		)
	);

	return is_wp_error( $response ) ? null : $response;
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

	$details = [
		'id'     => 'https://github.com/' . UPDATE_REPO,
		'slug'   => UPDATE_SLUG,
		'plugin' => $plugin_file,
	];

	/*
	 * Answer even when there is nothing to update to. WordPress skips this plugin outright on a falsy
	 * return (wp-includes/update.php:557), and only files an answer under `no_update` when it gets one
	 * (:589-595) -- and that entry is what carries the `slug` the plugins list needs before it will
	 * print "View details" (wp-admin/includes/class-wp-plugins-list-table.php:1204, verified).
	 * Returning false left the row with no slug, so View details, the details popup and the donate link
	 * inside it were all unreachable from the Plugins screen whenever this plugin was up to date, which
	 * is almost always, or whenever the release check failed.
	 */

	if ( ! has_release( $release ) ) {
		$details['version'] = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '0.0.0';

		return $details;
	}

	return array_merge(
		$details,
		[
			'version' => $release['version'],
			'url'     => $release['url'],
			'package' => $release['package'],
		]
	);
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

	// Fall back to the installed plugin's own details when no release can be
	// read, so the popup still describes the plugin instead of leaving
	// WordPress to report "Plugin not found".
	$has_release = has_release( $release );

	$changelog = '<p>' . esc_html__( 'See the GitHub releases page for the changelog.', 'additional-gallery-for-hivepress' ) . '</p>';

	if ( $has_release && $release['notes'] ) {
		$changelog = wpautop( esc_html( $release['notes'] ) );
	}

	return (object) [
		'name'          => $plugin_data['Name'],
		'slug'          => UPDATE_SLUG,
		'version'       => $has_release ? $release['version'] : HP_AGL_VERSION,
		'author'        => '<a href="' . esc_url( $plugin_data['AuthorURI'] ) . '">' . esc_html( $plugin_data['Author'] ) . '</a>',
		'homepage'      => 'https://github.com/' . UPDATE_REPO,

		// WordPress renders this as "Donate to this plugin »" in the details
		// popup, which is the second of the two permitted support placements
		// (the other is the Plugins-screen row meta below). Never inside the
		// plugin's own settings UI.
		'donate_link'   => DONATE_URL,
		'requires'      => $plugin_data['RequiresWP'],
		'requires_php'  => $plugin_data['RequiresPHP'],
		'last_updated'  => $has_release ? $release['published'] : '',
		'download_link' => $has_release ? $release['package'] : '',

		'sections'      => [
			'description' => wpautop( esc_html( $plugin_data['Description'] ) ),
			'changelog'   => $changelog,
		],
	];
}

add_filter( 'plugins_api', __NAMESPACE__ . '\\get_plugin_information', 10, 3 );

/**
 * Adds the Settings and "Check for updates" plugin action links.
 *
 * Keyed entries, not appended ones: a numeric key ends up as the CSS class of
 * the wrapping span WordPress prints around each action.
 *
 * @param array $links Action links.
 * @return array
 */
function add_update_check_link( $links ) {

	// The settings live on a single site's HivePress menu, so the link is not
	// offered in the network admin, where that page does not exist.
	if ( ! is_network_admin() && current_user_can( 'manage_options' ) ) {
		$links = array_merge(
			[
				'hp-agl-settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=hp_settings&tab=gallery' ) ) . '">' . esc_html__( 'Settings', 'additional-gallery-for-hivepress' ) . '</a>',
			],
			$links
		);
	}

	if ( current_user_can( 'update_plugins' ) ) {
		$links['hp-agl-check-updates'] = '<a href="' . esc_url( wp_nonce_url( self_admin_url( 'plugins.php?hp_agl_check_updates=1' ), 'hp_agl_check_updates' ) ) . '">' . esc_html__( 'Check for updates', 'additional-gallery-for-hivepress' ) . '</a>';
	}

	return $links;
}

add_filter( 'plugin_action_links_' . plugin_basename( HP_AGL_FILE ), __NAMESPACE__ . '\\add_update_check_link' );
add_filter( 'network_admin_plugin_action_links_' . plugin_basename( HP_AGL_FILE ), __NAMESPACE__ . '\\add_update_check_link' );

/**
 * Adds the "Donate" link to this plugin's row on the Plugins screen.
 *
 * House style, identical across every plugin: the label is exactly "Donate"
 * and the icon is exactly `dashicons-star-filled`. A Dashicon rather than Font
 * Awesome because this renders in wp-admin, where HivePress's Font Awesome is
 * not guaranteed to be enqueued. The `plugin_basename` gate stops the link
 * appearing on every other plugin's row, and WordPress joins row-meta items
 * with its own separator, so this returns a bare link.
 *
 * @param array  $links Row meta links.
 * @param string $file Plugin file.
 * @return array
 */
function add_donate_link( $links, $file ) {
	if ( plugin_basename( HP_AGL_FILE ) !== $file ) {
		return $links;
	}

	$links[] = '<a href="' . esc_url( DONATE_URL ) . '" target="_blank" rel="noopener noreferrer">'
		. '<span class="dashicons dashicons-star-filled" style="font-size:14px;line-height:1.3;"></span> '
		. esc_html__( 'Donate', 'additional-gallery-for-hivepress' )
		. '</a>';

	return $links;
}

add_filter( 'plugin_row_meta', __NAMESPACE__ . '\\add_donate_link', 10, 2 );

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

		// Only a failed lookup is an error. A repository with no published
		// release is simply nothing to update to, which is "up to date".
		$status = 'error';
	} elseif ( has_release( $release ) && version_compare( $release['version'], HP_AGL_VERSION, '>' ) ) {
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

/**
 * Puts the cached release into WordPress's update list whenever the list lacks it.
 *
 * Core builds that list in wp_update_plugins(), which stamps last_checked BEFORE it calls
 * api.wordpress.org and returns early, without asking any Update URI plugin, when that call fails
 * or times out (wp-includes/update.php, the is_wp_error check after wp_remote_post). The stamp
 * then keeps the empty list for up to twelve hours. That is how the second of two updates
 * failed with "up to date" straight after the first succeeded: the first update wiped the list,
 * the rebuild on the next click lost the wordpress.org race, and only Check for updates, which
 * wipes the stamp, put the entry back. Reading the answer into the list here means the release
 * this plugin already knows about is offered whatever wordpress.org did.
 *
 * The same read drops an entry that has become stale, so a list built before an update does not
 * keep offering the version that is now installed.
 *
 * @param object|false $transient The update_plugins transient.
 * @return object|false
 */
function inject_update( $transient ) {
	if ( ! is_object( $transient ) ) {
		return $transient;
	}

	$basename = plugin_basename( HP_AGL_FILE );
	$release  = get_site_transient( UPDATE_CACHE_KEY );
	$version  = HP_AGL_VERSION;

	if ( ! $basename || ! is_array( $release ) || empty( $release['version'] ) || empty( $release['package'] ) ) {
		return $transient;
	}

	if ( version_compare( $release['version'], $version, '>' ) ) {
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = [];
		}

		if ( ! isset( $transient->response[ $basename ] ) ) {
			$transient->response[ $basename ] = (object) [
				'id'          => 'https://github.com/' . UPDATE_REPO,
				'slug'        => UPDATE_SLUG,
				'plugin'      => $basename,
				'new_version' => $release['version'],
				'url'         => isset( $release['url'] ) ? $release['url'] : '',
				'package'     => $release['package'],
			];
		}

		if ( isset( $transient->no_update[ $basename ] ) ) {
			unset( $transient->no_update[ $basename ] );
		}
	} elseif ( isset( $transient->response[ $basename ] ) ) {
		$offered = $transient->response[ $basename ];
		$offered = is_object( $offered ) && isset( $offered->new_version ) ? $offered->new_version : '';

		if ( ! $offered || version_compare( $offered, $version, '<=' ) ) {
			unset( $transient->response[ $basename ] );
		}
	}

	return $transient;
}

/**
 * Adds the bulk action to the Plugins screen. Registered by one copy of this updater only.
 *
 * @param array<string, string> $actions Bulk actions.
 * @return array<string, string>
 */
function add_bulk_check( $actions ) {
	if ( current_user_can( 'update_plugins' ) ) {
		$actions['hpx_check_updates'] = __( 'Check for updates', 'additional-gallery-for-hivepress' );
	}

	return $actions;
}

/**
 * Answers the bulk action for this plugin: a fresh release lookup when it was selected.
 *
 * @param string   $redirect Redirect URL.
 * @param string   $action Bulk action name.
 * @param string[] $plugin_files Selected plugin basenames.
 * @return string
 */
function handle_bulk_check( $redirect, $action, $plugin_files ) {
	if ( 'hpx_check_updates' === $action && current_user_can( 'update_plugins' ) && in_array( plugin_basename( HP_AGL_FILE ), (array) $plugin_files, true ) ) {
		get_latest_release( true );
	}

	return $redirect;
}

/**
 * Rebuilds the update list once every copy has answered, and names the result in the redirect.
 *
 * Runs after every handle_bulk_check() (priority 20 against their 10), from the one copy that
 * registered the action.
 *
 * @param string   $redirect Redirect URL.
 * @param string   $action Bulk action name.
 * @param string[] $plugin_files Selected plugin basenames.
 * @return string
 */
function finish_bulk_check( $redirect, $action, $plugin_files ) {
	if ( 'hpx_check_updates' !== $action || ! current_user_can( 'update_plugins' ) ) {
		return $redirect;
	}

	wp_clean_plugins_cache();
	wp_update_plugins();

	$current   = get_site_transient( 'update_plugins' );
	$available = 0;

	foreach ( (array) $plugin_files as $file ) {
		if ( is_object( $current ) && isset( $current->response[ $file ] ) ) {
			++$available;
		}
	}

	return add_query_arg(
		[
			'hpx_checked'   => count( (array) $plugin_files ),
			'hpx_available' => $available,
		],
		$redirect
	);
}

/**
 * Shows the bulk check result.
 *
 * @return void
 */
function show_bulk_check_notice() {
	// Reads two counts the bulk handler put in its own redirect; the values only pick a sentence.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['hpx_checked'] ) || ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$checked   = absint( wp_unslash( $_GET['hpx_checked'] ) );
	$available = isset( $_GET['hpx_available'] ) ? absint( wp_unslash( $_GET['hpx_available'] ) ) : 0;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( $available ) {
		/* translators: 1: number of plugins checked, 2: number with an update available. */
		$message = sprintf( _n( 'Checked %1$s plugin for updates: %2$s can be updated.', 'Checked %1$s plugins for updates: %2$s can be updated.', $checked, 'additional-gallery-for-hivepress' ), number_format_i18n( $checked ), number_format_i18n( $available ) );
	} else {
		/* translators: %s: number of plugins checked. */
		$message = sprintf( _n( 'Checked %s plugin for updates: it is up to date.', 'Checked %s plugins for updates: all are up to date.', $checked, 'additional-gallery-for-hivepress' ), number_format_i18n( $checked ) );
	}

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
}

/**
 * Keeps an updating row full width on phones.
 *
 * Below 783px core lays every list-table row out as a wrapping flex row, and the single cell of
 * a plugin's update row then shrinks to the width of its "Updating..." text. Printed once, by the
 * copy of this updater that registered the bulk action.
 *
 * @return void
 */
function print_plugins_screen_styles() {
	echo '<style id="hpx-plugins-screen">@media screen and (max-width: 782px) { .wp-list-table.plugins .plugin-update-tr .plugin-update { flex: 1 1 100%; width: 100%; box-sizing: border-box; } }</style>';
}

add_filter( 'site_transient_update_plugins', __NAMESPACE__ . '\\inject_update' );
add_filter( 'handle_bulk_actions-plugins', __NAMESPACE__ . '\\handle_bulk_check', 10, 3 );

// The Plugins screen bulk action, its notice and the row style: one copy of this updater
// registers them (whichever loads first); every copy answers the action for its own plugin.
if ( empty( $GLOBALS['hpx_update_check_bulk'] ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- shared handshake between every copy of this updater; a plugin-specific prefix would defeat it.
	$GLOBALS['hpx_update_check_bulk'] = 'additional-gallery-for-hivepress'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- shared handshake between every copy of this updater; a plugin-specific prefix would defeat it.

	add_filter( 'bulk_actions-plugins', __NAMESPACE__ . '\\add_bulk_check' );
	add_filter( 'handle_bulk_actions-plugins', __NAMESPACE__ . '\\finish_bulk_check', 20, 3 );
	add_action( 'admin_notices', __NAMESPACE__ . '\\show_bulk_check_notice' );
	add_action( 'network_admin_notices', __NAMESPACE__ . '\\show_bulk_check_notice' );
	add_action( 'admin_print_styles-plugins.php', __NAMESPACE__ . '\\print_plugins_screen_styles' );
}
