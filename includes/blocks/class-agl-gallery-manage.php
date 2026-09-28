<?php
/**
 * Gallery manage block.
 *
 * @package AdditionalGalleryForHivePress\Blocks
 */

namespace HivePress\Blocks;

use HivePress\Models;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Renders the Manage card in the gallery and folder page sidebars: the owner's shortcuts to the
 * account Gallery pages, or wp-admin links for a site administrator. Renders nothing for anyone
 * else. Markup and classes follow the photo page's Manage card, so the three cards match.
 */
class Agl_Gallery_Manage extends Block {

	/**
	 * Renders block HTML.
	 *
	 * @return string
	 */
	public function render() {
		$vendor = $this->get_context( 'vendor' );

		if ( ! $vendor instanceof Models\Vendor || ! hivepress()->agl_gallery->can_manage_gallery( $vendor ) ) {
			return '';
		}

		$folder = $this->get_context( 'gallery_folder' );

		if ( ! $folder instanceof Models\Gallery_Folder ) {
			$folder = null;
		}

		$user_id  = get_current_user_id();
		$is_owner = $user_id && $user_id === $vendor->get_user__id();

		if ( $folder ) {
			$content = $is_owner ? $this->render_folder_owner( $folder ) : $this->render_folder_admin( $folder );
		} else {
			$content = $is_owner ? $this->render_gallery_owner( $vendor ) : $this->render_gallery_admin( $vendor );
		}

		if ( ! $content ) {
			return '';
		}

		$output  = '<div class="hp-widget widget widget--sidebar hp-agl-photo-manage hp-agl-manage">';
		$output .= '<h3 class="widget__title hp-section__title">' . esc_html__( 'Manage', 'additional-gallery-for-hivepress' ) . '</h3>';
		$output .= $content;
		$output .= '</div>';

		return $output;
	}

	/**
	 * Renders the owner's options for the whole gallery.
	 *
	 * @param \HivePress\Models\Vendor $vendor Vendor object.
	 * @return string
	 */
	protected function render_gallery_owner( $vendor ) {
		$gallery  = hivepress()->agl_gallery;
		$edit_url = hivepress()->router->get_url( 'gallery_edit_page' );
		$output   = '';

		// The same limit the account page applies before it shows the New Folder form.
		$max_folders = $gallery->get_folder_limit( $vendor );

		$folder_count = Models\Gallery_Folder::query()->filter(
			[
				'status' => 'publish',
				'vendor' => $vendor->get_id(),
			]
		)->get_count();

		if ( $max_folders && $folder_count >= $max_folders ) {
			/* translators: %s: folders number. */
			$output .= '<p class="hp-meta hp-agl-manage__limit">' . esc_html( sprintf( _n( 'You have reached the limit of %s folder.', 'You have reached the limit of %s folders.', $max_folders, 'additional-gallery-for-hivepress' ), number_format_i18n( $max_folders ) ) ) . '</p>';
		} else {
			$output .= $this->render_primary( $edit_url . '#hp-agl-new-folder', 'fa-folder-plus', esc_html__( 'Add New Folder', 'additional-gallery-for-hivepress' ) );
		}

		$links = [];

		// Each link only where the account page shows the matching panel.
		if ( $gallery->get_owner_display_surfaces() ) {
			$links[] = [ $edit_url . '#hp-agl-display', 'fa-eye', esc_html__( 'Where Your Gallery Appears', 'additional-gallery-for-hivepress' ) ];
		}

		if ( $gallery->is_paid_access_enabled() && $gallery->are_members_folders_enabled() && ! $gallery->is_folder_access_scope() ) {
			$links[] = [ $edit_url . '#hp-agl-paid-access', 'fa-tag', esc_html__( 'Paid Access', 'additional-gallery-for-hivepress' ) ];
		}

		$links[] = [ $edit_url, 'fa-cog', esc_html__( 'Gallery Settings', 'additional-gallery-for-hivepress' ) ];

		return $output . $this->render_links( $links );
	}

	/**
	 * Renders a site administrator's options for another Vendor's gallery.
	 *
	 * Their own account Gallery page is not this Vendor's, so the links go to wp-admin.
	 *
	 * @param \HivePress\Models\Vendor $vendor Vendor object.
	 * @return string
	 */
	protected function render_gallery_admin( $vendor ) {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return '';
		}

		// Narrowed by author, as in the settings link: the folder author is the Vendor's user.
		$list_url = add_query_arg(
			[
				'post_type' => 'hp_gallery_folder',
				'author'    => absint( $vendor->get_user__id() ),
			],
			admin_url( 'edit.php' )
		);

