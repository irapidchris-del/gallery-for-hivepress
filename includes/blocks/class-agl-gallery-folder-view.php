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
		$visibility = hivepress()->agl_gallery->get_effective_visibility( $folder );
		$locked     = 'members' === $visibility && ! hivepress()->agl_gallery->user_can_view_folder( $folder, $vendor );

		if ( $locked ) {
			$output .= '<p class="hp-agl-gallery__folder-title-badge">' . hivepress()->agl_gallery->render_members_badge() . '</p>';
		} elseif ( 'private' === $visibility ) {

			// Only the owner and a site owner ever reach a private folder page; the redirect turns
			// everybody else away (see redirect_gallery_folder_view_page). Saying so on the page
			// stops a vendor mistaking their own view of it for what a visitor gets.
			$output .= '<p class="hp-agl-gallery__folder-title-badge">' . hivepress()->agl_gallery->render_private_badge() . '</p>';
			$output .= '<p class="hp-agl-gallery__private-note hp-meta">' . esc_html__( 'This folder is private, so only you can open this page. Visitors are sent back to the gallery.', 'additional-gallery-for-hivepress' ) . '</p>';
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
			$output .= hivepress()->agl_gallery->render_unlock_actions( $vendor, $folder );
		}

		$output .= '</div>';

		return $output;
	}
}
