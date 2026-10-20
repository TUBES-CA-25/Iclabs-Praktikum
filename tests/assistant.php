<?php
if(PHP_SAPI!=='cli'||strpos(getenv('PRAKTIKUM_DB_NAME'),'test_')!==0)exit('Test database required');
require __DIR__.'/../app/bootstrap.php';require __DIR__.'/../app/admin.php';require __DIR__.'/../app/user_import.php';
$before=rows('SELECT id,password FROM users ORDER BY id');$assign=rows('SELECT id,asisten1_id,asisten2_id FROM jadwal_praktikum ORDER BY id');
foreach(explode(';',file_get_contents(__DIR__.'/../database/update-role-asisten.sql')) as $sql)if(trim($sql))$db->exec($sql);
$checks=0;function assistant_check($v){global $checks;if(!$v)throw new Exception('Check failed '.($checks+1));$checks++;}
assistant_check($before===rows('SELECT id,password FROM users ORDER BY id'));assistant_check($assign===rows('SELECT id,asisten1_id,asisten2_id FROM jadwal_praktikum ORDER BY id'));
assistant_check(!row("SELECT id FROM users WHERE role IN ('asisten1','asisten2')"));
$c=rows(class_sql())[0];$user=array('id'=>(int)$c['assistant1_user'],'role'=>'asisten');assistant_check(permitted($c));editable($c);assistant_check(true);
$other=$c;$other['assistant1_user']=$c['assistant2_user'];$other['assistant2_user']=$c['assistant1_user'];assistant_check(permitted($other));
$other['assistant1_user']=99999;$other['assistant2_user']=99998;assistant_check(!permitted($other));
$user=array('id'=>1,'role'=>'admin');$v=array_intersect_key($c,array_flip(master_fields()['jadwal_praktikum']));foreach($v as $k=>$value)if(substr($k,-3)==='_id'||$k==='active')$v[$k]=(int)$value;
$temp=$v['asisten1_id'];$v['asisten1_id']=$v['asisten2_id'];$v['asisten2_id']=$temp;validate_master('jadwal_praktikum',$v);assistant_check(true);
$v['asisten2_id']=$v['asisten1_id'];try{validate_master('jadwal_praktikum',$v);throw new Exception('Same account accepted');}catch(ApiError $e){assistant_check(true);}
$data=array('headers'=>array('nama','email','role','password','nim'),'rows'=>array(array('line'=>2,'values'=>array('nama'=>'Asisten Uji','email'=>'new-'.bin2hex(random_bytes(4)).'@example.com','role'=>'asisten','password'=>'Test-password-2026','nim'=>''))));assistant_check(!count(user_import_validate($data)['errors']));$data['rows'][0]['values']['role']='asisten1';assistant_check(count(user_import_validate($data)['errors'])===1);
echo "PASS $checks assistant checks on PHP ".PHP_VERSION."\n";
