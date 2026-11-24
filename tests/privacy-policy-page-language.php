<?php
/**
 * The privacy policy page option resolves to its translation in the current language
 * on the frontend: WP_LOC_Frontend::translate_privacy_policy_page().
 * Run: wp eval-file web/app/plugins/wp-loc/tests/privacy-policy-page-language.php --user=1
 *
 * Needs two active languages and translatable pages. Creates two TEST pages linked as
 * translations, points the option at them, and restores everything at the end.
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
if ( ! WP_LOC_Admin_Settings::is_translatable( 'page' ) ) { WP_CLI::error( 'Pages are not translatable.' ); }

$db    = WP_LOC::instance()->db;
$ptype = WP_LOC_DB::post_element_type( 'page' );

$_REQUEST['wp_loc_lang'] = $default;
$original = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'TEST Privacy Default' ] );
$db->set_element_language( $original, $ptype, $default );
$trid = $db->get_trid( $original, $ptype );

$_REQUEST['wp_loc_lang'] = $other;
$translation = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'TEST Privacy Other' ] );
$db->set_element_language( $translation, $ptype, $other, $trid );
unset( $_REQUEST['wp_loc_lang'] );

$untranslated = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'TEST Privacy Untranslated' ] );
$db->set_element_language( $untranslated, $ptype, $default );

$saved = get_option( 'wp_page_for_privacy_policy' );
remove_all_filters( 'pre_option_wp_page_for_privacy_policy' );

try {
    update_option( 'wp_page_for_privacy_policy', $original );

    WP_LOC_Routing::switch_language( $default );
    $check( 'default language keeps the original', (int) get_option( 'wp_page_for_privacy_policy' ), $original );

    WP_LOC_Routing::switch_language( $other );
    $check( 'other language gets the translation', (int) get_option( 'wp_page_for_privacy_policy' ), $translation );

    update_option( 'wp_page_for_privacy_policy', $untranslated );
    $check( 'no translation falls back to the original', (int) get_option( 'wp_page_for_privacy_policy' ), $untranslated );
} finally {
    WP_LOC_Routing::switch_language( $default );
    update_option( 'wp_page_for_privacy_policy', $saved );
    foreach ( [ $original, $translation, $untranslated ] as $id ) {
        wp_delete_post( $id, true );
    }
}

if ( $failed ) {
    WP_CLI::error( "{$failed} check(s) failed." );
}
WP_CLI::success( 'All checks passed.' );
