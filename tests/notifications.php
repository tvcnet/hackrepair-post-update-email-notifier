<?php
/** Isolated notification regressions; no WordPress database or real mail. */
define('ABSPATH',__DIR__.'/');define('HOUR_IN_SECONDS',3600);define('DAY_IN_SECONDS',86400);define('MINUTE_IN_SECONDS',60);
#[\AllowDynamicProperties]
class WP_Post{public function __construct($f=array()){foreach($f as $k=>$v)$this->$k=$v;}}
class WP_Error{private $m;private $c;private $d;public function __construct($c='',$m='',$d=array()){$this->c=$c;$this->m=$m;$this->d=$d;}public function get_error_message(){return $this->m;}public function get_error_code(){return $this->c;}public function get_error_data(){return $this->d;}}
$GLOBALS['options']=array('pue_roles'=>array('editor','author'),'pue_subject'=>'Updated: [post_title]','pue_message'=>'<p>[post_title]</p>','date_format'=>'Y-m-d','time_format'=>'H:i');$GLOBALS['filters']=array();$GLOBALS['mail']=array();$GLOBALS['meta']=array();$GLOBALS['cron']=array();$GLOBALS['mail_ok']=true;
function add_filter($name,$cb,$priority=10,$args=1){$GLOBALS['filters'][$name][$priority][]=array($cb,$args);}
function add_action($name,$cb,$priority=10,$args=1){}
function apply_filters($name,$value,...$args){$groups=$GLOBALS['filters'][$name]??array();ksort($groups);foreach($groups as $list)foreach($list as $f)$value=call_user_func_array($f[0],array_slice(array_merge(array($value),$args),0,$f[1]));return $value;}
function do_action($name,...$args){$GLOBALS['actions_fired'][$name][]=$args;}
function plugin_dir_path($file){return dirname($file).'/';}function plugin_dir_url($file){return 'https://example.test/plugin/';}function plugin_basename($file){return basename($file);}
function get_option($key,$default=false){return array_key_exists($key,$GLOBALS['options'])?$GLOBALS['options'][$key]:$default;}function update_option($key,$value,...$args){$GLOBALS['options'][$key]=$value;return true;}
function add_option($key,$value='',$dep='',$autoload='yes'){if(array_key_exists($key,$GLOBALS['options']))return false;$GLOBALS['options'][$key]=$value;return true;}function delete_option($key){unset($GLOBALS['options'][$key]);return true;}
function get_transient($k){return $GLOBALS['transients'][$k]??false;}function set_transient($k,$v,$t=0){$GLOBALS['transients'][$k]=$v;return true;}function delete_transient($k){unset($GLOBALS['transients'][$k]);}
function current_time($type){return time();}function wp_date($format,$ts=null){return gmdate($format,$ts??time());}function date_i18n($format,$ts=null,...$args){return wp_date($format,$ts);}
function home_url($path=''){return ($GLOBALS['fixture_home_url']??'https://News.Example.test:8443/subdir').$path;}function site_url($path=''){return home_url($path);}function admin_url($p=''){return home_url('/wp-admin/'.$p);}function wp_parse_url($url,$component=-1){return parse_url($url,$component);}
function __($text,...$args){return $text;}function _n($s,$p,$n,...$a){return 1===(int)$n?$s:$p;}function esc_html__($text,...$args){return $text;}function esc_html($text){return htmlspecialchars((string)$text,ENT_QUOTES,'UTF-8');}function esc_attr($text){return esc_html($text);}function esc_url($text){return esc_html($text);}function wp_strip_all_tags($s){return strip_tags($s);}function sanitize_text_field($s){return trim(strip_tags(str_replace(array("\r","\n"),' ',$s)));}function sanitize_email($s){return filter_var($s,FILTER_SANITIZE_EMAIL);}function is_email($s){return filter_var($s,FILTER_VALIDATE_EMAIL);}function wp_specialchars_decode($s,...$args){return html_entity_decode($s);}
function sanitize_hex_color($s){return preg_match('/^#[a-fA-F0-9]{6}$/',$s)?$s:null;}function wpautop($s){return '<p>'.$s.'</p>';}
function get_bloginfo($what){return 'Fixture Site';}function get_permalink($id){return home_url('/post/'.$id);}function get_edit_post_link($id,$c='display'){return admin_url('post.php?post='.$id);}function get_the_author_meta(...$args){return 'Author';}function get_post_type_object($type){return (object)array('public'=>true,'labels'=>(object)array('singular_name'=>'Post'));}
function get_the_title($id){return 'Title '.$id;}function get_post_type($id){return 'post';}function get_post($id){return $GLOBALS['posts'][$id]??null;}function get_userdata($id){return (object)array('ID'=>$id);}
function wp_get_post_revisions($id,$args=array()){return array(77=>(object)array('ID'=>77));}
function wp_get_current_user(){return(object)array('ID'=>1,'display_name'=>'Editor','user_email'=>'editor@example.test');}
function get_users($args){$GLOBALS['last_get_users']=$args;$all=array('editor'=>array('editor@example.test','Reader@example.test'),'author'=>array('reader@example.test'));$out=array();foreach((array)($args['role__in']??array()) as $r)foreach($all[$r]??array() as $e)$out[]=(object)array('user_email'=>$e);return $out;}
function wp_is_post_autosave($id){return !empty($GLOBALS['autosave']);}function wp_is_post_revision($id){return !empty($GLOBALS['revision']);}
function metadata_exists($type,$id,$key){return isset($GLOBALS['meta'][$id][$key]);}function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??'';}function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;}function delete_post_meta($id,$key){unset($GLOBALS['meta'][$id][$key]);}
function is_wp_error($e){return $e instanceof WP_Error;}function wp_generate_password($l=12,$s=true,$x=false){return 'job'.(++$GLOBALS['jobseq']);}$GLOBALS['jobseq']=0;
require __DIR__.'/queue-stubs.php';
function wp_mail($to,$subject,$body,$headers){if(isset($GLOBALS['mail_callback']))call_user_func($GLOBALS['mail_callback'],$to);$m=new stdClass();pue_phpmailer_set_altbody($m);$alt=$m->AltBody??null;$GLOBALS['mail'][]=compact('to','subject','body','headers','alt');if(!$GLOBALS['mail_ok']){pue_wp_mail_failed(new WP_Error('wp_mail_failed','SMTP connect() failed.',array('to'=>array($to),'subject'=>$subject,'headers'=>array('X-PUE'=>'1','X-PUE-Type'=>'update'))));}return $GLOBALS['mail_ok'];}
require dirname(__DIR__).'/post-update-email-notifier.php';
function verify($ok,$label){if(!$ok)throw new Exception($label);echo 'PASS '.$label."\n";}
function mails(){return count($GLOBALS['mail']);}
function last_log(){$l=$GLOBALS['options']['pue_logs']??array();return end($l);}
function __return_true_(){return true;}
$post=new WP_Post(array('ID'=>1,'post_status'=>'publish','post_type'=>'post','post_title'=>'A <b>title</b>','post_author'=>2,'post_content'=>'one two three','post_excerpt'=>''));
$GLOBALS['posts'][1]=$post;
$service=PUE_Notification_Service::instance();

