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
class Gallery_Folder_View extends Block {

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
		$locked = 'members' === $folder->get_visibility() && ! hivepress()->gallery->user_can_view_member_folders( $vendor );

		if ( $locked ) {
			$output .= '<p class="hp-agl-gallery__folder-title-badge"><span class="hp-agl-badge hp-agl-badge--members"><i class="hp-icon fas fa-lock"></i> ' . esc_html__( 'Members only', 'additional-gallery-for-hivepress' ) . '</span></p>';
		}

		if ( $folder->get_description() ) {
			$output .= '<p class="hp-agl-gallery__folder-description">' . esc_html( $folder->get_description() ) . '</p>';
		}

		// Media counts and last update.
		$meta_parts = [ hivepress()->gallery->get_media_count_label( hivepress()->gallery->get_media_counts( $folder ) ) ];

		$updated_time = hivepress()->gallery->get_updated_time( [ $folder->get_id() ] );

		if ( $updated_time ) {
			/* translators: %s: human-readable time difference. */
			$meta_parts[] = sprintf( __( 'Updated %s ago', 'additional-gallery-for-hivepress' ), human_time_diff( $updated_time ) );
		}

		$output .= '<p class="hp-agl-gallery__updated"><i class="hp-icon fas fa-clock"></i> ' . esc_html( implode( ' · ', $meta_parts ) ) . '</p>';

		// Media grid.
		$output .= hivepress()->gallery->render_folder_media( $folder, $locked );

		// Unlock link.
		if ( $locked ) {
			$upgrade_url = hivepress()->gallery->get_upgrade_url();

			if ( $upgrade_url ) {
				$output .= '<p class="hp-agl-gallery__folder-unlock"><a href="' . esc_url( $upgrade_url ) . '" class="button alt"><i class="hp-icon fas fa-unlock"></i><span>' . esc_html__( 'Unlock Access', 'additional-gallery-for-hivepress' ) . '</span></a></p>';
			}
		}

		$output .= '</div>';

		return $output;
	}
}
