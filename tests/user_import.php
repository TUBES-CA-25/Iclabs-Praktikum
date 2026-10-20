<?php
if(PHP_SAPI!=='cli'||strpos(getenv('PRAKTIKUM_DB_NAME'),'test_')!==0)exit('Test database required');
require __DIR__.'/../app/bootstrap.php';require __DIR__.'/../app/admin.php';require __DIR__.'/../app/user_import.php';
$user=array('id'=>1,'role'=>'admin');$checks=0;
function user_check($v){global $checks;if(!$v)throw new Exception('Failed '.($checks+1));$checks++;}
$s=row('SELECT * FROM mahasiswa WHERE id NOT IN(SELECT mahasiswa_id FROM akun_mahasiswa) LIMIT 1');
$prefix='import'.bin2hex(random_bytes(4));$password='Test-pass-2026!';
$data=array('headers'=>array('nama','email','role','password','nim'),'rows'=>array());
foreach(array('admin','dosen','asisten','asisten','mahasiswa') as $i=>$role)$data['rows'][]=array('line'=>$i+2,'values'=>array('nama'=>$role==='mahasiswa'?$s['name']:'Import Test','email'=>$prefix.$i.'@example.com','role'=>$role,'password'=>$password,'nim'=>$role==='mahasiswa'?$s['nim']:''));
$before=(int)query('SELECT COUNT(*) FROM users')->fetchColumn();$r=user_import_validate($data);user_check(count($r['valid'])===5&&!count($r['errors']));user_check((int)query('SELECT COUNT(*) FROM users')->fetchColumn()===$before);
$bad=$data;$bad['rows'][4]['values']['password']='short';try{user_import_commit($bad);throw new Exception('Invalid accepted');}catch(ApiError $e){user_check((int)query('SELECT COUNT(*) FROM users')->fetchColumn()===$before);}
$duplicate=$data;$duplicate['rows'][1]['values']['email']=$duplicate['rows'][0]['values']['email'];user_check(count(user_import_validate($duplicate)['errors'])===1);
$user['role']='dosen';try{user_import_commit($data);throw new Exception('Role bypass');}catch(ApiError $e){user_check($e->status===403);}$user['role']='admin';
user_check(user_import_commit($data)===5);
$u=row('SELECT * FROM users WHERE email=?',array($prefix.'1@example.com'));user_check(password_verify($password,$u['password']));user_check((bool)row('SELECT id FROM dosen WHERE user_id=?',array($u['id'])));
foreach(array(2,3) as $i){$u=row('SELECT id FROM users WHERE email=?',array($prefix.$i.'@example.com'));user_check((bool)row('SELECT id FROM asisten WHERE user_id=?',array($u['id'])));}
user_check((bool)row('SELECT user_id FROM akun_mahasiswa WHERE mahasiswa_id=?',array($s['id'])));user_check(count(user_import_validate($data)['errors'])===5);
// A late failure must roll back a user and its audit even after INSERT succeeded.
$email=$prefix.'rollback@example.com';try{transaction(function()use($email,$password){create_user(array('name'=>'Rollback','email'=>$email,'password'=>$password,'role'=>'admin'));reject('Late failure');});}catch(ApiError $e){}
user_check(!row('SELECT id FROM users WHERE email=?',array($email)));
user_check(!row('SELECT id FROM log_aktivitas WHERE new_value LIKE ?',array('%'.$password.'%')));
echo "PASS $checks user import checks on PHP ".PHP_VERSION."\n";
