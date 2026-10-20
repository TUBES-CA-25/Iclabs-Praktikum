<?php
function frequency_member($v, $prodi) {
    global $db;
    foreach (array('nim','nama','kelas','frekuensi') as $k) {
        if (!isset($v[$k]) || !is_scalar($v[$k]) || !trim((string)$v[$k]) || mb_strlen((string)$v[$k])>100) reject('NIM, nama, kelas dan frekuensi wajib diisi, maksimal 100 karakter.');
        $v[$k]=trim((string)$v[$k]);
    }
    if (!row('SELECT id FROM prodi WHERE id=?',array($prodi))) reject('Pilih program studi.');
    query('INSERT IGNORE INTO frekuensi_praktikum(name,prodi_id) VALUES(?,?)',array($v['frekuensi'],$prodi));
    $f=row('SELECT * FROM frekuensi_praktikum WHERE name=? FOR UPDATE',array($v['frekuensi']));
    if ((int)$f['prodi_id']!==(int)$prodi) reject('Program studi frekuensi tidak sesuai.');
    $m=row('SELECT * FROM mahasiswa WHERE nim=? FOR UPDATE',array($v['nim']));
    if ($m && ($m['name']!==$v['nama'] || (int)$m['prodi_id']!==(int)$prodi)) reject('Nama/prodi NIM '.$v['nim'].' berbeda dari data mahasiswa. Perbaiki data terlebih dahulu.');
    if (!$m) {
        query('INSERT INTO mahasiswa(nim,name,prodi_id,kelas_text) VALUES(?,?,?,?)',array($v['nim'],$v['nama'],$prodi,$v['kelas']));
        $m=array('id'=>(int)$db->lastInsertId());
    }
    if(row('SELECT id FROM anggota_frekuensi WHERE frekuensi_id=? AND mahasiswa_id=?',array($f['id'],$m['id']))) reject('NIM '.$v['nim'].' sudah terdaftar pada '.$v['frekuensi'].'.');
    query('INSERT INTO anggota_frekuensi(frekuensi_id,mahasiswa_id,kelas) VALUES(?,?,?)',array($f['id'],$m['id'],$v['kelas']));
    audit(null,'Tambah anggota frekuensi',null,$v);
}
function frequency_route($part) {
    if ($part==='frequency-template') {
        xlsx_download(array(array('nim','nama','kelas','frekuensi'),array('1302026001','Nama Mahasiswa','A1','TI_ALPRO-1')),'template-frekuensi.xlsx',1);exit;
    }
    if (!in_array($part,array('frequency-member','frequency-import'),true)) return;
    require_post();$b=input();
    $data=$part==='frequency-import'?read_xlsx($b['base64']??''):array('headers'=>array('nim','nama','kelas','frekuensi'),'rows'=>array($b));
    if(array_diff(array('nim','nama','kelas','frekuensi'),$data['headers']))reject('Header wajib: nim, nama, kelas, frekuensi.');
    if(!count($data['rows']))reject('File kosong.');
    transaction(function()use($data,$b){foreach($data['rows'] as $v)frequency_member($v['values']??$v,(int)($b['prodi_id']??0));});
    send_json(array('count'=>count($data['rows'])));
}
