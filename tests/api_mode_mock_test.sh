#!/usr/bin/env bash
set -euo pipefail

repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
test_dir="$(mktemp -d)"
mock_port="${ICLABS_MOCK_TEST_PORT:-18765}"
app_port="${ICLABS_APP_TEST_PORT:-18766}"
mkdir -m 700 "$test_dir/sessions"

cleanup() {
  kill "${app_pid:-}" "${mock_pid:-}" 2>/dev/null || true
  rm -rf -- "$test_dir"
}
trap cleanup EXIT

php -S "127.0.0.1:$mock_port" "$repo_dir/tests/fixtures/iclabs_api_mock_router.php" >"$test_dir/mock.log" 2>&1 &
mock_pid=$!
ICLABS_PRAKTIKUM_API_MODE=true \
ICLABS_API_BASE_URL="http://127.0.0.1:$mock_port/api/v1" \
ICLABS_API_TLS_VERIFY=false \
ICLABS_PRAKTIKUM_SECURE_COOKIE=false \
ICLABS_PRAKTIKUM_SESSION_PATH="$test_dir/sessions" \
PRAKTIKUM_DB_NAME=database_yang_tidak_ada \
php -S "127.0.0.1:$app_port" -t "$repo_dir" >"$test_dir/app.log" 2>&1 &
app_pid=$!

ready=false
for _ in $(seq 1 40); do
  if curl -sS "http://127.0.0.1:$app_port/api.php?route=%2Fme" >/dev/null 2>&1; then
    ready=true
    break
  fi
  sleep 0.1
done
if [[ "$ready" != true ]]; then
  echo "FAIL: server mock tidak siap"
  exit 1
fi

setup_status="$(curl -sS -o "$test_dir/setup.txt" -w '%{http_code}' "http://127.0.0.1:$app_port/setup.php")"
[[ "$setup_status" == 404 ]] || { echo "FAIL: installer database lokal harus ditutup pada mode API (HTTP $setup_status)"; exit 1; }
grep -q '^Not found\.$' "$test_dir/setup.txt" || { echo "FAIL: respons installer mode API tidak sesuai"; exit 1; }

cookie_jar="$test_dir/cookies.txt"
prelogin_csrf_response="$(curl -sS -c "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fauth%2Fcsrf")"
prelogin_csrf="$(printf '%s' "$prelogin_csrf_response" | php -r '$data=json_decode(stream_get_contents(STDIN),true); echo $data["csrf"] ?? "";')"
[[ -n "$prelogin_csrf" ]] || { echo "FAIL: token CSRF sebelum login tidak tersedia"; exit 1; }
activation_csrf_status="$(curl -sS -o "$test_dir/activation-csrf.json" -w '%{http_code}' -b "$cookie_jar" -H 'Content-Type: application/json' -d '{"email":"uji@student.umi.ac.id"}' "http://127.0.0.1:$app_port/api.php?route=%2Fauth%2Factivation-request")"
[[ "$activation_csrf_status" == 403 ]] || { echo "FAIL: permintaan aktivasi tanpa CSRF harus ditolak"; exit 1; }
invalid_activation_status="$(curl -sS -o "$test_dir/activation-invalid.json" -w '%{http_code}' -b "$cookie_jar" -H "X-CSRF-Token: $prelogin_csrf" -H 'Content-Type: application/json' -d '{"email":"bukan-email"}' "http://127.0.0.1:$app_port/api.php?route=%2Fauth%2Factivation-request")"
[[ "$invalid_activation_status" == 422 ]] || { echo "FAIL: validasi email aktivasi"; exit 1; }
activation_request_status="$(curl -sS -o "$test_dir/activation-request.json" -w '%{http_code}' -b "$cookie_jar" -H "X-CSRF-Token: $prelogin_csrf" -H 'Content-Type: application/json' -d '{"email":"uji@student.umi.ac.id"}' "http://127.0.0.1:$app_port/api.php?route=%2Fauth%2Factivation-request")"
[[ "$activation_request_status" == 202 ]] || { echo "FAIL: permintaan email aktivasi"; exit 1; }
grep -q 'Jika email terdaftar dan akun memenuhi syarat' "$test_dir/activation-request.json" || { echo "FAIL: pesan generik aktivasi"; exit 1; }
reset_request_status="$(curl -sS -o "$test_dir/reset-request.json" -w '%{http_code}' -b "$cookie_jar" -H "X-CSRF-Token: $prelogin_csrf" -H 'Content-Type: application/json' -d '{"email":"uji@student.umi.ac.id"}' "http://127.0.0.1:$app_port/api.php?route=%2Fauth%2Fpassword-reset-request")"
[[ "$reset_request_status" == 202 ]] || { echo "FAIL: permintaan reset kata sandi"; exit 1; }
rate_limit_status="$(curl -sS -o "$test_dir/rate-limit.json" -w '%{http_code}' -b "$cookie_jar" -H "X-CSRF-Token: $prelogin_csrf" -H 'Content-Type: application/json' -d '{"email":"rate-limit@iclabs.test"}' "http://127.0.0.1:$app_port/api.php?route=%2Fauth%2Fpassword-reset-request")"
[[ "$rate_limit_status" == 429 ]] || { echo "FAIL: penanganan rate limit permintaan akun"; exit 1; }
login_response="$(curl -sS -c "$cookie_jar" -H 'Content-Type: application/json' -d '{"email":"uji-admin@iclabs.test","password":"uji"}' "http://127.0.0.1:$app_port/api.php?route=%2Flogin")"
[[ "$login_response" == '{"ok":true}' ]] || { echo "FAIL: login bridge: $login_response"; exit 1; }

