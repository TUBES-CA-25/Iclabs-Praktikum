<?php
if(PHP_SAPI!=='cli'||strpos(getenv('PRAKTIKUM_DB_NAME'),'test_')!==0)exit('Test database required');
require __DIR__.'/../app/bootstrap.php';require __DIR__.'/../app/period.php';
foreach(explode(';',file_get_contents(__DIR__.'/../database/update-periode-aktif.sql')) as $sql)if(trim($sql))query($sql);
$user=array('id'=>1,'role'=>'admin');$checks=0;
function period_check($v){global $checks;if(!$v)throw new Exception('Check failed');$checks++;}
$old=active_period();
$v=array('tahun_id'=>1,'semester_id'=>1,'start_date'=>'2026-09-01','end_date'=>'2026-12-31','version'=>(int)$old['version']);
save_period($v);$p=active_period();period_check($p['semester']==='Ganjil'&&$p['academic_year']==='2026/2027');period_check($p['start_date']==='2026-09-01'&&$p['end_date']==='2026-12-31');
try{save_period($v);throw new Exception('Stale accepted');}catch(ApiError $e){period_check($e->status===409);}
$v['version']=$p['version'];$v['end_date']='2026-08-01';try{save_period($v);throw new Exception('Invalid range accepted');}catch(ApiError $e){period_check(true);}
$v['end_date']='2026-02-30';try{save_period($v);throw new Exception('Invalid date accepted');}catch(ApiError $e){period_check(true);}
$v['end_date']='2026-12-31';$user['role']='mahasiswa';try{save_period($v);throw new Exception('Student write accepted');}catch(ApiError $e){period_check($e->status===403);}
$user['role']='admin';$v['semester_id']=2;save_period($v);period_check(active_period()['semester']==='Genap');period_check((int)query('SELECT COUNT(*) FROM periode_aktif')->fetchColumn()===1);
echo "PASS $checks period checks on PHP ".PHP_VERSION."\n";
