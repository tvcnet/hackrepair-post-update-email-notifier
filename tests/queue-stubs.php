<?php
/** Option/cron adapter for isolated queue fault tests; production uses real WordPress SQL. */
function register_deactivation_hook(...$a){}
function maybe_serialize($v){return is_array($v)||is_object($v)?serialize($v):$v;}
function wp_cache_delete(...$a){return true;}
function wp_next_scheduled($hook,$args=array()){foreach($GLOBALS['cron'] as$e){if($e[0]===$hook&&$e[1]===$args)return $e[2]??time();}return false;}
function wp_schedule_single_event($ts,$hook,$args=array(),$error=false){
 if(isset($GLOBALS['schedule_failure'])&&$GLOBALS['schedule_failure'])return 'error'===$GLOBALS['schedule_failure']?new WP_Error('schedule_failure','Injected failure'):false;
 $GLOBALS['cron'][]=array($hook,$args,$ts);return true;
}
function wp_schedule_event($ts,$interval,$hook){$GLOBALS['cron'][]=array($hook,array(),$ts);return true;}
function wp_clear_scheduled_hook($hook,$args=array()){$GLOBALS['cron']=array_values(array_filter($GLOBALS['cron'],static function($e)use($hook,$args){return $e[0]!==$hook||$e[1]!==$args;}));}
function wp_unschedule_hook($hook){$GLOBALS['cron']=array_values(array_filter($GLOBALS['cron'],static function($e)use($hook){return $e[0]!==$hook;}));}
class PUE_Test_DB{
 public $options='options';public $before_query=null;
 function prepare($sql,...$args){return array($sql,$args);}
 function esc_like($v){return $v;}
 function get_col($sql){return array_values(array_filter(array_keys($GLOBALS['options']),static function($k){return strpos($k,'pue_job_')===0;}));}
 function query($q){
  list($sql,$args)=$q;
  if($this->before_query){$cb=$this->before_query;$this->before_query=null;$cb($sql,$args);}
  if(strpos($sql,'AS lease')!==false){
   list($lock,$owner)=$args;
   if(!isset($GLOBALS['options'][$lock])||maybe_serialize($GLOBALS['options'][$lock])!==$owner)return 0;
   if(strpos($sql,'UPDATE ')===0){$key=$args[3];if(!isset($GLOBALS['options'][$key]))return 0;$GLOBALS['options'][$key]=unserialize($args[2]);return 1;}
   $key=$args[2];if(!isset($GLOBALS['options'][$key]))return 0;unset($GLOBALS['options'][$key]);return 1;
  }
  list($key,$owner)=$args;if(!isset($GLOBALS['options'][$key])||maybe_serialize($GLOBALS['options'][$key])!==$owner)return 0;
  unset($GLOBALS['options'][$key]);return 1;
 }
}
$GLOBALS['wpdb']=new PUE_Test_DB();
