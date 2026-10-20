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

cookie_jar="$test_dir/cookies.txt"
login_response="$(curl -sS -c "$cookie_jar" -H 'Content-Type: application/json' -d '{"email":"uji-admin@iclabs.test","password":"uji"}' "http://127.0.0.1:$app_port/api.php?route=%2Flogin")"
[[ "$login_response" == '{"ok":true}' ]] || { echo "FAIL: login bridge: $login_response"; exit 1; }

me_response="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fme")"
grep -q '"api_mode":true' <<<"$me_response" || { echo "FAIL: /me tidak memvalidasi session pusat"; exit 1; }
csrf="$(printf '%s' "$me_response" | php -r '$data=json_decode(stream_get_contents(STDIN),true); echo $data["csrf"] ?? "";')"
[[ -n "$csrf" ]] || { echo "FAIL: token CSRF tidak tersedia"; exit 1; }

period_response="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Factive-period")"
grep -q '"academic_year":"2026' <<<"$period_response" || { echo "FAIL: pemetaan periode"; exit 1; }
classes_response="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fclasses")"
grep -q '"id_kelas_kuliah":10' <<<"$classes_response" || { echo "FAIL: daftar kelas"; exit 1; }
detail_response="$(curl -sS -b "$cookie_jar" "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10")"
grep -q '"kode":"TI_ALPRO-1"' <<<"$detail_response" || { echo "FAIL: detail kelas"; exit 1; }
changes_response="$(curl -sS -b "$cookie_jar" -X PUT -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"versi":1,"pertemuan":{"nomor_pertemuan":1,"tanggal_sesi":"2026-10-21","materi":"Uji API"},"kehadiran":[{"id_mahasiswa":700,"status":"HADIR","catatan":null}],"nilai":[{"id_mahasiswa":700,"kode_komponen":"MID","nilai":85}]}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fchanges")"
grep -q '"versi":2' <<<"$changes_response" || { echo "FAIL: simpan sesi/presensi/nilai atomik"; exit 1; }
workflow_response="$(curl -sS -b "$cookie_jar" -X PATCH -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' -d '{"versi":1,"versi_workflow":1,"status":"DRAF","catatan":"Uji reset"}' "http://127.0.0.1:$app_port/api.php?route=%2Fclasses%2F10%2Fschedules%2F99%2Fworkflow")"
grep -q '"versi_workflow":2' <<<"$workflow_response" || { echo "FAIL: transisi workflow"; exit 1; }

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

echo "ApiModeMockTest: OK (Admin/Mahasiswa login, sesi, periode, kelas, simpan atomik, workflow, nilai pribadi, fail-closed, logout; tanpa PDO)"
