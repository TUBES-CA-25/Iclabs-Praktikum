<?php
function active_period() {
    return row('SELECT a.*,t.name academic_year,s.name semester FROM periode_aktif a LEFT JOIN tahun_akademik t ON t.id=a.tahun_id LEFT JOIN semester s ON s.id=a.semester_id WHERE a.id=1');
}
function save_period($v) {
    require_admin();
    foreach(array('tahun_id','semester_id') as $k) if(!integer($v[$k]??null,1,PHP_INT_MAX))reject('Pilih tahun akademik dan semester.');
    foreach(array('start_date','end_date') as $k){
        $value=$v[$k]??null;
        if(!is_string($value))reject('Tanggal tidak valid.');
        $d=DateTime::createFromFormat('!Y-m-d',$value);
        if(!$d || $d->format('Y-m-d')!==$value || $value<'1000-01-01')reject('Tanggal tidak valid.');
    }
    if($v['end_date']<$v['start_date'])reject('Tanggal selesai tidak boleh sebelum tanggal mulai.');
    if(!row('SELECT id FROM tahun_akademik WHERE id=?',array($v['tahun_id'])) || !row('SELECT id FROM semester WHERE id=?',array($v['semester_id'])))reject('Tahun akademik atau semester tidak ditemukan.');
    transaction(function()use($v){
        $old=row('SELECT * FROM periode_aktif WHERE id=1 FOR UPDATE');
        if(!isset($v['version']) || (int)$v['version']!==(int)$old['version'])reject('Periode telah diubah. Muat ulang sebelum menyimpan.',409);
        query('UPDATE periode_aktif SET tahun_id=?,semester_id=?,start_date=?,end_date=?,version=version+1 WHERE id=1',array($v['tahun_id'],$v['semester_id'],$v['start_date'],$v['end_date']));
        audit(null,'Ubah periode semester aktif',$old,active_period());
    });
}
