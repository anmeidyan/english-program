# Keputusan Teknis (di luar cakupan PRD)

Dicatat sesuai PRD 15.2: bila ada keputusan yang tidak tercakup PRD, pilih opsi paling sederhana yang konsisten, catat di sini.

| # | Tanggal | Keputusan | Alasan |
|---|---------|-----------|--------|
| 1 | 2026-11-11 | Pest **4** (bukan Pest 3) | Versi yang dipilih otomatis oleh Breeze untuk Laravel 12; Pest 4 sudah membawa Pest Browser untuk E2E (PRD 14.1). API test kompatibel dengan Pest 3. |
| 2 | 2026-11-11 | `composer config platform.php 8.3.36` | Lock file harus bisa di-install di image `php:8.3-fpm-alpine` (PRD 12.1). |
| 3 | 2026-11-11 | Port host dapat diubah via `.env` (`APP_PORT`, `FORWARD_DB_PORT`, `VITE_PORT`, `FORWARD_MAILPIT_PORT`), default tetap 8080/3307/5173/8025 | Menghindari bentrok dengan project lain di mesin developer. |
| 4 | 2026-11-11 | Container dev **tidak** memakai `env_file`; Laravel membaca `.env` sendiri | Env var dari `env_file` meng-override `phpunit.xml`, sehingga test bisa berjalan di database utama. |
| 5 | 2026-11-11 | Test memakai database MySQL terpisah `englishpath_testing` (dibuat oleh `docker/mysql/init`) | Perilaku `lockForUpdate`, JSON, dan unique constraint harus sama dengan produksi. |
| 6 | 2026-11-11 | Telescope hanya diregistrasi di environment `local` (`dont-discover`) | Telescope adalah dependency dev; build prod memakai `composer install --no-dev`. |
| 7 | 2026-11-11 | Dark mode Tailwind 4: ikut sistem, dapat dipaksa via `<html data-theme="light|dark">` | Mendukung toggle manual tanpa mengabaikan preferensi OS. |
