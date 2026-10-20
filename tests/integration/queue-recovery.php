<?php
/** Real option-table leases and scheduling failures; pre_wp_mail captures every fixture message. */
if(PUE_Delivery::jobs()){WP_CLI::error('Queue fault checks require a site without pre-existing queued messages.');}
$GLOBALS['pue_q_failed']=0;$GLOBALS['pue_q_ids']=array();$GLOBALS['pue_q_mail']=array();$GLOBALS['pue_q_fail_schedule']=false;$GLOBALS['pue_q_throw']=false;
function pue_q_assert($ok,$label){echo ($ok?'PASS ':'FAIL ').$label."\n";if(!$ok)++$GLOBALS['pue_q_failed'];}
function pue_q_job(){
 $id=PUE_Delivery::queue(array('post_id'=>0,'editor_id'=>0,'subject'=>'Isolated queue fixture','body'=>'Fixture','headers'=>array(),'alt'=>null,'recipients'=>array('r1@example.invalid','r2@example.invalid','r3@example.invalid')));
 $GLOBALS['pue_q_ids'][]=$id;return $id;
}
function pue_q_run($id){wp_clear_scheduled_hook(PUE_Delivery::HOOK,array($id));PUE_Delivery::run_job($id);}
add_filter('pre_wp_mail',static function($pre,$atts){
 if($GLOBALS['pue_q_throw']){$GLOBALS['pue_q_throw']=false;throw new RuntimeException('Fixture transport exception');}
 $GLOBALS['pue_q_mail'][]=$atts['to'];return true;
},PHP_INT_MAX,2);
add_filter('pre_schedule_event',static function($pre,$event){return $GLOBALS['pue_q_fail_schedule']&&$event->hook===PUE_Delivery::HOOK?new WP_Error('fixture_schedule_failure','Fixture scheduling failure'):$pre;},10,2);
add_filter('pue_background_batch_size',static function(){return 1;});
$pue_q_prior=get_option('pue_enable_logging','__missing__');
try{
 update_option('pue_enable_logging',0);
 $id=pue_q_job();$pue_q_stale=get_option('pue_job_'.$id);$GLOBALS['pue_q_fail_schedule']=true;pue_q_run($id);$job=get_option('pue_job_'.$id);
 pue_q_assert(count($GLOBALS['pue_q_mail'])===1&&$job['state']==='paused'&&$job['offset']===1,'real scheduler failure preserves checkpoint and records paused state');
 $GLOBALS['pue_q_fail_schedule']=false;PUE_Delivery::recover_jobs();pue_q_assert(false!==wp_next_scheduled(PUE_Delivery::HOOK,array($id)),'real recovery schedules missing callback');
 wp_cache_set('pue_job_'.$id,$pue_q_stale,'options');
 pue_q_run($id);pue_q_run($id);pue_q_assert(count(array_unique(array_map(static function($to){return implode(',',(array)$to);},$GLOBALS['pue_q_mail'])))===3&&count($GLOBALS['pue_q_mail'])===3&&!get_option('pue_job_'.$id),'real fenced option writes ignore stale option cache and complete only remaining recipients');
 $id=pue_q_job();$n=count($GLOBALS['pue_q_mail']);$GLOBALS['pue_q_throw']=true;pue_q_run($id);pue_q_run($id);pue_q_run($id);
 pue_q_assert(count($GLOBALS['pue_q_mail'])===$n+2&&!get_option('pue_job_'.$id)&&!get_option('pue_job_'.$id.'_lock'),'transport exception releases the lease and does not replay uncertain recipient');
 $id=pue_q_job();$key='pue_job_'.$id;$job=get_option($key);$job['offset']=1;$job['inflight']=0;update_option($key,$job,false);add_option($key.'_lock',array('token'=>'expired','expires'=>time()-1),'','no');$n=count($GLOBALS['pue_q_mail']);
 pue_q_run($id);pue_q_run($id);pue_q_assert(count($GLOBALS['pue_q_mail'])===$n+2&&!get_option($key),'atomic expired-lease takeover skips interrupted recipient');
 $id=pue_q_job();$GLOBALS['pue_q_fail_schedule']=true;wp_clear_scheduled_hook(PUE_Delivery::HOOK,array($id));
 for($i=0;$i<5;$i++)PUE_Delivery::recover_jobs();
 pue_q_assert(get_option('pue_job_'.$id)['state']==='failed'&&PUE_Site_Health::test_cron()['status']==='recommended','five real scheduler errors stop retrying and surface in Site Health');
 $GLOBALS['pue_q_fail_schedule']=false;pue_q_assert(PUE_Delivery::retry_job($id),'operator retry reuses checkpoint under a real lease');
 pue_q_run($id);pue_q_run($id);pue_q_run($id);
 $id=pue_q_job();$job=get_option('pue_job_'.$id);$job['created']=time()-8*DAY_IN_SECONDS;update_option('pue_job_'.$id,$job,false);$n=count($GLOBALS['pue_q_mail']);PUE_Delivery::recover_jobs();
 pue_q_assert(!get_option('pue_job_'.$id)&&count($GLOBALS['pue_q_mail'])===$n,'seven-day expiry removes queued payload without sending');
}finally{
 foreach($GLOBALS['pue_q_ids']as$id){wp_clear_scheduled_hook(PUE_Delivery::HOOK,array($id));delete_option('pue_job_'.$id);delete_option('pue_job_'.$id.'_lock');}
 if($pue_q_prior==='__missing__')delete_option('pue_enable_logging');else update_option('pue_enable_logging',$pue_q_prior,false);
}
if($GLOBALS['pue_q_failed'])WP_CLI::error($GLOBALS['pue_q_failed'].' queue integration checks failed.');
WP_CLI::success('Queue fault checks passed; fixture jobs removed. No external mail sent.');