me_response="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fme")"
grep -q '"api_mode":true' <<<"$me_response" || { echo "FAIL: /me tidak memvalidasi session pusat"; exit 1; }
csrf="$(printf '%s' "$me_response" | php -r '$data=json_decode(stream_get_contents(STDIN),true); echo $data["csrf"] ?? "";')"
[[ -n "$csrf" ]] || { echo "FAIL: token CSRF tidak tersedia"; exit 1; }

period_response="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Factive-period")"
grep -q '"academic_year":"2026' <<<"$period_response" || { echo "FAIL: pemetaan periode"; exit 1; }
dashboard_response="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fdashboard")"
grep -q '"jumlah_kelas":2' <<<"$dashboard_response" || { echo "FAIL: ringkasan dashboard"; exit 1; }
audit_response="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faudit")"
grep -q '"trx_kelas_kuliah:10 · UPDATE"' <<<"$audit_response" || { echo "FAIL: audit aktivitas Praktikum"; exit 1; }
periods_response="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fperiods")"
grep -q '"id_periode_akademik":2' <<<"$periods_response" || { echo "FAIL: daftar periode akademik admin"; exit 1; }
grep -q '"kode":"GENAP"' <<<"$periods_response" || { echo "FAIL: referensi semester untuk periode"; exit 1; }
period_create="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"tahun_akademik":"2027/2028","id_semester":1,"tanggal_mulai":"2027-09-01","tanggal_selesai":"2027-12-31"}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fperiods")"
grep -q '"id_periode_akademik":3' <<<"$period_create" || { echo "FAIL: pembuatan periode akademik"; exit 1; }
period_update="$(curl -sS -b "$cookie_jar" -X PATCH -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"tanggal_mulai":"2027-02-02","tanggal_selesai":"2027-06-30"}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fperiods%2F2")"
grep -q '"tanggal_mulai":"2027-02-02"' <<<"$period_update" || { echo "FAIL: perubahan rentang periode"; exit 1; }
period_activate="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fperiods%2F2%2Factivate")"
grep -q '"id_periode_akademik":2' <<<"$period_activate" || { echo "FAIL: aktivasi periode akademik"; exit 1; }
sheet_sync="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"url":"https://docs.google.com/spreadsheets/d/public-sheet/edit?gid=290740537","id_periode_akademik":1,"jadwal":[{"baris_sumber":4,"prodi":"TI","hari":"Senin","jam":"08.00-10.20","kode_mk":"130107RP4","mata_kuliah":"Algoritma dan Pemrograman","dosen":"Dosen Utama","frekuensi":"TI_ALPRO-1","laboratorium":"Laboratorium IoT","asisten_1":"Asisten Satu","asisten_2":"Asisten Dua","asisten_3":"Pendamping Tiga","id_user_dosen":300,"id_user_asisten_utama":400,"id_user_asisten_pengajar_pendukung":401,"id_user_pendamping_teknis":402,"peserta":"17+5","sks":"3"}]}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fsync-google-sheet")"
grep -q '"jumlah_baris":1' <<<"$sheet_sync" || { echo "FAIL: proxy sinkronisasi Google Sheets: $sheet_sync"; exit 1; }
grep -q '"roster_diubah":false' <<<"$sheet_sync" || { echo "FAIL: sinkronisasi Sheet tidak boleh mengubah roster"; exit 1; }
grep -q '"assistant_slots_received":\["Asisten Satu","Asisten Dua","Pendamping Tiga"\]' <<<"$sheet_sync" || { echo "FAIL: semua tiga slot asisten harus diteruskan ke API: $sheet_sync"; exit 1; }
grep -q '"staff_user_ids_received":\[300,400,401,402\]' <<<"$sheet_sync" || { echo "FAIL: pemetaan personel canonical harus diteruskan ke API pusat: $sheet_sync"; exit 1; }
sheet_conflict="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"url":"https://docs.google.com/spreadsheets/d/public-sheet/edit?gid=290740537","id_periode_akademik":1,"jadwal":[{"baris_sumber":12,"prodi":"TI","hari":"Senin","jam":"08.00-10.20","kode_mk":"130107RP4","mata_kuliah":"Algoritma dan Pemrograman","dosen":"Dosen Utama","frekuensi":"TI_ALPRO-1","laboratorium":"Laboratorium IoT","asisten_1":"Asisten Satu","asisten_2":"__SIMULATE_CONFLICT__","asisten_3":"","id_user_dosen":300,"id_user_asisten_utama":400,"id_user_asisten_pengajar_pendukung":400,"id_user_pendamping_teknis":null,"peserta":"17","sks":"3"}]}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fsync-google-sheet")"
grep -q 'Baris 12: Satu akun tidak boleh mengisi lebih dari satu peran' <<<"$sheet_conflict" || { echo "FAIL: detail konflik API harus menampilkan nomor baris dan penyebab: $sheet_conflict"; exit 1; }
ai_match_response="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"jadwal":[{"baris_sumber":4,"prodi":"TI","kode_mk":"130107RP4","mata_kuliah":"Algoritma dan Pemrograman","dosen":"Dosen Uji","laboratorium":"Lab Uji","asisten_1":"Asisten Uji","asisten_2":"","asisten_3":"Pendampng Uji","frekuensi":"TI_ALPRO-1","peserta":"17+5","sks":"3"}]}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fmatch-google-sheet-ai")"
grep -q '"kandidat_id":402' <<<"$ai_match_response" || { echo "FAIL: proxy rekomendasi AI Google Sheets: $ai_match_response"; exit 1; }
preview_invalid_status="$(curl -sS -o "$test_dir/sheet-preview-invalid.json" -w '%{http_code}' -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"url":"http://docs.google.com/spreadsheets/d/public-sheet/edit?gid=290740537"}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fpreview-google-sheet")"
[[ "$preview_invalid_status" == 422 ]] || { echo "FAIL: pratinjau menolak URL Sheet yang tidak aman; HTTP $preview_invalid_status"; exit 1; }
assistant_availability="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"versi":1,"id_laboratorium":2,"hari_ke":2,"jam_mulai":"09:00","jam_selesai":"11:00","tanggal_mulai":"2026-09-07","tanggal_selesai":"2026-12-12","pola_perulangan":"MINGGUAN","status":"AKTIF","id_user_dosen":300,"id_user_asisten_utama":400,"id_user_asisten_pengajar_pendukung":401,"id_user_pendamping_teknis":402}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fschedule-assistant-availability")"
grep -q '"kode_ketersediaan":"BENTROK_JADWAL_KULIAH"' <<<"$assistant_availability" || { echo "FAIL: rekomendasi bentrok jadwal Asisten"; exit 1; }
grep -q '"dapat_dipilih":true' <<<"$assistant_availability" || { echo "FAIL: bentrok jadwal kuliah harus tetap dapat dipilih"; exit 1; }
grep -q '"nama_mata_kuliah":"Algoritma dan Pemrograman"' <<<"$assistant_availability" || { echo "FAIL: detail mata kuliah yang bentrok"; exit 1; }
grep -q '"id_user":401,"kode_peran":"ASISTEN_PENGAJAR_PENDUKUNG"' <<<"$assistant_availability" || { echo "FAIL: rekomendasi Asisten 2 opsional"; exit 1; }
grep -q '"id_user":402,"kode_peran":"PENDAMPING_TEKNIS"' <<<"$assistant_availability" || { echo "FAIL: rekomendasi Pendamping opsional"; exit 1; }
template_status="$(curl -sS -o "$test_dir/schedule-template.xlsx" -w '%{http_code}' -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fschedule-template")"
[[ "$template_status" == 200 ]] || { echo "FAIL: template import jadwal"; exit 1; }
template_base64="$(base64 -w 0 "$test_dir/schedule-template.xlsx")"
schedule_preview="$(curl -sS -b "$cookie_jar" -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d "{\"base64\":\"$template_base64\",\"preview\":true}" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fimport-schedule")"
grep -q '"errors":\[\]' <<<"$schedule_preview" || { echo "FAIL: pratinjau import jadwal API-only: $schedule_preview"; exit 1; }
grep -q '"id_kelas_kuliah":12' <<<"$schedule_preview" || { echo "FAIL: kelas canonical tidak dipetakan saat import jadwal"; exit 1; }
schedule_import="$(curl -sS -b "$cookie_jar" -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d "{\"base64\":\"$template_base64\"}" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fimport-schedule")"
grep -q '"count":1' <<<"$schedule_import" || { echo "FAIL: commit import jadwal API-only: $schedule_import"; exit 1; }
bands_response="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fgrade-bands")"
grep -q '"kode":"A"' <<<"$bands_response" || { echo "FAIL: ambang nilai admin"; exit 1; }
bands_update="$(curl -sS -b "$cookie_jar" -X PUT -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"versi":1,"rentang":[{"kode":"A","nilai_minimum":86},{"kode":"A-","nilai_minimum":80},{"kode":"B+","nilai_minimum":75},{"kode":"B","nilai_minimum":70},{"kode":"B-","nilai_minimum":65},{"kode":"C+","nilai_minimum":60},{"kode":"C","nilai_minimum":55},{"kode":"D","nilai_minimum":40},{"kode":"E","nilai_minimum":0}]}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fgrade-bands")"
grep -q '"versi":2' <<<"$bands_update" || { echo "FAIL: penyimpanan ambang nilai"; exit 1; }
references_response="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Freferences")"
grep -q '"kode":"LAB-UJI"' <<<"$references_response" || { echo "FAIL: referensi form jadwal"; exit 1; }
grep -q '"kode":"TI"' <<<"$references_response" || { echo "FAIL: referensi form kelas"; exit 1; }
classes_response="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fclasses")"
grep -q '"id_kelas_kuliah":10' <<<"$classes_response" || { echo "FAIL: daftar kelas"; exit 1; }
grep -q '"peran_di_kelas":"ADMIN"' <<<"$classes_response" || { echo "FAIL: peran_di_kelas admin pada daftar kelas: $classes_response"; exit 1; }
detail_response="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10")"
grep -q '"kode":"TI_ALPRO-1"' <<<"$detail_response" || { echo "FAIL: detail kelas"; exit 1; }
grep -q '"peran_di_kelas":"ADMIN"' <<<"$detail_response" || { echo "FAIL: peran_di_kelas admin pada detail kelas: $detail_response"; exit 1; }
xlsx_status="$(curl -sS -o "$test_dir/report.xlsx" -w '%{http_code}' -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fexport&format=xlsx&schedule_id=99")"
[[ "$xlsx_status" == 200 ]] || { echo "FAIL: ekspor Excel status $xlsx_status"; exit 1; }
php -r '$f=fopen($argv[1],"rb"); exit(fread($f,2)==="PK"?0:1);' "$test_dir/report.xlsx" || { echo "FAIL: berkas Excel tidak valid"; exit 1; }
php -r '$z=new ZipArchive(); exit($z->open($argv[1])===true && $z->locateName("xl/worksheets/sheet1.xml")!==false && $z->locateName("[Content_Types].xml")!==false?0:1);' "$test_dir/report.xlsx" || { echo "FAIL: struktur XLSX tidak valid"; exit 1; }
period_export_status="$(curl -sS -o "$test_dir/period_recap.xlsx" -w '%{http_code}' -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fperiods%2F1%2Fexport")"
[[ "$period_export_status" == 200 ]] || { echo "FAIL: ekspor rekap periode Excel status $period_export_status"; exit 1; }
php -r '$z=new ZipArchive(); exit($z->open($argv[1])===true && $z->locateName("xl/worksheets/sheet1.xml")!==false && $z->locateName("xl/workbook.xml")!==false?0:1);' "$test_dir/period_recap.xlsx" || { echo "FAIL: struktur XLSX rekap periode tidak valid"; exit 1; }
pdf_status="$(curl -sS -o "$test_dir/report.pdf" -w '%{http_code}' -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fexport&format=pdf&schedule_id=99")"
[[ "$pdf_status" == 200 ]] || { echo "FAIL: ekspor PDF status $pdf_status"; exit 1; }
php -r '$f=fopen($argv[1],"rb"); exit(fread($f,5)==="%PDF-"?0:1);' "$test_dir/report.pdf" || { echo "FAIL: berkas PDF tidak valid"; exit 1; }
php -r '$b=file_get_contents($argv[1]); exit(strpos($b,"(50.0) Tj")!==false?0:1);' "$test_dir/report.pdf" || { echo "FAIL: persentase hadir tidak memakai jumlah sesi yang tercatat"; exit 1; }
php -r '$b=file_get_contents($argv[1]); exit(strpos($b,"/Type /Catalog")!==false && strpos($b,"%%EOF")!==false?0:1);' "$test_dir/report.pdf" || { echo "FAIL: struktur PDF tidak valid"; exit 1; }
bad_format_status="$(curl -sS -o "$test_dir/bad-format.json" -w '%{http_code}' -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fexport&format=csv&schedule_id=99")"
[[ "$bad_format_status" == 422 ]] || { echo "FAIL: format ekspor tidak didukung harus ditolak"; exit 1; }
bad_schedule_status="$(curl -sS -o "$test_dir/bad-schedule.json" -w '%{http_code}' -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fexport&format=xlsx&schedule_id=999")"
[[ "$bad_schedule_status" == 422 ]] || { echo "FAIL: jadwal laporan tidak dikenal harus ditolak"; exit 1; }
class_create="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"id_periode_akademik":1,"id_program_studi":1,"id_mata_kuliah":2,"kode_kelompok":"TI_ALPRO-2","nama_kelompok":"Algoritma Kelompok 2","jumlah_pertemuan_rencana":14,"catatan":null}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses")"
grep -q '"id_kelas_kuliah":12' <<<"$class_create" || { echo "FAIL: pembuatan kelas"; exit 1; }
class_update="$(curl -sS -b "$cookie_jar" -X PATCH -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"versi":1,"nama_kelompok":"Algoritma Kelompok 1","jumlah_pertemuan_rencana":14,"catatan":"Uji perubahan"}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10")"
grep -q '"versi":2' <<<"$class_update" || { echo "FAIL: pembaruan kelas"; exit 1; }
component_update="$(curl -sS -b "$cookie_jar" -X PUT -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"versi":1,"jumlah_evaluasi":3,"jumlah_asistensi":2}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fcomponents")"
grep -q '"versi":2' <<<"$component_update" || { echo "FAIL: konfigurasi komponen nilai"; exit 1; }
changes_response="$(curl -sS -b "$cookie_jar" -X PUT -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"versi":1,"pertemuan":{"nomor_pertemuan":1,"tanggal_sesi":"2026-10-21","materi":"Uji API"},"kehadiran":[{"id_mahasiswa":700,"status":"HADIR","catatan":null}],"nilai":[{"id_mahasiswa":700,"kode_komponen":"MID","nilai":85}]}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fchanges")"
grep -q '"versi":2' <<<"$changes_response" || { echo "FAIL: simpan sesi/presensi/nilai atomik"; exit 1; }
workflow_response="$(curl -sS -b "$cookie_jar" -X PATCH -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"versi":1,"versi_workflow":1,"status":"DRAF","catatan":"Uji reset"}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fschedules%2F99%2Fworkflow")"
grep -q '"versi_workflow":2' <<<"$workflow_response" || { echo "FAIL: transisi workflow"; exit 1; }
schedule_create="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"versi":1,"id_laboratorium":2,"hari_ke":2,"jam_mulai":"09:00","jam_selesai":"11:00","tanggal_mulai":"2026-09-07","tanggal_selesai":"2026-12-12","pola_perulangan":"MINGGUAN","status":"AKTIF","id_user_dosen":300,"id_user_asisten_utama":400,"id_user_asisten_pengajar_pendukung":401,"id_user_pendamping_teknis":402}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fschedules")"
grep -q '"id_jadwal_kelas_kuliah":100' <<<"$schedule_create" || { echo "FAIL: pembuatan jadwal"; exit 1; }
schedule_update="$(curl -sS -b "$cookie_jar" -X PATCH -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"versi":1,"status":"NONAKTIF"}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fschedules%2F99")"
grep -q '"id_jadwal_kelas_kuliah":99' <<<"$schedule_update" || { echo "FAIL: pembaruan jadwal"; exit 1; }
sync_response="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fparticipants%2Fsync")"
grep -q '"jumlah_peserta_baru":1' <<<"$sync_response" || { echo "FAIL: sinkron peserta"; exit 1; }
candidate_response="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fprospective-participants&query=1302")"
grep -q '"nim":"13020260001"' <<<"$candidate_response" || { echo "FAIL: pencarian calon Mahasiswa canonical: $candidate_response"; exit 1; }
member_response="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"versi":1,"id_mahasiswa":701,"tanggal_mulai":"2026-09-07","catatan":"uji"}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fmembers")"
grep -q '"id_mahasiswa":701' <<<"$member_response" || { echo "FAIL: tambah anggota canonical: $member_response"; exit 1; }
member_batch_preview="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"versi":2,"preview":true,"anggota":[{"nim":"13020260001","tanggal_mulai":"2026-09-07"},{"nim":"13020260002","tanggal_mulai":"2026-09-07"}]}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fmembers%2Fbatch")"
grep -q '"jumlah_valid":2' <<<"$member_batch_preview" || { echo "FAIL: pratinjau tambah anggota batch: $member_batch_preview"; exit 1; }
member_batch_commit="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"versi":2,"preview":false,"anggota":[{"nim":"13020260001","tanggal_mulai":"2026-09-07"},{"nim":"13020260002","tanggal_mulai":"2026-09-07"}]}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fmembers%2Fbatch")"
grep -q '"jumlah_diimpor":2' <<<"$member_batch_commit" || { echo "FAIL: commit tambah anggota batch: $member_batch_commit"; exit 1; }
frequency_template_status="$(curl -sS -o "$test_dir/frequency-template.xlsx" -w '%{http_code}' -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Ffrequency-template")"
[[ "$frequency_template_status" == 200 ]] || { echo "FAIL: template import anggota frekuensi"; exit 1; }
frequency_template_base64="$(base64 -w 0 "$test_dir/frequency-template.xlsx")"
frequency_preview="$(curl -sS -b "$cookie_jar" -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d "{\"id_kelas_kuliah\":10,\"versi\":1,\"base64\":\"$frequency_template_base64\",\"preview\":true}" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fimport-frequency")"
grep -q '"pratinjau":true' <<<"$frequency_preview" || { echo "FAIL: pratinjau import anggota frekuensi: $frequency_preview"; exit 1; }
frequency_import="$(curl -sS -b "$cookie_jar" -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d "{\"id_kelas_kuliah\":10,\"versi\":1,\"base64\":\"$frequency_template_base64\",\"preview\":false}" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fimport-frequency")"
grep -q '"jumlah_diimpor":1' <<<"$frequency_import" || { echo "FAIL: commit import anggota frekuensi: $frequency_import"; exit 1; }
transfer_response="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"versi_sumber":1,"versi_tujuan":1,"id_kelas_tujuan":11,"id_mahasiswa":700,"tanggal_efektif":"2026-10-21","alasan":"Uji perpindahan"}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fparticipants%2Ftransfer")"
grep -q '"id_kelas_tujuan":11' <<<"$transfer_response" || { echo "FAIL: pindah peserta"; exit 1; }

# --- Pengujian Manajemen Akses & RBAC ---
roles_response="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faccess-management%2Froles")"
grep -q '"kode":"SUPER_ADMIN"' <<<"$roles_response" || { echo "FAIL: referensi peran manajemen akses: $roles_response"; exit 1; }
grep -q '"kode":"LABORAN"' <<<"$roles_response" || { echo "FAIL: peran laboran tidak ditemukan: $roles_response"; exit 1; }

