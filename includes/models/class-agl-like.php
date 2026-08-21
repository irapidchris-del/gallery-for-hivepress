<?php
/**
 * Gallery photo like model.
 *
 * @package AdditionalGalleryForHivePress\Models
 */

namespace HivePress\Models;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * A like on a single gallery photo.
 *
 * Stored as an `hp_agl_like` comment, mirroring how the Favorites extension
 * stores a favourite: the liker is `user_id` and the liked photo is
 * `comment_post_ID` (the attachment). One row per person per photo.
 *
 * The class name is short on purpose. A comment model's type is
 * `hp_` + the class name (`Comment::init()`), and WordPress' `comment_type`
 * column is only 20 characters, so a friendlier `Gallery_Photo_Like` would be
 * silently truncated and never match on read.
 *
 * @method int|null get_user__id()
 * @method int|null get_photo__id()
 * @method string|null get_created_date()
 */
class Agl_Like extends Comment {

	/**
	 * Class constructor.
	 *
	 * @param array $args Model arguments.
	 */
	public function __construct( $args = [] ) {
		$args = hp\merge_arrays(
			[
				'fields' => [
					'created_date' => [
						'type'   => 'date',
						'format' => 'Y-m-d H:i:s',
						'_alias' => 'comment_date',
					],

					'approved'     => [
						'type'      => 'number',
						'min_value' => 0,
						'max_value' => 1,
						'_alias'    => 'comment_approved',
					],

					'user'         => [
						'type'      => 'number',
						'min_value' => 1,
						'required'  => true,
						'_alias'    => 'user_id',
						'_model'    => 'user',
					],

					'photo'        => [
						'type'      => 'number',
						'min_value' => 1,
						'required'  => true,
						'_alias'    => 'comment_post_ID',
						'_model'    => 'attachment',
					],
				],
			],
			$args
		);

		parent::__construct( $args );
	}
}
