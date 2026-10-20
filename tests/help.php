<?php
/** Help is escaped, addressable and usable as a native disclosure without scripts. */
define('ABSPATH',__DIR__.'/');
function __($s,$domain){return $s;}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function esc_attr($s){return esc_html($s);}
function wp_unique_id($prefix=''){static $i=0;return $prefix.(++$i);}
require dirname(__DIR__).'/includes/class-pue-help.php';
function help_assert($ok,$label){if(!$ok)throw new Exception($label);echo 'PASS '.$label."\n";}
ob_start();PUE_Help::render('pue_send_policy','Default for each update');PUE_Help::render('pue_send_policy','Default for each update');$html=ob_get_clean();
help_assert(substr_count($html,'<details ')==2&&substr_count($html,'<summary ')==2,'help retains native keyboard and no-JavaScript disclosure controls');
preg_match_all('/aria-controls="([^"]+)"/',$html,$ids);
help_assert(count(array_unique($ids[1]))===2&&strpos($html,'id="'.$ids[1][0].'"')!==false,'repeated help controls have unique connected content IDs');
help_assert(strpos($html,'Save Notifications')!==false&&strpos($html,'No mail is sent unless recipient roles are selected')!==false,'default-policy help explains applying changes and sending prerequisites');
ob_start();PUE_Help::render('pue_subject','"><script>bad</script>');$html=ob_get_clean();
help_assert(strpos($html,'<script>')===false&&strpos($html,'&lt;script&gt;')!==false,'dynamic accessible labels are escaped');
ob_start();PUE_Help::render('unknown','Unknown');$html=ob_get_clean();help_assert($html==='','unknown help never renders an empty disclosure');
help_assert(strpos(PUE_Help::text('pue_preview'),'without sending')!==false&&strpos(PUE_Help::text('pue_test_email'),'real test email')!==false,'preview help distinguishes no-send preview from real saved-template test');
help_assert(strpos(PUE_Help::text('pue_reset'),'cancel queued')!==false&&strpos(PUE_Help::text('pue_bulk_count'),'200')!==false,'action help explains side effects and test limits');
