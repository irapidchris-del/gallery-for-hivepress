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
 *
 * Prefixed for the same reason as the gallery component: HivePress loads one
 * file per class name across all extensions, so an unprefixed
 * `controllers/class-gallery.php` risks colliding with a future core class.
 */
final class Agl_Gallery extends Controller {

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
					'gallery_folders_resource'      => [
						'path'   => '/gallery-folders',
						'method' => 'POST',
						'action' => [ $this, 'create_gallery_folder' ],
						'rest'   => true,
					],

					'gallery_folder_resource'       => [
						'base' => 'gallery_folders_resource',
						'path' => '/(?P<gallery_folder_id>\d+)',
						'rest' => true,
					],

					// REST: update a folder.
					'gallery_folder_update_action'  => [
						'base'   => 'gallery_folder_resource',
						'method' => 'POST',
						'action' => [ $this, 'update_gallery_folder' ],
						'rest'   => true,
					],

					// REST: reorder a folder.
					'gallery_folder_sort_action'    => [
						'base'   => 'gallery_folder_resource',
						'path'   => '/sort',
						'method' => 'POST',
						'action' => [ $this, 'sort_gallery_folder' ],
						'rest'   => true,
					],

					// REST: delete a folder.
					'gallery_folder_delete_action'  => [
						'base'   => 'gallery_folder_resource',
						'method' => 'DELETE',
						'action' => [ $this, 'delete_gallery_folder' ],
						'rest'   => true,
					],

					// REST: update a gallery image.
					'gallery_image_update_action'   => [
						'path'   => '/gallery-images/(?P<attachment_id>\d+)',
						'method' => 'POST',
						'action' => [ $this, 'update_gallery_image' ],
						'rest'   => true,
					],

					// REST: like or unlike a photo.
					'gallery_photo_like_action'     => [
						'path'   => '/gallery-images/(?P<attachment_id>\d+)/like',
						'method' => 'POST',
						'action' => [ $this, 'toggle_photo_like' ],
						'rest'   => true,
					],

					// REST: comment on a photo.
					'gallery_photo_comment_action'  => [
						'path'   => '/gallery-images/(?P<attachment_id>\d+)/comments',
						'method' => 'POST',
						'action' => [ $this, 'create_photo_comment' ],
						'rest'   => true,
					],

					// REST: delete a photo comment.
					'gallery_comment_delete_action' => [
						'path'   => '/gallery-comments/(?P<comment_id>\d+)',
						'method' => 'DELETE',
						'action' => [ $this, 'delete_photo_comment' ],
						'rest'   => true,
					],

					// REST: like or unlike a photo comment.
					'gallery_comment_like_action'   => [
						'path'   => '/gallery-comments/(?P<comment_id>\d+)/like',
						'method' => 'POST',
						'action' => [ $this, 'toggle_comment_like' ],
						'rest'   => true,
					],

					// REST: move a photo to another folder.
					'gallery_photo_move_action'     => [
						'path'   => '/gallery-images/(?P<attachment_id>\d+)/move',
						'method' => 'POST',
						'action' => [ $this, 'move_photo' ],
						'rest'   => true,
					],

					'gallery_photo_cover_action'    => [
						'path'   => '/gallery-images/(?P<attachment_id>\d+)/cover',
						'method' => 'POST',
						'action' => [ $this, 'set_photo_cover' ],
						'rest'   => true,
					],

					// REST: set the vendor's paid access price.
					'gallery_price_action'          => [
						'path'   => '/gallery-price',
						'method' => 'POST',
						'action' => [ $this, 'set_gallery_price' ],
						'rest'   => true,
					],

					// Account page: folder overview.
					'gallery_edit_page'             => [
						'title'    => esc_html__( 'Gallery', 'additional-gallery-for-hivepress' ),
						'base'     => 'user_account_page',
						'path'     => '/gallery',
						'redirect' => [ $this, 'redirect_gallery_edit_page' ],
						'action'   => [ $this, 'render_gallery_edit_page' ],
					],

					// Account page: edit a single folder.
					'gallery_folder_edit_page'      => [
						'base'     => 'gallery_edit_page',
						'path'     => '/(?P<gallery_folder_id>\d+)',
						'title'    => [ $this, 'get_gallery_folder_edit_title' ],
						'redirect' => [ $this, 'redirect_gallery_folder_edit_page' ],
						'action'   => [ $this, 'render_gallery_folder_edit_page' ],
					],

					// Public: stream a protected gallery file through an access check.
					'gallery_file_view_page'        => [
						'path'     => '/gallery-file/(?P<attachment_id>\d+)',
						'redirect' => [ $this, 'redirect_gallery_file' ],
					],

