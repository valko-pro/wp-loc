<?php
/**
 * A translation copy gets the source post's terms, mapped to its language, the
 * moment it is created — not only on the next save of the source — and every
 * copied meta key ends up as exactly one row.
 * Run: wp eval-file web/app/plugins/wp-loc/tests/translation-copy-terms.php --user=1
 *
 * Needs two active languages, taxonomy sync enabled and a translatable taxonomy
 * term that has a translation in the second language. Creates TEST posts and
 * deletes them at the end.
 */

$failed = 0;
$check = static function ( string $label, $actual, $expected ) use ( &$failed ) {
    if ( $actual === $expected ) {
        WP_CLI::log( "PASS {$label}" );
        return;
    }
    $failed++;
    WP_CLI::warning( "FAIL {$label}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
};

$default = WP_LOC_Languages::get_default_language();
$other   = WP_LOC_Languages::get_additional_languages()[0] ?? null;
if ( ! $other ) { WP_CLI::error( 'Need a second language.' ); }
if ( ! WP_LOC_Admin_Settings::should_sync_post_taxonomies() ) { WP_CLI::error( 'Taxonomy sync is off.' ); }

$post_type = in_array( 'product', apply_filters( 'wp_loc_translatable_post_types', [ 'post', 'page' ] ), true ) ? 'product' : 'post';
$taxonomy  = $post_type === 'product' ? 'product_cat' : 'category';
$db        = WP_LOC::instance()->db;
$ptype     = WP_LOC_DB::post_element_type( $post_type );

// A default-language term that already has a translation.
$source_term = $translated_term = 0;
foreach ( get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false, 'lang' => $default, 'number' => 200 ] ) as $term ) {
    $twin = WP_LOC_Terms::get_term_translation( (int) $term->term_id, $taxonomy, $other );
    if ( $twin ) {
        $source_term     = (int) $term->term_id;
        $translated_term = (int) $twin;
        break;
    }
}
if ( ! $source_term ) { WP_CLI::error( "No translated {$taxonomy} term found." ); }

$terms_of = static fn( int $post_id ) => WP_LOC_Terms::without_language_filter(
    static fn() => array_map( 'intval', (array) wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] ) )
);
$duplicate_meta_keys = static function ( int $post_id ): array {
    global $wpdb;
    return $wpdb->get_col( $wpdb->prepare(
        "SELECT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d GROUP BY meta_key, meta_value HAVING COUNT(*) > 1",
        $post_id
    ) );
};

$_COOKIE['admin_lang'] = WP_LOC_Languages::get_language_locale( $default );
$source = wp_insert_post( [ 'post_type' => $post_type, 'post_status' => 'draft', 'post_title' => 'TEST copy-terms source' ] );
if ( ! $db->get_element_language( $source, $ptype ) ) {
    $db->set_element_language( $source, $ptype, $default );
}
wp_set_object_terms( $source, [ $source_term ], $taxonomy, false );
if ( $post_type === 'product' ) {
    update_post_meta( $source, '_regular_price', '123' );
    update_post_meta( $source, '_price', '123' );
    update_post_meta( $source, '_sku', 'TEST-COPY-TERMS' );
}
$check( 'source has the term', $terms_of( $source ), [ $source_term ] );

// --- create_translations(): the auto-create path and the AI add-on's "create missing" path ---
WP_LOC::instance()->content->create_translations( $source );
$group = $db->get_element_translations( $db->get_trid( $source, $ptype ), $ptype );
$copy  = (int) ( $group[ $other ]->element_id ?? 0 );
$check( 'copy created', $copy > 0, true );
if ( $copy ) {
    $check( 'copy has the translated term at once', $terms_of( $copy ), [ $translated_term ] );
    $check( 'source terms untouched', $terms_of( $source ), [ $source_term ] );
    $check( 'copy has no duplicate meta rows', $duplicate_meta_keys( $copy ), [] );
}

// --- sync_terms_to_translation(): what the `+` button's AJAX handler calls on its own copy ---
$manual = wp_insert_post( [ 'post_type' => $post_type, 'post_status' => 'draft', 'post_title' => 'TEST copy-terms manual copy' ] );
$db->set_element_language( $manual, $ptype, $other ); // its own group: the real `+` copy is linked after this call
$check( 'manual copy starts without terms', $terms_of( $manual ), [] );
WP_LOC::instance()->content->sync_terms_to_translation( $source, $manual, $other );
$check( 'manual copy has the translated term', $terms_of( $manual ), [ $translated_term ] );

// --- cleanup ---
foreach ( array_filter( [ $manual, $copy, $source ] ) as $id ) {
    wp_delete_post( $id, true );
}
foreach ( $group as $row ) {
    if ( get_post( (int) $row->element_id ) ) {
        wp_delete_post( (int) $row->element_id, true );
    }
}

$failed ? WP_CLI::error( "{$failed} failed" ) : WP_CLI::success( 'translation-copy-terms OK' );
