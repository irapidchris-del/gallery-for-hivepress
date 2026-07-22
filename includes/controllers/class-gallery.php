<?php
/**
 * Gallery controller.
 *
 * @package AdditionalGalleryForHivePress\Controllers
 */

namespace HivePress\Controllers;

use HivePress\Helpers as hp;
use HivePress\Blocks;
use HivePress\Forms;
use HivePress\Models;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Handles gallery routes and REST actions.
 */
final class Gallery extends Controller {

	/**
	 * Class constructor.
	 *
	 * @param array $args Controller arguments.
	 */
	public function __construct( $args = [] ) {
		$args = hp\merge_arrays(
			[
				'routes' => [

					// REST: create a folder.
					'gallery_folders_resource'     => [
						'path'   => '/gallery-folders',
						'method' => 'POST',
						'action' => [ $this, 'create_gallery_folder' ],
						'rest'   => true,
					],

					'gallery_folder_resource'      => [
						'base' => 'gallery_folders_resource',
						'path' => '/(?P<gallery_folder_id>\d+)',
						'rest' => true,
					],

					// REST: update a folder.
					'gallery_folder_update_action' => [
						'base'   => 'gallery_folder_resource',
						'method' => 'POST',
						'action' => [ $this, 'update_gallery_folder' ],
						'rest'   => true,
					],

					// REST: reorder a folder.
					'gallery_folder_sort_action'   => [
						'base'   => 'gallery_folder_resource',
						'path'   => '/sort',
						'method' => 'POST',
						'action' => [ $this, 'sort_gallery_folder' ],
						'rest'   => true,
					],

					// REST: delete a folder.
					'gallery_folder_delete_action' => [
						'base'   => 'gallery_folder_resource',
						'method' => 'DELETE',
						'action' => [ $this, 'delete_gallery_folder' ],
						'rest'   => true,
					],

					// REST: update a gallery image.
					'gallery_image_update_action'  => [
						'path'   => '/gallery-images/(?P<attachment_id>\d+)',
						'method' => 'POST',
						'action' => [ $this, 'update_gallery_image' ],
						'rest'   => true,
					],

					// Account page: folder overview.
					'gallery_edit_page'            => [
						'title'    => esc_html__( 'Gallery', 'additional-gallery-for-hivepress' ),
						'base'     => 'user_account_page',
						'path'     => '/gallery',
						'redirect' => [ $this, 'redirect_gallery_edit_page' ],
						'action'   => [ $this, 'render_gallery_edit_page' ],
					],

					// Account page: edit a single folder.
					'gallery_folder_edit_page'     => [
						'base'     => 'gallery_edit_page',
						'path'     => '/(?P<gallery_folder_id>\d+)',
						'title'    => [ $this, 'get_gallery_folder_edit_title' ],
						'redirect' => [ $this, 'redirect_gallery_folder_edit_page' ],
						'action'   => [ $this, 'render_gallery_folder_edit_page' ],
					],

					// Public: stream a protected gallery file through an access check.
					'gallery_file_view_page'       => [
						'path'     => '/gallery-file/(?P<attachment_id>\d+)',
						'redirect' => [ $this, 'redirect_gallery_file' ],
					],

					// Public page: a vendor's gallery.
					'gallery_view_page'            => [
						'path'     => '/gallery/(?P<vendor_id>\d+)',
						'title'    => [ $this, 'get_gallery_view_title' ],
						'redirect' => [ $this, 'redirect_gallery_view_page' ],
						'action'   => [ $this, 'render_gallery_view_page' ],
					],

					// Public page: a single folder.
					'gallery_folder_view_page'     => [
						'base'     => 'gallery_view_page',
						'path'     => '/(?P<gallery_folder_id>\d+)',
						'title'    => [ $this, 'get_gallery_folder_view_title' ],
						'redirect' => [ $this, 'redirect_gallery_folder_view_page' ],
						'action'   => [ $this, 'render_gallery_folder_view_page' ],
					],
				],
			],
			$args
		);

		parent::__construct( $args );
	}

