# EnglishPath

Aplikasi web PWA untuk belajar bahasa Inggris dari Pre-A1 sampai C2 dengan **jalur belajar terkunci** (mastery gate): lesson, unit, dan level berikutnya baru terbuka setelah ujian sebelumnya lulus. Laravel 12 + MySQL 8, Livewire 3 + Alpine + Tailwind 4, Filament 3 untuk admin, semua berjalan di Docker.

## Menjalankan

Satu-satunya kebutuhan di host: Docker (Compose v2).

```bash
cp .env.example .env      # ubah APP_PORT / FORWARD_DB_PORT bila 8080/3307 sudah terpakai
make setup                # build image, start, composer install, key, migrate:fresh --seed, build aset
```

Buka http://localhost:8080 (atau `APP_PORT` di `.env`).

| Akun | Email | Password |
|---|---|---|
| Pelajar | `student@demo.test` | `password` |
| Super admin | `admin@demo.test` | `password` |
| Content admin | `content@demo.test` | `password` |
| Reviewer | `reviewer@demo.test` | `password` |

Akun-akun ini juga tampil di halaman login (kotak **Akses demo**, klik untuk langsung masuk). Matikan dengan `SHOW_DEMO_LOGINS=false` di `.env`.

Admin: `/admin` · Horizon: `/horizon` · Mailpit: http://localhost:8025

### Perintah Makefile

| Target | Fungsi |
|---|---|
| `make up` / `make down` | start / stop semua service |
| `make fresh` | `migrate:fresh --seed` (data demo A1) |
| `make assets` | build aset produksi (dibutuhkan service worker/PWA) |
| `make test` | Pest (unit + feature) |
| `make e2e` | smoke test browser (Playwright di Docker) |
| `make pint` / `make stan` | format kode / Larastan level 6 |
| `make vapid` | buat kunci VAPID untuk Web Push (lalu `make assets`) |
| `make shell`, `make logs` | shell di container app, log |

`docker compose up -d` juga menjalankan Vite dev server (port 5173). Untuk menguji PWA/offline, hentikan service `node` dan jalankan `make assets` (file `public/hot` harus tidak ada). Di luar localhost PWA butuh HTTPS.

## Isi

- **Kurikulum** Level → Modul → Unit → Lesson (7 level CEFR; konten seed lengkap untuk **A1**: 2 modul, 6 unit, 30 lesson, 90 kata, ±490 soal) + **Tenses Master** (16 tense) + **placement test** adaptif.
- **11 tipe soal** dengan grader per tipe (Strategy pattern): pilihan ganda, T/F/NG, isian, susun kata, jodohkan, dikte, koreksi, transformasi, speaking (rekam), writing.
- **Ujian**: Lesson Quiz, Unit Exam, Level Exam, Tenses Checkpoint — timer server, autosave, batas putar audio, log integritas, syarat skor total + per skill, cooldown bertahap, remedial, soal acak baru tiap percobaan. Answer key tidak pernah dikirim ke browser.
- **Penilaian speaking/writing**: antrean review dengan rubrik 0–9, AI grader opsional (`AI_GRADER_DRIVER=anthropic`).
- **Progres & gamifikasi**: dashboard, peta jalur belajar, radar skill, XP, streak (+freeze), badge, flashcard SM-2, sertifikat PDF dengan verifikasi publik `/verify/{code}`.
- **PWA**: installable, unduh unit untuk offline, sinkronisasi latihan/flashcard (Background Sync), Web Push, dark mode, UI Indonesia/English.

## Dokumentasi

- `docs/decisions.md` — keputusan teknis di luar PRD.
- `docs/content-format.md` — format import JSON (kurikulum) dan CSV (bank soal).

## Struktur kode

```
app/Services/            logika bisnis (UnlockService = mastery gate, ExamService, Grading/*, Review/*, …)
app/Repositories/        interface + implementasi Eloquent (di-bind di RepositoryServiceProvider)
app/Http/Controllers/    controller tipis (web + JSON /app/api)
app/Filament/            panel admin
app/Livewire/            onboarding, placement test
resources/js/            Alpine modules (lesson player, exam runner, audio/TTS, recorder, offline), sw.js
database/seeders/content konten A1, placement, tenses
docker/                  Dockerfile multi-stage, nginx, php.ini, my.cnf
```
