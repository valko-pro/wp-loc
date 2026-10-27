<?php
/**
 * A term added from the post editor's inline "+ Add new" box (admin-ajax action
 * `add-{taxonomy}`, no `taxonomy` field) gets its sibling translations like one added
 * on the taxonomy screen (`add-tag`): same slug, linked by trid, creation action fired.
 * Run: wp eval-file web/app/plugins/wp-loc/tests/term-inline-add.php --user=1
 *
 * Needs two active languages, a translatable hierarchical taxonomy and the
 * "auto-create term translations" setting on. Creates TEST terms and deletes them.
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
$taxonomy = taxonomy_exists( 'product_cat' ) && WP_LOC_Terms::is_translatable( 'product_cat' ) ? 'product_cat' : 'category';
if ( ! $other ) { WP_CLI::error( 'Need a second language.' ); }
if ( ! WP_LOC_Admin_Settings::should_auto_create_term_translations() ) { WP_CLI::error( 'Auto-create term translations is off.' ); }

$db    = WP_LOC::instance()->db;
$ttype = WP_LOC_DB::tax_element_type( $taxonomy );
$unscoped = static fn( callable $fn ) => WP_LOC_Terms::without_language_filter( $fn );

// The add-on (wp-loc-auto-translate) would queue a real translation job: just record the action.
remove_all_actions( 'wp_loc_term_translation_created' );
$fired = [];
add_action( 'wp_loc_term_translation_created', static function ( $new_id, $source_id, $tax, $lang ) use ( &$fired ) {
    $fired[] = [ $new_id, $source_id, $tax, $lang ];
}, 10, 4 );

// The request _wp_ajax_add_hierarchical_term() runs in.
$_SERVER['REQUEST_METHOD'] = 'POST';
$_REQUEST['action'] = $_POST['action'] = "add-{$taxonomy}";
unset( $_REQUEST['taxonomy'], $_REQUEST['lang'], $_REQUEST['wp_loc_lang'] );
$_COOKIE['admin_lang'] = WP_LOC_Languages::get_language_locale( $default );

$name = 'TEST Inline Add ' . wp_rand( 1000, 9999 );
$inserted = wp_insert_term( $name, $taxonomy, [ 'parent' => 0 ] );
if ( is_wp_error( $inserted ) ) { WP_CLI::error( $inserted->get_error_message() ); }
$source_id = (int) $inserted['term_id'];
$source_tt = (int) $inserted['term_taxonomy_id'];

$check( 'source registered in the default language', $db->get_element_language( $source_tt, $ttype ), $default );

$trid  = $db->get_trid( $source_tt, $ttype );
$group = $db->get_element_translations( $trid, $ttype );
$copy_tt = (int) ( $group[ $other ]->element_id ?? 0 );
$check( 'sibling created and linked', $copy_tt > 0, true );

if ( $copy_tt ) {
    $copy = $unscoped( static fn() => get_term_by( 'term_taxonomy_id', $copy_tt, $taxonomy ) );
    $source = $unscoped( static fn() => get_term( $source_id, $taxonomy ) );
    $check( 'sibling keeps the source slug', $copy->slug, $source->slug );
    $check( 'sibling keeps the name', $copy->name, $name );
    $check( 'creation action fired once for the sibling', $fired, [ [ (int) $copy->term_id, $source_id, $taxonomy, $other ] ] );
}

// --- cleanup ---
unset( $_REQUEST['action'], $_POST['action'] );
foreach ( $group as $row ) {
    $term = $unscoped( static fn() => get_term_by( 'term_taxonomy_id', (int) $row->element_id, $taxonomy ) );
    if ( $term ) {
        $unscoped( static fn() => wp_delete_term( $term->term_id, $taxonomy ) );
    }
}
if ( get_term( $source_id, $taxonomy ) ) {
    $unscoped( static fn() => wp_delete_term( $source_id, $taxonomy ) );
}

$failed ? WP_CLI::error( "{$failed} failed" ) : WP_CLI::success( 'term-inline-add OK' );