$service->handle_post_update(1,$post,null);verify(mails()===0,'default skip sends nothing');
$GLOBALS['meta'][1]['_pue_do_not_notify']=0;$GLOBALS['options']['pue_exclude_updater']=1;$service->handle_post_update(1,$post,null);
verify(mails()===1&&strtolower($GLOBALS['mail'][0]['to'])==='reader@example.test','roles deduplicated case-insensitively and updating user excluded');
verify(($GLOBALS['last_get_users']['fields']??null)===array('user_email')&&isset($GLOBALS['last_get_users']['role__in']),'recipients fetched in one query returning only email addresses');
verify(strpos($GLOBALS['mail'][0]['subject'],'[news.example.test:8443] [Post Update Email Notifier] ')===0,'domain port and plugin tags precede purpose');
verify(strpos($GLOBALS['mail'][0]['body'],'max-width:600px')===false,'HTML wrapper fills available width');
verify(!metadata_exists('post',1,'_pue_do_not_notify')&&pue_post_is_skipped(1),'notification returns post to the site default (skip)');
$service->handle_post_update(1,$post,null);verify(mails()===1,'next update does not repeat notification');
foreach(array('autosave','revision')as $flag){$GLOBALS['meta'][1]['_pue_do_not_notify']=0;$GLOBALS[$flag]=true;$service->handle_post_update(1,$post,null);$GLOBALS[$flag]=false;verify(mails()===1,$flag.' skipped');}
$post->post_status='draft';$service->handle_post_update(1,$post,null);verify(mails()===1,'unpublished content skipped');$post->post_status='publish';
$service->send_test_email('preview@example.test','Preview','<p>Sample</p>',false);$last=end($GLOBALS['mail']);verify(strpos($last['subject'],'[news.example.test:8443] [Post Update Email Notifier] Test: ')===0,'test subject follows shared tags');
$before=mails();$service->send_test_email('invalid','Preview','Sample',false);verify(mails()===$before,'invalid test recipient rejected');

