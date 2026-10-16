<?php
/**
 * Creates or updates the WordPress draft for an AffilStack article.
 *
 * Sending the same article again updates the same draft (matched by its
 * AffilStack id), but never touches a post that has been published,
 * scheduled or trashed, so edits made on the live site are safe.
 *
 * @package AffilStack_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AffilStack_Importer {

	const EXTERNAL_ID_META = '_affilstack_external_id';
	const FAQ_META         = '_affilstack_faq';
	const COVER_META        = '_affilstack_featured_source';
	const IMAGES_META       = '_affilstack_inline_images';
	const MAX_INLINE_IMAGES = 10;

	/**
	 * @param array $article Payload from AffilStack.
	 * @return array|WP_Error
	 */
	public static function import( array $article ) {
		$external_id = isset( $article['external_id'] ) ? sanitize_text_field( $article['external_id'] ) : '';
		$title       = isset( $article['title'] ) ? sanitize_text_field( $article['title'] ) : '';

		if ( '' === $external_id || '' === $title ) {
			return new WP_Error( 'affilstack_invalid', __( 'The article is missing its title.', 'affilstack-connector' ), array( 'status' => 422 ) );
		}

		$existing = self::find( $external_id );

		if ( $existing && ! in_array( $existing->post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
			return new WP_Error(
				'affilstack_published',
				__( 'This article is already published on this site.', 'affilstack-connector' ),
				array(
					'status'      => 409,
					'post_id'     => (int) $existing->ID,
					'post_status' => $existing->post_status,
					'edit_url'    => 'trash' === $existing->post_status ? null : get_edit_post_link( $existing->ID, 'raw' ),
				)
			);
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$author  = self::author();
		$content = isset( $article['content'] ) ? self::clean_content( (string) $article['content'] ) : '';
		$postarr = array(
			'post_type'    => 'post',
			'post_status'  => $existing ? $existing->post_status : 'draft',
			'post_title'   => $title,
			'post_name'    => isset( $article['slug'] ) ? sanitize_title( $article['slug'] ) : sanitize_title( $title ),
			'post_content' => $content,
			'post_excerpt' => isset( $article['excerpt'] ) ? sanitize_textarea_field( $article['excerpt'] ) : '',
			'post_author'  => $author,
		);

		if ( $existing ) {
			$postarr['ID'] = $existing->ID;
			$post_id       = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$post_id = wp_insert_post( wp_slash( $postarr ), true );
		}

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error( 'affilstack_save_failed', $post_id->get_error_message(), array( 'status' => 500 ) );
		}

		update_post_meta( $post_id, self::EXTERNAL_ID_META, $external_id );

		self::set_terms( $post_id, $article );
		self::set_featured_image( $post_id, isset( $article['featured_image'] ) && is_array( $article['featured_image'] ) ? $article['featured_image'] : null );
		self::localise_inline_images( $post_id, $content );
		self::set_seo( $post_id, isset( $article['seo'] ) && is_array( $article['seo'] ) ? $article['seo'] : array() );
		self::set_faq( $post_id, isset( $article['faq'] ) && is_array( $article['faq'] ) ? $article['faq'] : array() );

		$post = get_post( $post_id );

		return array(
			'post_id'     => (int) $post_id,
			'status'      => $post ? $post->post_status : 'draft',
			'edit_url'    => get_edit_post_link( $post_id, 'raw' ),
			'preview_url' => get_preview_post_link( $post_id ),
			'updated'     => (bool) $existing,
		);
	}

	/**
	 * @return WP_Post|null
	 */
	public static function find( $external_id ) {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'draft', 'pending', 'auto-draft', 'publish', 'future', 'private', 'trash' ),
				'meta_key'       => self::EXTERNAL_ID_META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $external_id, // phpcs:ignore WordPress.DB.SlowDBQuery
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);

		return $posts ? $posts[0] : null;
	}

	/**
	 * The person who connected the site writes the drafts; otherwise the
	 * first administrator.
	 *
	 * @return int
	 */
	protected static function author() {
		$connection = AffilStack_Connection::get();

		if ( ! empty( $connection['connected_by'] ) && get_userdata( (int) $connection['connected_by'] ) ) {
			return (int) $connection['connected_by'];
		}

		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);

		return $admins ? (int) $admins[0] : 0;
	}

	protected static function set_terms( $post_id, array $article ) {
		$category_ids = array();

		foreach ( array_slice( isset( $article['categories'] ) && is_array( $article['categories'] ) ? $article['categories'] : array(), 0, 3 ) as $name ) {
			$name = sanitize_text_field( (string) $name );

			if ( '' === $name ) {
				continue;
			}

			$term = term_exists( $name, 'category' );

			if ( ! $term ) {
				$term = wp_insert_term( $name, 'category' );
			}

			if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
				$category_ids[] = (int) $term['term_id'];
			}
		}

		if ( $category_ids ) {
			wp_set_post_categories( $post_id, $category_ids, false );
		}

		$tags = array();

		foreach ( array_slice( isset( $article['tags'] ) && is_array( $article['tags'] ) ? $article['tags'] : array(), 0, 10 ) as $tag ) {
			$tag = sanitize_text_field( (string) $tag );

			if ( '' !== $tag ) {
				$tags[] = $tag;
			}
		}

		if ( $tags ) {
			wp_set_post_tags( $post_id, $tags, false );
		}
	}

	protected static function set_featured_image( $post_id, $image ) {
		if ( ! $image || empty( $image['url'] ) || ! wp_http_validate_url( $image['url'] ) ) {
			return;
		}

		$url = esc_url_raw( $image['url'] );

		// Already downloaded this exact image for this post.
		if ( get_post_meta( $post_id, self::COVER_META, true ) === $url && has_post_thumbnail( $post_id ) ) {
			return;
		}

		$attachment_id = media_sideload_image( $url, $post_id, isset( $image['alt'] ) ? sanitize_text_field( $image['alt'] ) : null, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			return;
		}

		if ( ! empty( $image['alt'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $image['alt'] ) );
		}

		set_post_thumbnail( $post_id, $attachment_id );
		update_post_meta( $post_id, self::COVER_META, $url );
	}

	/**
	 * Post HTML as an author may write it. Script and style blocks go
	 * completely, not just their tags.
	 *
	 * @return string
	 */
	protected static function clean_content( $html ) {
		$html = preg_replace( '#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $html );

		return wp_kses_post( (string) $html );
	}

	/**
	 * Copies images the article links to into the media library, so the
	 * draft doesn't depend on another server. Each image is downloaded
	 * once per post: sending the article again reuses the copies.
	 */
	protected static function localise_inline_images( $post_id, $content ) {
		if ( ! preg_match_all( '/<img[^>]+src=["\'](https?:\/\/[^"\']+)["\']/i', $content, $matches ) ) {
			return;
		}

		$uploads = wp_get_upload_dir();
		$known   = get_post_meta( $post_id, self::IMAGES_META, true );
		$known   = is_array( $known ) ? $known : array();
		$updated = $content;
		$fetched = 0;

		foreach ( array_unique( $matches[1] ) as $src ) {
			if ( 0 === strpos( $src, $uploads['baseurl'] ) ) {
				continue;
			}

			$key = md5( $src );

			if ( isset( $known[ $key ] ) && wp_get_attachment_url( (int) $known[ $key ] ) ) {
				$updated = str_replace( $src, wp_get_attachment_url( (int) $known[ $key ] ), $updated );
				continue;
			}

			if ( $fetched >= self::MAX_INLINE_IMAGES ) {
				continue;
			}

			$attachment_id = media_sideload_image( esc_url_raw( $src ), $post_id, null, 'id' );
			++$fetched;

			if ( ! is_wp_error( $attachment_id ) ) {
				$known[ $key ] = (int) $attachment_id;
				$updated       = str_replace( $src, wp_get_attachment_url( $attachment_id ), $updated );
			}
		}

		update_post_meta( $post_id, self::IMAGES_META, $known );

		if ( $updated !== $content ) {
			wp_update_post(
				wp_slash(
					array(
						'ID'           => $post_id,
						'post_content' => $updated,
					)
				)
			);
		}
	}

	protected static function set_seo( $post_id, array $seo ) {
		$title       = isset( $seo['title'] ) ? sanitize_text_field( $seo['title'] ) : '';
		$description = isset( $seo['description'] ) ? sanitize_textarea_field( $seo['description'] ) : '';
		$keyword     = isset( $seo['focus_keyword'] ) ? sanitize_text_field( $seo['focus_keyword'] ) : '';
		$plugin      = AffilStack_Connection::seo_plugin();

		// Yoast SEO and Rank Math read plain post meta. Both are written when
		// neither is active yet, so switching SEO plugins later keeps them.
		if ( in_array( $plugin, array( 'yoast', 'none' ), true ) ) {
			self::meta( $post_id, '_yoast_wpseo_title', $title );
			self::meta( $post_id, '_yoast_wpseo_metadesc', $description );
			self::meta( $post_id, '_yoast_wpseo_focuskw', $keyword );
		}

		if ( in_array( $plugin, array( 'rank_math', 'none' ), true ) ) {
			self::meta( $post_id, 'rank_math_title', $title );
			self::meta( $post_id, 'rank_math_description', $description );
			self::meta( $post_id, 'rank_math_focus_keyword', $keyword );
		}

		if ( 'aioseo' === $plugin ) {
			self::meta( $post_id, '_aioseo_title', $title );
			self::meta( $post_id, '_aioseo_description', $description );
			self::meta( $post_id, '_aioseo_keywords', $keyword );

			// All in One SEO 4 keeps its fields in its own table.
			if ( class_exists( '\AIOSEO\Plugin\Common\Models\Post' ) ) {
				try {
					$aioseo_post = \AIOSEO\Plugin\Common\Models\Post::getPost( $post_id );

					if ( $aioseo_post ) {
						$aioseo_post->post_id     = $post_id;
						$aioseo_post->title       = $title;
						$aioseo_post->description = $description;

						if ( '' !== $keyword ) {
							$aioseo_post->keyphrases = wp_json_encode(
								array(
									'focus'      => array( 'keyphrase' => $keyword ),
									'additional' => array(),
								)
							);
						}

						$aioseo_post->save();
					}
				} catch ( \Throwable $e ) {
					// The plain meta above is still there for AIOSEO to pick up.
					unset( $e );
				}
			}
		}
	}

	protected static function set_faq( $post_id, array $faq ) {
		$clean = array();

		foreach ( array_slice( $faq, 0, 8 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$question = isset( $item['question'] ) ? sanitize_text_field( $item['question'] ) : '';
			$answer   = isset( $item['answer'] ) ? sanitize_textarea_field( $item['answer'] ) : '';

			if ( '' !== $question && '' !== $answer ) {
				$clean[] = array(
					'question' => $question,
					'answer'   => $answer,
				);
			}
		}

		if ( $clean ) {
			update_post_meta( $post_id, self::FAQ_META, $clean );
		} else {
			delete_post_meta( $post_id, self::FAQ_META );
		}
	}

	protected static function meta( $post_id, $key, $value ) {
		if ( '' === $value ) {
			return;
		}

		update_post_meta( $post_id, $key, $value );
	}
}