users_response="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faccess-management%2Fusers")"
grep -q '"username":"uji-admin"' <<<"$users_response" || { echo "FAIL: daftar pengguna manajemen akses: $users_response"; exit 1; }

users_filtered="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faccess-management%2Fusers&cari=laboran&kode_peran=LABORAN&status_akun=AKTIF")"
grep -q '"username":"laboran-uji"' <<<"$users_filtered" || { echo "FAIL: filter pengguna manajemen akses: $users_filtered"; exit 1; }

user_create="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"email_login":"baru@iclabs.test","nama_lengkap":"Pengguna Baru","kode_peran":"ASISTEN","nomor_induk":"13020269999"}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faccess-management%2Fusers")"
grep -q '"id":9099' <<<"$user_create" || { echo "FAIL: pembuatan pengguna: $user_create"; exit 1; }
grep -q '"email_login":"baru@iclabs.test"' <<<"$user_create" || { echo "FAIL: email pengguna baru: $user_create"; exit 1; }

profile_update="$(curl -sS -b "$cookie_jar" -X PATCH -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"nama_lengkap":"Dosen Pengampu Update","gelar_depan":"Dr.","gelar_belakang":"M.T.","versi":1}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faccess-management%2Fusers%2F9004%2Fprofile")"
grep -q '"nama_lengkap":"Dosen Pengampu Update"' <<<"$profile_update" || { echo "FAIL: update profil pengguna: $profile_update"; exit 1; }

