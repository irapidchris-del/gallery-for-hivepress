<?php
/**
 * Gallery folder model.
 *
 * @package AdditionalGalleryForHivePress\Models
 */

namespace HivePress\Models;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Gallery folder.
 *
 * Stored as the `hp_gallery_folder` post type (the alias is derived from the
 * model name by the HivePress Post model). The folder owner is the post
 * author, the vendor is the post parent, visibility is the `hp_visibility`
 * meta, and images are WordPress attachments managed by the HivePress
 * attachment system.
 *
 * Every field getter below is magic, dispatched by `Model::__call()`, so each
 * one is declared here for static analysis. Note that `method_exists()` is
 * therefore always false for them, and `is_callable()` always true: use
 * `instanceof` to check for this model.
 *
 * **This class deliberately keeps its unprefixed name, and must not be
 * renamed** (checked again and rejected in 1.8.0, when every other class in the
 * plugin took the `Agl_` prefix). Four separate mechanisms pin it, and each one
 * fails silently rather than fatally:
 *
 * 1. The post type is `hp_` + the class name (`models/class-post.php`), and it
 *    holds the site's real folder data.
 * 2. Every gallery image stores `hp_parent_model = 'gallery_folder'` as post
 *    meta, which core resolves back into `\HivePress\Models\Gallery_Folder`
 *    (`models/class-attachment.php`, `components/class-model.php`). A rename
 *    orphans every photo already uploaded.
 * 3. Core's own attachment endpoints return a 400 the moment that lookup
 *    returns null, so sorting and deleting images would break.
 * 4. The wp-admin uploader posts `parent_model` derived from the post type
 *    (`components/class-admin.php`), so admin uploads would break with no
 *    plugin-side hook available to correct them.
 *
 * The collision risk this leaves is accepted and understood: it needs a data
 * migration of every `hp_parent_model` row, not a rename.
 *
 * @method string|null get_title()
 * @method string|null get_description()
 * @method string|null get_visibility()
 * @method string|null get_status()
 * @method int|null get_sort_order()
 * @method void set_sort_order( int $order )
 * @method int|null get_user__id()
 * @method int|null get_vendor__id()
 * @method array get_images()
 * @method void set_images( array $ids )
 */
class Gallery_Folder extends Post {

	/**
	 * Class constructor.
	 *
	 * @param array $args Model arguments.
	 */
	public function __construct( $args = [] ) {

		// Get the per-folder image limit. A membership plan can raise it for
		// the folder's owner, so the limit is resolved for the current user
		// (the only person whose uploads this field ever governs on the front
		// end; the admin meta box uses the site-wide value).
		$max_images = 30;

		// The visibility choices honour the site's members-only setting, so a
		// site with monetisation switched off offers vendors a simple
		// public-or-private choice.
		$visibility_options = [
			'public'  => esc_html__( 'Public', 'additional-gallery-for-hivepress' ),
			'members' => esc_html__( 'Members only', 'additional-gallery-for-hivepress' ),
			'private' => esc_html__( 'Private', 'additional-gallery-for-hivepress' ),
		];

		if ( function_exists( 'hivepress' ) && hivepress()->agl_gallery ) {
			$max_images         = hivepress()->agl_gallery->get_image_limit( get_current_user_id() );
			$visibility_options = hivepress()->agl_gallery->get_visibility_options();
		} else {
			$stored = hp_agl_int( get_option( 'hp_gallery_max_images' ) );

			if ( $stored ) {
				$max_images = $stored;
			}
		}

		// Get the accepted formats (honouring the admin format restrictions).
		$formats = hp_agl_get_upload_formats();

		$args = hp\merge_arrays(
			[
				'fields' => [
					'title'        => [
						'label'      => esc_html__( 'Folder Name', 'additional-gallery-for-hivepress' ),
						'type'       => 'text',
						'max_length' => 128,
						'required'   => true,
						'_alias'     => 'post_title',
					],

					'description'  => [
						'label'      => esc_html__( 'Description', 'additional-gallery-for-hivepress' ),
						'type'       => 'textarea',
						'max_length' => 2048,
						'html'       => false,
						'_alias'     => 'post_content',
					],

					'visibility'   => [
						'label'       => esc_html__( 'Visibility', 'additional-gallery-for-hivepress' ),
						'description' => esc_html__( 'Public folders are visible to everyone. Members-only folders are locked for visitors without member access. Private folders are visible only to you.', 'additional-gallery-for-hivepress' ),
						'type'        => 'select',
						'required'    => true,
						'default'     => 'public',
						'_external'   => true,
						'options'     => $visibility_options,
					],

					'status'       => [
						'type'    => 'select',
						'_alias'  => 'post_status',

						'options' => [
							'publish' => '',
							'draft'   => '',
							'trash'   => '',
						],
					],

					'sort_order'   => [
						'type'      => 'number',
						'min_value' => 0,
						'_alias'    => 'menu_order',
					],

					'created_date' => [
						'type'   => 'date',
						'format' => 'Y-m-d H:i:s',
						'_alias' => 'post_date',
					],

					'user'         => [
						'type'     => 'id',
						'required' => true,
						'_alias'   => 'post_author',
						'_model'   => 'user',
					],

					'vendor'       => [
						'type'   => 'id',
						'_alias' => 'post_parent',
						'_model' => 'vendor',
					],

					'images'       => [
						'label'     => esc_html__( 'Images', 'additional-gallery-for-hivepress' ),
						'caption'   => esc_html__( 'Select Images', 'additional-gallery-for-hivepress' ),
						'type'      => 'attachment_upload',
						'multiple'  => true,
						'max_files' => $max_images,
						'formats'   => $formats,
						'protected' => (bool) get_option( 'hp_gallery_protect_files' ),
						'_model'    => 'attachment',
						'_relation' => 'one_to_many',
					],
				],
			],
			$args
		);

		parent::__construct( $args );
	}

	/**
	 * Gets image IDs.
	 *
	 * One-to-many relation fields are not populated when a model is read
	 * from the database, so this loads them lazily, mirroring the core
	 * Listing model. `get_attached_media()` orders results by `menu_order`,
	 * preserving the drag-and-drop sort order set in the upload field.
	 *
	 * The IDs are cached per folder in the attachment cache group, which
	 * HivePress clears automatically whenever an attachment of this folder
	 * is added, updated or deleted.
	 *
	 * @return array
	 */
	final public function get_images__id() {
		if ( ! isset( $this->values['images__id'] ) ) {

			// Get cached image IDs.
			$image_ids = hivepress()->cache->get_post_cache( $this->id, 'image_ids', 'models/attachment' );

			if ( is_null( $image_ids ) ) {
				$image_ids = [];

				// Get file formats.
				$formats = [ 'image' ];

				if ( get_option( 'hp_gallery_allow_video' ) ) {
					$formats[] = 'video';
				}

				// Get image IDs.
				foreach ( get_attached_media( $formats, $this->id ) as $image ) {
					if ( 'images' === $image->hp_parent_field ) {
						$image_ids[] = $image->ID;
					}
				}

				// Cache image IDs.
				hivepress()->cache->set_post_cache( $this->id, 'image_ids', 'models/attachment', $image_ids );
			}

			// Set field value.
			$this->set_images( $image_ids );
			$this->values['images__id'] = $image_ids;
		}

		return $this->fields['images']->get_value();
	}
}