	/**
	 * Creates a gallery folder.
	 *
	 * @param \WP_REST_Request $request API request.
	 * @return \WP_REST_Response
	 */
	public function create_gallery_folder( $request ) {

		// Check authentication.
		if ( ! is_user_logged_in() ) {
			return hp\rest_error( 401 );
		}

		// Get vendor.
		$vendor = hivepress()->gallery->get_current_vendor();

		if ( empty( $vendor ) ) {
			return hp\rest_error( 403, esc_html__( 'Only vendors can create gallery folders.', 'additional-gallery-for-hivepress' ) );
		}

		// Check gallery access.
		if ( ! hivepress()->gallery->vendor_can_use_gallery( $vendor ) ) {
			return hp\rest_error( 403, esc_html__( 'Your current membership does not include the gallery feature.', 'additional-gallery-for-hivepress' ) );
		}

		// Check folder limit.
		$max_folders = hp_agl_int( get_option( 'hp_gallery_max_folders' ) );

		if ( $max_folders ) {
			$folder_count = Models\Gallery_Folder::query()->filter(
				[
					'status' => 'publish',
					'vendor' => $vendor->get_id(),
				]
			)->get_count();

			if ( $folder_count >= $max_folders ) {
				/* translators: %s: folders number. */
				return hp\rest_error( 403, sprintf( esc_html__( 'Only up to %s folders can be created.', 'additional-gallery-for-hivepress' ), number_format_i18n( $max_folders ) ) );
			}
		}

		// Validate form.
		$form = new Forms\Gallery_Folder_Create();

		$form->set_values( $request->get_params() );

		if ( ! $form->validate() ) {
			return hp\rest_error( 400, $form->get_errors() );
		}

		// Create folder.
		$folder = new Models\Gallery_Folder();

		$folder->fill(
			array_merge(
				$form->get_values(),
				[
					'status'     => 'publish',
					'user'       => get_current_user_id(),
					'vendor'     => $vendor->get_id(),
					'sort_order' => Models\Gallery_Folder::query()->filter(
						[
							'status' => 'publish',
							'vendor' => $vendor->get_id(),
						]
					)->get_count(),
				]
			)
		);

		if ( ! $folder->save() ) {
			return hp\rest_error( 400, $folder->_get_errors() );
		}

		return hp\rest_response(
			201,
			[
				'id' => $folder->get_id(),
			]
		);
	}

	/**
	 * Updates a gallery folder.
	 *
	 * @param \WP_REST_Request $request API request.
	 * @return \WP_REST_Response
	 */
	public function update_gallery_folder( $request ) {

		// Check authentication.
		if ( ! is_user_logged_in() ) {
			return hp\rest_error( 401 );
		}

		// Get folder.
		$folder = Models\Gallery_Folder::query()->get_by_id( $request->get_param( 'gallery_folder_id' ) );

		if ( empty( $folder ) || 'publish' !== $folder->get_status() ) {
			return hp\rest_error( 404 );
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_others_posts' ) && get_current_user_id() !== $folder->get_user__id() ) {
			return hp\rest_error( 403 );
		}

		// Check gallery access.
		if ( ! current_user_can( 'edit_others_posts' ) && ! hivepress()->gallery->vendor_can_use_gallery( hivepress()->gallery->get_current_vendor() ) ) {
			return hp\rest_error( 403, esc_html__( 'Your current membership does not include the gallery feature.', 'additional-gallery-for-hivepress' ) );
		}

		// Validate form.
		$form = new Forms\Gallery_Folder_Update( [ 'model' => $folder ] );

		$form->set_values( $request->get_params() );

		if ( ! $form->validate() ) {
			return hp\rest_error( 400, $form->get_errors() );
		}

		// Review the photos with AI, if enabled. The check fails open:
		// when the service is unavailable the save proceeds unchecked.
		if ( get_option( 'hp_gallery_ai_moderation' ) ) {
			$image_urls = hivepress()->gallery->prepare_moderation_urls( (array) $folder->get_images__id() );

			if ( $image_urls && true === hivepress()->gallery->moderate_image_urls( $image_urls ) ) {
				return hp\rest_error( 400, esc_html__( 'One or more of your photos appears to contain inappropriate content. Please replace or remove it and try again.', 'additional-gallery-for-hivepress' ) );
			}
		}

		// Update folder.
		$folder->fill( $form->get_values() );

		if ( ! $folder->save() ) {
			return hp\rest_error( 400, $folder->_get_errors() );
		}

		return hp\rest_response(
			200,
			[
				'id' => $folder->get_id(),
			]
		);
	}