		return $this->render_links( [ [ $list_url, 'fa-cog', esc_html__( 'Manage Folders', 'additional-gallery-for-hivepress' ) ] ] );
	}

	/**
	 * Renders the owner's options for one folder.
	 *
	 * @param \HivePress\Models\Gallery_Folder $folder Folder object.
	 * @return string
	 */
	protected function render_folder_owner( $folder ) {
		$gallery  = hivepress()->agl_gallery;
		$edit_url = hivepress()->router->get_url( 'gallery_folder_edit_page', [ 'gallery_folder_id' => $folder->get_id() ] );

		$output = $this->render_primary( $edit_url . '#hp-agl-folder-form', 'fa-images', esc_html__( 'Add Photos', 'additional-gallery-for-hivepress' ) );

		$links = [
			[ $edit_url, 'fa-cog', esc_html__( 'Edit Folder', 'additional-gallery-for-hivepress' ) ],
		];

		// Folder prices live on the folder's edit page only under the per-folder scope.
		if ( 'members' === $folder->get_visibility() && $gallery->is_paid_access_enabled() && $gallery->are_members_folders_enabled() && $gallery->is_folder_access_scope() ) {
			$links[] = [ $edit_url . '#hp-agl-paid-access', 'fa-tag', esc_html__( 'Paid Access', 'additional-gallery-for-hivepress' ) ];
		}

		return $output . $this->render_links( $links ) . $this->render_delete( $folder );
	}

	/**
	 * Renders a site administrator's options for another Vendor's folder.
	 *
	 * @param \HivePress\Models\Gallery_Folder $folder Folder object.
	 * @return string
	 */
	protected function render_folder_admin( $folder ) {
		$output   = '';
		$edit_url = current_user_can( 'edit_others_posts' ) ? get_edit_post_link( $folder->get_id() ) : '';

		if ( $edit_url ) {
			$output .= $this->render_links( [ [ $edit_url, 'fa-cog', esc_html__( 'Edit Folder', 'additional-gallery-for-hivepress' ) ] ] );
		}

		// The same capability the delete endpoint checks.
		if ( current_user_can( 'delete_others_posts' ) ) {
			$output .= $this->render_delete( $folder );
		}

		return $output;
	}

	/**
	 * Renders the card's main button.
	 *
	 * @param string $url Link URL.
	 * @param string $icon Font Awesome icon class.
	 * @param string $label Escaped label.
	 * @return string
	 */
	protected function render_primary( $url, $icon, $label ) {
		return '<a href="' . esc_url( $url ) . '" class="hp-button hp-button--wide button button--primary alt hp-agl-manage__primary"><i class="hp-icon fas ' . esc_attr( $icon ) . '"></i><span>' . $label . '</span></a>';
	}

	/**
	 * Renders the card's list of links.
	 *
	 * @param array $links Links, each a URL, a Font Awesome icon class and an escaped label.
	 * @return string
	 */
	protected function render_links( $links ) {
		$output = '<ul class="hp-agl-manage__links">';

		foreach ( $links as $link ) {
			$output .= '<li><a href="' . esc_url( $link[0] ) . '" class="hp-link"><i class="hp-icon fas ' . esc_attr( $link[1] ) . '"></i><span>' . $link[2] . '</span></a></li>';
		}

		$output .= '</ul>';

		return $output;
	}

	/**
	 * Renders the folder delete control.
	 *
	 * The script asks for the same confirmation as the account page's Delete Folder form, then
	 * calls the same endpoint and returns to the gallery page.
	 *
	 * @param \HivePress\Models\Gallery_Folder $folder Folder object.
	 * @return string
	 */
	protected function render_delete( $folder ) {
		$gallery_url = hivepress()->router->get_url( 'gallery_view_page', [ 'vendor_id' => $folder->get_vendor__id() ] );

		$output  = '<div class="hp-agl-photo-manage__delete">';
		$output .= '<button type="button" class="hp-agl-action hp-link" data-agl-folder-delete="' . esc_attr( (string) $folder->get_id() ) . '" data-agl-redirect="' . esc_url( $gallery_url ) . '"><i class="hp-icon fas fa-times"></i><span>' . esc_html__( 'Delete Folder', 'additional-gallery-for-hivepress' ) . '</span></button>';
		$output .= '<p class="hp-meta">' . esc_html__( 'Deleting a folder permanently removes all of its images.', 'additional-gallery-for-hivepress' ) . '</p>';
		$output .= '</div>';

		return $output;
	}
}