role_assign="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"kode_peran":"ADMIN_ICLABS","catatan_pemberian":"Penugasan peran tambahan untuk uji integrasi"}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faccess-management%2Fusers%2F9004%2Froles")"
grep -q '"id":201' <<<"$role_assign" || { echo "FAIL: penetapan peran pengguna: $role_assign"; exit 1; }

role_end="$(curl -sS -b "$cookie_jar" -X PATCH -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"versi":1,"catatan_berakhir":"Pengakhiran peran masa tugas selesai pengujian"}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faccess-management%2Fusers%2F9004%2Froles%2F104%2Fend")"
grep -q '"id":104' <<<"$role_end" || { echo "FAIL: pengakhiran peran pengguna: $role_end"; exit 1; }

bulk_assign="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"kode_peran":"ASISTEN","id_pengguna":[9003,9004],"catatan_pemberian":"Penetapan massal asisten laboratorium periode baru"}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faccess-management%2Froles%2Fbulk")"
grep -q '"jumlah_diminta":2' <<<"$bulk_assign" || { echo "FAIL: penetapan peran massal: $bulk_assign"; exit 1; }

password_reset="$(curl -sS -b "$cookie_jar" -X PATCH -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"password_baru":"KataSandiBaru2026!"}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faccess-management%2Fusers%2F9004%2Fpassword")"
grep -q 'Password akun berhasil diperbarui' <<<"$password_reset" || { echo "FAIL: reset password pengguna: $password_reset"; exit 1; }

