<?php
if(PHP_SAPI!=='cli' || strpos(getenv('PRAKTIKUM_DB_NAME'),'test_')!==0)exit('Test database required');
require __DIR__.'/../app/bootstrap.php';require __DIR__.'/../app/classes.php';require __DIR__.'/../app/admin.php';require __DIR__.'/../app/frequency.php';require __DIR__.'/../app/exchange.php';
$user=array('id'=>1,'role'=>'admin');$checks=0;
function check_frequency($value){global $checks;if(!$value)throw new Exception('Failed check '.($checks+1));$checks++;}
$db->beginTransaction();
try {
    check_frequency(!isset(master_fields()['kelas']));
    check_frequency(count(rows(class_sql()))===3);
    $v=array('nim'=>'TEST-NIM-001','nama'=>'Mahasiswa Uji','kelas'=>'A1','frekuensi'=>'TI_ALPRO-TEST');
    frequency_member($v,1);$v['frekuensi']='TI_BASDAT-TEST';frequency_member($v,1);
    check_frequency((int)query("SELECT COUNT(*) FROM mahasiswa WHERE nim='TEST-NIM-001'")->fetchColumn()===1);
    check_frequency((int)query("SELECT COUNT(*) FROM anggota_frekuensi a JOIN mahasiswa m ON m.id=a.mahasiswa_id WHERE m.nim='TEST-NIM-001'")->fetchColumn()===2);
    try{frequency_member($v,1);throw new Exception('Duplicate accepted');}catch(ApiError $e){check_frequency(true);}
    $v['nama']='Wrong';$v['frekuensi']='TI_NEW-TEST';try{frequency_member($v,1);throw new Exception('Mismatch accepted');}catch(ApiError $e){check_frequency(true);}
    $c=row('SELECT * FROM jadwal_praktikum LIMIT 1');$c['frequency']='TI_ALPRO-TEST';$c['prodi_id']=1;
    $values=array_intersect_key($c,array_flip(master_fields()['jadwal_praktikum']));foreach($values as $k=>$value)if(substr($k,-3)==='_id'||$k==='active')$values[$k]=(int)$value;
    validate_master('jadwal_praktikum',$values);check_frequency(true);
    $headers=schedule_headers();check_frequency(!in_array('kelas',$headers)&&in_array('frekuensi',$headers));
    $v=array('tahun akademik'=>'2026/2027','semester'=>'Ganjil','prodi'=>'Teknik Informatika','kode mk'=>'130106RP4','laboratorium'=>'Lab Startup','email dosen'=>'dosen@fikom.umi.ac.id','email asisten 1'=>'asisten1@fikom.umi.ac.id','email asisten 2'=>'asisten2@fikom.umi.ac.id','hari'=>'Senin','jam'=>'07.00-10.20','aktif'=>'Aktif','frekuensi'=>'TI_ALPRO-TEST');
    $r=schedule_rows(array('headers'=>$headers,'rows'=>array(array('line'=>2,'values'=>$v))));check_frequency(count($r['errors'])===0);
} finally {$db->rollBack();}
// Synchronization has its own transaction; original data remains in this disposable database.
transaction(function(){frequency_member(array('nim'=>'TEST-SYNC','nama'=>'Uji Sync','kelas'=>'OTHER','frekuensi'=>'LEGACY-1'),1);});
$before=(int)query('SELECT COUNT(*) FROM peserta_praktikum WHERE jadwal_id=1')->fetchColumn();sync_students(1);
check_frequency((int)query('SELECT COUNT(*) FROM peserta_praktikum WHERE jadwal_id=1')->fetchColumn()===$before+1);
sync_students(1);check_frequency((int)query('SELECT COUNT(*) FROM peserta_praktikum WHERE jadwal_id=1')->fetchColumn()===$before+1);
echo 'PASS '.$checks." frequency checks on PHP ".PHP_VERSION."\n";
