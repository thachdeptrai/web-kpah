<?php
/** Structural contract smoke for Player Portal v3 — read only, no database. */
$root=dirname(__DIR__);
function pp_ok($condition,$label){if(!$condition){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
function pp_read($path){global $root;$s=@file_get_contents($root.'/'.$path);pp_ok(is_string($s),"available $path");return $s;}
$base=pp_read('app/includes/player_portal.php');
$css=pp_read('assets/css/player-portal-v3.css');
$dashboard=pp_read('app/pages/home.php');
$profile=pp_read('app/pages/profile.php');
$characters=pp_read('app/pages/characters.php');
$queries=pp_read('app/includes/dashboard_data.php');
$script=pp_read('assets/js/user-home.js');
pp_ok(strpos($base,"require_once __DIR__.'/farmer_points.php'")!==false,'existing player dependencies preserved');
pp_ok(strpos($base,'function kp_player_account()')!==false &&
      strpos($base,"WHERE id=? LIMIT 1")!==false,
      'authenticated user ownership guard intact');
pp_ok(strpos($base,'player-portal-v3.css?v=')!==false,'v3 last-layer styles loaded with cache busting');
pp_ok(strpos($base,'aria-label="Các chức năng tài khoản"')!==false,'accessible labelled navigation');
pp_ok(strpos($base,'function kp_player_icon_svg')!==false,'fixed inline icon library');
foreach(['/tai-khoan','/ho-so','/nhan-vat','/bang-xep-hang','/nap','/giftcode-cua-toi','/thong-bao','/theo-doi-don-nap','/lich-su-bao-mat','/api/logout'] as $route)
 pp_ok(strpos($base,$route)!==false,"existing portal route $route");
pp_ok(strpos($css,'background:linear-gradient(180deg,rgba(214,240,255')!==false,'bright player palette');
pp_ok(strpos($css,'@media(max-width:680px)')!==false &&
      strpos($css,'inset:auto!important')!==false &&
      strpos($css,'overflow-x:auto!important')!==false,'mobile legacy bottom rail override and scrollable menu');
foreach(['pd-stat-grid','pd-character-list','pd-payment-list','pd-account-list','pd-reward-balance','pd-achievement-row','portal-action-grid'] as $cls)
 pp_ok(strpos($dashboard,'class="'.$cls)!==false || strpos($dashboard,' '.$cls)!==false,"live dashboard section $cls");
foreach(['vnd','tongnap','points','characters_count','payments','achievement'] as $key)
 pp_ok(strpos($dashboard,"\$dashboard['".$key."']")!==false,"dashboard uses live $key");
pp_ok(strpos($queries,'UPDATE team_user')===false && strpos($queries,'INSERT INTO')===false,'dashboard queries remain read-only');
pp_ok(strpos($queries,'WHERE user_id=?')!==false && strpos($queries,'WHERE w.userid=?')!==false,'ownership joins unchanged');
foreach(['profile-form','change-password-form','old_email_code','email_code','phone','current_password','new_password','confirm_password'] as $field)
 pp_ok(strpos($profile,$field)!==false,"profile form contract $field");
pp_ok(strpos($profile,'/api/user-profile')!==false && strpos($profile,'/api/change-password')!==false &&
      substr_count($profile,'name="csrf"')===2,'existing profile endpoints and both CSRF fields');
pp_ok(strpos($script,'profile-form')!==false && strpos($script,'change-password-form')!==false,'profile interaction preserved');
pp_ok(strpos($characters,'kp_player_characters')!==false &&
      strpos($characters,'kp_e($charName)')!==false,'owned character output escaped');
$dashboard=file_get_contents(KP_ROOT.'/app/pages/home.php');
pp_ok(strpos($dashboard,'href="/cong-dong"')!==false && strpos($dashboard,'href="/#dien-dan"')===false,'dashboard community shortcut reaches actual community page');
echo "Player Portal v3 contract smoke PASS\n";
