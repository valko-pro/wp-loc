<?php
/**
 * hreflang of a paginated term archive points only at pages the translation has:
 * WP_LOC_Frontend::translated_archive_page_exists() probes the target language.
 * Run: wp eval-file web/app/plugins/wp-loc/tests/hreflang-paged-archive.php --user=1
 *
 * Needs two active languages and a translatable taxonomy with a translatable post type.
 * Creates TEST terms and posts (3 in the default language, 2 in the other) and deletes
 * them at the end.
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

$taxonomy  = '';
$post_type = '';
foreach ( [ 'category' => 'post', 'product_cat' => 'product' ] as $candidate_tax => $candidate_type ) {
    if ( taxonomy_exists( $candidate_tax ) && WP_LOC_Terms::is_translatable( $candidate_tax ) && WP_LOC_Admin_Settings::is_translatable( $candidate_type ) ) {
        $taxonomy  = $candidate_tax;
        $post_type = $candidate_type;
        break;
    }
}
if ( ! $taxonomy ) { WP_CLI::error( 'No translatable taxonomy with a translatable post type.' ); }

$db     = WP_LOC::instance()->db;
$ttype  = WP_LOC_DB::tax_element_type( $taxonomy );
$ptype  = WP_LOC_DB::post_element_type( $post_type );
$tt_of  = static fn( int $term_id ) => (int) WP_LOC_Terms::get_term_taxonomy_id( $term_id, $taxonomy );
$suffix = wp_rand( 1000, 9999 );

// Term in the default language, plus its translation linked in the same group.
$_REQUEST['wp_loc_lang'] = $default;
$term_default = wp_insert_term( "TEST Hreflang Paged {$suffix}", $taxonomy, [ 'slug' => "test-hreflang-paged-{$suffix}" ] );
if ( is_wp_error( $term_default ) ) { WP_CLI::error( $term_default->get_error_message() ); }
$term_default_id = (int) $term_default['term_id'];
if ( ! $db->get_element_language( $tt_of( $term_default_id ), $ttype ) ) {
    $db->set_element_language( $tt_of( $term_default_id ), $ttype, $default );
}
$trid = $db->get_trid( $tt_of( $term_default_id ), $ttype );

$group         = $db->get_element_translations( $trid, $ttype );
$term_other_id = isset( $group[ $other ] ) ? (int) WP_LOC_Terms::get_term_id_from_taxonomy_id( (int) $group[ $other ]->element_id, $taxonomy ) : 0;
if ( ! $term_other_id ) {
    $_REQUEST['wp_loc_lang'] = $other;
    $term_other = WP_LOC_Terms::without_language_filter(
        static fn() => wp_insert_term( "TEST Hreflang Paged {$suffix} Other", $taxonomy, [ 'slug' => "test-hreflang-paged-{$suffix}-other" ] )
    );
    if ( is_wp_error( $term_other ) ) { WP_CLI::error( $term_other->get_error_message() ); }
    $term_other_id = (int) $term_other['term_id'];
    $db->set_element_language( $tt_of( $term_other_id ), $ttype, $other, $trid, $default );
}
unset( $_REQUEST['wp_loc_lang'] );

// Published posts: three in the default language, one in the other.
$post_ids = [];
$make_post = static function ( string $lang, int $term_id, int $n ) use ( &$post_ids, $db, $ptype, $post_type, $taxonomy, $suffix ): void {
    $_REQUEST['wp_loc_lang'] = $lang;
    $post_id = wp_insert_post( [
        'post_type'   => $post_type,
        'post_status' => 'publish',
        'post_title'  => "TEST Hreflang Paged {$suffix} {$lang} {$n}",
    ] );
    unset( $_REQUEST['wp_loc_lang'] );
    if ( ! $post_id || is_wp_error( $post_id ) ) { WP_CLI::error( "Cannot create {$lang} post {$n}." ); }
    $post_id = (int) $post_id;
    $post_ids[] = $post_id;
    // Unconditional: the post was registered in the admin language on insert.
    $db->set_element_language( $post_id, $ptype, $lang );
    WP_LOC_Terms::without_language_filter(
        static fn() => wp_set_object_terms( $post_id, [ $term_id ], $taxonomy )
    );
};
for ( $n = 1; $n <= 3; $n++ ) { $make_post( $default, $term_default_id, $n ); }
for ( $n = 1; $n <= 2; $n++ ) { $make_post( $other, $term_other_id, $n ); }

$exists = static fn( int $term_id, string $lang, int $paged ) => WP_LOC_Frontend::translated_archive_page_exists( $term_id, $taxonomy, $lang, $paged, 1, [ $post_type ] );

$check( 'default language, page 3 exists', $exists( $term_default_id, $default, 3 ), true );
$check( 'default language, page 4 is past the end', $exists( $term_default_id, $default, 4 ), false );
$check( 'other language, page 1 exists', $exists( $term_other_id, $other, 1 ), true );
// The request language is the default one, so these probe a language that is NOT current.
$check( 'other language, page 2 exists', $exists( $term_other_id, $other, 2 ), true );
$check( 'other language, page 3 does not exist', $exists( $term_other_id, $other, 3 ), false );
$check( 'other language, page 0 counts as page 1', $exists( $term_other_id, $other, 0 ), true );
$check( 'post type any falls back to the taxonomy objects', WP_LOC_Frontend::translated_archive_page_exists( $term_default_id, $taxonomy, $default, 3, 1, [ 'any' ] ), true );
$check( 'post type defaults to the taxonomy objects', WP_LOC_Frontend::translated_archive_page_exists( $term_default_id, $taxonomy, $default, 3, 1 ), true );

// --- cleanup ---
$post_groups = [];
foreach ( $post_ids as $post_id ) {
    $trid_of_post = $db->get_trid( $post_id, $ptype );
    foreach ( $trid_of_post ? $db->get_element_translations( $trid_of_post, $ptype ) : [] as $row ) {
        $post_groups[] = (int) $row->element_id;
    }
}
foreach ( array_unique( array_merge( $post_ids, $post_groups ) ) as $post_id ) {
    wp_delete_post( $post_id, true );
}
foreach ( array_unique( array_filter( [ $term_default_id, $term_other_id ] ) ) as $term_id ) {
    WP_LOC_Terms::without_language_filter( static fn() => wp_delete_term( $term_id, $taxonomy ) );
}

$failed ? WP_CLI::error( "{$failed} failed" ) : WP_CLI::success( 'hreflang-paged-archive OK' );
