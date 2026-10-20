<?php
function transfer_frequency($v){
 require_admin();
 foreach(array('member_id','source_id','target_id') as $k)if(!integer($v[$k]??null,1,PHP_INT_MAX))reject('Pilih keanggotaan dan jadwal asal/tujuan.');
 $reason=trim((string)($v['reason']??''));if(!$reason||mb_strlen($reason)>1000)reject('Alasan wajib diisi, maksimal 1000 karakter.');
 transaction(function()use($v,$reason){
  $ids=array($v['source_id'],$v['target_id']);sort($ids);foreach($ids as $id)load_class($id,true);
  $a=load_class($v['source_id']);$b=load_class($v['target_id']);editable($a);editable($b);
  if($a['frequency']===$b['frequency']||$a['mk_id']!==$b['mk_id']||$a['tahun_id']!==$b['tahun_id']||$a['semester_id']!==$b['semester_id']||$a['prodi_id']!==$b['prodi_id'])reject('Tujuan harus frekuensi berbeda pada mata kuliah, tahun, semester, dan prodi yang sama.');
  $member=row('SELECT a.*,f.name frequency FROM anggota_frekuensi a JOIN frekuensi_praktikum f ON f.id=a.frekuensi_id WHERE a.id=? FOR UPDATE',array($v['member_id']));
  if(!$member||$member['frequency']!==$a['frequency'])reject('Keanggotaan asal sudah berubah. Muat ulang data.');
  $target=row('SELECT id FROM frekuensi_praktikum WHERE name=?',array($b['frequency']));
  if(row('SELECT id FROM anggota_frekuensi WHERE mahasiswa_id=? AND frekuensi_id=?',array($member['mahasiswa_id'],$target['id'])))reject('Mahasiswa sudah menjadi anggota frekuensi tujuan.');
  if(row('SELECT id FROM peserta_praktikum WHERE mahasiswa_id=? AND jadwal_id=?',array($member['mahasiswa_id'],$b['id'])))reject('Mahasiswa sudah memiliki peserta/riwayat pada jadwal tujuan. Pemindahan tidak dapat menggabungkan riwayat.');
  query('UPDATE anggota_frekuensi SET frekuensi_id=? WHERE id=?',array($target['id'],$member['id']));
  query('UPDATE peserta_praktikum SET active=0 WHERE mahasiswa_id=? AND jadwal_id=?',array($member['mahasiswa_id'],$a['id']));
  query('INSERT INTO peserta_praktikum(jadwal_id,mahasiswa_id) VALUES(?,?)',array($b['id'],$member['mahasiswa_id']));
  foreach(array($a['id'],$b['id']) as $id){query('UPDATE jadwal_praktikum SET version=version+1 WHERE id=?',array($id));audit($id,'Pindah frekuensi mahasiswa',$member,array('mahasiswa_id'=>$member['mahasiswa_id'],'source_id'=>$a['id'],'target_id'=>$b['id'],'frekuensi'=>$b['frequency'],'alasan'=>$reason));}
 });
}