token_issue="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"kadaluarsa_jam":48}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faccess-management%2Fusers%2F9004%2Factivation-token")"
grep -q '"token":"mock-token-aktivasi-abcdef123456"' <<<"$token_issue" || { echo "FAIL: penerbitan token aktivasi: $token_issue"; exit 1; }

email_activation="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faccess-management%2Fusers%2F9004%2Factivation-email")"
grep -q 'Email instruksi aktivasi akun telah dikirimkan' <<<"$email_activation" || { echo "FAIL: pengiriman email aktivasi: $email_activation"; exit 1; }

lab_scopes_get="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faccess-management%2Fusers%2F9003%2Froles%2F103%2Flab-scopes")"
grep -q '"SEMUA_LAB"' <<<"$lab_scopes_get" || { echo "FAIL: get cakupan laboratorium: $lab_scopes_get"; exit 1; }

lab_scopes_post="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"jenis_cakupan":"LAB_TERTENTU","id_laboratorium":2,"catatan_pemberian":"Penugasan laboratorium riset komputer dasar"}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faccess-management%2Fusers%2F9003%2Froles%2F103%2Flab-scopes")"
grep -q '"id":502' <<<"$lab_scopes_post" || { echo "FAIL: penugasan cakupan laboratorium: $lab_scopes_post"; exit 1; }

