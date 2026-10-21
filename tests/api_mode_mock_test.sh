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
detail_response="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10")"
grep -q '"kode":"TI_ALPRO-1"' <<<"$detail_response" || { echo "FAIL: detail kelas"; exit 1; }
xlsx_status="$(curl -sS -o "$test_dir/report.xlsx" -w '%{http_code}' -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fexport&format=xlsx&schedule_id=99")"
[[ "$xlsx_status" == 200 ]] || { echo "FAIL: ekspor Excel status $xlsx_status"; exit 1; }
php -r '$f=fopen($argv[1],"rb"); exit(fread($f,2)==="PK"?0:1);' "$test_dir/report.xlsx" || { echo "FAIL: berkas Excel tidak valid"; exit 1; }
php -r '$z=new ZipArchive(); exit($z->open($argv[1])===true && $z->locateName("xl/worksheets/sheet1.xml")!==false && $z->locateName("[Content_Types].xml")!==false?0:1);' "$test_dir/report.xlsx" || { echo "FAIL: struktur XLSX tidak valid"; exit 1; }
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
student_audit_status="$(curl -sS -o "$test_dir/student-audit.json" -w '%{http_code}' -b "$student_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Fadmin%2Faudit")"
[[ "$student_audit_status" == 403 ]] || { echo "FAIL: audit harus ditolak untuk Mahasiswa"; exit 1; }
student_export_status="$(curl -sS -o "$test_dir/student-export.json" -w '%{http_code}' -b "$student_cookies" "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fexport&format=xlsx&schedule_id=99")"
[[ "$student_export_status" == 403 ]] || { echo "FAIL: ekspor laporan harus ditolak untuk Mahasiswa"; exit 1; }

echo "ApiModeMockTest: OK (auth, dashboard, audit/periode admin, rekomendasi bentrok jadwal Asisten, template/pratinjau/commit import jadwal/anggota frekuensi, konfigurasi nilai admin, referensi, CRUD kelas/jadwal/komponen, ekspor XLSX/PDF dengan kontrol role, simpan atomik, workflow, sinkron/tambah/import/pindah peserta, nilai Mahasiswa, fail-closed; tanpa PDO)"
