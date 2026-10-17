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
$rankings=pp_read('app/pages/rankings.php');
$inventoryCss=pp_read('assets/css/character-inventory.css');
$queries=pp_read('app/includes/dashboard_data.php');
$script=pp_read('assets/js/user-home.js');
pp_ok(strpos($base,"require_once __DIR__.'/farmer_points.php'")!==false,'existing player dependencies preserved');
pp_ok(strpos($base,'function kp_player_account()')!==false &&
      strpos($base,"WHERE id=? LIMIT 1")!==false,
      'authenticated user ownership guard intact');
pp_ok(strpos($base,'player-portal-v3.css?v=')!==false,'v3 last-layer styles loaded with cache busting');
pp_ok(strpos($base,'aria-label="Các chức năng tài khoản"')!==false,'accessible labelled navigation');
pp_ok(strpos($base,'function kp_player_icon_art')!==false,'fixed original raster icon library');
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
// Dashboard source is already loaded by pp_read() above.
pp_ok(strpos($dashboard,'href="/cong-dong"')!==false && strpos($dashboard,'href="/#dien-dan"')===false,'dashboard community shortcut reaches actual community page');
pp_ok(strpos($dashboard,'portal-hero-emblem')===false && strpos($dashboard,'rank-guardian.webp')===false,'dashboard hero no longer repeats leaderboard character art');
pp_ok(strpos($css,'portal-sky-vista.webp')!==false && strpos($css,'portal-adventurer.webp')!==false,'dashboard uses newly painted local portal art');
pp_ok(is_file($root.'/assets/img/portal-sky-vista.webp') && is_file($root.'/assets/img/portal-adventurer.webp') && !is_file($root.'/assets/img/portal-sky-vista.png') && !is_file($root.'/assets/img/portal-adventurer.png'),'optimized WebP portal artwork replaces old PNGs');
$rankHeroStart=strpos($rankings,'<section class="portal-hero leaderboard-hero">');
$rankHero=$rankHeroStart===false?'':substr($rankings,$rankHeroStart,strpos($rankings,'</section>',$rankHeroStart)-$rankHeroStart);
pp_ok(strpos($rankHero,"kp_player_icon_art('trophy')")!==false && strpos($rankHero,'rank-guardian.webp')===false && strpos($rankings,'rank-guardian.webp')!==false,'leaderboard masthead uses a trophy while podium keeps its own heroes');
pp_ok(strpos($css,'.portal-page .podium-card{')!==false && strpos($css,'.portal-page .lb-lower{')!==false,'leaderboard podium and table use light concept styling');
pp_ok(strpos($css,'.portal-page .ci-section{')!==false && strpos($inventoryCss,'.ci-section')!==false,'character inventory receives light concept overrides');
$art=pp_read('assets/css/player-art-icons.css');
pp_ok(strpos($art,'kpah-player-icon-atlas.png')!==false,'portal includes custom illustrated raster sprite');
pp_ok(strpos($base,'player-art-icons.css')!==false,'original illustrated icon CSS loaded by shared portal shell');
pp_ok(strpos($dashboard,'kp_player_icon_art')!==false,'dashboard uses bespoke raster illustrations');
pp_ok(strpos($characters,'char-ui-clan-art')!==false,'character guild badge uses custom fantasy icon');
pp_ok(strpos($characters,'char-ui-stat-icon')!==false && strpos($characters,"kp_player_icon_art('points')")!==false,'character currencies use custom painted icons');
pp_ok(strpos($characters,'char-ui-time-art')!==false,'recent activity uses CSS illustration instead of inline SVG icon');
pp_ok(strpos($base,'portal-nav-toggle')!==false && strpos($base,'aria-expanded="false"')!==false,'accessible mobile account menu trigger');
pp_ok(strpos($base,'player-mobile-nav.js')!==false,'mobile menu behavior loaded on all user pages');
pp_ok(strpos($base,'id="portal-nav-list"')!==false,'menu trigger references the server navigation');
echo "Player Portal v3 contract smoke PASS\n";
