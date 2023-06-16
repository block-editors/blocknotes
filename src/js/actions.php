<?php

add_filter( 'wp_insert_post_data', function( $data ) {
	if ( $data['post_type'] !== 'hypernote' ) return $data;
	if ( $data['post_status'] !== 'private' ) return $data;
	$blocks = parse_blocks( $data['post_content'] );

	$i = 0;
	$text = '';

	while ( isset( $blocks[ $i ] ) ) {
		$text = wp_trim_words( $blocks[ $i ]['innerHTML'], 10, '' );
		$i++;

		if ( $text ) {
			break;
		}
	}
	if ( ! $text ) return $data;
	post_message_to_js( json_encode( array(
		'name' => $data['post_title'],
		'newName' => $text,
		'content' => $data['post_content'],
	) ) );
	$data['post_title'] = $text;
	return $data;
} );

function get_taxonomy_hierarchy($term_id) {
	$taxonomy_titles = [];

	// Get the term by its ID.
	$term = get_term($term_id);

	if ($term && !is_wp_error($term)) {
		// Add the term's name to the beginning of the array.
		array_unshift($taxonomy_titles, $term->name);

		// Get the term's ancestors.
		$ancestors = get_ancestors($term_id, $term->taxonomy);

		// For each ancestor, get its name and add it to the beginning of the array.
		foreach ($ancestors as $ancestor_id) {
			$ancestor = get_term($ancestor_id);

			if ($ancestor && !is_wp_error($ancestor)) {
				array_unshift($taxonomy_titles, $ancestor->name);
			}
		}
	}

	return $taxonomy_titles;
}

function hypernotes_set_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
	if ( $taxonomy !== 'hypernote-folder' ) return;
	$post = get_post( $object_id );

	if ( count( $tt_ids ) > 1 ) {
		remove_action( 'set_object_terms', 'hypernotes_set_object_terms', 10, 6 );
		wp_set_object_terms( $object_id, (int) $tt_ids[0], $taxonomy );
		add_action( 'set_object_terms', 'hypernotes_set_object_terms', 10, 6 );
	}

	post_message_to_js( json_encode( array(
		'name' => $post->post_title,
		'newPath' => get_taxonomy_hierarchy( (int) $tt_ids[0] ),
		'path' => get_taxonomy_hierarchy( (int) $old_tt_ids[0] ),
	) ) );
}

add_action( 'set_object_terms', 'hypernotes_set_object_terms', 10, 6 );

add_action( 'created_term', function( $term_id, $tt_id, $taxonomy ) {
	if ( $taxonomy !== 'hypernote-folder' ) return;
	post_message_to_js( json_encode( array(
		'newPath' => get_taxonomy_hierarchy( (int) $term_id ),
	) ) );
}, 10, 3 );

global $_hypernotes_path;

add_action( 'edited_terms', function( $term_id, $taxonomy ) {
	if ( $taxonomy !== 'hypernote-folder' ) return;
	global $_hypernotes_path;
	$_hypernotes_path = get_taxonomy_hierarchy( (int) $term_id );
}, 10, 3 );

add_action( 'edited_term', function( $term_id, $tt_id, $taxonomy ) {
	if ( $taxonomy !== 'hypernote-folder' ) return;
	global $_hypernotes_path;
	post_message_to_js( json_encode( array(
		'newPath' => get_taxonomy_hierarchy( (int) $term_id ),
		'path' => $_hypernotes_path,
	) ) );
}, 10, 3 );
