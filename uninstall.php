<?php
/**
 * Uninstall routine.
 *
 * **Deleting this plugin keeps the site owner's data by default.** Folders,
 * photos, likes, comments, purchased access and every setting survive, so a
 * plugin removed by accident, or removed to reinstall a clean copy, can be put
 * back exactly as it was. Destruction is opt-in: it happens only when the
 * owner has ticked "Delete all gallery data when this plugin is deleted" on the
 * settings screen first. WordPress prints its own "(will also delete its data)"
 * warning on the delete screen whenever an uninstall.php exists, whatever that
 * file actually does, so the setting's description says plainly that the
 * warning does not apply here.
 *
 * Retaining means changing nothing the owner would notice: only regenerable
 * caches go, because they rebuild themselves. In particular the files in the
 * protected store stay exactly where they are, still behind their deny rule.
 * Moving them back into public uploads would be a privacy regression rather
 * than a courtesy: the photos keep their media-library entries, so a private
 * photo would become directly fetchable the moment the proxy route stopped
 * guarding it. They are moved back only on the delete path, where the plugin
 * really is going for good and an unreachable file would otherwise be lost.
 *
 * Ordering matters on the delete path: the options are cleared first because
 * WP-CLI's `plugin uninstall --deactivate` runs this file in the same process
 * that loaded the plugin, so its hooks may still be listening, and with
 * `hp_gallery_protect_files` already gone nothing re-protects the files this
 * routine is moving back out.
 *
 * @package AdditionalGalleryForHivePress
 */

// Exit if not uninstalling.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Cleans up the current site, honouring the owner's "delete all data" choice.
 *
 * Written against plain WordPress APIs only: the plugin's own classes and
 * helper functions are not loaded during uninstall.
 *
 * @param bool $delete_data Whether the owner opted in to full deletion.
 * @return void
 */