$GLOBALS['options']['pue_enable_logging']=1;$GLOBALS['options']['pue_roles']=array();$GLOBALS['meta'][1]['_pue_do_not_notify']=0;$before=mails();$service->handle_post_update(1,$post,null);
verify(mails()===$before&&!metadata_exists('post',1,'_pue_do_not_notify')&&last_log()['reason']==='no_roles','no roles: nothing sent, opt-in consumed, reason logged');
$GLOBALS['options']['pue_roles']=array('editor','author');$GLOBALS['options']['pue_post_types']=array('page');$GLOBALS['meta'][1]['_pue_do_not_notify']=0;$service->handle_post_update(1,$post,null);
verify(mails()===$before&&last_log()['reason']==='post_type','excluded post type: nothing sent, reason logged');$GLOBALS['options']['pue_post_types']=array();
verify(PUE_Logger::instance()->count_failures_since(0)===0,'skipped entries do not count as delivery failures');

$GLOBALS['filters']['pue_placeholders'][10][]=array(function($maps,$id,$after,$before){$GLOBALS['seen_before']=$before;return $maps;},4);
$draft=new WP_Post(array('ID'=>1,'post_status'=>'draft','post_title'=>'Old title','post_content'=>'one','post_excerpt'=>''));
$GLOBALS['options']['pue_message']='<p>[event]</p>[change_summary]<a href="[revision_url]">diff</a>';$GLOBALS['meta'][1]['_pue_do_not_notify']=0;
pue_notify_after_insert(1,$post,true,$draft);$last=end($GLOBALS['mail']);
verify($GLOBALS['seen_before']===$draft,'wp_after_insert_post $post_before reaches developer filters');
verify(strpos($last['body'],'published')!==false&&strpos($last['body'],'First published.')!==false,'first publish is labeled and summarized');
verify(strpos($last['body'],'Title changed from “Old title”')!==false&&strpos($last['body'],'2 words added')!==false,'change summary lists title and content changes');
verify(strpos($last['body'],'revision.php?revision=77')!==false,'revision link points at the latest revision');
$GLOBALS['options']['pue_notify_events']='updates';$GLOBALS['meta'][1]['_pue_do_not_notify']=0;$before=mails();pue_notify_after_insert(1,$post,true,$draft);
verify(mails()===$before&&metadata_exists('post',1,'_pue_do_not_notify'),'updates-only setting ignores first publish and keeps the opt-in');
$before=mails();pue_notify_after_insert(1,$post,false,null);verify(mails()===$before,'brand-new inserts never notify');
$GLOBALS['options']['pue_notify_events']='all';delete_post_meta(1,'_pue_do_not_notify');

$GLOBALS['options']['pue_send_policy']='send';$before=mails();$service->handle_post_update(1,$post,$post);verify(mails()>$before,'send policy notifies without an opt-in');
$GLOBALS['meta'][1]['_pue_do_not_notify']=1;$before=mails();$service->handle_post_update(1,$post,$post);verify(mails()===$before&&!metadata_exists('post',1,'_pue_do_not_notify'),'send policy opt-out skips once then returns to default');
$GLOBALS['options']['pue_send_policy']='skip';

