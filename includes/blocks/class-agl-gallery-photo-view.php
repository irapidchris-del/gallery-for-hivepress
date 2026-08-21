<?php
/**
 * Gallery photo view block.
 *
 * @package AdditionalGalleryForHivePress\Blocks
 */

namespace HivePress\Blocks;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Renders the content column of a single photo's page: the image, its
 * description, the like bar, previous/next navigation, and the comment thread
 * with an inline composer beneath it. The owner's management options live in
 * the sidebar (see Agl_Gallery_Photo_Manage).
 */
class Agl_Gallery_Photo_View extends Block {

	/**
	 * Renders block HTML.
	 *
	 * @return string
	 */
	public function render() {
		$output = '';

		// Get context.
		$vendor = $this->get_context( 'vendor' );
		$folder = $this->get_context( 'gallery_folder' );
		$photo  = $this->get_context( 'gallery_photo' );

		if ( ! $vendor instanceof \HivePress\Models\Vendor || ! $folder instanceof \HivePress\Models\Gallery_Folder || ! $photo instanceof \HivePress\Models\Attachment ) {
			return $output;
		}

		$gallery  = hivepress()->agl_gallery;
		$photo_id = $photo->get_id();

		$output .= '<div class="hp-agl-photo">';

		// Back link.
		$folder_url = hivepress()->router->get_url(
			'gallery_folder_view_page',
			[
				'vendor_id'         => $vendor->get_id(),
				'gallery_folder_id' => $folder->get_id(),
			]
		);

		/* translators: %s: folder name. */
		$output .= '<p class="hp-agl-gallery__profile"><a href="' . esc_url( $folder_url ) . '" class="hp-link"><i class="hp-icon fas fa-arrow-left"></i> ' . esc_html( sprintf( __( 'Back to %s', 'additional-gallery-for-hivepress' ), $folder->get_title() ) ) . '</a></p>';

		// The image (or video). Protected files resolve through the proxy
		// automatically via the attachment URL filters.
		$caption = get_post_field( 'post_excerpt', $photo_id );
		$caption = is_string( $caption ) ? trim( $caption ) : '';

		$output .= '<figure class="hp-agl-photo__media">';

		if ( 0 === strpos( (string) $photo->get_mime_type(), 'video/' ) ) {
			$output .= '<video controls preload="metadata" playsinline><source src="' . esc_url( $photo->get_url() . '#t=0.001' ) . '" type="' . esc_attr( $photo->get_mime_type() ) . '"></video>';
		} else {
			$image = wp_get_attachment_image(
				$photo_id,
				'large',
				false,
				[
					'alt' => $caption ? $caption : $folder->get_title(),
				]
			);

			// With the lightbox on, clicking the photo enlarges it in the
			// core pop-up viewer; this is the only place the lightbox
			// appears, as gallery tiles always open the photo page instead.
			$full_url = ( (bool) get_option( 'hp_gallery_enable_lightbox' ) ) ? wp_get_attachment_image_url( $photo_id, 'full' ) : '';

			if ( $full_url ) {
				$output .= '<a href="' . esc_url( $full_url ) . '" class="hp-agl-photo__zoom" data-fancybox data-caption="' . esc_attr( $caption ? $caption : $folder->get_title() ) . '" title="' . esc_attr__( 'Click to enlarge', 'additional-gallery-for-hivepress' ) . '">' . $image . '</a>';
			} else {
				$output .= $image;
			}
		}

		$output .= '</figure>';

		// Description and the action row. No heading here: the theme already
		// prints the photo's title as the page heading, from the route title.
		$output .= '<div class="hp-agl-photo__details">';

		if ( $caption ) {
			$output .= '<p class="hp-agl-photo__description">' . esc_html( $caption ) . '</p>';
		}

		$counts     = $gallery->get_engagement_counts( [ $photo_id ] );
		$liked_ids  = $gallery->are_likes_enabled() ? $gallery->get_liked_photo_ids( [ $photo_id ] ) : [];
		$action_bar = $gallery->render_photo_actions( $folder, $photo_id, isset( $counts[ $photo_id ] ) ? $counts[ $photo_id ] : [], in_array( $photo_id, $liked_ids, true ) );

		if ( $action_bar ) {
			$output .= '<div class="hp-agl-photo__actions">' . $action_bar . '</div>';
		}

		$output .= '</div>';

		// Previous / next within the folder.
		$siblings = $gallery->get_photo_siblings( $folder, $photo_id );

		if ( $siblings['previous'] || $siblings['next'] ) {
			$output .= '<nav class="hp-agl-photo__nav">';

			if ( $siblings['previous'] ) {
				$output .= '<a href="' . esc_url( $gallery->get_photo_url( $folder, $siblings['previous'] ) ) . '" class="hp-button hp-agl-photo__nav-link button button--secondary"><i class="hp-icon fas fa-arrow-left"></i><span>' . esc_html__( 'Previous', 'additional-gallery-for-hivepress' ) . '</span></a>';
			} else {
				$output .= '<span></span>';
			}

			if ( $siblings['next'] ) {
				$output .= '<a href="' . esc_url( $gallery->get_photo_url( $folder, $siblings['next'] ) ) . '" class="hp-button hp-agl-photo__nav-link button button--secondary"><span>' . esc_html__( 'Next', 'additional-gallery-for-hivepress' ) . '</span><i class="hp-icon fas fa-arrow-right"></i></a>';
			}

			$output .= '</nav>';
		}

		// The comment thread and composer.
		if ( $gallery->are_comments_enabled() ) {
			$comment_counts = isset( $counts[ $photo_id ] ) ? $counts[ $photo_id ] : [ 'comments' => 0 ];

			$output .= '<section id="hp-agl-comments" class="hp-agl-comments" data-agl-comments="' . esc_attr( (string) $photo_id ) . '">';

			// An h3, not an h2: the heading should sit below the page and
			// widget titles in the hierarchy, and reads better smaller.
			/* translators: %s: comments number. */
			$output .= '<h3 class="hp-agl-comments__title">' . esc_html( sprintf( __( 'Comments (%s)', 'additional-gallery-for-hivepress' ), number_format_i18n( hp_agl_int( hp\get_array_value( $comment_counts, 'comments' ) ) ) ) ) . '</h3>';

			$output .= '<div class="hp-agl-comments__list" data-agl-comment-list>';
			$output .= $gallery->render_photo_comment_thread( $photo_id );
			$output .= '</div>';

			$output .= $gallery->render_photo_comment_form( $photo_id );

			$output .= '</section>';
		}

		$output .= '</div>';

		return $output;
	}
}