function hp_agl_uninstall_site( $delete_data ) {
	$upload_dir = wp_get_upload_dir();
	$basedir    = $upload_dir['basedir'];

	// 1. Regenerable caches go whatever the owner chose: they are junk the
	// moment the plugin is gone, and both rebuild by themselves on a reinstall.
	// (There are no scheduled actions to unschedule: this plugin queues none.)
	delete_site_transient( 'hp_agl_github_release' );
	hp_agl_uninstall_rmdir( $basedir . '/hp-agl-teasers' );

	// Retaining is the default and stops here, leaving every folder, photo,
	// like, comment, access grant, setting and protected file untouched.
	if ( ! $delete_data ) {
		return;
	}

	// 2. Options go first, so any listener still registered in this process
	// stands down before the files move.
	$options = [
		'hp_gallery_hide_vendor_link',
		'hp_gallery_hide_listing_link',
		'hp_gallery_layout',
		'hp_gallery_max_folders',
		'hp_gallery_max_images',
		'hp_gallery_allow_video',
		'hp_gallery_ai_moderation',
		'hp_gallery_locked_display',
		'hp_gallery_upgrade_page',
		'hp_gallery_protect_files',
		'hp_gallery_max_filesize',
		'hp_gallery_image_formats',
		'hp_gallery_max_dimensions',
		'hp_gallery_enable_likes',
		'hp_gallery_enable_comments',
		'hp_gallery_enable_lightbox',
		'hp_gallery_show_button_count',
		'hp_gallery_enable_members',
		'hp_gallery_enable_paid_access',
		'hp_gallery_access_period',
		'hp_gallery_access_period_2',
		'hp_gallery_access_period_3',
		'hp_gallery_commission_rate',
		'hp_gallery_commission_fee',
		'hp_gallery_storage_limit',
		'hp_gallery_photo_sidebar',

		// Retired in 1.5.0, deleted again in case an upgrade never ran.
		'hp_gallery_image_quality',
		'hp_gallery_strip_metadata',
		'hp_gallery_convert_webp',
		'hp_gallery_keep_originals',
		'hp_gallery_access_gated',
		'hp_gallery_view_gated',
		'hp_agl_version',
		'hp_agl_flush_rewrite_rules',

		// Retired in 1.3.0, deleted again in case an upgrade never ran.
		'hp_gallery_manage_plans',
		'hp_gallery_view_plans',
	];

	// `hp_gallery_delete_data` is deliberately absent from that list, even
	// though it shares the `hp_gallery_` prefix. It is removed last instead
	// (see the end of this function), so a failure part way through leaves the
	// flag set: a second delete then finishes the job, rather than the site
	// silently reverting to "retain" with half its data already gone. Anyone
	// converting this list to a prefix sweep must exclude it explicitly.
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// `hp_openai_api_key` is deliberately NOT deleted on either path: it is
	// shared with other OpenAI-based HivePress extensions, and removing it
	// would break them.

	// 3. Find every gallery folder.
	$folder_ids = get_posts(
		[
			'post_type'   => 'hp_gallery_folder',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		]
	);

	foreach ( $folder_ids as $folder_id ) {

		// Get the folder's attachments directly, so this does not depend on the
		// plugin's model layer being available.
		$attachment_ids = get_posts(
			[
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'post_parent' => $folder_id,
				'numberposts' => -1,
				'fields'      => 'ids',
			]
		);

		foreach ( $attachment_ids as $attachment_id ) {

			// 4. Move protected files back to their normal location. Without
			// this the files would stay in a directory that denies direct
			// access, with no proxy route left to serve them, leaving every
			// members-only and private photo permanently unviewable even
			// though the photo itself is kept.
			hp_agl_uninstall_unprotect( $attachment_id, $basedir );

			// Drop the backup copy kept for "Restore original images".
			$backup = get_post_meta( $attachment_id, 'hp_agl_original', true );

			if ( is_array( $backup ) && ! empty( $backup['file'] ) ) {
				$backup_path = $basedir . '/' . $backup['file'];

				if ( is_string( $backup['file'] ) && file_exists( $backup_path ) ) {
					wp_delete_file( $backup_path );
				}
			}

			delete_post_meta( $attachment_id, 'hp_agl_original' );
			delete_post_meta( $attachment_id, 'hp_agl_protected' );

			// Remove the photo's likes, comments and comment likes. These are
			// our own comment rows, so they go even though the photo is kept.
			$engagement = get_comments(
				[
					'post_id'  => $attachment_id,
					'type__in' => [ 'hp_agl_like', 'hp_agl_comment', 'hp_agl_clike' ],
					'status'   => 'any',
					'fields'   => 'ids',
					'number'   => 0,
				]
			);

			foreach ( (array) $engagement as $comment_id ) {
				wp_delete_comment( absint( $comment_id ), true );
			}

			// 5. Detach the photo before the folder is deleted. The vendors'
			// images are their own content, so they stay in the media library
			// as ordinary uploads rather than being destroyed with the folder.
			// Clearing the HivePress parent meta too stops the core attachment
			// component from treating them as gallery images and deleting them.
			delete_post_meta( $attachment_id, 'hp_parent_model' );
			delete_post_meta( $attachment_id, 'hp_parent_field' );

			wp_update_post(
				[
					'ID'          => $attachment_id,
					'post_parent' => 0,
				]
			);
		}

		// 6. Delete the folder itself, bypassing the trash.
		wp_delete_post( $folder_id, true );
	}

	// 7. Remove the plugin's meta from membership plans and vendors. These
	// live on posts that are not ours to delete, so they need clearing by key.
	// The folders' own `hp_visibility` meta and the cached image-ID transients
	// are post meta on the folders deleted above, so they go with them.
	delete_metadata( 'post', 0, 'hp_gallery_access', '', true );
	delete_metadata( 'post', 0, 'hp_gallery_view', '', true );
	delete_metadata( 'post', 0, 'hp_gallery_folder_limit', '', true );
	delete_metadata( 'post', 0, 'hp_gallery_image_limit', '', true );
	delete_metadata( 'post', 0, 'hp_gallery_storage_limit', '', true );
	delete_metadata( 'post', 0, 'hp_gallery_price', '', true );
	delete_metadata( 'post', 0, 'hp_gallery_product', '', true );
	delete_metadata( 'post', 0, 'hp_gallery_price_2', '', true );
	delete_metadata( 'post', 0, 'hp_gallery_product_2', '', true );
	delete_metadata( 'post', 0, 'hp_gallery_price_3', '', true );
	delete_metadata( 'post', 0, 'hp_gallery_days', '', true );
	delete_metadata( 'post', 0, 'hp_gallery_days_2', '', true );
	delete_metadata( 'post', 0, 'hp_gallery_days_3', '', true );
	delete_metadata( 'post', 0, 'hp_agl_cover', '', true );
	delete_metadata( 'post', 0, 'hp_gallery_product_3', '', true );

	// Markers on the access products themselves. The products are WooCommerce's, and someone's
	// order history refers to them, so they are left in place with our markings taken off.
	delete_metadata( 'post', 0, 'hp_agl_vendor', '', true );
	delete_metadata( 'post', 0, 'hp_agl_period', '', true );
	delete_metadata( 'post', 0, 'hp_agl_tier', '', true );

	// 8. Remove purchased-access grants. The meta key embeds the vendor ID,
	// so wildcard keys need SQL (with the underscores escaped).
	global $wpdb;

	$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'hp\\_agl\\_access\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- wildcard meta keys cannot be removed with the meta API.

	// 9. Trash the gallery-access products this plugin created. Trashed, not
	// deleted: order history may still reference them, and an admin can decide.
	$product_ids = get_posts(
		[
			'post_type'   => 'product',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',

			'meta_query'  => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one-off uninstall lookup.
				[
					'key'     => 'hp_agl_vendor',
					'compare' => 'EXISTS',
				],
			],
		]
	);

	foreach ( $product_ids as $product_id ) {
		wp_trash_post( $product_id );
	}

	// 10. Remove the plugin's private directories (blurred previews, originals
	// backups and the protected store, which is empty by now).
	foreach ( [ 'hp-agl-teasers', 'hp-agl-originals', 'hp-agl-protected' ] as $dir ) {
		hp_agl_uninstall_rmdir( $basedir . '/' . $dir );
	}

	// 11. Sweep the markers again at the end. Deleting folders and trashing
	// products fires post hooks, and under WP-CLI's
	// `plugin uninstall --deactivate` this file runs with the plugin's
	// listeners still registered, so `refresh_gating_flags()` can write the
	// gating flags back after step 2 has already removed them.
	foreach ( [ 'hp_gallery_access_gated', 'hp_gallery_view_gated', 'hp_agl_version', 'hp_agl_flush_rewrite_rules' ] as $marker ) {
		delete_option( $marker );
	}

	// 12. The opt-in flag itself goes last of all, so a failure anywhere above
	// leaves it set and a second delete finishes the job.
	delete_option( 'hp_gallery_delete_data' );
}

