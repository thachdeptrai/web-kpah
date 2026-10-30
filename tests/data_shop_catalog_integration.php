<?php
/** MariaDB integration for separate game data_item/data_shop schemas. */
if(getenv('KPAH_DB_NAME')!=='kpah_forum_test'){fwrite(STDERR,"Refusing non-disposable DB\n");exit(1);}
require dirname(__DIR__).'/app/includes/bootstrap.php';
require_once KP_ROOT.'/app/includes/giftcodes.php';
function catalog_assert($ok,$label){if(!$ok)throw new RuntimeException('Shop catalog: '.$label);echo 'PASS '.$label."\n";}
$db=kp_db();
$db->exec('CREATE TABLE IF NOT EXISTS data_attribute (id INT PRIMARY KEY,name VARCHAR(60),ispercent INT,idcolor INT,decript VARCHAR(200)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec("REPLACE INTO data_attribute(id,name,ispercent,idcolor,decript) VALUES(0,'Sát thương',0,0,'Điểm')");
$parts=[];
foreach(['id INT PRIMARY KEY','name VARCHAR(120)','type INT','style INT','he INT','gender INT','level INT','durable INT','xstart INT','nloan INT'] as $p)$parts[]=$p;
for($i=0;$i<10;$i++)$parts[]='atb'.$i.' INT NOT NULL DEFAULT 0';
$db->exec('CREATE TABLE IF NOT EXISTS data_item ('.implode(',',$parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec("REPLACE INTO data_item(id,name,type,style,he,gender,level,durable,xstart,nloan,atb0) VALUES(17,'Kiếm data_item',3,0,0,0,1,0,49,0,10)");
$db->exec('CREATE TABLE IF NOT EXISTS data_shop (id INT PRIMARY KEY,idImg INT,name VARCHAR(120),decript VARCHAR(200),value INT,price INT,type INT,tab INT,sell INT,priceSale INT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec("REPLACE INTO data_shop(id,idImg,name,decript,value,price,type,tab,sell,priceSale) VALUES(17,999,'Vật phẩm Data Shop','Mô tả Shop',3,25,2,4,1,10),(0,9,'Shop ID không','ID 0 hợp lệ',1,2,1,1,1,2)");
$catalog=kp_giftcode_reward_catalog($db);
catalog_assert(count($catalog['items'])>=1 && count($catalog['shop_items'])===2,'loads both independent sources');
catalog_assert($catalog['item_map'][17]['name']==='Kiếm data_item','data_item map preserves data_item template');
catalog_assert($catalog['shop_map'][17]['name']==='Vật phẩm Data Shop','data_shop map preserves ShopTemplate when ID overlaps');
catalog_assert($catalog['shop_map'][17]['icon_id']===999,'shop idImg becomes icon_id');
catalog_assert($catalog['shop_map'][17]['tab']===4,'real shop tab category preserved');
catalog_assert(isset($catalog['shop_map'][0]),'ShopTemplate ID zero supported');
catalog_assert(($catalog['shop_map'][17]['source']??'')==='data_shop','shop source is tagged');
catalog_assert($catalog['item_map'][17]['source']==='data_item','equipment source is tagged');
catalog_assert(!kp_giftcode_shop_enabled(),'SHOP gift redemption remains disabled until Java patch');
echo "Data Shop catalog integration COMPLETE\n";