$GLOBALS['options']['pue_cooldown_minutes']=15;$GLOBALS['meta'][1]['_pue_do_not_notify']=0;$before=mails();$service->handle_post_update(1,$post,$post);
verify(mails()===$before&&last_log()['reason']==='cooldown','cooldown blocks a repeat within the window');
$GLOBALS['meta'][1]['_pue_last_sent']=time()-16*60;$GLOBALS['meta'][1]['_pue_do_not_notify']=0;$service->handle_post_update(1,$post,$post);verify(mails()>$before,'cooldown allows sending after the window');
$GLOBALS['options']['pue_cooldown_minutes']=0;

$GLOBALS['options']['pue_exclude_updater']=0;$GLOBALS['filters']['pue_email_plaintext_enabled'][10][]=array('__return_true_',1);
$GLOBALS['meta'][1]['_pue_do_not_notify']=0;$before=mails();$service->handle_post_update(1,$post,$post);$sent=array_slice($GLOBALS['mail'],$before);
verify(count($sent)===2&&$sent[0]['alt']&&$sent[1]['alt'],'plaintext part attached to every recipient');
$probe=new stdClass();pue_phpmailer_set_altbody($probe);verify(!isset($probe->AltBody),'plaintext part does not leak into unrelated mail');
unset($GLOBALS['filters']['pue_email_plaintext_enabled']);

$GLOBALS['mail_ok']=false;$GLOBALS['options']['pue_logs']=array();$GLOBALS['meta'][1]['_pue_do_not_notify']=0;$service->handle_post_update(1,$post,$post);$GLOBALS['mail_ok']=true;
$logs=$GLOBALS['options']['pue_logs'];verify(count($logs)===1&&$logs[0]['error']==='SMTP connect() failed.'&&$logs[0]['error_code']==='wp_mail_failed','failure detail attached to a single summary row');
verify(PUE_Logger::instance()->count_failures_since(0)===1,'real failures still count');

$GLOBALS['options']['pue_delivery_mode']='background';$GLOBALS['options']['pue_logs']=array();$GLOBALS['meta'][1]['_pue_do_not_notify']=0;$before=mails();$service->handle_post_update(1,$post,$post);
$job=end($GLOBALS['cron']);verify(mails()===$before&&$job[0]==='pue_send_batch'&&last_log()['mode']==='queued','background mode queues instead of sending during save');
add_filter('pue_background_batch_size',function(){return 1;});PUE_Delivery::run_job($job[1][0]);
verify(mails()===$before+1&&last_log()['mode']==='queued'&&last_log()['sent_count']===1,'first batch sends and records progress');
$next=end($GLOBALS['cron']);PUE_Delivery::run_job($next[1][0]);
verify(mails()===$before+2&&last_log()['sent']===1&&last_log()['mode']==='background'&&!isset($GLOBALS['options']['pue_job_'.$job[1][0]]),'final batch completes the log entry and removes the job');
$GLOBALS['options']['pue_delivery_mode']='auto';
add_filter('pue_background_threshold',function(){return 1;});verify(PUE_Delivery::should_queue(2)&&!PUE_Delivery::should_queue(1),'automatic mode queues only lists above the threshold');
unset($GLOBALS['filters']['pue_background_threshold'],$GLOBALS['filters']['pue_background_batch_size']);

unset($GLOBALS['options']['pue_header_text'],$GLOBALS['options']['pue_footer_text']);$html=pue_email_template_branded('<p>x</p>',array('[site_name]'=>'Fixture Site'));
verify(strpos($html,'Fixture Site Notification')!==false&&strpos($html,'Thank you for supporting Fixture Site')!==false,'template defaults match the displayed defaults');

$GLOBALS['options']['pue_roles']=array('editor');unset($GLOBALS['options']['pue_subject'],$GLOBALS['options']['pue_message']);$GLOBALS['meta'][2]['_pue_do_not_notify']=0;$service->handle_post_update(2,$post,null);$last=end($GLOBALS['mail']);verify(strpos($last['subject'],'Post Updated:')!==false&&strpos($last['body'],'has been updated')!==false,'unset stored templates use meaningful notification defaults');
