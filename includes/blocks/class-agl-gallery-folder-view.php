<?php
/**
 * Gallery folder view block.
 *
 * @package AdditionalGalleryForHivePress\Blocks
 */

namespace HivePress\Blocks;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Renders a single gallery folder page: description, last update, media
 * grid, and the unlock link when the folder is locked for the visitor.
 */
class Agl_Gallery_Folder_View extends Block {

	/**
	 * Renders block HTML.
	 *
	 * @return string
	 */
	public function render() {
		$output = '';

		// Get vendor and folder.
		$vendor = $this->get_context( 'vendor' );
		$folder = $this->get_context( 'gallery_folder' );

		if ( ! $vendor instanceof \HivePress\Models\Vendor || ! $folder instanceof \HivePress\Models\Gallery_Folder ) {
			return $output;
		}

		$output .= '<div class="hp-agl-gallery hp-agl-gallery--folder">';

		// Link back to the gallery.
		/* translators: %s: vendor name. */
		$output .= '<p class="hp-agl-gallery__profile"><a href="' . esc_url( hivepress()->router->get_url( 'gallery_view_page', [ 'vendor_id' => $vendor->get_id() ] ) ) . '"><i class="hp-icon fas fa-arrow-left"></i> ' . esc_html( sprintf( __( 'Back to %s\'s gallery', 'additional-gallery-for-hivepress' ), $vendor->get_name() ) ) . '</a></p>';

		// Folder details.
		$locked = 'members' === hivepress()->agl_gallery->get_effective_visibility( $folder ) && ! hivepress()->agl_gallery->user_can_view_member_folders( $vendor );

		if ( $locked ) {
			// Two spans open here and both must close. The outer hp-status span
			// was left unclosed until 1.8.15, so the browser repaired it at the
			// closing </p>: nothing looked wrong, but the markup was invalid and
			// anything appended after the badge inherited the pill's styling.
			$output .= '<p class="hp-agl-gallery__folder-title-badge"><span class="hp-status hp-status--pending"><span><i class="hp-icon fas fa-lock"></i> ' . esc_html__( 'Members only', 'additional-gallery-for-hivepress' ) . '</span></span></p>';
		}

		if ( $folder->get_description() ) {
			$output .= '<p class="hp-agl-gallery__folder-description">' . esc_html( $folder->get_description() ) . '</p>';
		}

		// Media counts and last update.
		$meta_parts = [ hivepress()->agl_gallery->get_media_count_label( hivepress()->agl_gallery->get_media_counts( $folder ) ) ];

		$updated_time = hivepress()->agl_gallery->get_updated_time( [ $folder->get_id() ] );

		if ( $updated_time ) {
			/* translators: %s: human-readable time difference. */
			$meta_parts[] = sprintf( __( 'Updated %s ago', 'additional-gallery-for-hivepress' ), human_time_diff( $updated_time ) );
		}

		$output .= '<p class="hp-agl-gallery__updated hp-meta"><i class="hp-icon fas fa-clock"></i> ' . esc_html( implode( ' · ', $meta_parts ) ) . '</p>';

		// Media grid.
		$output .= hivepress()->agl_gallery->render_folder_media( $folder, $locked );

		// Unlock actions: buy access and/or upgrade a membership.
		if ( $locked ) {
			$output .= hivepress()->agl_gallery->render_unlock_actions( $vendor );
		}

		$output .= '</div>';

		return $output;
	}
}
