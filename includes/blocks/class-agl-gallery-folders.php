<?php
/**
 * Gallery folders block.
 *
 * @package AdditionalGalleryForHivePress\Blocks
 */

namespace HivePress\Blocks;

use HivePress\Helpers as hp;
use HivePress\Forms;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Renders the account gallery overview: shareable public link, drag-sortable
 * folder list and the folder creation form.
 */
class Agl_Gallery_Folders extends Block {

	/**
	 * Renders block HTML.
	 *
	 * @return string
	 */
	public function render() {
		$output = '';

		// Get vendor.
		$vendor = $this->get_context( 'vendor' );

		if ( ! $vendor instanceof \HivePress\Models\Vendor ) {
			$vendor = hivepress()->agl_gallery->get_current_vendor();
		}

		if ( ! $vendor instanceof \HivePress\Models\Vendor ) {
			return $output;
		}

		// Get folders.
		$folders = $this->get_context( 'gallery_folders' );

		if ( ! $folders instanceof \HivePress\Queries\Post ) {
			$folders = null;
		}

		$output .= '<div class="hp-agl-account">';

		// Shareable public link.
		$listed_count = 0;

		foreach ( hivepress()->agl_gallery->get_listed_folders( $vendor->get_id() ) as $listed_folder ) {
			$listed_count += count( (array) $listed_folder->get_images__id() );
		}

		if ( $listed_count ) {
			$public_url = hivepress()->router->get_url( 'gallery_view_page', [ 'vendor_id' => $vendor->get_id() ] );

			$output .= '<div class="hp-agl-account__share">';
			$output .= '<strong>' . esc_html__( 'Your public gallery link', 'additional-gallery-for-hivepress' ) . '</strong>';
			$output .= '<div class="hp-agl-account__share-row">';
			$output .= '<input type="text" readonly value="' . esc_url( $public_url ) . '">';
			$output .= '<button type="button" class="button hp-agl-copy" data-agl-copy="' . esc_url( $public_url ) . '">' . esc_html__( 'Copy', 'additional-gallery-for-hivepress' ) . '</button>';
			$output .= '<a href="' . esc_url( $public_url ) . '" target="_blank" class="button alt">' . esc_html__( 'View', 'additional-gallery-for-hivepress' ) . '</a>';
			$output .= '</div>';
			$output .= '</div>';
		} elseif ( $folders && $folders->count() ) {
			/*
			 * There is no link to show, and until now nothing said so: a vendor with only private
			 * folders, or a public folder they have not put a photo in yet, simply found the whole
			 * block missing with no way of knowing whether it was a fault or a rule.
			 */
			$output .= '<div class="hp-agl-account__share hp-agl-account__share--empty">';
			$output .= '<strong>' . esc_html__( 'Your public gallery link', 'additional-gallery-for-hivepress' ) . '</strong>';
			$output .= '<p class="hp-agl-account__share-note">' . esc_html__( 'Your gallery gets a link to share once a public or members-only folder has a photo in it. Private folders stay visible to you alone, so they never appear here.', 'additional-gallery-for-hivepress' ) . '</p>';
			$output .= '</div>';
		}

		// Folder list.
		if ( $folders && $folders->count() ) {

			// Storage usage is only the vendor's business when a quota applies;
			// with no quota the number is just noise, so nothing is shown.
			$storage_limit = hivepress()->agl_gallery->get_storage_limit( $vendor );

			if ( $storage_limit ) {
				$storage_used = hivepress()->agl_gallery->get_storage_used( $vendor );

				/* translators: 1: used size, 2: allowed size. */
				$output .= '<p class="hp-agl-account__weight hp-meta"><i class="hp-icon fas fa-database"></i> ' . esc_html( sprintf( __( 'Storage: %1$s used of %2$s allowed', 'additional-gallery-for-hivepress' ), hivepress()->agl_gallery->format_size( $storage_used ), hivepress()->agl_gallery->format_size( $storage_limit ) ) ) . '</p>';
			}

			if ( $folders->count() > 1 ) {
				$output .= '<p class="hp-agl-account__hint hp-meta">' . esc_html__( 'Drag folders to reorder them in your gallery.', 'additional-gallery-for-hivepress' ) . '</p>';
			}

			$output .= '<div class="hp-agl-folders" data-component="sortable">';

			foreach ( $folders as $folder ) {
				if ( ! $folder instanceof \HivePress\Models\Gallery_Folder ) {
					continue;
				}

				$edit_url = hivepress()->router->get_url( 'gallery_folder_edit_page', [ 'gallery_folder_id' => $folder->get_id() ] );
				$sort_url = hivepress()->router->get_url( 'gallery_folder_sort_action', [ 'gallery_folder_id' => $folder->get_id() ] );

				// Get visibility details (unknown values are treated as private).
				$visibility = $folder->get_visibility();

				if ( ! in_array( $visibility, [ 'public', 'members' ], true ) ) {
					$visibility = 'private';
				}

				// Visibility maps onto core's own status pill, so the badges
				// inherit HivePress' colours instead of inventing new ones:
				// publish reads as live, pending as gated, draft as hidden.
				$badges = [
					'public'  => [ 'fa-folder-open', esc_html__( 'Public', 'additional-gallery-for-hivepress' ), 'publish' ],
					'members' => [ 'fa-user-lock', esc_html__( 'Members only', 'additional-gallery-for-hivepress' ), 'pending' ],
					'private' => [ 'fa-lock', esc_html__( 'Private', 'additional-gallery-for-hivepress' ), 'draft' ],
				];

				$output     .= '<div class="hp-agl-folder" data-url="' . esc_url( $sort_url ) . '">';
				$output     .= '<i class="hp-icon fas fa-grip-lines hp-agl-folder__handle" title="' . esc_attr__( 'Drag to reorder', 'additional-gallery-for-hivepress' ) . '"></i>';
				$output     .= '<a href="' . esc_url( $edit_url ) . '" class="hp-agl-folder__link">';
				$output     .= '<i class="hp-icon fas ' . esc_attr( $badges[ $visibility ][0] ) . '"></i>';
				$output     .= '<span class="hp-agl-folder__title">' . esc_html( $folder->get_title() ) . '</span>';
				$output     .= '</a>';
				$output     .= '<span class="hp-status hp-status--' . esc_attr( $badges[ $visibility ][2] ) . '"><span>' . $badges[ $visibility ][1] . '</span></span>';
				$count_label = hivepress()->agl_gallery->get_media_count_label( hivepress()->agl_gallery->get_media_counts( $folder ) );

				// Per-folder sizes only matter under a quota.
				if ( $storage_limit ) {
					$folder_size = hivepress()->agl_gallery->get_folder_size( $folder );

					if ( $folder_size ) {
						$count_label .= ' · ' . hivepress()->agl_gallery->format_size( $folder_size );
					}
				}

				$output .= '<span class="hp-agl-folder__count hp-meta">' . esc_html( $count_label ) . '</span>';

				// Copy the folder link for shareable folders.
				if ( 'private' !== $visibility ) {
					$output .= '<button type="button" class="hp-agl-folder__copy" data-agl-icon-only="1" data-agl-copy="' . esc_url( hivepress()->agl_gallery->get_folder_url( $folder ) ) . '" title="' . esc_attr__( 'Copy folder link', 'additional-gallery-for-hivepress' ) . '"><i class="hp-icon fas fa-link"></i></button>';
				}

				$output .= '</div>';
			}

			$output .= '</div>';
		} else {
			$output .= '<p class="hp-agl-account__empty">' . esc_html__( 'You have no gallery folders yet. Create your first folder below to start showcasing your work.', 'additional-gallery-for-hivepress' ) . '</p>';
		}

		// Folder creation form (respecting the folder limit, which a membership
		// plan may raise or lower for this vendor).
		$max_folders  = hivepress()->agl_gallery->get_folder_limit( $vendor );
		$folder_count = $folders ? $folders->count() : 0;

		if ( $max_folders && $folder_count >= $max_folders ) {
			/* translators: %s: folders number. */
			$output .= '<p class="hp-agl-account__limit">' . esc_html( sprintf( _n( 'You have reached the limit of %s folder.', 'You have reached the limit of %s folders.', $max_folders, 'additional-gallery-for-hivepress' ), number_format_i18n( $max_folders ) ) ) . '</p>';
		} else {
			$output .= '<div class="hp-agl-account__create">';
			$output .= '<h3>' . esc_html__( 'New Folder', 'additional-gallery-for-hivepress' ) . '</h3>';
			$output .= ( new Forms\Agl_Gallery_Folder_Create() )->render();
			$output .= '</div>';
		}

		// Paid access pricing, when the site sells it.
		if ( hivepress()->agl_gallery->is_paid_access_enabled() && hivepress()->agl_gallery->are_members_folders_enabled() ) {
			$gallery    = hivepress()->agl_gallery;
			$durations  = $gallery->get_access_durations();
			$commission = $gallery->get_commission();
			$max        = $gallery::MAX_TIERS;

			// Every slot the vendor is already selling, in the order they will be shown.
			$rows = [];

			for ( $tier = 1; $tier <= $max; $tier++ ) {
				$price = $gallery->get_access_price( $vendor->get_id(), $tier );

				if ( $price ) {
					$rows[] = [
						'days'  => $gallery->get_tier_days( $vendor->get_id(), $tier ),
						'price' => $price,
					];
				}
			}

			$output .= '<div class="hp-agl-account__paid">';
			$output .= '<h3>' . esc_html__( 'Paid Access (optional)', 'additional-gallery-for-hivepress' ) . '</h3>';
			$output .= '<p class="hp-meta">' . esc_html__( 'If you want to, you can charge visitors to unlock your members-only folders. Choose how long the access lasts and what it costs. Buyers pay through the normal checkout.', 'additional-gallery-for-hivepress' ) . '</p>';
			$output .= '<p class="hp-meta">' . esc_html(
				sprintf(
					/* translators: %s: number of lengths. */
					_n( 'You can offer %s length at a time.', 'You can offer up to %s lengths at a time, and buyers pick between them.', $max, 'additional-gallery-for-hivepress' ),
					number_format_i18n( $max )
				)
			) . '</p>';

			/*
			 * The site's cut is stated here rather than left for the vendor to find in their
			 * earnings. It is added to what the buyer pays, so the vendor still receives the figure
			 * they type in, and saying so plainly stops it reading as a deduction.
			 */
			if ( $commission ) {
				if ( $commission['rate'] && $commission['fee'] ) {
					$note = sprintf(
						/* translators: 1: percentage, 2: amount. */
						__( 'Buyers also pay this site a fee of %1$s%% plus %2$s on top of your price. You receive the price you set.', 'additional-gallery-for-hivepress' ),
						number_format_i18n( $commission['rate'], 2 ),
						wp_strip_all_tags( wc_price( $commission['fee'] ) )
					);
				} elseif ( $commission['rate'] ) {
					$note = sprintf(
						/* translators: %s: percentage. */
						__( 'Buyers also pay this site a fee of %s%% on top of your price. You receive the price you set.', 'additional-gallery-for-hivepress' ),
						number_format_i18n( $commission['rate'], 2 )
					);
				} else {
					$note = sprintf(
						/* translators: %s: amount. */
						__( 'Buyers also pay this site a fee of %s on top of your price. You receive the price you set.', 'additional-gallery-for-hivepress' ),
						wp_strip_all_tags( wc_price( $commission['fee'] ) )
					);
				}

				$output .= '<p class="hp-meta">' . esc_html( $note ) . '</p>';
			}

			$symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '';

			$output .= '<form class="hp-form hp-agl-account__price-form" data-agl-price-form data-agl-max="' . esc_attr( $max ) . '">';
			$output .= '<div class="hp-agl-account__tiers" data-agl-tiers>';

			// One row per length being sold, and an empty one to start from when none is.
			$initial = $rows ? $rows : [
				[
					'days'  => null,
					'price' => '',
				],
			];

			foreach ( $initial as $row ) {
				$output .= $this->render_price_row( $durations, $symbol, $row['days'], $row['price'] );
			}

			$output .= '</div>';

			$output .= '<p class="hp-agl-account__tier-add"><a href="#" class="hp-agl-account__add-tier" data-agl-add-tier>' . esc_html__( '+ Add another length', 'additional-gallery-for-hivepress' ) . '</a></p>';
			$output .= '<p class="hp-meta">' . esc_html__( 'To stop selling a length, remove its row and save.', 'additional-gallery-for-hivepress' ) . '</p>';
			$output .= '<div class="hp-agl-account__price-row hp-agl-account__price-row--submit">';
			$output .= '<button type="submit" class="hp-form__button button button--primary alt"><span>' . esc_html__( 'Save Access Pricing', 'additional-gallery-for-hivepress' ) . '</span></button>';
			$output .= '</div>';
			$output .= '<div class="hp-form__messages" data-agl-price-message></div>';
			$output .= '</form>';

			// The template the Add button clones. Kept out of the form so it is never submitted.
			$output .= '<template data-agl-tier-template>' . $this->render_price_row( $durations, $symbol, null, '' ) . '</template>';
			$output .= '</div>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Renders one length-and-price row.
	 *
	 * @param array        $durations Lengths a vendor may choose from.
	 * @param string       $symbol Currency symbol.
	 * @param int|null     $days Chosen length, null for a blank row.
	 * @param float|string $price Price, empty for a blank row.
	 * @return string
	 */
	protected function render_price_row( $durations, $symbol, $days, $price ) {
		$output = '<div class="hp-agl-account__tier" data-agl-tier>';

		$output .= '<div class="hp-form__field hp-form__field--select">';
		$output .= '<label class="hp-field__label">' . esc_html__( 'Access lasts', 'additional-gallery-for-hivepress' ) . '</label>';
		$output .= '<select name="days[]" class="hp-field hp-field--select">';

		foreach ( $durations as $value => $label ) {
			$output .= '<option value="' . esc_attr( $value ) . '"' . selected( (string) $value, is_null( $days ) ? '' : (string) absint( $days ), false ) . '>' . esc_html( $label ) . '</option>';
		}

		/*
		 * A length the owner has filtered away since this vendor chose it is added back for this
		 * vendor only, and only while they are still selling it. Dropping it from the list would
		 * silently change what they are selling the moment they saved anything at all.
		 */
		if ( ! is_null( $days ) && ! isset( $durations[ absint( $days ) ] ) ) {
			$output .= '<option value="' . esc_attr( absint( $days ) ) . '" selected>' . esc_html( hivepress()->agl_gallery->get_duration_label( $days ) ) . '</option>';
		}

		$output .= '</select>';
		$output .= '</div>';

		$output .= '<div class="hp-form__field hp-form__field--number">';
		/* translators: %s: currency symbol. */
		$output .= '<label class="hp-field__label">' . esc_html( sprintf( __( 'Price (%s)', 'additional-gallery-for-hivepress' ), $symbol ) ) . '</label>';
		$output .= '<input type="number" name="price[]" class="hp-field hp-field--number" min="0" step="0.01" value="' . esc_attr( '' === $price ? '' : (string) $price ) . '" placeholder="0.00">';
		$output .= '</div>';

		$output .= '<button type="button" class="hp-agl-account__tier-remove" data-agl-remove-tier aria-label="' . esc_attr__( 'Remove this length', 'additional-gallery-for-hivepress' ) . '"><i class="hp-icon fas fa-times"></i></button>';
		$output .= '</div>';

		return $output;
	}
}