					// Public page: a vendor's gallery.
					'gallery_view_page'             => [
						'path'     => '/gallery/(?P<vendor_id>\d+)',
						'title'    => [ $this, 'get_gallery_view_title' ],
						'redirect' => [ $this, 'redirect_gallery_view_page' ],
						'action'   => [ $this, 'render_gallery_view_page' ],
					],

					// Public page: a single folder.
					'gallery_folder_view_page'      => [
						'base'     => 'gallery_view_page',
						'path'     => '/(?P<gallery_folder_id>\d+)',
						'title'    => [ $this, 'get_gallery_folder_view_title' ],
						'redirect' => [ $this, 'redirect_gallery_folder_view_page' ],
						'action'   => [ $this, 'render_gallery_folder_view_page' ],
					],

					// Public page: a single photo, with its comment thread.
					'gallery_photo_view_page'       => [
						'base'     => 'gallery_folder_view_page',
						'path'     => '/(?P<attachment_id>\d+)',
						'title'    => [ $this, 'get_gallery_photo_view_title' ],
						'redirect' => [ $this, 'redirect_gallery_photo_view_page' ],
						'action'   => [ $this, 'render_gallery_photo_view_page' ],
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
		$vendor = hivepress()->agl_gallery->get_current_vendor();

		if ( empty( $vendor ) ) {
			return hp\rest_error( 403, esc_html__( 'Only vendors can create gallery folders.', 'additional-gallery-for-hivepress' ) );
		}

		// Check gallery access.
		if ( ! hivepress()->agl_gallery->vendor_can_use_gallery( $vendor ) ) {
			return hp\rest_error( 403, esc_html__( 'Your current membership does not include the gallery feature.', 'additional-gallery-for-hivepress' ) );
		}

		// Check folder limit, which a membership plan may raise or lower.
		$max_folders = hivepress()->agl_gallery->get_folder_limit( $vendor );

		if ( $max_folders ) {
			$folder_count = Models\Gallery_Folder::query()->filter(
				[
					'status' => 'publish',
					'vendor' => $vendor->get_id(),
				]
			)->get_count();

			if ( $folder_count >= $max_folders ) {
				/* translators: %s: folders number. */
				return hp\rest_error( 403, sprintf( _n( 'You can create up to %s folder.', 'You can create up to %s folders.', $max_folders, 'additional-gallery-for-hivepress' ), number_format_i18n( $max_folders ) ) );
			}
		}

		// Validate form.
		$form = new Forms\Agl_Gallery_Folder_Create();

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
		if ( ! current_user_can( 'edit_others_posts' ) && ! hivepress()->agl_gallery->vendor_can_use_gallery( hivepress()->agl_gallery->get_current_vendor() ) ) {
			return hp\rest_error( 403, esc_html__( 'Your current membership does not include the gallery feature.', 'additional-gallery-for-hivepress' ) );
		}

		// Validate form.
		$form = new Forms\Agl_Gallery_Folder_Update( [ 'model' => $folder ] );

		$form->set_values( $request->get_params() );

		if ( ! $form->validate() ) {
			return hp\rest_error( 400, $form->get_errors() );
		}

		// Update folder.
		$folder->fill( $form->get_values() );

		if ( ! $folder->save() ) {
			return hp\rest_error( 400, $folder->_get_errors() );
		}

		// Review the photos with AI, if enabled. QUEUED, never run here: it is
		// one remote call per photo to a service that downloads each image
		// itself, so doing it inline held this request - and one of the site's
		// PHP workers - for as long as OpenAI took. The sibling moderation
		// plugin measured 21-32 seconds doing exactly this on a six-photo
		// listing (2026-08-19); a handful at once exhausts a small worker pool
		// and every other visitor gets a 504. A flagged folder is set to draft
		// by the queued job, so it still stops being public.
		// Nothing to send unless a photo has arrived that has not been checked before, so editing a
		// folder's title or description no longer queues a job that would find nothing to do.
		if ( get_option( 'hp_gallery_ai_moderation' ) && hivepress()->agl_gallery->get_unchecked_images( $folder ) ) {

			// hivepress()->scheduler resolves through Core::__get(), which
			// returns null for an unregistered component and defines no
			// __isset(), so this must be assigned and tested rather than
			// called straight through.
			$scheduler = hivepress()->scheduler;

			if ( $scheduler ) {
				$scheduler->add_action( 'hp_agl_review_folder_images', [ $folder->get_id() ] );
			}
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

		// Check gallery access, matching the other folder actions so a vendor
		// whose membership no longer includes the gallery cannot reorder it.
		if ( ! current_user_can( 'edit_others_posts' ) && ! hivepress()->agl_gallery->vendor_can_use_gallery( hivepress()->agl_gallery->get_current_vendor() ) ) {
			return hp\rest_error( 403, esc_html__( 'Your current membership does not include the gallery feature.', 'additional-gallery-for-hivepress' ) );
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
		if ( ! current_user_can( 'edit_others_posts' ) && ! hivepress()->agl_gallery->vendor_can_use_gallery( hivepress()->agl_gallery->get_current_vendor() ) ) {
			return hp\rest_error( 403, esc_html__( 'Your current membership does not include the gallery feature.', 'additional-gallery-for-hivepress' ) );
		}

		// Validate the caption.
		$caption = trim( sanitize_text_field( hp_agl_string( $request->get_param( 'caption' ) ) ) );

		$caption_length = function_exists( 'mb_strlen' ) ? mb_strlen( $caption ) : strlen( $caption );

		if ( $caption_length > 500 ) {
			return hp\rest_error( 400, esc_html__( 'Image descriptions can be up to 500 characters long.', 'additional-gallery-for-hivepress' ) );
		}

		// Update the caption, and the title when one was sent.
		$post_data = [
			'ID'           => $attachment->get_id(),
			'post_excerpt' => $caption,
		];

		if ( null !== $request->get_param( 'title' ) ) {
			$title = trim( sanitize_text_field( hp_agl_string( $request->get_param( 'title' ) ) ) );

			$title_length = function_exists( 'mb_strlen' ) ? mb_strlen( $title ) : strlen( $title );

			if ( $title_length > 128 ) {
				return hp\rest_error( 400, esc_html__( 'Photo titles can be up to 128 characters long.', 'additional-gallery-for-hivepress' ) );
			}

			$post_data['post_title'] = $title;
		}

		$result = wp_update_post( $post_data, true );

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
	 * Resolves a photo the current user is allowed to see, for the engagement
	 * endpoints.
	 *
	 * Reuses the same access check the protected-file proxy uses, so a photo in
	 * a folder someone cannot open can be neither liked nor commented on, and
	 * its comments cannot be read.
	 *
	 * @param mixed $attachment_id Attachment ID.
	 * @return \HivePress\Models\Attachment|null
	 */
	protected function get_visible_photo( $attachment_id ) {
		$attachment = Models\Attachment::query()->get_by_id( $attachment_id );

		if ( empty( $attachment ) || 'gallery_folder' !== $attachment->get_parent_model() ) {
			return null;
		}

		if ( ! hivepress()->agl_gallery->can_access_attachment( $attachment->get_id() ) ) {
			return null;
		}

		return $attachment;
	}

	/**
	 * Likes or unlikes a gallery photo.
	 *
	 * @param \WP_REST_Request $request API request.
	 * @return \WP_REST_Response
	 */
	public function toggle_photo_like( $request ) {

		// Check authentication.
		if ( ! is_user_logged_in() ) {
			return hp\rest_error( 401 );
		}

		// Check the feature.
		if ( ! hivepress()->agl_gallery->are_likes_enabled() ) {
			return hp\rest_error( 403 );
		}

		// Get the photo.
		$photo = $this->get_visible_photo( $request->get_param( 'attachment_id' ) );

		if ( ! $photo ) {
			return hp\rest_error( 404 );
		}

		$user_id = get_current_user_id();

		// Find an existing like.
		$like = Models\Agl_Like::query()->filter(
			[
				'user'  => $user_id,
				'photo' => $photo->get_id(),
			]
		)->get_first();

		if ( $like ) {
			$like->delete();

			$liked = false;
		} else {
			$like = ( new Models\Agl_Like() )->fill(
				[
					'user'     => $user_id,
					'photo'    => $photo->get_id(),
					'approved' => 1,
				]
			);

			if ( ! $like->save() ) {
				return hp\rest_error( 400, $like->_get_errors() );
			}

			$liked = true;
		}

		$counts = hivepress()->agl_gallery->get_engagement_counts( [ $photo->get_id() ], true );

		if ( $liked ) {
			$folder = $photo->get_parent();

			/**
			 * Fires when someone likes a gallery photo. Hook here to notify
			 * the photo's owner.
			 *
			 * @hook hp_agl/photo_liked
			 * @param {int} $photo_id Attachment ID.
			 * @param {int} $user_id Liker user ID.
			 * @param {int} $owner_id Folder owner user ID.
			 */
			do_action( 'hp_agl/photo_liked', $photo->get_id(), $user_id, $folder ? absint( $folder->get_user__id() ) : 0 );
		}

		return hp\rest_response(
			200,
			[
				'id'    => $photo->get_id(),
				'liked' => $liked,
				'count' => hp_agl_int( hp\get_array_value( $counts[ $photo->get_id() ], 'likes' ) ),
			]
		);
	}

	/**
	 * Adds a comment to a gallery photo.
	 *
	 * @param \WP_REST_Request $request API request.
	 * @return \WP_REST_Response
	 */
	public function create_photo_comment( $request ) {

		// Check authentication.
		if ( ! is_user_logged_in() ) {
			return hp\rest_error( 401 );
		}

		// Check the feature.
		if ( ! hivepress()->agl_gallery->are_comments_enabled() ) {
			return hp\rest_error( 403 );
		}

		// Get the photo.
		$photo = $this->get_visible_photo( $request->get_param( 'attachment_id' ) );

		if ( ! $photo ) {
			return hp\rest_error( 404 );
		}

		// Get the author.
		$user = get_userdata( get_current_user_id() );

		if ( ! $user ) {
			return hp\rest_error( 401 );
		}

		// Resolve the reply target. Replies stay one level deep: replying to a
		// reply attaches to its top-level parent, the way Reviews does it.
		$parent_id = absint( $request->get_param( 'parent' ) );

		if ( $parent_id ) {
			$parent = get_comment( $parent_id );

			if ( ! $parent || 'hp_agl_comment' !== $parent->comment_type || absint( $parent->comment_post_ID ) !== absint( $photo->get_id() ) ) {
				return hp\rest_error( 400 );
			}

			if ( absint( $parent->comment_parent ) ) {
				$parent_id = absint( $parent->comment_parent );
			}
		}

		// Create the comment. The model validates length and requires the text.
		$comment_data = [
			'text'                 => hp_agl_string( $request->get_param( 'text' ) ),
			'photo'                => $photo->get_id(),
			'author'               => $user->ID,
			'author__display_name' => $user->display_name,
			'author__email'        => $user->user_email,
			'approved'             => 1,
		];

		$comment = new Models\Agl_Comment();

		if ( $parent_id ) {
			$comment_data['parent'] = $parent_id;
		}

		$comment->fill( $comment_data );

		if ( ! $comment->save() ) {
			return hp\rest_error( 400, $comment->_get_errors() );
		}

		$folder   = $photo->get_parent();
		$owner_id = $folder ? absint( $folder->get_user__id() ) : 0;

		if ( $parent_id ) {
			$parent = get_comment( $parent_id );

			/**
			 * Fires when someone replies to a gallery photo comment. Hook here
			 * to notify the original commenter.
			 *
			 * @hook hp_agl/comment_replied
			 * @param {int} $comment_id New reply comment ID.
			 * @param {int} $parent_id Parent comment ID.
			 * @param {int} $parent_author_id Parent comment author user ID.
			 * @param {int} $photo_id Attachment ID.
			 */
			do_action( 'hp_agl/comment_replied', $comment->get_id(), $parent_id, $parent ? absint( $parent->user_id ) : 0, $photo->get_id() );
		} else {

			/**
			 * Fires when someone comments on a gallery photo. Hook here to
			 * notify the photo's owner.
			 *
			 * @hook hp_agl/photo_commented
			 * @param {int} $comment_id Comment ID.
			 * @param {int} $photo_id Attachment ID.
			 * @param {int} $user_id Commenter user ID.
			 * @param {int} $owner_id Folder owner user ID.
			 */
			do_action( 'hp_agl/photo_commented', $comment->get_id(), $photo->get_id(), $user->ID, $owner_id );
		}

		return hp\rest_response(
			201,
			[
				'id'   => $comment->get_id(),
				'html' => hivepress()->agl_gallery->render_photo_comment_thread( $photo->get_id() ),
			]
		);
	}

	/**
	 * Deletes a gallery photo comment.
	 *
	 * @param \WP_REST_Request $request API request.
	 * @return \WP_REST_Response
	 */
	public function delete_photo_comment( $request ) {

		// Check authentication.
		if ( ! is_user_logged_in() ) {
			return hp\rest_error( 401 );
		}

		// Get the comment.
		$comment = get_comment( absint( $request->get_param( 'comment_id' ) ) );

		if ( ! $comment || 'hp_agl_comment' !== $comment->comment_type ) {
			return hp\rest_error( 404 );
		}

		// Check permissions.
		if ( ! hivepress()->agl_gallery->can_delete_photo_comment( $comment ) ) {
			return hp\rest_error( 403 );
		}

		$photo_id = absint( $comment->comment_post_ID );

		// Replies and likes go with the comment; WordPress would otherwise
		// re-parent the replies into top-level comments.
		hivepress()->agl_gallery->delete_comment_children( absint( $comment->comment_ID ) );

		if ( ! wp_delete_comment( absint( $comment->comment_ID ), true ) ) {
			return hp\rest_error( 400 );
		}

		return hp\rest_response(
			200,
			[
				'id'   => $photo_id,
				'html' => hivepress()->agl_gallery->render_photo_comment_thread( $photo_id ),
			]
		);
	}

	/**
	 * Likes or unlikes a photo comment.
	 *
	 * @param \WP_REST_Request $request API request.
	 * @return \WP_REST_Response
	 */
	public function toggle_comment_like( $request ) {

		// Check authentication.
		if ( ! is_user_logged_in() ) {
			return hp\rest_error( 401 );
		}

		// Check the features. Comment likes ride both toggles.
		if ( ! hivepress()->agl_gallery->are_likes_enabled() || ! hivepress()->agl_gallery->are_comments_enabled() ) {
			return hp\rest_error( 403 );
		}

		// Get the comment.
		$comment = get_comment( absint( $request->get_param( 'comment_id' ) ) );

		if ( ! $comment || 'hp_agl_comment' !== $comment->comment_type ) {
			return hp\rest_error( 404 );
		}

		// The photo's access rules cover its comments too.
		$photo = $this->get_visible_photo( absint( $comment->comment_post_ID ) );

		if ( ! $photo ) {
			return hp\rest_error( 404 );
		}

		$user_id    = get_current_user_id();
		$comment_id = absint( $comment->comment_ID );

		/*
		 * Find an existing like of this comment.
		 *
		 * 'status' => 'any' is deliberate. A like that an administrator has trashed still occupies
		 * the pair, so hiding it here would let a second row be created for the same person and
		 * the same comment, leaving the trashed one orphaned for good. Seeing it means the toggle
		 * force-deletes it and the next click creates a clean one.
		 */
		$existing = get_comments(
			[
				'type'    => 'hp_agl_clike',
				'parent'  => $comment_id,
				'user_id' => $user_id,
				'status'  => 'any',
				'fields'  => 'ids',
				'number'  => 1,
			]
		);

		if ( $existing ) {
			wp_delete_comment( absint( $existing[0] ), true );

			$liked = false;
		} else {
			$like = ( new Models\Agl_Clike() )->fill(
				[
					'user'     => $user_id,
					'photo'    => $photo->get_id(),
					'parent'   => $comment_id,
					'approved' => 1,
				]
			);

			if ( ! $like->save() ) {
				return hp\rest_error( 400, $like->_get_errors() );
			}

			$liked = true;

			/**
			 * Fires when someone likes a gallery photo comment. Hook here to
			 * notify the comment's author.
			 *
			 * @hook hp_agl/comment_liked
			 * @param {int} $comment_id Comment ID.
			 * @param {int} $user_id Liker user ID.
			 * @param {int} $author_id Comment author user ID.
			 */
			do_action( 'hp_agl/comment_liked', $comment_id, $user_id, absint( $comment->user_id ) );
		}

		$like_data = hivepress()->agl_gallery->get_comment_like_data( $photo->get_id() );

		return hp\rest_response(
			200,
			[
				'id'    => $comment_id,
				'liked' => $liked,
				'count' => isset( $like_data['counts'][ $comment_id ] ) ? hp_agl_int( $like_data['counts'][ $comment_id ] ) : 0,
			]
		);
	}

	/**
	 * Makes a photo its folder's cover.
	 *
	 * @param \WP_REST_Request $request API request.
	 * @return \WP_REST_Response
	 */
	public function set_photo_cover( $request ) {

		// Check authentication.
		if ( ! is_user_logged_in() ) {
			return hp\rest_error( 401 );
		}

		// Get the photo and its folder.
		$attachment = Models\Attachment::query()->get_by_id( $request->get_param( 'attachment_id' ) );

		if ( empty( $attachment ) || 'gallery_folder' !== $attachment->get_parent_model() ) {
			return hp\rest_error( 404 );
		}

		$folder = $attachment->get_parent();

		if ( empty( $folder ) ) {
			return hp\rest_error( 404 );
		}

		// Check permissions, matching the other photo actions.
		if ( ! current_user_can( 'edit_others_posts' ) && get_current_user_id() !== $folder->get_user__id() ) {
			return hp\rest_error( 403 );
		}

		if ( ! current_user_can( 'edit_others_posts' ) && ! hivepress()->agl_gallery->vendor_can_use_gallery( hivepress()->agl_gallery->get_current_vendor() ) ) {
			return hp\rest_error( 403, esc_html__( 'Your current membership does not include the gallery feature.', 'additional-gallery-for-hivepress' ) );
		}

		// A video cannot be a cover, because the grid shows a still.
		if ( 0 !== strpos( (string) get_post_mime_type( $attachment->get_id() ), 'image' ) ) {
			return hp\rest_error( 400, esc_html__( 'Only a photo can be used as the folder cover.', 'additional-gallery-for-hivepress' ) );
		}

		if ( ! hivepress()->agl_gallery->set_folder_cover( $folder, $attachment->get_id() ) ) {
			return hp\rest_error( 400 );
		}

		return hp\rest_response(
			200,
			[
				'id'     => $attachment->get_id(),
				'folder' => $folder->get_id(),
			]
		);
	}

	/**
	 * Moves a photo to another folder of the same vendor.
	 *
	 * @param \WP_REST_Request $request API request.
	 * @return \WP_REST_Response
	 */
	public function move_photo( $request ) {

		// Check authentication.
		if ( ! is_user_logged_in() ) {
			return hp\rest_error( 401 );
		}

		// Get the photo and its current folder.
		$attachment = Models\Attachment::query()->get_by_id( $request->get_param( 'attachment_id' ) );

		if ( empty( $attachment ) || 'gallery_folder' !== $attachment->get_parent_model() ) {
			return hp\rest_error( 404 );
		}

		$folder = $attachment->get_parent();

		if ( empty( $folder ) ) {
			return hp\rest_error( 404 );
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_others_posts' ) && get_current_user_id() !== $folder->get_user__id() ) {
			return hp\rest_error( 403 );
		}

		// Check gallery access, matching the other folder actions so a vendor
		// whose membership no longer includes the gallery cannot reorganise it.
		if ( ! current_user_can( 'edit_others_posts' ) && ! hivepress()->agl_gallery->vendor_can_use_gallery( hivepress()->agl_gallery->get_current_vendor() ) ) {
			return hp\rest_error( 403, esc_html__( 'Your current membership does not include the gallery feature.', 'additional-gallery-for-hivepress' ) );
		}

		// Get the target folder, which must belong to the same owner.
		$target = Models\Gallery_Folder::query()->get_by_id( $request->get_param( 'folder' ) );

		if ( empty( $target ) || 'publish' !== $target->get_status() || $target->get_user__id() !== $folder->get_user__id() ) {
			return hp\rest_error( 400, esc_html__( 'Choose one of your own folders to move the photo to.', 'additional-gallery-for-hivepress' ) );
		}

		if ( $target->get_id() === $folder->get_id() ) {
			return hp\rest_response(
				200,
				[
					'id'     => $attachment->get_id(),
					'folder' => $target->get_id(),
				]
			);
		}

		// Respect the target folder's photo limit.
		$limit = hivepress()->agl_gallery->get_image_limit( $target->get_user__id() );

		if ( $limit && count( (array) $target->get_images__id() ) >= $limit ) {
			/* translators: %s: images number. */
			return hp\rest_error( 400, sprintf( esc_html__( 'That folder is full (up to %s photos).', 'additional-gallery-for-hivepress' ), number_format_i18n( $limit ) ) );
		}

		// Move the photo, appending it at the end of the target folder.
		$result = wp_update_post(
			[
				'ID'          => $attachment->get_id(),
				'post_parent' => $target->get_id(),
				'menu_order'  => count( (array) $target->get_images__id() ),
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			return hp\rest_error( 400 );
		}

		// The attachment save only busts the NEW parent's cached image list,
		// so the old folder's is cleared by hand or the photo would still
		// appear there until the cache expired.
		hivepress()->cache->delete_post_cache( $folder->get_id(), 'image_ids', 'models/attachment' );

		// Both folders re-check protection: the visibility may differ.
		hivepress()->agl_gallery->sync_folder_protection( $folder->get_id() );
		hivepress()->agl_gallery->sync_folder_protection( $target->get_id() );

		return hp\rest_response(
			200,
			[
				'id'     => $attachment->get_id(),
				'folder' => $target->get_id(),
			]
		);
	}

	/**
	 * Sets the current vendor's paid gallery access price.
	 *
	 * @param \WP_REST_Request $request API request.
	 * @return \WP_REST_Response
	 */
	public function set_gallery_price( $request ) {

		// Check authentication.
		if ( ! is_user_logged_in() ) {
			return hp\rest_error( 401 );
		}

		// Check the feature.
		if ( ! hivepress()->agl_gallery->is_paid_access_enabled() ) {
			return hp\rest_error( 403 );
		}

		// Get the vendor.
		$vendor = hivepress()->agl_gallery->get_current_vendor();

		if ( empty( $vendor ) || ! hivepress()->agl_gallery->vendor_can_use_gallery( $vendor ) ) {
			return hp\rest_error( 403 );
		}

		/*
		 * Which folder's prices these are, under the "each folder separately" scope. The folder is
		 * re-checked here rather than trusted from the form: the endpoint is public, and a folder ID
		 * belonging to somebody else would otherwise let one vendor set another vendor's prices.
		 */
		$folder = null;

		if ( hivepress()->agl_gallery->is_folder_access_scope() ) {
			$folder = Models\Gallery_Folder::query()->get_by_id( $request->get_param( 'folder' ) );

			if ( empty( $folder ) || get_current_user_id() !== $folder->get_user__id() || absint( $folder->get_vendor__id() ) !== absint( $vendor->get_id() ) ) {
				return hp\rest_error( 403 );
			}
		}

		/*
		 * Validate the whole set before saving any of it. Saving row by row would leave a vendor who
		 * mistyped one figure with some lengths saved and some not, and an error that does not say
		 * which.
		 */
		$days   = (array) $request->get_param( 'days' );
		$prices = (array) $request->get_param( 'price' );
		$max    = hivepress()->agl_gallery::MAX_TIERS;

		if ( count( $days ) !== count( $prices ) ) {
			return hp\rest_error( 400 );
		}

		if ( count( $days ) > $max ) {
			return hp\rest_error(
				400,
				esc_html(
					sprintf(
						/* translators: %s: number of lengths. */
						_n( 'You can offer %s length at a time.', 'You can offer up to %s lengths at a time.', $max, 'additional-gallery-for-hivepress' ),
						number_format_i18n( $max )
					)
				)
			);
		}

		$rows = [];
		$seen = [];

		foreach ( $days as $index => $day ) {
			$price = hp\get_array_value( $prices, $index );

			// A row left blank is a row the vendor has not filled in, not an error.
			if ( '' === $price || is_null( $price ) ) {
				continue;
			}

			if ( ! is_numeric( $price ) ) {
				return hp\rest_error( 400, esc_html__( 'Enter a valid price, or clear the row to stop selling that length.', 'additional-gallery-for-hivepress' ) );
			}

			$price = (float) $price;

			if ( $price <= 0 || $price > 100000 ) {
				return hp\rest_error( 400, esc_html__( 'Enter a valid price, or clear the row to stop selling that length.', 'additional-gallery-for-hivepress' ) );
			}

			$day = absint( $day );

			if ( isset( $seen[ $day ] ) ) {
				return hp\rest_error( 400, esc_html__( 'You have the same length listed twice. Each length can only have one price.', 'additional-gallery-for-hivepress' ) );
			}

			$seen[ $day ] = true;

			$rows[] = [
				'days'  => $day,
				'price' => $price,
			];
		}

		/*
		 * Written to the slots in order, and every slot beyond what was sent is cleared, which is how
		 * removing a row stops that length being sold. set_access_tier() drafts the product behind a
		 * cleared slot rather than deleting it, so somebody's order history still resolves.
		 */
		for ( $tier = 1; $tier <= $max; $tier++ ) {
			$row = hp\get_array_value( $rows, $tier - 1 );

			$saved = hivepress()->agl_gallery->set_access_tier(
				$vendor,
				$tier,
				$row ? $row['days'] : 0,
				$row ? $row['price'] : 0,
				$folder
			);

			if ( ! $saved ) {
				return hp\rest_error( 400 );
			}
		}

		return hp\rest_response(
			200,
			[
				'id'    => $folder ? $folder->get_id() : $vendor->get_id(),
				'tiers' => $rows,
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

		hivepress()->agl_gallery->serve_protected_file( hivepress()->request->get_param( 'attachment_id' ), $size );

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
		$vendor = hivepress()->agl_gallery->get_current_vendor();

		if ( ! $vendor ) {
			return hivepress()->router->get_url( 'user_account_page' );
		}

		// Check gallery access.
		if ( ! hivepress()->agl_gallery->vendor_can_use_gallery( $vendor ) ) {
			$upgrade_url = hivepress()->agl_gallery->get_upgrade_url();

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
		$vendor = hivepress()->agl_gallery->get_current_vendor();

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
				'template' => 'agl_gallery_edit_page',

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
		if ( ! hivepress()->agl_gallery->vendor_can_use_gallery( hivepress()->agl_gallery->get_current_vendor() ) ) {
			$upgrade_url = hivepress()->agl_gallery->get_upgrade_url();

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
				'template' => 'agl_gallery_folder_edit_page',

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

		if ( ! $vendor || ! hivepress()->agl_gallery->vendor_can_use_gallery( $vendor ) ) {
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

		if ( ! $vendor || ! hivepress()->agl_gallery->vendor_can_use_gallery( $vendor ) ) {
			return home_url( '/' );
		}

		// Check folder.
		$folder = hivepress()->request->get_context( 'gallery_folder' );

		if ( ! $folder ) {
			return hivepress()->router->get_url( 'gallery_view_page', [ 'vendor_id' => $vendor->get_id() ] );
		}

		/*
		 * The person who owns a private folder can open it. Everyone was refused before, including
		 * them, and the refusal was a silent bounce to the gallery index -- so a vendor following
		 * their own link landed somewhere else with nothing to say why, and no way to see the folder
		 * outside the account area. Site owners get the same allowance, through the capability the
		 * rest of the plugin already uses for this.
		 */
		$user_id  = get_current_user_id();
		$is_owner = current_user_can( 'edit_others_posts' ) || ( $user_id && $user_id === $folder->get_user__id() );

		if ( ! $is_owner && ! in_array( hivepress()->agl_gallery->get_effective_visibility( $folder ), [ 'public', 'members' ], true ) ) {
			return hivepress()->router->get_url( 'gallery_view_page', [ 'vendor_id' => $vendor->get_id() ] );
		}

		// Check the locked display.
		if ( 'members' === hivepress()->agl_gallery->get_effective_visibility( $folder ) && 'hide' === hivepress()->agl_gallery->get_locked_display() && ! hivepress()->agl_gallery->user_can_view_folder( $folder, $vendor ) ) {
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
				'template' => 'agl_gallery_folder_view_page',

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

		/*
		 * Listed folders: public and members-only for everybody, plus the vendor's private folders
		 * when the vendor themselves or a site owner is the one looking. A vendor could already open
		 * a private folder's own page - the folder redirect has allowed that since 1.8.x - but their
		 * own gallery index pretended those folders did not exist, so the only route to one was a URL
		 * they had to have kept. Each is badged Private on the page and a note under the grid says
		 * plainly that visitors see none of them.
		 */
		$folders = hivepress()->agl_gallery->get_listed_folders( $vendor->get_id(), hivepress()->agl_gallery->can_manage_gallery( $vendor ) );

		return ( new Blocks\Template(
			[
				'template' => 'agl_gallery_view_page',

				'context'  => [
					'vendor'          => $vendor,
					'gallery_folders' => $folders,
				],
			]
		) )->render();
	}

	/**
	 * Gets the photo page title.
	 *
	 * Also resolves and stores every object the page needs: a child route
	 * inherits only its parent's path, never its callbacks, so nothing from the
	 * folder route has run.
	 *
	 * @return string|null
	 */
	public function get_gallery_photo_view_title() {
		$title  = null;
		$vendor = null;
		$folder = Models\Gallery_Folder::query()->get_by_id( hivepress()->request->get_param( 'gallery_folder_id' ) );
		$photo  = null;

		if ( $folder && 'publish' === $folder->get_status() ) {
			$vendor = Models\Vendor::query()->get_by_id( $folder->get_vendor__id() );

			if ( $vendor && 'publish' === $vendor->get_status() && $vendor->get_id() === absint( hivepress()->request->get_param( 'vendor_id' ) ) ) {
				$attachment = Models\Attachment::query()->get_by_id( hivepress()->request->get_param( 'attachment_id' ) );

				if ( $attachment && 'gallery_folder' === $attachment->get_parent_model() && absint( $attachment->get_parent__id() ) === absint( $folder->get_id() ) ) {
					$photo = $attachment;
					$title = hivepress()->agl_gallery->get_photo_title( $folder, $photo->get_id() );
				}
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
		hivepress()->request->set_context( 'gallery_photo', $photo );

		return $title;
	}

	/**
	 * Redirects the photo page.
	 *
	 * @return mixed
	 */
	public function redirect_gallery_photo_view_page() {

		// Check vendor and folder.
		$vendor = hivepress()->request->get_context( 'gallery_vendor' );
		$folder = hivepress()->request->get_context( 'gallery_folder' );
		$photo  = hivepress()->request->get_context( 'gallery_photo' );

		if ( ! $vendor || ! hivepress()->agl_gallery->vendor_can_use_gallery( $vendor ) ) {
			return home_url( '/' );
		}

		if ( ! $folder || ! $photo ) {
			return hivepress()->router->get_url( 'gallery_view_page', [ 'vendor_id' => $vendor->get_id() ] );
		}

		// A photo page is only for photos the visitor can actually see: a
		// locked folder shows teasers on its folder page instead, so its photo
		// pages bounce back there.
		if ( ! hivepress()->agl_gallery->can_access_attachment( $photo->get_id() ) ) {
			return hivepress()->router->get_url(
				'gallery_folder_view_page',
				[
					'vendor_id'         => $vendor->get_id(),
					'gallery_folder_id' => $folder->get_id(),
				]
			);
		}

		return false;
	}

	/**
	 * Renders the photo page.
	 *
	 * @return string
	 */
	public function render_gallery_photo_view_page() {
		return ( new Blocks\Template(
			[
				'template' => 'agl_gallery_photo_view_page',

				'context'  => [
					'vendor'         => hivepress()->request->get_context( 'gallery_vendor' ),
					'gallery_folder' => hivepress()->request->get_context( 'gallery_folder' ),
					'gallery_photo'  => hivepress()->request->get_context( 'gallery_photo' ),
				],
			]
		) )->render();
	}
}
