<?php
/**
 * Gallery folder edit block.
 *
 * @package AdditionalGalleryForHivePress\Blocks
 */

namespace HivePress\Blocks;

use HivePress\Helpers as hp;
use HivePress\Forms;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Renders the folder edit screen: image uploads, details form and the
 * delete section.
 */
class Agl_Gallery_Folder_Edit extends Block {

	/**
	 * Renders block HTML.
	 *
	 * @return string
	 */
	public function render() {
		$output = '';

		// Get folder.
		$folder = $this->get_context( 'gallery_folder' );

		if ( ! $folder instanceof \HivePress\Models\Gallery_Folder ) {
			return $output;
		}

		$output .= '<div class="hp-agl-folder-edit">';

		// Back link.
		$output .= '<p class="hp-agl-folder-edit__back"><a href="' . esc_url( hivepress()->router->get_url( 'gallery_edit_page' ) ) . '"><i class="hp-icon fas fa-arrow-left"></i> ' . esc_html__( 'Back to Gallery', 'additional-gallery-for-hivepress' ) . '</a></p>';

		// Visibility hint.
		$visibility = $folder->get_visibility();
		$public_url = hivepress()->router->get_url( 'gallery_view_page', [ 'vendor_id' => $folder->get_vendor__id() ] );
		$view_link  = ' <a href="' . esc_url( $public_url ) . '" target="_blank">' . esc_html__( 'View it live', 'additional-gallery-for-hivepress' ) . '</a>';

		if ( 'public' === $visibility ) {
			$output .= '<p class="hp-agl-folder-edit__hint hp-agl-folder-edit__hint--public"><i class="hp-icon fas fa-eye"></i> ' . esc_html__( 'This folder is public and appears in your gallery.', 'additional-gallery-for-hivepress' ) . $view_link . '</p>';
		} elseif ( 'members' === $visibility ) {
			$output .= '<p class="hp-agl-folder-edit__hint hp-agl-folder-edit__hint--members"><i class="hp-icon fas fa-user-lock"></i> ' . esc_html__( 'This folder is members-only. It appears in your gallery, but the photos stay locked until a visitor unlocks access.', 'additional-gallery-for-hivepress' ) . $view_link . '</p>';
		} else {
			$output .= '<p class="hp-agl-folder-edit__hint hp-agl-folder-edit__hint--private"><i class="hp-icon fas fa-lock"></i> ' . esc_html__( 'This folder is private. Only you can see it.', 'additional-gallery-for-hivepress' ) . '</p>';
		}

		// Folder link for shareable folders.
		if ( in_array( $folder->get_visibility(), [ 'public', 'members' ], true ) ) {
			$folder_url = hivepress()->agl_gallery->get_folder_url( $folder );

			$output .= '<div class="hp-agl-account__share">';
			$output .= '<strong>' . esc_html__( 'Folder link', 'additional-gallery-for-hivepress' ) . '</strong>';
			$output .= '<div class="hp-agl-account__share-row">';
			$output .= '<input type="text" readonly value="' . esc_url( $folder_url ) . '">';
			$output .= '<button type="button" class="button hp-agl-copy" data-agl-copy="' . esc_url( $folder_url ) . '">' . esc_html__( 'Copy', 'additional-gallery-for-hivepress' ) . '</button>';
			$output .= '</div>';
			$output .= '</div>';
		}

		// Update form.
		$output .= ( new Forms\Agl_Gallery_Folder_Update( [ 'model' => $folder ] ) )->render();
		$output .= '<p class="hp-meta hp-agl-folder-edit__hint-edit">' . esc_html__( 'To edit a photo\'s title and description, move it to another folder, or delete it, open the photo\'s own page from your gallery and use the Manage Photo options.', 'additional-gallery-for-hivepress' ) . '</p>';

		/*
		 * This folder's own prices, where the site sells access folder by folder. Only a members-only
		 * folder is ever locked, so only a members-only folder has anything to charge for; on any
		 * other folder the panel would be a price nobody could ever be asked to pay.
		 * render_price_panel() itself returns nothing under the whole-gallery scope, where these
		 * prices live on the account gallery page instead.
		 */
		if ( 'members' === $folder->get_visibility() ) {
			$vendor = \HivePress\Models\Vendor::query()->get_by_id( $folder->get_vendor__id() );

			if ( $vendor ) {
				$output .= hivepress()->agl_gallery->render_price_panel( $vendor, $folder );
			}
		}

		// Delete section.
		$output .= '<div class="hp-agl-folder-edit__delete">';
		$output .= '<h3 class="hp-section__title">' . esc_html__( 'Delete Folder', 'additional-gallery-for-hivepress' ) . '</h3>';
		$output .= ( new Forms\Agl_Gallery_Folder_Delete( [ 'model' => $folder ] ) )->render();
		$output .= '</div>';

		$output .= '</div>';

		return $output;
	}
}
