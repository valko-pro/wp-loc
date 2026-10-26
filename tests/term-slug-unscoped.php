<?php
/**
 * A term translation may keep the slug its source holds, also when it is written
 * inside without_language_filter() — the way add-ons (wp-loc-auto-translate) write
 * from cron, where no admin language is set.
 * Run: wp eval-file web/app/plugins/wp-loc/tests/term-slug-unscoped.php --user=1
 *
 * Needs two active languages and a translatable taxonomy. Creates TEST terms and
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

$default  = WP_LOC_Languages::get_default_language();
$other    = WP_LOC_Languages::get_additional_languages()[0] ?? null;
$taxonomy = taxonomy_exists( 'product_brand' ) && WP_LOC_Terms::is_translatable( 'product_brand' ) ? 'product_brand' : 'category';
if ( ! $other ) { WP_CLI::error( 'Need a second language.' ); }
if ( ! WP_LOC_Terms::is_translatable( $taxonomy ) ) { WP_CLI::error( "{$taxonomy} is not translatable." ); }

$slug = 'test-slug-unscoped-' . wp_rand( 1000, 9999 );
$unscoped = static fn( callable $fn ) => WP_LOC_Terms::without_language_filter( $fn );
$slug_of = static fn( int $term_id ) => $unscoped( static fn() => get_term( $term_id, $taxonomy )->slug );

$db    = WP_LOC::instance()->db;
$ttype = WP_LOC_DB::tax_element_type( $taxonomy );
$tt_of = static fn( int $term_id ) => (int) WP_LOC_Terms::get_term_taxonomy_id( $term_id, $taxonomy );

$_REQUEST['wp_loc_lang'] = $default;
$source = wp_insert_term( 'TEST Slug Unscoped', $taxonomy, [ 'slug' => $slug ] );
if ( is_wp_error( $source ) ) { WP_CLI::error( $source->get_error_message() ); }
$source_id = (int) $source['term_id'];
if ( ! $db->get_element_language( $tt_of( $source_id ), $ttype ) ) {
    $db->set_element_language( $tt_of( $source_id ), $ttype, $default );
}
$trid = $db->get_trid( $tt_of( $source_id ), $ttype );

// The translation starts as a copy with a suffixed slug, like the `+` button makes it
// (unless wp-loc auto-created one on insert).
$group   = $db->get_element_translations( $trid, $ttype );
$copy_id = isset( $group[ $other ] ) ? (int) WP_LOC_Terms::get_term_id_from_taxonomy_id( (int) $group[ $other ]->element_id, $taxonomy ) : 0;
if ( ! $copy_id ) {
    $_REQUEST['wp_loc_lang'] = $other;
    $copy = $unscoped( static fn() => wp_insert_term( 'TEST Slug Unscoped', $taxonomy, [ 'slug' => $slug . '-copy' ] ) );
    if ( is_wp_error( $copy ) ) { WP_CLI::error( $copy->get_error_message() ); }
    $copy_id = (int) $copy['term_id'];
    $db->set_element_language( $tt_of( $copy_id ), $ttype, $other, $trid, $default );
}
unset( $_REQUEST['wp_loc_lang'] );
$check( 'copy starts with another slug', $slug_of( $copy_id ) !== $slug, true );

// 1. Explicit slug equal to the source's, written unscoped (translate_slug on).
$result = $unscoped( static fn() => wp_update_term( $copy_id, $taxonomy, [ 'name' => 'TEST Slug Unscoped RU', 'slug' => $slug ] ) );
$check( 'explicit same slug: no error', is_wp_error( $result ) ? $result->get_error_code() : 'ok', 'ok' );
$check( 'explicit same slug: slug kept, no suffix', $slug_of( $copy_id ), $slug );
$check( 'explicit same slug: source untouched', $slug_of( $source_id ), $slug );

// 2. Name only — core re-submits the term's current slug (translate_slug off).
$result = $unscoped( static fn() => wp_update_term( $copy_id, $taxonomy, [ 'name' => 'TEST Slug Unscoped RU 2' ] ) );
$check( 'name only: no error', is_wp_error( $result ) ? $result->get_error_code() : 'ok', 'ok' );
$check( 'name only: slug kept', $slug_of( $copy_id ), $slug );

// 3. Still a duplicate within ONE language.
$_REQUEST['wp_loc_lang'] = $default;
$sibling = wp_insert_term( 'TEST Slug Unscoped Sibling', $taxonomy, [ 'slug' => $slug . '-sibling' ] );
unset( $_REQUEST['wp_loc_lang'] );
$sibling_id = is_wp_error( $sibling ) ? 0 : (int) $sibling['term_id'];
if ( $sibling_id ) {
    if ( ! $db->get_element_language( $tt_of( $sibling_id ), $ttype ) ) {
        $db->set_element_language( $tt_of( $sibling_id ), $ttype, $default );
    }
    $result = $unscoped( static fn() => wp_update_term( $sibling_id, $taxonomy, [ 'slug' => $slug ] ) );
    $check( 'same language duplicate still refused', is_wp_error( $result ) ? $result->get_error_code() : 'ok', 'duplicate_term_slug' );
}

// --- cleanup ---
$to_delete = [];
foreach ( array_filter( [ $sibling_id, $source_id ] ) as $id ) {
    $group = $db->get_element_translations( $db->get_trid( $tt_of( $id ), $ttype ), $ttype );
    foreach ( $group as $row ) {
        $to_delete[] = (int) WP_LOC_Terms::get_term_id_from_taxonomy_id( (int) $row->element_id, $taxonomy );
    }
    $to_delete[] = $id;
}
foreach ( array_unique( array_filter( array_merge( $to_delete, [ $copy_id ] ) ) ) as $id ) {
    $unscoped( static fn() => wp_delete_term( $id, $taxonomy ) );
}

$failed ? WP_CLI::error( "{$failed} failed" ) : WP_CLI::success( 'term-slug-unscoped OK' );
