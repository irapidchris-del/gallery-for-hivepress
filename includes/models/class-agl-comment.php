<?php
/**
 * Gallery photo comment model.
 *
 * @package AdditionalGalleryForHivePress\Models
 */

namespace HivePress\Models;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * A comment on a single gallery photo.
 *
 * Stored as an `hp_agl_comment` comment, following the Review model: the text
 * is `comment_content`, the author is `user_id` (with the display name and
 * email in the author columns, as WordPress and the admin comments screen
 * expect), and the photo is `comment_post_ID`.
 *
 * The class name is short for the same reason as the like model: the comment
 * type is `hp_` + the class name and the column holds only 20 characters.
 *
 * @method string|null get_text()
 * @method int|null get_author__id()
 * @method string|null get_author__display_name()
 * @method int|null get_parent__id()
 * @method int|null get_photo__id()
 * @method string|null get_created_date()
 * @method int|null get_approved()
 */
class Agl_Comment extends Comment {

	/**
	 * Class constructor.
	 *
	 * @param array $args Model arguments.
	 */
	public function __construct( $args = [] ) {
		$args = hp\merge_arrays(
			[
				'fields' => [
					'text'                 => [
						'label'      => esc_html__( 'Comment', 'additional-gallery-for-hivepress' ),
						'type'       => 'textarea',
						'max_length' => 1000,
						'required'   => true,
						'html'       => false,
						'_alias'     => 'comment_content',
					],

					'created_date'         => [
						'type'   => 'date',
						'format' => 'Y-m-d H:i:s',
						'_alias' => 'comment_date',
					],

					'approved'             => [
						'type'      => 'number',
						'min_value' => 0,
						'max_value' => 1,
						'_alias'    => 'comment_approved',
					],

					'author'               => [
						'type'     => 'id',
						'required' => true,
						'_alias'   => 'user_id',
						'_model'   => 'user',
					],

					'author__display_name' => [
						'type'       => 'text',
						'max_length' => 256,
						'required'   => true,
						'_alias'     => 'comment_author',
					],

					'author__email'        => [
						'type'     => 'email',
						'required' => true,
						'_alias'   => 'comment_author_email',
					],

					'parent'               => [
						'type'   => 'id',
						'_alias' => 'comment_parent',
						'_model' => 'agl_comment',
					],

					'photo'                => [
						'type'     => 'id',
						'required' => true,
						'_alias'   => 'comment_post_ID',
						'_model'   => 'attachment',
					],
				],
			],
			$args
		);

		parent::__construct( $args );
	}
}
