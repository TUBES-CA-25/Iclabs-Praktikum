<?php
if(PHP_SAPI!=='cli'||strpos(getenv('PRAKTIKUM_DB_NAME'),'test_')!==0)exit('Test database required');
require __DIR__.'/../app/bootstrap.php';require __DIR__.'/../app/classes.php';require __DIR__.'/../app/admin.php';require __DIR__.'/../app/transfer.php';
$user=array('id'=>1,'role'=>'admin');$checks=0;function transfer_check($x){global $checks;if(!$x)throw new Exception('Check '.($checks+1).' failed');$checks++;}
$a=load_class(1);$member=row('SELECT a.* FROM anggota_frekuensi a JOIN frekuensi_praktikum f ON f.id=a.frekuensi_id WHERE f.name=? LIMIT 1',array($a['frequency']));
query("INSERT INTO frekuensi_praktikum(name,prodi_id) VALUES('TRANSFER-TEST',?)",array($a['prodi_id']));
$fields=master_fields()['jadwal_praktikum'];$values=array();foreach($fields as $k)$values[]=$k==='frequency'?'TRANSFER-TEST':$a[$k];query('INSERT INTO jadwal_praktikum('.implode(',',$fields).') VALUES('.implode(',',array_fill(0,count($fields),'?')).')',$values);$target=(int)$db->lastInsertId();
$old=row('SELECT id FROM peserta_praktikum WHERE jadwal_id=1 AND mahasiswa_id=?',array($member['mahasiswa_id']));query("INSERT INTO nilai_ujian(peserta_id,kind,value,updated_by,updated_at) VALUES(?,'MID',88,1,?)",array($old['id'],stamp()));
$v=array('member_id'=>(int)$member['id'],'source_id'=>1,'target_id'=>$target,'reason'=>'Pindah kelompok');
$user['role']='asisten';try{transfer_frequency($v);throw new Exception('Asisten allowed');}catch(ApiError $e){transfer_check($e->status===403);}$user['role']='admin';
query("UPDATE jadwal_praktikum SET state='Dikunci' WHERE id=?",array($target));try{transfer_frequency($v);throw new Exception('Locked allowed');}catch(ApiError $e){transfer_check(true);}query("UPDATE jadwal_praktikum SET state='Draft' WHERE id=?",array($target));
transfer_frequency($v);
transfer_check((int)row('SELECT active FROM peserta_praktikum WHERE id=?',array($old['id']))['active']===0);
transfer_check((float)row('SELECT value FROM nilai_ujian WHERE peserta_id=?',array($old['id']))['value']===88.0);
transfer_check(count(detail(load_class(1))['students'])===11);transfer_check(count(detail(load_class($target))['students'])===1);
transfer_check(!count(detail(load_class($target))['students'][0]['evaluations']));transfer_check(detail(load_class($target))['students'][0]['mid']===null);
sync_students(1);transfer_check(count(detail(load_class(1))['students'])===11);
sync_students($target);transfer_check(count(detail(load_class($target))['students'])===1);
try{transfer_frequency($v);throw new Exception('Stale allowed');}catch(ApiError $e){transfer_check(true);}
// Different-length stambuk must sort numerically rather than by name or text.
query("UPDATE mahasiswa SET nim='10',name='AAA' WHERE id=(SELECT mahasiswa_id FROM peserta_praktikum WHERE jadwal_id=1 AND active=1 ORDER BY id LIMIT 1)");
query("UPDATE mahasiswa SET nim='2',name='ZZZ' WHERE id=(SELECT mahasiswa_id FROM peserta_praktikum WHERE jadwal_id=1 AND active=1 ORDER BY id DESC LIMIT 1)");
$list=detail(load_class(1))['students'];transfer_check($list[0]['nim']==='2'&&$list[1]['nim']==='10');
echo "PASS $checks transfer/order checks on PHP ".PHP_VERSION."\n";
