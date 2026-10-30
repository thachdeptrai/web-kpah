<?php
/** Validate source-aware Giftcode rewards without touching production SQL. */
define('KP_ROOT',dirname(__DIR__));
$GLOBALS['test_shop_patch_enabled']=false;
function kp_config($key,$default=null){
    return $key==='giftcode.shop_enabled'?$GLOBALS['test_shop_patch_enabled']:$default;
}
require KP_ROOT.'/app/includes/giftcodes.php';
function shop_check($ok,$description){if(!$ok)throw new RuntimeException('Shop picker: '.$description);}
$items=[17=>['id'=>17,'name'=>'Data Item 17']];
$shop=[17=>['id'=>17,'name'=>'Shop 17','source'=>'data_shop']];
$shopJson=json_encode([[
    'source'=>'data_shop','item_id'=>17,'quantity'=>3,'minutes'=>0,
    'lock'=>0,'magic'=>0,'options'=>[],
]]);
$rejected=false;
try{kp_giftcode_validate_reward_payload($shopJson,$items,[],false,$shop);}
catch(RuntimeException $e){$rejected=strpos($e->getMessage(),'SHOP')!==false;}
shop_check($rejected,'SHOP gift is disabled until server patch enabled');
$GLOBALS['test_shop_patch_enabled']=true;
$r=kp_giftcode_validate_reward_payload($shopJson,$items,[],false,$shop);
shop_check($r[0]['source']==='data_shop','source is preserved despite colliding ID');
shop_check(kp_giftcode_serialize_rewards($r)==='SHOP:17:3','SHOP token never becomes DB');
$parsed=kp_giftcode_parse_rewards('SHOP:17:3',$items,$shop);
shop_check(count($parsed['rewards'])===1 && $parsed['rewards'][0]['source']==='data_shop','SHOP token loads for edit');
$unknown=kp_giftcode_parse_rewards('SHOP:18:3',$items,$shop);
shop_check($unknown['legacy']===['SHOP:18:3'],'unknown Shop token preserved as legacy');
$bad=json_encode([[
    'source'=>'data_shop','item_id'=>17,'quantity'=>1,'minutes'=>5,
    'lock'=>0,'magic'=>0,'options'=>[],
]]);
$rejected=false;
try{kp_giftcode_validate_reward_payload($bad,$items,[],false,$shop);}
catch(RuntimeException $e){$rejected=true;}
shop_check($rejected,'shop-specific reward cannot carry equipment duration');
$bad=json_encode([[
    'source'=>'data_shop','item_id'=>17,'quantity'=>1,'minutes'=>0,
    'lock'=>0,'magic'=>0,'options'=>[['id'=>0,'param'=>100]],
]]);
$rejected=false;
try{kp_giftcode_validate_reward_payload($bad,$items,[0=>['max'=>32767]],true,$shop);}
catch(RuntimeException $e){$rejected=true;}
shop_check($rejected,'shop-specific reward cannot carry equipment options');
$dataItem=json_encode([['item_id'=>17,'quantity'=>3,'minutes'=>0,'lock'=>0,'magic'=>0,'options'=>[]]]);
$normal=kp_giftcode_validate_reward_payload($dataItem,$items,[],false,$shop);
shop_check(kp_giftcode_serialize_rewards($normal)==='DB:17:3:0','legacy data_item DB token unchanged');
echo "PASS: Data Shop Giftcode source-aware guard and legacy compatibility\n";
