<?php
/**
 * Gallery photo manage block.
 *
 * @package AdditionalGalleryForHivePress\Blocks
 */

namespace HivePress\Blocks;

use HivePress\Helpers as hp;
use HivePress\Models;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Renders the owner's management card in the photo page sidebar: title,
 * description, a move-to-folder choice and deletion. Renders nothing for
 * anyone but the folder's owner.
 *
 * The form is submitted by the plugin's own JavaScript rather than the core
 * form handler, because saving spans two endpoints (details, then an optional
 * move) and ends in a redirect or reload.
 */
class Agl_Gallery_Photo_Manage extends Block {

	/**
	 * Renders block HTML.
	 *
	 * @return string
	 */
	public function render() {
		$output = '';

		// Get context.
		$folder = $this->get_context( 'gallery_folder' );
		$photo  = $this->get_context( 'gallery_photo' );

		if ( ! $folder instanceof \HivePress\Models\Gallery_Folder || ! $photo instanceof \HivePress\Models\Attachment ) {
			return $output;
		}

		// Owner only.
		if ( ! get_current_user_id() || get_current_user_id() !== $folder->get_user__id() ) {
			return $output;
		}

		$photo_id   = $photo->get_id();
		$folder_url = hivepress()->router->get_url(
			'gallery_folder_view_page',
			[
				'vendor_id'         => $folder->get_vendor__id(),
				'gallery_folder_id' => $folder->get_id(),
			]
		);

		$caption = get_post_field( 'post_excerpt', $photo_id );
		$caption = is_string( $caption ) ? trim( $caption ) : '';

		// A raw file-name title is noise, so the field starts empty then.
		$title = trim( (string) get_the_title( $photo_id ) );
		$file  = pathinfo( (string) get_post_meta( $photo_id, '_wp_attached_file', true ), PATHINFO_FILENAME );

		if ( sanitize_title( $title ) === sanitize_title( $file ) ) {
			$title = '';
		}

		// After a move the photo's address changes with its folder, so the
		// script rebuilds the URL from this template by swapping the sentinel
		// for the chosen folder's ID. A sentinel survives plain-permalink
		// sites, where the ID is a query argument rather than a path segment.
		$move_template = hivepress()->router->get_url(
			'gallery_photo_view_page',
			[
				'vendor_id'         => $folder->get_vendor__id(),
				'gallery_folder_id' => 888888888,
				'attachment_id'     => $photo_id,
			]
		);

		$output .= '<div class="hp-widget widget widget--sidebar hp-agl-photo-manage">';
		$output .= '<h3 class="widget__title hp-section__title">' . esc_html__( 'Manage Photo', 'additional-gallery-for-hivepress' ) . '</h3>';

		// The details form.
		$output .= '<form class="hp-form hp-agl-photo-manage__form" data-agl-photo-edit="' . esc_attr( (string) $photo_id ) . '" data-agl-folder="' . esc_attr( (string) $folder->get_id() ) . '" data-agl-move-template="' . esc_url( $move_template ) . '">';
		$output .= '<div class="hp-form__fields">';

		// Class placement follows core exactly: the wrapper carries
		// hp-form__field--{type} (forms/class-form.php:433) and the control
		// itself carries hp-field hp-field--{type} (fields/class-field.php:249).
		// Putting the control classes on the wrapper, as an earlier build did,
		// lands every core input rule on a div and leaves the real control
		// unstyled.
		$output .= '<div class="hp-form__field hp-form__field--text">';
		$output .= '<label class="hp-field__label">' . esc_html__( 'Title', 'additional-gallery-for-hivepress' ) . ' <small>' . esc_html__( '(optional)', 'additional-gallery-for-hivepress' ) . '</small></label>';
		$output .= '<input type="text" name="title" class="hp-field hp-field--text" maxlength="128" value="' . esc_attr( $title ) . '" placeholder="' . esc_attr__( 'Name this photo...', 'additional-gallery-for-hivepress' ) . '">';
		$output .= '</div>';

		$output .= '<div class="hp-form__field hp-form__field--textarea">';
		$output .= '<label class="hp-field__label">' . esc_html__( 'Description', 'additional-gallery-for-hivepress' ) . ' <small>' . esc_html__( '(optional)', 'additional-gallery-for-hivepress' ) . '</small></label>';

		/*
		 * No mention of alt text. It is still what the description becomes, but "alt text" means
		 * nothing to most of the people typing into this box, and a placeholder is read at the moment
		 * somebody is deciding what to write - a sentence of jargon there is a sentence they have to
		 * get past first. What it does for screen readers and search engines belongs in the
		 * documentation, not in the box.
		 */
		$output .= '<textarea name="caption" class="hp-field hp-field--textarea" rows="4" maxlength="500" placeholder="' . esc_attr__( 'Describe this photo... It shows here and under the thumbnail.', 'additional-gallery-for-hivepress' ) . '">' . esc_textarea( $caption ) . '</textarea>';
		$output .= '</div>';

		// Move to another folder.
		$folders = Models\Gallery_Folder::query()->filter(
			[
				'status' => 'publish',
				'user'   => $folder->get_user__id(),
			]
		)->order(
			[
				'sort_order'   => 'asc',
				'created_date' => 'asc',
			]
		)->get();

		$output .= '<div class="hp-form__field hp-form__field--select">';
		$output .= '<label class="hp-field__label">' . esc_html__( 'Folder', 'additional-gallery-for-hivepress' ) . '</label>';
		$output .= '<select name="folder" class="hp-field hp-field--select">';

		foreach ( $folders as $candidate ) {
			if ( ! $candidate instanceof \HivePress\Models\Gallery_Folder ) {
				continue;
			}

			$output .= '<option value="' . esc_attr( (string) $candidate->get_id() ) . '"' . selected( $candidate->get_id(), $folder->get_id(), false ) . '>' . esc_html( $candidate->get_title() ) . '</option>';
		}

		$output .= '</select>';
		$output .= '<small class="hp-meta">' . esc_html__( 'Choosing a different folder moves this photo there when you save.', 'additional-gallery-for-hivepress' ) . '</small>';
		$output .= '</div>';

		$output .= '</div>';
		$output .= '<div class="hp-form__footer">';
		$output .= '<button type="submit" class="hp-form__button button button--primary alt"><span>' . esc_html__( 'Save Changes', 'additional-gallery-for-hivepress' ) . '</span></button>';
		$output .= '</div>';
		$output .= '<div class="hp-form__messages" data-agl-photo-message></div>';
		$output .= '</form>';

		/*
		 * The cover control, above deletion, because it is the one thing here somebody is likely to
		 * come looking for. A video is never offered: the gallery grid shows a still.
		 */
		if ( 0 === strpos( (string) get_post_mime_type( $photo_id ), 'image' ) ) {
			$is_cover = hivepress()->agl_gallery->is_folder_cover( $folder, $photo_id );

			$output .= '<div class="hp-agl-photo-manage__cover">';

			if ( $is_cover ) {
				$output .= '<p class="hp-agl-photo-manage__cover-state"><i class="hp-icon fas fa-star"></i><span>' . esc_html__( 'This is the folder cover', 'additional-gallery-for-hivepress' ) . '</span></p>';
			} else {
				$output .= '<button type="button" class="hp-agl-action hp-link" data-agl-photo-cover="' . esc_attr( (string) $photo_id ) . '"><i class="hp-icon far fa-star"></i><span>' . esc_html__( 'Use as folder cover', 'additional-gallery-for-hivepress' ) . '</span></button>';
			}

			$output .= '<p class="hp-meta">' . esc_html__( 'The cover is the picture shown for this folder on your gallery page. Without a choice, the first photo is used.', 'additional-gallery-for-hivepress' ) . '</p>';
			$output .= '</div>';
		}

		// Deletion, tucked under the form.
		$output .= '<div class="hp-agl-photo-manage__delete">';
		$output .= '<button type="button" class="hp-agl-action hp-link" data-agl-photo-delete="' . esc_attr( (string) $photo_id ) . '" data-agl-redirect="' . esc_url( $folder_url ) . '"><i class="hp-icon fas fa-times"></i><span>' . esc_html__( 'Delete this photo', 'additional-gallery-for-hivepress' ) . '</span></button>';
		$output .= '<p class="hp-meta">' . esc_html__( 'Deleting a photo also removes its likes and comments. This cannot be undone.', 'additional-gallery-for-hivepress' ) . '</p>';
		$output .= '</div>';

		$output .= '</div>';

		return $output;
	}
}
