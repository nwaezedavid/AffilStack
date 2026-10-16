<?php
/**
 * FAQPage structured data for posts that came from AffilStack with FAQ
 * entries, so Google can show them as rich results.
 *
 * @package AffilStack_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AffilStack_Schema {

	public static function print_faq_schema() {
		if ( ! is_singular( 'post' ) ) {
			return;
		}

		$faq = get_post_meta( get_queried_object_id(), AffilStack_Importer::FAQ_META, true );

		if ( ! is_array( $faq ) || ! $faq || ! apply_filters( 'affilstack_connector_print_faq_schema', true, get_queried_object_id() ) ) {
			return;
		}

		$entities = array();

		foreach ( $faq as $item ) {
			if ( empty( $item['question'] ) || empty( $item['answer'] ) ) {
				continue;
			}

			$entities[] = array(
				'@type'          => 'Question',
				'name'           => wp_strip_all_tags( $item['question'] ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( $item['answer'] ),
				),
			);
		}

		if ( ! $entities ) {
			return;
		}

		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);

		echo "\n<script type=\"application/ld+json\" class=\"affilstack-faq-schema\">" . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
