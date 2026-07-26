<?php
/**
 * GitHub release updater.
 *
 * Lets WordPress check for, notify about and install plugin updates straight
 * from the plugin's GitHub releases, using the `.zip` asset attached to each
 * release. Self-contained: no external library or update service required.
 *
 * On each GitHub release (with the plugin header Version bumped to match the
 * release tag), sites see the update on their Plugins screen and can install
 * it with one click, exactly like a wordpress.org plugin.
 *
 * @package AdditionalGalleryForHivePress
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'HP_AGL_GitHub_Updater' ) ) {

	/**
	 * Serves plugin updates from GitHub releases.
	 */
	final class HP_AGL_GitHub_Updater {

		/**
		 * Absolute path to the main plugin file.
		 *
		 * @var string
		 */
		private $file;

		/**
		 * Plugin basename, e.g. `plugin-dir/plugin-file.php`.
		 *
		 * @var string
		 */
		private $basename;

		/**
		 * Plugin slug (its directory name).
		 *
		 * @var string
		 */
		private $slug;

		/**
		 * GitHub repository owner.
		 *
		 * @var string
		 */
		private $owner;

		/**
		 * GitHub repository name.
		 *
		 * @var string
		 */
		private $repo;

		/**
		 * Regex the release asset filename must match.
		 *
		 * @var string
		 */
		private $asset_regex;

		/**
		 * Transient key for the cached release lookup.
		 *
		 * @var string
		 */
		private $cache_key = 'hp_agl_github_release';

		/**
		 * Constructor.
		 *
		 * @param array $args {
		 *     Updater arguments.
		 *
		 *     @type string $file        Absolute path to the main plugin file.
		 *     @type string $owner       GitHub repository owner.
		 *     @type string $repo        GitHub repository name.
		 *     @type string $asset_regex Optional regex the release asset name must match.
		 * }
		 */
		public function __construct( $args ) {
			$this->file     = $args['file'];
			$this->basename = plugin_basename( $this->file );
			$this->slug     = ( false !== strpos( $this->basename, '/' ) ) ? dirname( $this->basename ) : basename( $this->file, '.php' );
			$this->owner    = $args['owner'];
			$this->repo     = $args['repo'];

			$this->asset_regex = isset( $args['asset_regex'] ) ? $args['asset_regex'] : '/\.zip$/i';

			// Inject the available update into the plugins update transient.
			add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );

			// Provide the "View details" information popup.
			add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );

			// Make sure the installed folder is named after the slug.
			add_filter( 'upgrader_source_selection', [ $this, 'fix_source_dir' ], 10, 4 );

			// Add a manual "Check for updates" row action, and handle it.
			add_filter( 'plugin_row_meta', [ $this, 'add_check_link' ], 10, 2 );
			add_action( 'admin_init', [ $this, 'handle_manual_check' ] );

			// Drop the cache whenever WordPress refreshes its update data.
			add_action( 'upgrader_process_complete', [ $this, 'flush_cache' ] );
		}

		/**
		 * Gets the installed plugin version from its header.
		 *
		 * @return string
		 */
		private function get_installed_version() {
			if ( defined( 'HP_AGL_VERSION' ) ) {
				return HP_AGL_VERSION;
			}

			$data = get_file_data( $this->file, [ 'Version' => 'Version' ] );

			return isset( $data['Version'] ) ? $data['Version'] : '0';
		}

		/**
		 * Reads the plugin header fields used in the details popup.
		 *
		 * @return array
		 */
		private function get_plugin_header() {
			return get_file_data(
				$this->file,
				[
					'Name'        => 'Plugin Name',
					'Author'      => 'Author',
					'PluginURI'   => 'Plugin URI',
					'RequiresWP'  => 'Requires at least',
					'RequiresPHP' => 'Requires PHP',
				]
			);
		}

		/**
		 * Fetches the latest GitHub release, cached to avoid rate limits.
		 *
		 * @return array|null Release data, or null if unavailable.
		 */
		private function get_release() {
			$cached = get_site_transient( $this->cache_key );

			if ( false !== $cached ) {
				return is_array( $cached ) && ! empty( $cached['version'] ) ? $cached : null;
			}

			$release = null;

			$url = sprintf(
				'https://api.github.com/repos/%s/%s/releases/latest',
				rawurlencode( $this->owner ),
				rawurlencode( $this->repo )
			);

			$headers = [
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'AdditionalGalleryForHivePress',
			];

			/**
			 * Filters an optional GitHub token for the update check (needed only
			 * for private repositories or to raise the API rate limit).
			 *
			 * @param string $token GitHub token.
			 */
			$token = hp_agl_string( apply_filters( 'hp_agl/github_token', '' ) );

			if ( $token ) {
				$headers['Authorization'] = 'Bearer ' . $token;
			}

			$response = wp_remote_get(
				$url,
				[
					'timeout' => 15,
					'headers' => $headers,
				]
			);

			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( is_array( $data ) && ! empty( $data['tag_name'] ) ) {

					// Find the matching release asset (the clean distributable zip).
					$asset_url = '';

					foreach ( (array) ( isset( $data['assets'] ) ? $data['assets'] : [] ) as $asset ) {
						if ( ! empty( $asset['name'] ) && ! empty( $asset['browser_download_url'] ) && preg_match( $this->asset_regex, $asset['name'] ) ) {
							$asset_url = $asset['browser_download_url'];

							break;
						}
					}

					$release = [
						'version'   => ltrim( hp_agl_string( $data['tag_name'] ), 'vV' ),
						'download'  => $asset_url ? $asset_url : hp_agl_string( isset( $data['zipball_url'] ) ? $data['zipball_url'] : '' ),
						'has_asset' => (bool) $asset_url,
						'body'      => hp_agl_string( isset( $data['body'] ) ? $data['body'] : '' ),
						'html_url'  => hp_agl_string( isset( $data['html_url'] ) ? $data['html_url'] : '' ),
						'published' => hp_agl_string( isset( $data['published_at'] ) ? $data['published_at'] : '' ),
					];
				}
			}

			// Cache success for 12 hours; back off for 1 hour on failure.
			set_site_transient( $this->cache_key, $release ? $release : [ 'failed' => 1 ], $release ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );

			return $release;
		}

		/**
		 * Adds an available update to the plugins update transient.
		 *
		 * @param mixed $transient Update transient.
		 * @return mixed
		 */
		public function check_for_update( $transient ) {
			if ( ! is_object( $transient ) ) {
				$transient = new stdClass();
			}

			$release = $this->get_release();

			if ( ! $release || empty( $release['download'] ) ) {
				return $transient;
			}

			$current = $this->get_installed_version();

			$item = [
				'id'          => $this->owner . '/' . $this->repo,
				'slug'        => $this->slug,
				'plugin'      => $this->basename,
				'new_version' => $release['version'],
				'url'         => $release['html_url'],
				'package'     => $release['download'],
			];

			if ( version_compare( $release['version'], $current, '>' ) ) {
				if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
					$transient->response = [];
				}

				$transient->response[ $this->basename ] = (object) $item;
			} else {

				// Record "no update" so WordPress shows the plugin as up to date.
				if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
					$transient->no_update = [];
				}

				$item['new_version']          = $current;
				$item['package']              = '';
				$transient->no_update[ $this->basename ] = (object) $item;
			}

			return $transient;
		}

		/**
		 * Supplies the plugin information popup ("View details").
		 *
		 * @param mixed  $result Result object or false.
		 * @param string $action API action.
		 * @param object $args API arguments.
		 * @return mixed
		 */
		public function plugin_info( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) {
				return $result;
			}

			$release = $this->get_release();

			if ( ! $release ) {
				return $result;
			}

			$header = $this->get_plugin_header();

			$info = [
				'name'          => $header['Name'],
				'slug'          => $this->slug,
				'version'       => $release['version'],
				'author'        => $header['Author'],
				'homepage'      => $header['PluginURI'] ? $header['PluginURI'] : $release['html_url'],
				'download_link' => $release['download'],
				'trunk'         => $release['download'],
				'requires'      => $header['RequiresWP'],
				'requires_php'  => $header['RequiresPHP'],
				'last_updated'  => $release['published'],

				'sections'      => [
					'changelog' => $this->format_changelog( $release['body'], $release['html_url'] ),
				],
			];

			return (object) $info;
		}

		/**
		 * Renames the extracted update folder to the plugin slug, so an update
		 * never lands in a version-suffixed directory.
		 *
		 * @param string $source Extracted source directory.
		 * @param string $remote_source Remote source directory.
		 * @param object $upgrader Upgrader instance.
		 * @param array  $hook_extra Extra hook arguments.
		 * @return string|\WP_Error
		 */
		public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = [] ) {
			global $wp_filesystem;

			// Only act on this plugin's own update.
			if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
				return $source;
			}

			if ( ! $wp_filesystem || basename( untrailingslashit( $source ) ) === $this->slug ) {
				return $source;
			}

			$desired = trailingslashit( $remote_source ) . $this->slug;

			if ( $wp_filesystem->move( untrailingslashit( $source ), untrailingslashit( $desired ), true ) ) {
				return trailingslashit( $desired );
			}

			return $source;
		}

		/**
		 * Adds a "Check for updates" link to the plugin's row.
		 *
		 * @param array  $links Row meta links.
		 * @param string $file Plugin file.
		 * @return array
		 */
		public function add_check_link( $links, $file ) {
			if ( $file === $this->basename && current_user_can( 'update_plugins' ) ) {
				$url = wp_nonce_url(
					add_query_arg( 'hp_agl_check_update', '1', self_admin_url( 'plugins.php' ) ),
					'hp_agl_check_update'
				);

				$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for updates', 'additional-gallery-for-hivepress' ) . '</a>';
			}

			return $links;
		}

		/**
		 * Handles the manual update check: clears the caches and forces a recheck.
		 *
		 * @return void
		 */
		public function handle_manual_check() {
			if ( empty( $_GET['hp_agl_check_update'] ) || ! current_user_can( 'update_plugins' ) ) {
				return;
			}

			check_admin_referer( 'hp_agl_check_update' );

			$this->flush_cache();
			delete_site_transient( 'update_plugins' );
			wp_update_plugins();

			wp_safe_redirect( add_query_arg( 'hp_agl_checked', '1', self_admin_url( 'plugins.php' ) ) );

			exit;
		}

		/**
		 * Clears the cached release lookup.
		 *
		 * @return void
		 */
		public function flush_cache() {
			delete_site_transient( $this->cache_key );
		}

		/**
		 * Renders the release notes as basic HTML for the details popup.
		 *
		 * @param string $body Release body (Markdown).
		 * @param string $url Release URL.
		 * @return string
		 */
		private function format_changelog( $body, $url ) {
			$body = trim( (string) $body );
			$out  = '';

			if ( '' !== $body ) {
				$in_list = false;

				foreach ( preg_split( '/\r\n|\r|\n/', esc_html( $body ) ) as $line ) {
					if ( preg_match( '/^\s*[-*]\s+(.*)$/', $line, $matches ) ) {
						if ( ! $in_list ) {
							$out    .= '<ul>';
							$in_list = true;
						}

						$out .= '<li>' . $matches[1] . '</li>';
					} else {
						if ( $in_list ) {
							$out    .= '</ul>';
							$in_list = false;
						}

						if ( '' !== trim( $line ) ) {
							$out .= '<p>' . $line . '</p>';
						}
					}
				}

				if ( $in_list ) {
					$out .= '</ul>';
				}
			}

			if ( $url ) {
				$out .= '<p><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View release on GitHub', 'additional-gallery-for-hivepress' ) . '</a></p>';
			}

			return $out;
		}
	}
}
