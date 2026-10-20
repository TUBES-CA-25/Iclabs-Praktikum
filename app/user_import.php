<?php
function user_import_validate($data) {
    $headers=array('nama','email','role','password','nim');
    if(array_diff($headers,$data['headers']))reject('Header wajib: nama, email, role, password, nim.');
    if(!count($data['rows'])||count($data['rows'])>100)reject('Import akun harus berisi 1–100 baris.');
    $valid=array();$errors=array();$emails=array();$students=array();
    foreach($data['rows'] as $r){try{
        $v=$r['values'];$b=array('name'=>trim((string)($v['nama']??'')),'email'=>strtolower(trim((string)($v['email']??''))),'role'=>strtolower(trim((string)($v['role']??''))),'password'=>(string)($v['password']??''));
        $b['role']=str_replace(' ','',$b['role']);
        if(!in_array($b['role'],array('admin','dosen','asisten','mahasiswa'),true))reject('Role tidak valid.');
        if(!filter_var($b['email'],FILTER_VALIDATE_EMAIL)||strlen($b['email'])>191)reject('Email tidak valid.');
        if(strlen($b['password'])<12||strlen($b['password'])>72)reject('Password harus 12–72 byte.');
        if(!$b['name']||mb_strlen($b['name'])>191)reject('Nama wajib diisi, maksimal 191 karakter.');
        if(isset($emails[$b['email']])||row('SELECT id FROM users WHERE email=?',array($b['email'])))reject('Email sudah terdaftar atau duplikat dalam file.');
        $nim=trim((string)($v['nim']??''));
        if($b['role']==='mahasiswa'){
            $m=row('SELECT id,name FROM mahasiswa WHERE nim=?',array($nim));
            if(!$nim||!$m)reject('NIM mahasiswa belum terdaftar.');
            if($m['name']!==$b['name'])reject('Nama tidak sesuai dengan NIM pada master mahasiswa.');
            if(isset($students[$m['id']])||row('SELECT user_id FROM akun_mahasiswa WHERE mahasiswa_id=?',array($m['id'])))reject('NIM sudah memiliki akun atau duplikat dalam file.');
            $students[$m['id']]=true;$b['mahasiswa_id']=(int)$m['id'];
        }elseif($nim!=='')reject('NIM hanya diisi untuk role mahasiswa.');
        $emails[$b['email']]=true;$valid[]=array('line'=>$r['line'],'account'=>$b,'nim'=>$nim);
    }catch(ApiError $e){$errors[]=array('line'=>$r['line'],'error'=>$e->getMessage());}}
    return array('valid'=>$valid,'errors'=>$errors);
}
function user_import_commit($data){
    require_admin();
    return transaction(function()use($data){
        $result=user_import_validate($data);
        if(count($result['errors']))reject('Import dibatalkan. Baris '.$result['errors'][0]['line'].': '.$result['errors'][0]['error']);
        foreach($result['valid'] as $r)create_user($r['account']);
        return count($result['valid']);
    });
}
function user_import_route($part){
    if($part==='users-template'&&$_SERVER['REQUEST_METHOD']==='GET'){
        xlsx_download(array(array('nama','email','role','password','nim'),array('Nama Dosen','dosen.baru@example.com','dosen','GantiPasswordIni123!','')),'template-akun-pengguna.xlsx',1);exit;
    }
    if($part!=='import-users')return;
    require_admin();require_post();$b=input();$data=read_xlsx($b['base64']??'');
    if(!empty($b['preview'])){
        $result=user_import_validate($data);$out=array();
        foreach($result['valid'] as $r)$out[]=array('line'=>$r['line'],'nama'=>$r['account']['name'],'email'=>$r['account']['email'],'role'=>$r['account']['role'],'nim'=>$r['nim']);
        send_json(array('rows'=>$out,'errors'=>$result['errors']));
    }
    send_json(array('count'=>user_import_commit($data)));
}
