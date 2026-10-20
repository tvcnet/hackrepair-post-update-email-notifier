<?php
/** Isolated notification regressions; no WordPress database or real mail. */
define('ABSPATH',__DIR__.'/');define('HOUR_IN_SECONDS',3600);define('DAY_IN_SECONDS',86400);
$GLOBALS['options']=array('pue_roles'=>array('editor','author'),'pue_subject'=>'Updated: [post_title]','pue_message'=>'<p>[post_title]</p>','date_format'=>'Y-m-d','time_format'=>'H:i');$GLOBALS['filters']=array();$GLOBALS['mail']=array();$GLOBALS['meta']=array();
function add_filter($name,$cb,$priority=10,$args=1){$GLOBALS['filters'][$name][$priority][]=array($cb,$args);}
function add_action($name,$cb,$priority=10,$args=1){}
function apply_filters($name,$value,...$args){$groups=$GLOBALS['filters'][$name]??array();ksort($groups);foreach($groups as $list)foreach($list as $f)$value=call_user_func_array($f[0],array_slice(array_merge(array($value),$args),0,$f[1]));return $value;}
function do_action($name,...$args){}
function plugin_dir_path($file){return dirname($file).'/';}function plugin_dir_url($file){return 'https://example.test/plugin/';}function plugin_basename($file){return basename($file);}
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}function update_option($key,$value,...$args){$GLOBALS['options'][$key]=$value;}
function current_time($type){return time();}function wp_date($format,$ts=null){return gmdate($format,$ts??time());}function date_i18n($format,$ts=null,...$args){return wp_date($format,$ts);}
function home_url($path=''){return 'https://News.Example.test:8443/subdir'.$path;}function site_url($path=''){return home_url($path);}function admin_url($p=''){return home_url('/wp-admin/'.$p);}function wp_parse_url($url,$component=-1){return parse_url($url,$component);}
function __($text,...$args){return $text;}function esc_html__($text,...$args){return $text;}function esc_html($text){return htmlspecialchars((string)$text,ENT_QUOTES,'UTF-8');}function esc_attr($text){return esc_html($text);}function esc_url($text){return esc_html($text);}function wp_strip_all_tags($s){return strip_tags($s);}function sanitize_text_field($s){return trim(strip_tags(str_replace(array("\r","\n"),' ',$s)));}function sanitize_email($s){return filter_var($s,FILTER_SANITIZE_EMAIL);}function is_email($s){return filter_var($s,FILTER_VALIDATE_EMAIL);}function wp_specialchars_decode($s,...$args){return html_entity_decode($s);}
function sanitize_hex_color($s){return preg_match('/^#[a-fA-F0-9]{6}$/',$s)?$s:null;}
function get_bloginfo($what){return 'Fixture Site';}function get_permalink($id){return home_url('/post/'.$id);}function get_edit_post_link($id){return admin_url('post.php?post='.$id);}function get_the_author_meta(...$args){return 'Author';}function get_post_type_object($type){return (object)array('labels'=>(object)array('singular_name'=>'Post'));}
function wp_get_current_user(){return(object)array('ID'=>1,'display_name'=>'Editor','user_email'=>'editor@example.test');}
function get_users($args){return array((object)array('user_email'=>'editor@example.test'),(object)array('user_email'=>'reader@example.test'));}
function wp_is_post_autosave($id){return !empty($GLOBALS['autosave']);}function wp_is_post_revision($id){return !empty($GLOBALS['revision']);}
function metadata_exists($type,$id,$key){return isset($GLOBALS['meta'][$id]);}function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id]??'';}function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id]=$value;}
function wp_mail($to,$subject,$body,$headers){$GLOBALS['mail'][]=compact('to','subject','body','headers');return true;}
require dirname(__DIR__).'/post-update-email-notifier.php';
function verify($ok,$label){if(!$ok)throw new Exception($label);echo 'PASS '.$label."\n";}
$post=(object)array('post_status'=>'publish','post_type'=>'post','post_title'=>'A <b>title</b>','post_author'=>2);
$service=PUE_Notification_Service::instance();
$service->handle_post_update(1,$post,null);verify(count($GLOBALS['mail'])===0,'default skip sends nothing');
$GLOBALS['meta'][1]=0;$GLOBALS['options']['pue_exclude_updater']=1;$service->handle_post_update(1,$post,null);
verify(count($GLOBALS['mail'])===1&&$GLOBALS['mail'][0]['to']==='reader@example.test','roles deduplicated and updating user excluded');
verify(strpos($GLOBALS['mail'][0]['subject'],'[news.example.test:8443] [Post Update Email Notifier] ')===0,'domain port and plugin tags precede purpose');
verify(strpos($GLOBALS['mail'][0]['body'],'max-width:600px')===false,'HTML wrapper fills available width');
verify($GLOBALS['meta'][1]===1,'notification returns post to skip mode');$service->handle_post_update(1,$post,null);verify(count($GLOBALS['mail'])===1,'next update does not repeat notification');
foreach(array('autosave','revision')as $flag){$GLOBALS['meta'][1]=0;$GLOBALS[$flag]=true;$service->handle_post_update(1,$post,null);$GLOBALS[$flag]=false;verify(count($GLOBALS['mail'])===1,$flag.' skipped');}
$post->post_status='draft';$service->handle_post_update(1,$post,null);verify(count($GLOBALS['mail'])===1,'unpublished content skipped');$post->post_status='publish';
$GLOBALS['options']['pue_roles']=array();$GLOBALS['meta'][1]=0;$service->handle_post_update(1,$post,null);verify(count($GLOBALS['mail'])===1,'no role selection sends no mail');
$service->send_test_email('preview@example.test','Preview','<p>Sample</p>',false);$last=end($GLOBALS['mail']);verify(strpos($last['subject'],'[news.example.test:8443] [Post Update Email Notifier] Test: ')===0,'test subject follows shared tags');
$before=count($GLOBALS['mail']);$service->send_test_email('invalid','Preview','Sample',false);verify(count($GLOBALS['mail'])===$before,'invalid test recipient rejected');

$GLOBALS['options']['pue_roles']=array('editor');unset($GLOBALS['options']['pue_subject'],$GLOBALS['options']['pue_message']);$GLOBALS['meta'][2]=0;$service->handle_post_update(2,$post,null);$last=end($GLOBALS['mail']);verify(strpos($last['subject'],'Post Updated:')!==false&&strpos($last['body'],'has been updated')!==false,'unset stored templates use meaningful notification defaults');
