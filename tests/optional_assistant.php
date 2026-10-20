<?php
if(PHP_SAPI!=='cli'||strpos(getenv('PRAKTIKUM_DB_NAME'),'test_')!==0)exit('Test DB required');
require __DIR__.'/../app/bootstrap.php';require __DIR__.'/../app/admin.php';
$db->exec(file_get_contents(__DIR__.'/../database/update-asisten2-opsional.sql'));
$user=array('id'=>1,'role'=>'admin');$checks=0;function optional_check($v){global $checks;if(!$v)throw new Exception('Check '.($checks+1).' failed');$checks++;}
$db->beginTransaction();try{
 $c=rows(class_sql())[0];query('UPDATE jadwal_praktikum SET asisten2_id=NULL WHERE id=?',array($c['id']));$c=load_class($c['id']);optional_check($c['asisten2_id']===null);optional_check($c['assistant2']==='—');
 $v=array_intersect_key($c,array_flip(master_fields()['jadwal_praktikum']));foreach($v as $k=>$x)if($x!==null&&(substr($k,-3)==='_id'||$k==='active'))$v[$k]=(int)$x;validate_master('jadwal_praktikum',$v);optional_check(true);
 $user=array('id'=>(int)$c['assistant1_user'],'role'=>'asisten');optional_check(permitted($c));$user['id']=999999;optional_check(!permitted($c));
 $v['asisten2_id']=$v['asisten1_id'];try{validate_master('jadwal_praktikum',$v);throw new Exception('Same accepted');}catch(ApiError $e){optional_check(true);}
 query("INSERT INTO frekuensi_praktikum(name,prodi_id) VALUES('OPTIONAL_TEST',?)",array($c['prodi_id']));
 $r=array('tahun akademik'=>$c['year'],'semester'=>$c['semester'],'prodi'=>$c['prodi'],'kode mk'=>$c['code'],'laboratorium'=>$c['lab'],'email dosen'=>row('SELECT email FROM users WHERE id=?',array($c['lecturer_user']))['email'],'email asisten 1'=>row('SELECT email FROM users WHERE id=?',array($c['assistant1_user']))['email'],'email asisten 2'=>'','hari'=>$c['day'],'jam'=>$c['time'],'aktif'=>'Aktif','frekuensi'=>'OPTIONAL_TEST');
 $data=array('headers'=>array_keys($r),'rows'=>array(array('line'=>2,'values'=>$r)));$out=schedule_rows($data);optional_check(!count($out['errors'])&&$out['rows'][0]['values']['asisten2_id']===null);
 unset($r['email asisten 2']);$data=array('headers'=>array_keys($r),'rows'=>array(array('line'=>2,'values'=>$r)));optional_check(!count(schedule_rows($data)['errors']));
 $data['rows'][0]['values']['email asisten 2']='bad@example.com';optional_check(count(schedule_rows($data)['errors'])===1);
 $data['rows'][0]['values']['email asisten 1']='';optional_check(count(schedule_rows($data)['errors'])===1);
}finally{$db->rollBack();}
echo "PASS $checks optional assistant checks on PHP ".PHP_VERSION."\n";
