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
