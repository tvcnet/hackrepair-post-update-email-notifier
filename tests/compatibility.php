<?php
/** Verify public wrappers still reach real services after fallback removal. */
require __DIR__ . '/notifications.php';
function wp_roles(){return (object)array('roles'=>array('editor'=>array(),'author'=>array()));}
function sanitize_key($value){return strtolower(preg_replace('/[^a-zA-Z0-9_-]/','',$value));}
function get_post_types($args=array(),$output='names'){return array('post','page');}
function wp_kses_post($value){return strip_tags($value,'<p><strong><a>');}
verify(pue_sanitize_roles(array('editor','invalid','editor','author'))===array('editor','author'),'role wrapper keeps validation and deduplication');
verify(pue_sanitize_post_types(array('page','invalid','post','page'))===array('page','post'),'post-type wrapper keeps validation and deduplication');
verify(pue_sanitize_subject(array('bad'))==='' && pue_sanitize_subject('<b>Hi</b>')==='Hi','subject wrapper rejects non-scalars and strips markup');
verify(pue_sanitize_message('<p>Hello</p><script>bad</script>')==='<p>Hello</p>bad','message wrapper uses the HTML sanitizer');
verify(pue_sanitize_retention(12)===50 && pue_sanitize_retention(1000)===1000,'retention wrapper preserves accepted limits');
verify(pue_sanitize_bool(0)===0 && pue_sanitize_bool(1)===1 && pue_sanitize_hex('invalid')==='#0073aa','boolean and color wrappers preserve defaults');
verify(pue_sanitize_email(' person@example.test ')==='person@example.test','email wrapper preserves sanitation');
$GLOBALS['options']['pue_enable_logging']=1;$GLOBALS['options']['pue_log_retention']=50;$GLOBALS['options']['pue_logs']=array();
for($i=0;$i<55;$i++){pue_log_event('update',true,array('reader@example.test'),'Item '.$i,$i,array('mode'=>'individual'));}
$logs=$GLOBALS['options']['pue_logs'];verify(count($logs)===50 && $logs[0]['post_id']===5 && $logs[49]['post_id']===54,'log wrapper retains the latest configured number of entries');
$GLOBALS['options']['pue_enable_logging']=0;pue_log_event('test',false,array(),'Skipped');verify($GLOBALS['options']['pue_logs']===$logs,'disabled logging remains unchanged');
verify(pue_csv_safe(' =1+1')==="' =1+1" && pue_csv_safe('Safe')==='Safe','CSV compatibility wrapper retains formula protection');

// Bulk test daily cap
add_filter('pue_bulk_daily_cap',function(){return 30;});unset($GLOBALS['options']['pue_bulk_usage']);
verify(pue_bulk_remaining()===30,'bulk cap starts full each day');pue_record_bulk_usage(25);verify(pue_bulk_remaining()===5,'bulk usage reduces the remaining allowance');
$GLOBALS['options']['pue_bulk_usage']=array('day'=>'2000-01-01','count'=>999);verify(pue_bulk_remaining()===30,'bulk allowance resets on a new day');

// Privacy export and erase of logged addresses
$GLOBALS['options']['pue_enable_logging']=1;$GLOBALS['options']['pue_logs']=array(array('ts'=>1,'type'=>'update','sent'=>1,'recipients'=>array('Keep@example.test','gone@example.test'),'subject'=>'S'));
$export=PUE_Logger::instance()->privacy_export('GONE@example.test');verify(count($export['data'])===1&&$export['done'],'privacy exporter finds log entries by address');
$erase=PUE_Logger::instance()->privacy_erase('gone@example.test');verify($erase['items_removed']&&$GLOBALS['options']['pue_logs'][0]['recipients']===array('Keep@example.test'),'privacy eraser removes only the requested address');

// Site Health sender alignment
function esc_url_raw($u){return $u;}
$GLOBALS['options']['pue_from_email']='news@other-domain.test';verify(PUE_Site_Health::test_from_alignment()['status']==='recommended','mismatched From domain is flagged in Site Health');
$GLOBALS['options']['pue_from_email']='news@news.example.test';verify(PUE_Site_Health::test_from_alignment()['status']==='good','aligned From domain passes Site Health');
$GLOBALS['options']['pue_logs']=array(array('ts'=>time(),'sent'=>0,'mode'=>'skipped'),array('ts'=>time(),'sent'=>0,'mode'=>'skipped'),array('ts'=>time(),'sent'=>0,'mode'=>'skipped'));
verify(PUE_Site_Health::test_failures()['status']==='good','skipped updates never trigger the delivery-failure warning');

// View Details keeps the update checker's reported version
function wp_enqueue_style(...$a){}function plugins_url($p='',$f=''){return 'https://example.test/'.$p;}
$info=(object)array('version'=>'9.9.9','sections'=>array('description'=>'x'));$args=(object)array('slug'=>'hackrepair-post-update-email-notifier');
$out=pue_companion_plugin_info($info,'plugin_information',$args);verify($out->version==='9.9.9'&&isset($out->sections['other_THRG_plugins']),'View Details shows the available version, not the installed one');

// Literal address-domain comparisons do not guess public suffix ownership.
foreach(array(
 array('https://client.co.uk','sender@unrelated.co.uk','recommended'),
 array('https://client.co.uk','sender@client.co.uk','good'),
 array('https://www.Client.CO.UK','sender@client.co.uk','good'),
 array('https://client.co.uk','sender@notclient.co.uk','recommended'),
 array('https://news.client.co.uk','sender@client.co.uk','recommended'),
 array('https://client.com.au','sender@unrelated.com.au','recommended')
) as $case){$GLOBALS['fixture_home_url']=$case[0];$GLOBALS['options']['pue_from_email']=$case[1];verify(PUE_Site_Health::test_from_alignment()['status']===$case[2],'sender comparison '.$case[0].' / '.$case[1]);}
unset($GLOBALS['fixture_home_url']);

$GLOBALS['options']['pue_logs']=array(array('job_id'=>'recovered','sent'=>0,'mode'=>'queued','error'=>'Old scheduler error','error_code'=>'pue_schedule_failed'));
PUE_Logger::instance()->update_job_entry('recovered',array('error'=>'','error_code'=>''));
verify($GLOBALS['options']['pue_logs'][0]['error']===''&&$GLOBALS['options']['pue_logs'][0]['error_code']==='','recovered log entries clear stale scheduling diagnostics');
