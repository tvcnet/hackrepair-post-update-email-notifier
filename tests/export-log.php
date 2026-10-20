<?php
/** Exercise the exiting export response, authorization and non-JavaScript admin actions in child processes. */
if ( isset( $argv[1] ) && '--case' === $argv[1] ) {
    define( 'ABSPATH', __DIR__ . '/' ); define( 'MINUTE_IN_SECONDS', 60 );
    $GLOBALS['case'] = $case = $argv[2];
    $GLOBALS['options'] = array( 'pue_header_text' => 'Old', 'pue_logs' => 'populated' === $case ? array(
        array('ts'=>1700000000,'type'=>'update','sent'=>1,'post_id'=>9,'post_title'=>'=HYPERLINK("bad")','post_type'=>'post','editor_id'=>1,'recipients'=>array('+alias@example.test'),'subject'=>'@formula'),
        array('ts'=>1700000001,'type'=>'update','sent'=>0,'mode'=>'skipped','reason'=>'cooldown'),
        array('ts'=>1700000002,'type'=>'test','sent'=>0,'error'=>'-bad'),
    ) : array() );
    function add_action( $hook, $callback ) { $GLOBALS['actions'][$hook] = $callback; }
    function add_filter( ...$a ) {} function apply_filters( $n, $v ) { return $v; }
    function current_action() { return $GLOBALS['current_action']; }
    function current_user_can( $cap ) { return 'manage_options' === $cap && 'forbidden' !== $GLOBALS['case']; }
    function pue_capability() { return 'manage_options'; }
    function pue_settings_url( $s = '' ) { return 'settings#' . $s; }
    function __( $t, $d = null ) { return $t; } function esc_html__( $t, $d = null ) { return $t; } function esc_html( $t ) { return htmlspecialchars( (string) $t ); } function esc_attr( $t ) { return esc_html( $t ); }
    function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); } function wp_unslash( $s ) { return $s; }
    function wp_verify_nonce( $n, $a ) { return 'valid' === $n; }
    function wp_doing_ajax() { return 'ajax' === $GLOBALS['case']; }
    function wp_send_json_success( $d ) { echo json_encode( array( 'success' => true, 'data' => $d ) ); exit( 0 ); }
    function wp_send_json_error( $d, $s = null ) { echo json_encode( array( 'success' => false, 'data' => $d ) ); exit( 0 ); }
    function get_current_user_id() { return 1; }
    function set_transient( $k, $v, $t ) { $GLOBALS['transient'] = $v; }
    function wp_safe_redirect( $url ) { fwrite( STDERR, 'REDIRECT ' . $url . ' ' . json_encode( $GLOBALS['transient'] ) . ' ' . json_encode( $GLOBALS['options']['pue_header_text'] ?? null ) ); return true; }
    function nocache_headers() {}
    function date_i18n( $format, $time ) { return gmdate( $format, $time ); }
    function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
    function update_option( $key, $value, ...$a ) { $GLOBALS['options'][ $key ] = $value; }
    function sanitize_hex_color( $c ) { return preg_match( '/^#[0-9a-f]{6}$/i', $c ) ? $c : null; }
    foreach ( array( 'class-pue-defaults', 'class-pue-logger', 'class-pue-settings-manager', 'class-pue-admin-actions', 'sanitizers', 'admin-handlers' ) as $f ) { require dirname( __DIR__ ) . '/includes/' . $f . '.php'; }
    $GLOBALS['current_action'] = 'branding' === $case || 'ajax' === $case ? 'admin_post_pue_save_branding' : 'admin_post_pue_export_log';
    if ( ( $GLOBALS['actions'][ $GLOBALS['current_action'] ] ?? '' ) !== 'pue_route_admin_action' ) { exit( 5 ); }
    $_POST = array( 'pue_export_log_nonce' => 'invalid' === $case ? 'invalid' : 'valid', 'pue_save_branding_nonce' => 'valid', 'pue_header_text' => 'New <b>header</b>', 'pue_header_bg_color' => '#123456', 'pue_footer_text' => 'Foot' );
    pue_route_admin_action();
    echo "NO_EXIT\n";
    exit;
}
function run_case( $case ) {
    $process = proc_open( array( PHP_BINARY, __FILE__, '--case', $case ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
    $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] );
    fclose( $pipes[1] ); fclose( $pipes[2] ); $code = proc_close( $process );
    return array( $code, $out, $err );
}
function export_assert( $ok, $label ) { if ( ! $ok ) { throw new Exception( $label ); } echo 'PASS ' . $label . "\n"; }
list( $code, $out, $err ) = run_case( 'forbidden' ); export_assert( strpos( $err, 'REDIRECT settings#logging' ) === 0 && strpos( $err, 'permission' ) !== false && strpos( $out, 'time,' ) === false, 'forbidden export redirects with a notice and no CSV' );
list( $code, $out, $err ) = run_case( 'invalid' ); export_assert( strpos( $err, 'expired' ) !== false && strpos( $out, 'time,' ) === false, 'invalid nonce export rejected before CSV output' );
list( $code, $out, $err ) = run_case( 'empty' );
export_assert( 0 === $code && '' === $err && trim( $out ) === 'time,type,status,post_id,post_title,post_type,editor_id,recipients,subject,reason,error', 'empty export returns only the CSV header without a fatal error' );
list( $code, $out, $err ) = run_case( 'populated' );
$rows = array_map( static function ( $line ) { return str_getcsv( $line, ',', '"', '' ); }, explode( "\n", trim( $out ) ) );
export_assert( 0 === $code && '' === $err && count( $rows ) === 4 && $rows[1][2] === 'sent' && $rows[2][2] === 'skipped' && $rows[2][9] === 'cooldown' && $rows[3][2] === 'failed', 'populated export preserves rows, statuses and skip reasons without page HTML' );
export_assert( $rows[1][4][0] === "'" && $rows[1][7][0] === "'" && $rows[1][8][0] === "'" && $rows[3][10][0] === "'", 'spreadsheet-sensitive title, recipient, subject and error are escaped' );
list( $code, $out, $err ) = run_case( 'branding' );
export_assert( strpos( $err, 'REDIRECT settings#branding' ) === 0 && strpos( $err, '"success"' ) !== false && strpos( $err, '"New header"' ) !== false, 'branding saves without JavaScript and redirects back with a notice' );
list( $code, $out, $err ) = run_case( 'ajax' );
export_assert( strpos( $out, '"success":true' ) !== false && '' === $err, 'the same branding action answers JSON over AJAX' );