/**
 * Moves an attachment's files out of the protected directory and repoints the
 * attachment at their normal location.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $basedir Uploads base directory.
 * @return void
 */
function hp_agl_uninstall_unprotect( $attachment_id, $basedir ) {
	$prefix   = 'hp-agl-protected/';
	$relative = get_post_meta( $attachment_id, '_wp_attached_file', true );

	if ( ! is_string( $relative ) || 0 !== strpos( $relative, $prefix ) ) {
		return;
	}

	$destination = substr( $relative, strlen( $prefix ) );

	$from_dir = dirname( $basedir . '/' . $relative );
	$to_dir   = dirname( $basedir . '/' . $destination );

	if ( ! wp_mkdir_p( $to_dir ) ) {
		return;
	}

	// Collect the original, every generated size and the scaled backup. The
	// metadata is annotated loosely because WordPress adds keys beyond the
	// documented shape, including `original_image` on scaled uploads.
	$files = [ basename( $relative ) ];

	/** @var array<string,mixed>|false $metadata */
	$metadata = wp_get_attachment_metadata( $attachment_id );

	if ( is_array( $metadata ) ) {
		if ( ! empty( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) && is_string( $size['file'] ) ) {
					$files[] = $size['file'];
				}
			}
		}

		if ( ! empty( $metadata['original_image'] ) && is_string( $metadata['original_image'] ) ) {
			$files[] = $metadata['original_image'];
		}
	}

	$main = basename( $relative );

	// Abort if the main file cannot be moved, leaving the attachment consistent
	// with wherever its files actually are.
	if ( ! hp_agl_uninstall_move( $from_dir . '/' . $main, $to_dir . '/' . $main ) ) {
		return;
	}

	foreach ( array_unique( $files ) as $file ) {
		if ( $file !== $main ) {
			hp_agl_uninstall_move( $from_dir . '/' . $file, $to_dir . '/' . $file );
		}
	}

	update_post_meta( $attachment_id, '_wp_attached_file', $destination );

	if ( is_array( $metadata ) ) {
		$metadata['file'] = $destination;

		wp_update_attachment_metadata( $attachment_id, $metadata );
	}
}

/**
 * Moves a single file, tolerating an already-moved source.
 *
 * @param string $from Source path.
 * @param string $to Destination path.
 * @return bool
 */
function hp_agl_uninstall_move( $from, $to ) {
	if ( $from === $to ) {
		return true;
	}

	if ( ! file_exists( $from ) ) {
		return file_exists( $to );
	}

	if ( @rename( $from, $to ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- direct move of a local upload; falls back to copy below.
		return true;
	}

	if ( @copy( $from, $to ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		wp_delete_file( $from );

		return true;
	}

	return false;
}

/**
 * Deletes a directory and its contents.
 *
 * Uses `scandir()` rather than `glob()` because glob skips dot files, which
 * would leave the `.htaccess` guard behind and so leave the directory itself
 * undeletable.
 *
 * @param string $dir Directory path.
 * @return void
 */
function hp_agl_uninstall_rmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$items = scandir( $dir );

	foreach ( (array) $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}

		$path = trailingslashit( $dir ) . $item;

		if ( is_dir( $path ) ) {
			hp_agl_uninstall_rmdir( $path );
		} else {
			wp_delete_file( $path );
		}
	}

	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rmdir_rmdir, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- removing our own now-empty upload directory during uninstall; WP_Filesystem targets remote/credentialed setups and would need filesystem credentials we cannot prompt for here.
	@rmdir( $dir );
}

// Run for every site on the network, so a multisite install is left clean too.
// The opt-in is read per site, inside the loop: each site's owner decides what
// happens to their own gallery, and one site opting in never wipes another.
if ( is_multisite() ) {
	$hp_agl_site_ids = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	);

	foreach ( $hp_agl_site_ids as $hp_agl_site_id ) {
		switch_to_blog( $hp_agl_site_id );

		hp_agl_uninstall_site( (bool) get_option( 'hp_gallery_delete_data' ) );

		restore_current_blog();
	}
} else {
	hp_agl_uninstall_site( (bool) get_option( 'hp_gallery_delete_data' ) );
}