lab_scope_end="$(curl -sS -b "$cookie_jar" -X PATCH -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"versi":1,"catatan_berakhir":"Pengakhiran cakupan lab semua lab beralih spesifik"}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faccess-management%2Fusers%2F9003%2Froles%2F103%2Flab-scopes%2F501%2Fend")"
grep -q '"id":501' <<<"$lab_scope_end" || { echo "FAIL: pengakhiran cakupan laboratorium: $lab_scope_end"; exit 1; }

unsupported_response="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{}' "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fusers")"
grep -q '"error":"Fitur ini belum dialihkan ke API pusat' <<<"$unsupported_response" || { echo "FAIL: route belum siap tidak gagal tertutup"; exit 1; }

logout_response="$(curl -sS -b "$cookie_jar" -X POST -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{}' "http://127.0.0.1:$app_port/api.php?route=%2Flogout")"
[[ "$logout_response" == '{"ok":true}' ]] || { echo "FAIL: logout pusat"; exit 1; }
after_logout="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fme")"
grep -q '"error":"Silakan login."' <<<"$after_logout" || { echo "FAIL: session lokal masih aktif setelah logout"; exit 1; }

student_cookies="$test_dir/student-cookies.txt"
student_login="$(curl -sS -c "$student_cookies" -H 'Content-Type: application/json' -d '{"email":"uji-mahasiswa@iclabs.test","password":"uji"}' "http://127.0.0.1:$app_port/api.php?route=%2Flogin")"
[[ "$student_login" == '{"ok":true}' ]] || { echo "FAIL: login Mahasiswa"; exit 1; }
student_me="$(curl -sS -b "$student_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Fme")"
grep -q '"role":"mahasiswa"' <<<"$student_me" || { echo "FAIL: role Mahasiswa"; exit 1; }
student_grades="$(curl -sS -b "$student_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Fstudent%2Fgrades")"
grep -q '"kode_komponen":"MID"' <<<"$student_grades" || { echo "FAIL: nilai pribadi Mahasiswa"; exit 1; }
grep -q '"bobot_persen":30' <<<"$student_grades" || { echo "FAIL: bobot komponen nilai Mahasiswa"; exit 1; }
grep -q '"kehadiran"' <<<"$student_grades" || { echo "FAIL: absensi mahasiswa harus ada di student/grades"; exit 1; }
grep -q '"total_hadir":2' <<<"$student_grades" || { echo "FAIL: ringkasan kehadiran mahasiswa harus ada di student/grades"; exit 1; }
student_access_users_status="$(curl -sS -o "$test_dir/student-access-users.json" -w '%{http_code}' -b "$student_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faccess-management%2Fusers")"
[[ "$student_access_users_status" == 403 ]] || { echo "FAIL: manajemen akses pengguna harus ditolak untuk Mahasiswa"; exit 1; }
student_access_roles_status="$(curl -sS -o "$test_dir/student-access-roles.json" -w '%{http_code}' -b "$student_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faccess-management%2Froles")"
[[ "$student_access_roles_status" == 403 ]] || { echo "FAIL: manajemen akses peran harus ditolak untuk Mahasiswa"; exit 1; }
student_audit_status="$(curl -sS -o "$test_dir/student-audit.json" -w '%{http_code}' -b "$student_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faudit")"
[[ "$student_audit_status" == 403 ]] || { echo "FAIL: audit harus ditolak untuk Mahasiswa"; exit 1; }
student_export_status="$(curl -sS -o "$test_dir/student-export.json" -w '%{http_code}' -b "$student_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fexport&format=xlsx&schedule_id=99")"
[[ "$student_export_status" == 403 ]] || { echo "FAIL: ekspor laporan harus ditolak untuk Mahasiswa"; exit 1; }
student_period_export_status="$(curl -sS -o "$test_dir/student-period-export.json" -w '%{http_code}' -b "$student_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Fperiods%2F1%2Fexport")"
[[ "$student_period_export_status" == 403 ]] || { echo "FAIL: ekspor rekap periode harus ditolak untuk Mahasiswa"; exit 1; }

