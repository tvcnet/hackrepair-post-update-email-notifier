<?php
/** Deterministic failures and competing workers; never real mail, cron or database. */
require __DIR__.'/notifications.php';
function queue_fixture($count=3){
 $GLOBALS['options']=array();$GLOBALS['mail']=array();$GLOBALS['cron']=array();$GLOBALS['schedule_failure']=false;$GLOBALS['filters']=array();$GLOBALS['mail_ok']=true;
 unset($GLOBALS['mail_callback']);$GLOBALS['wpdb']->before_query=null;
 return PUE_Delivery::queue(array('post_id'=>0,'editor_id'=>0,'subject'=>'Queue fixture','body'=>'Fixture','headers'=>array(),'alt'=>null,'recipients'=>array_map(static function($n){return 'r'.$n.'@example.invalid';},range(1,$count))));
}
function run_fixture($id){wp_clear_scheduled_hook(PUE_Delivery::HOOK,array($id));PUE_Delivery::run_job($id);}
$id=queue_fixture();add_filter('pue_background_batch_size',static function(){return 1;});$GLOBALS['schedule_failure']=true;run_fixture($id);
$job=get_option('pue_job_'.$id);
verify(mails()===1&&$job['offset']===1&&$job['state']==='paused'&&!wp_next_scheduled(PUE_Delivery::HOOK,array($id)),'failed continuation keeps progress and a recoverable paused job even with logging off');
verify(wp_next_scheduled(PUE_Delivery::RECOVERY_HOOK)!==false,'independent recovery event exists');
$GLOBALS['schedule_failure']=false;PUE_Delivery::recover_jobs();verify(wp_next_scheduled(PUE_Delivery::HOOK,array($id))!==false&&get_option('pue_job_'.$id)['error_code']==='','recovery restores a missing continuation and clears stale scheduling errors');
run_fixture($id);run_fixture($id);
verify(mails()===3&&count(array_unique(array_column($GLOBALS['mail'],'to')))===3&&!get_option('pue_job_'.$id),'recovery completes remaining recipients without replaying accepted mail');
$id=queue_fixture(2);$GLOBALS['schedule_failure']='error';run_fixture($id); // Both recipients fit; test initial scheduling failure separately.
$id=queue_fixture(2);wp_clear_scheduled_hook(PUE_Delivery::HOOK,array($id));$GLOBALS['schedule_failure']='error';
for($i=0;$i<5;$i++)PUE_Delivery::recover_jobs();
verify(get_option('pue_job_'.$id)['state']==='failed'&&mails()===0,'WP_Error scheduling stops after five failures without falling back to a synchronous send');
verify(PUE_Site_Health::test_cron()['status']==='recommended','failed jobs remain visible through Site Health when logging is off');
$GLOBALS['schedule_failure']=false;verify(PUE_Delivery::retry_job($id),'operator can explicitly retry scheduling');run_fixture($id);
verify(mails()===2&&!get_option('pue_job_'.$id),'explicit retry sends only untouched recipients');
$id=queue_fixture(2);$once=true;$GLOBALS['mail_callback']=static function()use(&$once){if($once){$once=false;throw new RuntimeException('Injected exception');}};
run_fixture($id);
verify(mails()===1&&!get_option('pue_job_'.$id)&&!get_option('pue_job_'.$id.'_lock'),'transport exception does not trap the lock or replay the uncertain recipient');
$id=queue_fixture(3);$job=get_option('pue_job_'.$id);$job['inflight']=0;$job['offset']=1;update_option('pue_job_'.$id,$job);update_option('pue_job_'.$id.'_lock',array('token'=>'dead','expires'=>time()-1));run_fixture($id);
verify(mails()===2&&$GLOBALS['mail'][0]['to']==='r2@example.invalid','expired lease resumes after an interrupted checkpoint without resending its recipient');
$id=queue_fixture(2);$lock='pue_job_'.$id.'_lock';update_option($lock,array('token'=>'live','expires'=>time()+300));run_fixture($id);verify(mails()===0,'overlapping worker cannot send while a live lease exists');
$id=queue_fixture(2);$key='pue_job_'.$id;$lock=$key.'_lock';$GLOBALS['wpdb']->before_query=static function($sql,$args)use($lock){$GLOBALS['options'][$lock]=array('token'=>'replacement','expires'=>time()+300);};run_fixture($id);
verify(mails()===0&&get_option($lock)['token']==='replacement','lost worker is fenced before transport and cannot delete the replacement lease');
$id=queue_fixture(2);$GLOBALS['mail_callback']=static function()use($id){$GLOBALS['options']['pue_job_'.$id.'_lock']=array('token'=>'replacement','expires'=>time()+300);};run_fixture($id);unset($GLOBALS['mail_callback']);
verify(mails()===1&&isset(get_option('pue_job_'.$id)['inflight']),'lease loss during transport preserves the durable uncertain checkpoint');
update_option('pue_job_'.$id.'_lock',array('token'=>'replacement','expires'=>time()-1));run_fixture($id);verify(mails()===2&&count(array_unique(array_column($GLOBALS['mail'],'to')))===2,'recovery after takeover avoids duplicate recipients');
$id=queue_fixture(2);$job=get_option('pue_job_'.$id);$job['created']=time()-8*DAY_IN_SECONDS;update_option('pue_job_'.$id,$job);PUE_Delivery::recover_jobs();
verify(!get_option('pue_job_'.$id)&&mails()===0,'expired jobs purge their payload without sending old messages');
$id=queue_fixture(2);PUE_Delivery::deactivate();verify(!wp_next_scheduled(PUE_Delivery::HOOK,array($id))&&!wp_next_scheduled(PUE_Delivery::RECOVERY_HOOK)&&get_option('pue_job_'.$id)!==false,'deactivation removes callbacks but preserves bounded pending progress');
PUE_Delivery::ensure_recovery();PUE_Delivery::recover_jobs();verify(wp_next_scheduled(PUE_Delivery::HOOK,array($id))!==false,'reactivation can schedule the preserved pending job');
