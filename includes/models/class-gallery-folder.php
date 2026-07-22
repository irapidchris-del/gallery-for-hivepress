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
 */
class Gallery_Folder extends Post {

	/**
	 * Class constructor.
	 *
	 * @param array $args Model arguments.
	 */
	public function __construct( $args = [] ) {

		// Get the per-folder image limit.
		$max_images = hp_agl_int( get_option( 'hp_gallery_max_images' ) );

		if ( ! $max_images ) {
			$max_images = 30;
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

						'options'     => [
							'public'  => esc_html__( 'Public', 'additional-gallery-for-hivepress' ),
							'members' => esc_html__( 'Members only', 'additional-gallery-for-hivepress' ),
							'private' => esc_html__( 'Private', 'additional-gallery-for-hivepress' ),
						],
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