# --- Pengujian Asisten 3 (PENDAMPING_TEKNIS) & Praktikan Enrolled ---
asisten3_cookies="$test_dir/asisten3-cookies.txt"
asisten3_login="$(curl -sS -c "$asisten3_cookies" -H 'Content-Type: application/json' -d '{"email":"uji-asisten3@iclabs.test","password":"uji"}' "http://127.0.0.1:$app_port/api.php?route=%2Flogin")"
[[ "$asisten3_login" == '{"ok":true}' ]] || { echo "FAIL: login Asisten 3"; exit 1; }
asisten3_me="$(curl -sS -b "$asisten3_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Fme")"
asisten3_csrf="$(printf '%s' "$asisten3_me" | php -r '$data=json_decode(stream_get_contents(STDIN),true); echo $data["csrf"] ?? "";')"
asisten3_classes="$(curl -sS -b "$asisten3_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Fclasses")"
grep -q '"peran_di_kelas":"ASISTEN_3"' <<<"$asisten3_classes" || { echo "FAIL: peran_di_kelas Asisten 3: $asisten3_classes"; exit 1; }
asisten3_detail="$(curl -sS -b "$asisten3_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10")"
grep -q '"peran_di_kelas":"ASISTEN_3"' <<<"$asisten3_detail" || { echo "FAIL: peran_di_kelas detail Asisten 3: $asisten3_detail"; exit 1; }
if grep -q '"ringkasan_nilai"' <<<"$asisten3_detail"; then
    echo "FAIL: Asisten 3 tidak boleh melihat nilai praktikum peserta: $asisten3_detail"
    exit 1
fi
asisten3_grades="$(curl -sS -b "$asisten3_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Fstudent%2Fgrades")"
grep -q '"kode_komponen":"MID"' <<<"$asisten3_grades" || { echo "FAIL: Asisten 3 harus bisa melihat nilai miliknya sendiri di Nilai Saya: $asisten3_grades"; exit 1; }
asisten3_change_status="$(curl -sS -o "$test_dir/asisten3-change.json" -w '%{http_code}' -b "$asisten3_cookies" -X PUT -H "X-CSRF-Token: $asisten3_csrf" -H 'Content-Type: application/json' -d '{"versi":1,"nilai":[{"id_mahasiswa":700,"kode_komponen":"EVALUASI_1","nilai":90}]}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fchanges")"
[[ "$asisten3_change_status" == 403 ]] || { echo "FAIL: Asisten 3 tidak boleh mengubah nilai praktikum; HTTP $asisten3_change_status"; exit 1; }
asisten3_sched_avail="$(curl -sS -o "$test_dir/asisten3-sched-avail.json" -w '%{http_code}' -b "$asisten3_cookies" -X POST -H "X-CSRF-Token: $asisten3_csrf" -H 'Content-Type: application/json' -d '{"versi":1,"id_laboratorium":2,"hari_ke":2,"jam_mulai":"09:00","jam_selesai":"11:00","tanggal_mulai":"2026-09-07","tanggal_selesai":"2026-12-12","pola_perulangan":"MINGGUAN","status":"AKTIF","id_user_dosen":300,"id_user_asisten_utama":400}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fschedule-assistant-availability")"
[[ "$asisten3_sched_avail" == 403 ]] || { echo "FAIL: Asisten 3 tidak boleh mengakses ketersediaan jadwal; HTTP $asisten3_sched_avail"; exit 1; }
asisten3_sched_create="$(curl -sS -o "$test_dir/asisten3-sched-create.json" -w '%{http_code}' -b "$asisten3_cookies" -X POST -H "X-CSRF-Token: $asisten3_csrf" -H 'Content-Type: application/json' -d '{"versi":1,"id_laboratorium":2,"hari_ke":2,"jam_mulai":"09:00","jam_selesai":"11:00","tanggal_mulai":"2026-10-20","pola_perulangan":"SEKALI","status":"AKTIF","id_user_dosen":300,"id_user_asisten_utama":400}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fschedules")"
[[ "$asisten3_sched_create" == 403 ]] || { echo "FAIL: Asisten 3 tidak boleh membuat jadwal; HTTP $asisten3_sched_create"; exit 1; }