	/**
	 * Reorders a gallery folder.
	 *
	 * Called by the core sortable component, which posts the new position
	 * of every folder after a drag.
	 *
	 * @param \WP_REST_Request $request API request.
	 * @return \WP_REST_Response
	 */
	public function sort_gallery_folder( $request ) {

		// Check authentication.
		if ( ! is_user_logged_in() ) {
			return hp\rest_error( 401 );
		}

		// Get folder.
		$folder = Models\Gallery_Folder::query()->get_by_id( $request->get_param( 'gallery_folder_id' ) );

		if ( empty( $folder ) || 'publish' !== $folder->get_status() ) {
			return hp\rest_error( 404 );
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_others_posts' ) && get_current_user_id() !== $folder->get_user__id() ) {
			return hp\rest_error( 403 );
		}

		// Update the sort order.
		$folder->set_sort_order( hp_agl_int( $request->get_param( 'sort_order' ) ) );

		if ( ! $folder->save() ) {
			return hp\rest_error( 400, $folder->_get_errors() );
		}

		return hp\rest_response(
			200,
			[
				'id' => $folder->get_id(),
			]
		);
	}

	/**
	 * Updates a gallery image.
	 *
	 * Saves the image description entered on the folder edit page as the
	 * attachment caption, and mirrors it to the image alt text.
	 *
	 * @param \WP_REST_Request $request API request.
	 * @return \WP_REST_Response
	 */
	public function update_gallery_image( $request ) {

		// Check authentication.
		if ( ! is_user_logged_in() ) {
			return hp\rest_error( 401 );
		}

		// Get attachment.
		$attachment = Models\Attachment::query()->get_by_id( $request->get_param( 'attachment_id' ) );

		if ( empty( $attachment ) || 'gallery_folder' !== $attachment->get_parent_model() ) {
			return hp\rest_error( 404 );
		}

		// Get folder.
		$folder = $attachment->get_parent();

		if ( empty( $folder ) || 'publish' !== $folder->get_status() ) {
			return hp\rest_error( 404 );
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_others_posts' ) && get_current_user_id() !== $folder->get_user__id() ) {
			return hp\rest_error( 403 );
		}

		// Check gallery access.
		if ( ! current_user_can( 'edit_others_posts' ) && ! hivepress()->gallery->vendor_can_use_gallery( hivepress()->gallery->get_current_vendor() ) ) {
			return hp\rest_error( 403, esc_html__( 'Your current membership does not include the gallery feature.', 'additional-gallery-for-hivepress' ) );
		}

		// Validate the caption.
		$caption = trim( sanitize_text_field( hp_agl_string( $request->get_param( 'caption' ) ) ) );

		$caption_length = function_exists( 'mb_strlen' ) ? mb_strlen( $caption ) : strlen( $caption );

		if ( $caption_length > 500 ) {
			return hp\rest_error( 400, esc_html__( 'Image descriptions can be up to 500 characters long.', 'additional-gallery-for-hivepress' ) );
		}

		// Update the caption.
		$result = wp_update_post(
			[
				'ID'           => $attachment->get_id(),
				'post_excerpt' => $caption,
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			return hp\rest_error( 400 );
		}

		// Mirror to the image alt text.
		if ( 0 === strpos( (string) $attachment->get_mime_type(), 'image/' ) ) {
			update_post_meta( $attachment->get_id(), '_wp_attachment_image_alt', $caption );
		}

		return hp\rest_response(
			200,
			[
				'id'      => $attachment->get_id(),
				'caption' => $caption,
			]
		);
	}

	/**
	 * Deletes a gallery folder.
	 *
	 * Attached images are removed automatically: HivePress fires the
	 * `hivepress/v1/models/post/delete` action for the folder, and the core
	 * attachment component deletes all attachments linked to it.
	 *
	 * @param \WP_REST_Request $request API request.
	 * @return \WP_REST_Response
	 */
	public function delete_gallery_folder( $request ) {

		// Check authentication.
		if ( ! is_user_logged_in() ) {
			return hp\rest_error( 401 );
		}

		// Get folder.
		$folder = Models\Gallery_Folder::query()->get_by_id( $request->get_param( 'gallery_folder_id' ) );

		if ( empty( $folder ) ) {
			return hp\rest_error( 404 );
		}

		// Check permissions.
		if ( ! current_user_can( 'delete_others_posts' ) && get_current_user_id() !== $folder->get_user__id() ) {
			return hp\rest_error( 403 );
		}

		// Delete folder.
		if ( ! $folder->delete() ) {
			return hp\rest_error( 400 );
		}

		return hp\rest_response( 204 );
	}

	/**
	 * Streams a protected gallery file to authorised visitors.
	 *
	 * @return mixed
	 */
	public function redirect_gallery_file() {

		// The size is a query argument; read it directly so it does not depend
		// on the router exposing unknown query parameters. This is a public,
		// read-only file view, so no nonce is involved.
		$size = isset( $_GET['size'] ) ? sanitize_key( wp_unslash( $_GET['size'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		hivepress()->gallery->serve_protected_file( hivepress()->request->get_param( 'attachment_id' ), $size );

		// serve_protected_file() always exits; redirect home as a safeguard.
		return home_url( '/' );
	}

	/**
	 * Redirects the gallery edit page.
	 *
	 * @return mixed
	 */
	public function redirect_gallery_edit_page() {

		// Check authentication.
		if ( ! is_user_logged_in() ) {
			return hivepress()->router->get_return_url( 'user_login_page' );
		}

		// Check vendor.
		$vendor = hivepress()->gallery->get_current_vendor();

		if ( ! $vendor ) {
			return hivepress()->router->get_url( 'user_account_page' );
		}

		// Check gallery access.
		if ( ! hivepress()->gallery->vendor_can_use_gallery( $vendor ) ) {
			$upgrade_url = hivepress()->gallery->get_upgrade_url();

			return $upgrade_url ? $upgrade_url : hivepress()->router->get_url( 'user_account_page' );
		}

		return false;
	}

	/**
	 * Renders the gallery edit page.
	 *
	 * @return string
	 */
	public function render_gallery_edit_page() {

		// Get vendor.
		$vendor = hivepress()->gallery->get_current_vendor();

		// Get folders.
		$folders = Models\Gallery_Folder::query()->filter(
			[
				'status' => 'publish',
				'vendor' => $vendor->get_id(),
			]
		)->order(
			[
				'sort_order'   => 'asc',
				'created_date' => 'asc',
			]
		)->get();

		return ( new Blocks\Template(
			[
				'template' => 'gallery_edit_page',

				'context'  => [
					'vendor'          => $vendor,
					'gallery_folders' => $folders,
				],
			]
		) )->render();
	}

	/**
	 * Gets the gallery folder edit page title.
	 *
	 * @return string|null
	 */
	public function get_gallery_folder_edit_title() {
		$title = null;

		// Get folder.
		$folder = Models\Gallery_Folder::query()->get_by_id( hivepress()->request->get_param( 'gallery_folder_id' ) );

		// Set title.
		if ( $folder ) {
			$title = $folder->get_title();
		}

		// Set request context.
		hivepress()->request->set_context( 'gallery_folder', $folder );

		return $title;
	}

	/**
	 * Redirects the gallery folder edit page.
	 *
	 * @return mixed
	 */
	public function redirect_gallery_folder_edit_page() {

		// Check authentication.
		if ( ! is_user_logged_in() ) {
			return hivepress()->router->get_return_url( 'user_login_page' );
		}

		// Check folder.
		$folder = hivepress()->request->get_context( 'gallery_folder' );

		if ( empty( $folder ) || get_current_user_id() !== $folder->get_user__id() || 'publish' !== $folder->get_status() ) {
			return hivepress()->router->get_url( 'gallery_edit_page' );
		}

		// Check gallery access.
		if ( ! hivepress()->gallery->vendor_can_use_gallery( hivepress()->gallery->get_current_vendor() ) ) {
			$upgrade_url = hivepress()->gallery->get_upgrade_url();

			return $upgrade_url ? $upgrade_url : hivepress()->router->get_url( 'user_account_page' );
		}

		return false;
	}

	/**
	 * Renders the gallery folder edit page.
	 *
	 * @return string
	 */
	public function render_gallery_folder_edit_page() {
		return ( new Blocks\Template(
			[
				'template' => 'gallery_folder_edit_page',

				'context'  => [
					'gallery_folder' => hivepress()->request->get_context( 'gallery_folder' ),
				],
			]
		) )->render();
	}

	/**
	 * Gets the public gallery page title.
	 *
	 * @return string|null
	 */
	public function get_gallery_view_title() {
		$title = null;

		// Get vendor.
		$vendor = Models\Vendor::query()->get_by_id( hivepress()->request->get_param( 'vendor_id' ) );

		// Set title.
		if ( $vendor && 'publish' === $vendor->get_status() ) {
			/* translators: %s: vendor name. */
			$title = sprintf( esc_html__( 'Gallery: %s', 'additional-gallery-for-hivepress' ), $vendor->get_name() );
		} else {
			$vendor = null;
		}

		// Set request context.
		hivepress()->request->set_context( 'gallery_vendor', $vendor );

		return $title;
	}

	/**
	 * Redirects the public gallery page.
	 *
	 * @return mixed
	 */
	public function redirect_gallery_view_page() {

		// Check vendor.
		$vendor = hivepress()->request->get_context( 'gallery_vendor' );

		if ( ! $vendor || ! hivepress()->gallery->vendor_can_use_gallery( $vendor ) ) {
			return home_url( '/' );
		}

		return false;
	}

	/**
	 * Gets the folder page title.
	 *
	 * @return string|null
	 */
	public function get_gallery_folder_view_title() {
		$title  = null;
		$vendor = null;
		$folder = Models\Gallery_Folder::query()->get_by_id( hivepress()->request->get_param( 'gallery_folder_id' ) );

		if ( $folder && 'publish' === $folder->get_status() ) {

			// Get vendor.
			$vendor = Models\Vendor::query()->get_by_id( $folder->get_vendor__id() );

			if ( $vendor && 'publish' === $vendor->get_status() && $vendor->get_id() === absint( hivepress()->request->get_param( 'vendor_id' ) ) ) {

				// Set title.
				$title = $folder->get_title();
			} else {
				$vendor = null;
				$folder = null;
			}
		} else {
			$folder = null;
		}

		// Set request context.
		hivepress()->request->set_context( 'gallery_vendor', $vendor );
		hivepress()->request->set_context( 'gallery_folder', $folder );

		return $title;
	}

	/**
	 * Redirects the folder page.
	 *
	 * @return mixed
	 */
	public function redirect_gallery_folder_view_page() {

		// Check vendor.
		$vendor = hivepress()->request->get_context( 'gallery_vendor' );

		if ( ! $vendor || ! hivepress()->gallery->vendor_can_use_gallery( $vendor ) ) {
			return home_url( '/' );
		}

		// Check folder.
		$folder = hivepress()->request->get_context( 'gallery_folder' );

		if ( ! $folder || ! in_array( $folder->get_visibility(), [ 'public', 'members' ], true ) ) {
			return hivepress()->router->get_url( 'gallery_view_page', [ 'vendor_id' => $vendor->get_id() ] );
		}

		// Check the locked display.
		if ( 'members' === $folder->get_visibility() && 'hide' === hivepress()->gallery->get_locked_display() && ! hivepress()->gallery->user_can_view_member_folders( $vendor ) ) {
			return hivepress()->router->get_url( 'gallery_view_page', [ 'vendor_id' => $vendor->get_id() ] );
		}

		return false;
	}

	/**
	 * Renders the folder page.
	 *
	 * @return string
	 */
	public function render_gallery_folder_view_page() {
		return ( new Blocks\Template(
			[
				'template' => 'gallery_folder_view_page',

				'context'  => [
					'vendor'         => hivepress()->request->get_context( 'gallery_vendor' ),
					'gallery_folder' => hivepress()->request->get_context( 'gallery_folder' ),
				],
			]
		) )->render();
	}

	/**
	 * Renders the public gallery page.
	 *
	 * @return string
	 */
	public function render_gallery_view_page() {

		// Get vendor.
		$vendor = hivepress()->request->get_context( 'gallery_vendor' );

		// Get listed folders (public and members-only; never private).
		$folders = hivepress()->gallery->get_listed_folders( $vendor->get_id() );

		return ( new Blocks\Template(
			[
				'template' => 'gallery_view_page',

				'context'  => [
					'vendor'          => $vendor,
					'gallery_folders' => $folders,
				],
			]
		) )->render();
	}
}
