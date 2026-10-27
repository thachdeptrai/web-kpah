<?php
/** Point icon and real game option picker smoke test (no production DB). */
define('KP_ROOT',dirname(__DIR__));
require KP_ROOT.'/app/includes/points_reward_picker.php';
require KP_ROOT.'/app/includes/points_tiers.php';
function pp_assert($ok,$message){if(!$ok)throw new RuntimeException('Points picker: '.$message);}
$catalog=[
 'items'=>[['id'=>11,'name'=>'Kiếm K','icon_id'=>42,'type'=>1,'type_name'=>'Vũ khí','image'=>'/res/icon/42.png']],
 'options'=>[
    ['id'=>0,'name'=>'Tấn công','min'=>1,'max'=>32767,'percent'=>false,'display_multiplier'=>1,'description'=>''],
    ['id'=>33,'name'=>'Tỷ lệ','min'=>1,'max'=>127,'percent'=>true,'display_multiplier'=>1000,'description'=>'']
 ],
];
$payload=[
 'icon_id'=>'42','item_id'=>'11','option_lines'=>'tampered text',
 'point_picker_options'=>json_encode([['id'=>0,'param'=>20],['id'=>33,'param'=>3]]),
];
$p=kp_points_picker_display_input($payload,$catalog);
pp_assert($p['icon_id']===42 && $p['item_id']===11,'real selection preserves game IDs');
pp_assert(strpos($p['option_lines'],'#0 Tấn công +20')!==false,'normal option stored canonically');
pp_assert(strpos($p['option_lines'],'#33 Tỷ lệ +3.000%')!==false,'percent display multiplier is applied');
pp_assert(strpos($p['option_lines'],'tampered')===false,'server ignores arbitrary textarea when chosen option payload exists');
pp_assert(kp_point_reward_icon($p)==='/res/icon/42.png','icon path stays local and numeric');
foreach([
 [['point_picker_options'=>'[{"id":1,"param":9}]','item_id'=>'11'], 'unknown ID'],
 [['point_picker_options'=>'[{"id":0,"param":0}]','item_id'=>'11'], 'low param'],
 [['point_picker_options'=>'[{"id":0,"param":32768}]','item_id'=>'11'], 'high param'],
 [['point_picker_options'=>'[{"id":0,"param":2},{"id":0,"param":3}]','item_id'=>'11'], 'duplicate option'],
 [['point_picker_options'=>'[{"id":"0","param":2}]','item_id'=>'11'], 'string ID'],
 [['point_picker_options'=>'[{"id":0,"param":1}]','item_id'=>'12'], 'missing item'],
 [['point_picker_options'=>'[{"id":0,"param":1}]','item_id'=>''], 'no item'],
 [['point_picker_options'=>'<svg/onload=alert(1)>','item_id'=>'11'], 'malformed JSON'],
] as $scenario){
 try{kp_points_picker_display_input($scenario[0],$catalog);
    pp_assert(false,'rejected '.$scenario[1]);
 }catch(RuntimeException $expected){}
}
$legacy=kp_points_picker_display_input([
 'icon_id'=>'42','item_id'=>'11','option_lines'=>"Công +30\nHP +40",
 'point_picker_options'=>''
],null);
pp_assert($legacy['option_lines']==="Công +30\nHP +40",'legacy text editors work without game catalog');
$js=(string)file_get_contents(KP_ROOT.'/assets/js/points-reward-picker.js');
pp_assert(strpos($js,'innerHTML')===false && strpos($js,'document.write')===false
    && strpos($js,'eval(')===false && strpos($js,'new Function')===false,
    'picker does not execute unsanitized HTML/JS');
$page=(string)file_get_contents(KP_ROOT.'/app/topup/admin/points.php');
pp_assert(strpos($page,'kp_points_picker_display_input($_POST,$pointCatalog)')!==false,
    'backend always validates structured options when saving');
pp_assert(strpos($page,'JSON_HEX_TAG|JSON_HEX_AMP')!==false,
    'game catalog escapes HTML script closing tags');
pp_assert(strpos($page,'kp_admin_csrf_valid')!==false,
    'admin CSRF remains required');
$tiers=kp_points_tiers();
pp_assert(count($tiers)===4,'rank count unchanged');
foreach($tiers as $tier){
 pp_assert(strpos($tier['image'],'/assets/img/point-tier-')===0,'tier has local image');
 $path=KP_ROOT.$tier['image'];
 pp_assert(is_file($path),'missing tier WEBP: '.$path);
 $magic=(string)file_get_contents($path,false,null,0,16);
 pp_assert(substr($magic,0,4)==='RIFF' && substr($magic,8,4)==='WEBP',
    'tier badge must be an actual WEBP binary');
}
echo "PASS: Point editor icon/option validation, JS injection safety and 4 WEBP ranks\n";