# --- Pengujian Asisten Pengajar (Asisten 1 & 2 Wewenang Jadwal Ujian) ---
asisten_cookies="$test_dir/asisten-cookies.txt"
asisten_login="$(curl -sS -c "$asisten_cookies" -H 'Content-Type: application/json' -d '{"email":"uji-asisten@iclabs.test","password":"uji"}' "http://127.0.0.1:$app_port/api.php?route=%2Flogin")"
[[ "$asisten_login" == '{"ok":true}' ]] || { echo "FAIL: login Asisten Pengajar"; exit 1; }
asisten_me="$(curl -sS -b "$asisten_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Fme")"
asisten_csrf="$(printf '%s' "$asisten_me" | php -r '$data=json_decode(stream_get_contents(STDIN),true); echo $data["csrf"] ?? "";')"
asisten_refs="$(curl -sS -b "$asisten_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Freferences")"
grep -q '"laboratorium"' <<<"$asisten_refs" || { echo "FAIL: Asisten harus bisa melihat referensi laboratorium: $asisten_refs"; exit 1; }
grep -q '"dosen"' <<<"$asisten_refs" || { echo "FAIL: Asisten harus bisa melihat referensi dosen: $asisten_refs"; exit 1; }
grep -q '"asisten"' <<<"$asisten_refs" || { echo "FAIL: Asisten harus bisa melihat referensi asisten: $asisten_refs"; exit 1; }
asisten_sched_avail="$(curl -sS -b "$asisten_cookies" -X POST -H "X-CSRF-Token: $asisten_csrf" -H 'Content-Type: application/json' -d '{"versi":1,"id_laboratorium":2,"hari_ke":2,"jam_mulai":"09:00","jam_selesai":"11:00","tanggal_mulai":"2026-10-20","pola_perulangan":"SEKALI","status":"AKTIF","id_user_dosen":300,"id_user_asisten_utama":400}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fschedule-assistant-availability")"
grep -q '"rekomendasi_ketersediaan_asisten"' <<<"$asisten_sched_avail" || { echo "FAIL: Asisten Pengajar harus bisa memeriksa ketersediaan jadwal: $asisten_sched_avail"; exit 1; }
asisten_sched_create="$(curl -sS -b "$asisten_cookies" -X POST -H "X-CSRF-Token: $asisten_csrf" -H 'Content-Type: application/json' -d '{"versi":1,"id_laboratorium":2,"hari_ke":2,"jam_mulai":"09:00","jam_selesai":"11:00","tanggal_mulai":"2026-10-20","pola_perulangan":"SEKALI","status":"AKTIF","id_user_dosen":300,"id_user_asisten_utama":400,"catatan":"UJIAN_MID","nomor_pertemuan":7}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fschedules")"
grep -q '"id_jadwal_kelas_kuliah":100' <<<"$asisten_sched_create" || { echo "FAIL: Asisten Pengajar harus bisa membuat jadwal ujian MID: $asisten_sched_create"; exit 1; }
asisten_sched_patch="$(curl -sS -b "$asisten_cookies" -X PATCH -H "X-CSRF-Token: $asisten_csrf" -H 'Content-Type: application/json' -d '{"versi":1,"jam_mulai":"09:00","jam_selesai":"11:00","catatan":"UJIAN_UAS"}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fschedules%2F99")"
grep -q '"id_jadwal_kelas_kuliah":99' <<<"$asisten_sched_patch" || { echo "FAIL: Asisten Pengajar harus bisa mengedit jadwal ujian UAS: $asisten_sched_patch"; exit 1; }

praktikan_cookies="$test_dir/praktikan-cookies.txt"
praktikan_login="$(curl -sS -c "$praktikan_cookies" -H 'Content-Type: application/json' -d '{"email":"uji-praktikan@iclabs.test","password":"uji"}' "http://127.0.0.1:$app_port/api.php?route=%2Flogin")"
[[ "$praktikan_login" == '{"ok":true}' ]] || { echo "FAIL: login Praktikan"; exit 1; }
praktikan_me="$(curl -sS -b "$praktikan_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Fme")"
praktikan_csrf="$(printf '%s' "$praktikan_me" | php -r '$data=json_decode(stream_get_contents(STDIN),true); echo $data["csrf"] ?? "";')"
praktikan_classes="$(curl -sS -b "$praktikan_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Fclasses")"
grep -q '"peran_di_kelas":"PRAKTIKAN"' <<<"$praktikan_classes" || { echo "FAIL: peran_di_kelas Praktikan: $praktikan_classes"; exit 1; }
praktikan_detail="$(curl -sS -b "$praktikan_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10")"
grep -q '"peran_di_kelas":"PRAKTIKAN"' <<<"$praktikan_detail" || { echo "FAIL: peran_di_kelas detail Praktikan: $praktikan_detail"; exit 1; }
grep -q '"nilai_saya"' <<<"$praktikan_detail" || { echo "FAIL: Praktikan harus menerima nilai_saya: $praktikan_detail"; exit 1; }
grep -q '"bobot_persen":20' <<<"$praktikan_detail" || { echo "FAIL: Praktikan harus menerima bobot komponen: $praktikan_detail"; exit 1; }
if grep -q '"peserta"' <<<"$praktikan_detail"; then
    echo "FAIL: Praktikan tidak boleh melihat roster peserta lain: $praktikan_detail"
    exit 1
fi
praktikan_change_status="$(curl -sS -o "$test_dir/praktikan-change.json" -w '%{http_code}' -b "$praktikan_cookies" -X PUT -H "X-CSRF-Token: $praktikan_csrf" -H 'Content-Type: application/json' -d '{"versi":1,"nilai":[{"id_mahasiswa":700,"kode_komponen":"EVALUASI_1","nilai":90}]}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fchanges")"
[[ "$praktikan_change_status" == 403 ]] || { echo "FAIL: Praktikan tidak boleh mengubah nilai praktikum; HTTP $praktikan_change_status"; exit 1; }

echo "ApiModeMockTest: OK (auth, dashboard, audit/periode admin, manajemen akses RBAC, rekomendasi bentrok jadwal Asisten, template/pratinjau/commit import jadwal/anggota frekuensi, konfigurasi nilai admin, referensi, CRUD kelas/jadwal/komponen, ekspor XLSX/PDF dengan kontrol role, simpan atomik, workflow, sinkron/tambah/import/pindah peserta, nilai Mahasiswa, peran kontekstual asisten 3 & praktikan, fail-closed; tanpa PDO)"
