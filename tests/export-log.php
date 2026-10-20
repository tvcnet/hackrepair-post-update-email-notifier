<?php
/** Exercise the exiting export response and authorization in child processes. */
if ( isset( $argv[1] ) && '--case' === $argv[1] ) {
    define( 'ABSPATH', __DIR__ . '/' );
    $case = $argv[2];
    function add_action( $hook, $callback ) { $GLOBALS['actions'][$hook] = $callback; }
    function current_user_can( $cap ) { return 'manage_options' === $cap && 'forbidden' !== $GLOBALS['case']; }
    function esc_html__( $text, $domain ) { return $text; }
    function wp_die( $text ) { fwrite( STDERR, 'forbidden' ); exit( 3 ); }
    function check_admin_referer( $action, $field ) {
        if ( 'pue_export_log_action' !== $action || 'pue_export_log_nonce' !== $field || 'valid' !== ($_POST[$field] ?? '') ) { fwrite( STDERR, 'bad_nonce' ); exit( 4 ); }
    }
    function nocache_headers() {}
    function date_i18n( $format, $time ) { return gmdate( $format, $time ); }
    function get_option( $key, $default = false ) {
        if ( 'populated' !== $GLOBALS['case'] ) { return array(); }
        return array(
            array('ts'=>1700000000,'type'=>'update','sent'=>1,'post_id'=>9,'post_title'=>'=HYPERLINK("bad")','post_type'=>'post','editor_id'=>1,'recipients'=>array('+alias@example.test'),'subject'=>'@formula'),
            array('ts'=>1700000001,'type'=>'update','sent'=>0,'mode'=>'skipped'),
            array('ts'=>1700000002,'type'=>'test','sent'=>0),
        );
    }
    require dirname(__DIR__) . '/includes/class-pue-logger.php';
    require dirname(__DIR__) . '/includes/admin-handlers.php';
    if ( ($GLOBALS['actions']['load-settings_page_pue-settings'] ?? '') !== 'pue_handle_export_log' ) { exit( 5 ); }
    $_POST = 'absent' === $case ? array() : array('pue_export_log'=>1,'pue_export_log_nonce'=>'invalid' === $case ? 'invalid' : 'valid');
    pue_handle_export_log();
    echo "NO_EXPORT\n";
    exit;
}
function run_export_case( $case ) {
    $process = proc_open( array(PHP_BINARY, __FILE__, '--case', $case), array(1=>array('pipe','w'),2=>array('pipe','w')), $pipes );
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $code=proc_close($process);
    return array($code,$out,$err);
}
function export_assert( $ok, $label ) { if(!$ok){throw new Exception($label);} echo 'PASS '.$label."\n"; }
list($code,$out,$err)=run_export_case('absent'); export_assert($code===0 && $out==="NO_EXPORT\n",'unrelated settings request does not export');
foreach(array('forbidden'=>3,'invalid'=>4) as $case=>$expected){
    list($code,$out,$err)=run_export_case($case); export_assert($code===$expected && $out==='', $case.' export rejected before CSV output');
}
list($code,$out,$err)=run_export_case('empty');
export_assert($code===0 && $err==='' && trim($out)==='time,type,status,post_id,post_title,post_type,editor_id,recipients,subject','empty export returns only the CSV header without a fatal error');
list($code,$out,$err)=run_export_case('populated');
$rows=array_map(static function($line){return str_getcsv($line, ',', '"', '');},explode("\n",trim($out)));
export_assert($code===0 && $err==='' && count($rows)===4 && $rows[1][2]==='sent' && $rows[2][2]==='skipped' && $rows[3][2]==='failed','populated export preserves rows and statuses without page HTML');
export_assert($rows[1][4][0]==="'" && $rows[1][7][0]==="'" && $rows[1][8][0]==="'",'spreadsheet-sensitive title recipient and subject are escaped');
