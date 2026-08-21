<?php
/**
 * Scripts configuration.
 *
 * Merged into the HivePress `scripts` config, so the script is enqueued and
 * localized by the core asset component (which exposes the `data` array as
 * `hivepressGalleryFrontendData`). Only the account routes with the copy
 * button and the delete form need it.
 *
 * @package AdditionalGalleryForHivePress\Configs
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Append the file modification time to the version. WordPress cache-busts on
// `?ver=`, so a version that only changes on release serves stale JS from
// browsers that already cached it, and fixes appear to silently revert.
$hp_agl_script         = HP_AGL_DIR . '/assets/js/frontend.js';
$hp_agl_script_version = HP_AGL_VERSION . ( file_exists( $hp_agl_script ) ? '.' . filemtime( $hp_agl_script ) : '' );

return [
	'gallery_frontend' => [
		'handle'  => 'hivepress-gallery-frontend',
		'src'     => HP_AGL_URL . '/assets/js/frontend.js',
		'version' => $hp_agl_script_version,
		'deps'    => [ 'jquery', 'jquery-ui-sortable' ],

		// The public gallery pages are included because the like and comment
		// controls live there.
		'scope'   => [
			'gallery_edit_page',
			'gallery_folder_edit_page',
			'gallery_view_page',
			'gallery_folder_view_page',
			'gallery_photo_view_page',
		],

		'data'    => [
			'deleteConfirm'        => esc_html__( 'Are you sure you want to delete this folder? All of its images will be permanently removed.', 'additional-gallery-for-hivepress' ),
			'copied'               => esc_html__( 'Link copied!', 'additional-gallery-for-hivepress' ),
			'like'                 => esc_html__( 'Like this photo', 'additional-gallery-for-hivepress' ),
			'unlike'               => esc_html__( 'Remove your like', 'additional-gallery-for-hivepress' ),
			'signInToComment'      => esc_html__( 'Please sign in to comment.', 'additional-gallery-for-hivepress' ),
			'commentFailed'        => esc_html__( 'Your comment could not be posted. Please try again.', 'additional-gallery-for-hivepress' ),
			'commentDeleteConfirm' => esc_html__( 'Delete this comment?', 'additional-gallery-for-hivepress' ),
			'replyPlaceholder'     => esc_html__( 'Write a reply...', 'additional-gallery-for-hivepress' ),
			'postReply'            => esc_html__( 'Post Reply', 'additional-gallery-for-hivepress' ),
			'photoDeleteConfirm'   => esc_html__( 'Delete this photo? Its likes and comments go with it.', 'additional-gallery-for-hivepress' ),
			'moveConfirm'          => esc_html__( 'Move this photo to the selected folder?', 'additional-gallery-for-hivepress' ),
			'saved'                => esc_html__( 'Saved.', 'additional-gallery-for-hivepress' ),
			'saveFailed'           => esc_html__( 'Your changes could not be saved.', 'additional-gallery-for-hivepress' ),
			'moveFailed'           => esc_html__( 'The photo could not be moved.', 'additional-gallery-for-hivepress' ),
			'priceSaved'           => esc_html__( 'Price saved.', 'additional-gallery-for-hivepress' ),
			'priceFailed'          => esc_html__( 'The price could not be saved.', 'additional-gallery-for-hivepress' ),
			'loginUrl'             => hivepress()->router->get_return_url( 'user_login_page' ),
		],
	],
];
