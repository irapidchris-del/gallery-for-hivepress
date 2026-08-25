<?php
/**
 * Gallery settings link block.
 *
 * @package AdditionalGalleryForHivePress\Blocks
 */

namespace HivePress\Blocks;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Renders a quiet "Gallery Settings" link in the top right of a gallery page,
 * for the vendor who owns it and for the site owner. Nobody else sees anything.
 *
 * A gallery page is the page a vendor sends people to, so it is the page they
 * are looking at when they decide something needs changing - and until now the
 * only way back to the controls was to remember that they live under Account,
 * then Gallery. This is the same affordance a profile gets from an Edit Profile
 * link, and it is deliberately a plain link rather than a button: it is for one
 * reader in a hundred, and it must not compete with the gallery itself.
 */
class Agl_Gallery_Settings_Link extends Block {

	/**
	 * Renders block HTML.
	 *
	 * @return string
	 */
	public function render() {
		$vendor = $this->get_context( 'vendor' );

		if ( ! $vendor instanceof \HivePress\Models\Vendor ) {
			return '';
		}

		if ( ! hivepress()->agl_gallery->can_manage_gallery( $vendor ) ) {
			return '';
		}

		$folder = $this->get_context( 'gallery_folder' );

		if ( ! $folder instanceof \HivePress\Models\Gallery_Folder ) {
			$folder = null;
		}

		$user_id  = get_current_user_id();
		$is_owner = $user_id && $user_id === $vendor->get_user__id();

		if ( $is_owner ) {

			// The vendor's own account pages, which is where they can actually change anything.
			if ( $folder ) {
				$url   = hivepress()->router->get_url( 'gallery_folder_edit_page', [ 'gallery_folder_id' => $folder->get_id() ] );
				$label = esc_html__( 'Folder Settings', 'additional-gallery-for-hivepress' );
			} else {
				$url   = hivepress()->router->get_url( 'gallery_edit_page' );
				$label = esc_html__( 'Gallery Settings', 'additional-gallery-for-hivepress' );
			}
		} else {
			/*
			 * A site owner looking at somebody else's gallery. Their own account gallery page is not
			 * this vendor's, so linking there would have sent them somewhere unrelated with a label
			 * saying otherwise. wp-admin is where they can genuinely edit this vendor's folders.
			 */
			if ( ! current_user_can( 'edit_others_posts' ) ) {
				return '';
			}

			if ( $folder ) {
				$url   = get_edit_post_link( $folder->get_id() );
				$label = esc_html__( 'Edit Folder', 'additional-gallery-for-hivepress' );
			} else {
				/*
				 * Narrowed by `author`, not by the vendor. The folder list screen is an ordinary
				 * WordPress post list, so it honours the query variables WordPress itself registers
				 * and `post_parent` is not one of them; the post author is, and it is the vendor's
				 * user, because sync_folder_author() keeps it so.
				 */
				$url = add_query_arg(
					[
						'post_type' => 'hp_gallery_folder',
						'author'    => absint( $vendor->get_user__id() ),
					],
					admin_url( 'edit.php' )
				);

				$label = esc_html__( 'Manage Folders', 'additional-gallery-for-hivepress' );
			}

			if ( ! $url ) {
				return '';
			}
		}

		return '<div class="hp-agl-settings-link"><a href="' . esc_url( $url ) . '" class="hp-link hp-agl-settings-link__link"><i class="hp-icon fas fa-cog"></i><span>' . $label . '</span></a></div>';
	}
}
