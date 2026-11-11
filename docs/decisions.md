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
| 8 | 2026-11-11 | Audio listening/dikte/pelafalan memakai **text-to-speech browser** (`speechSynthesis`, aksen US/UK/AU) bila soal/blok tidak punya file audio | Seed tidak menyertakan rekaman. Admin tetap bisa mengunggah mp3/ogg; file selalu diprioritaskan. Untuk TTS, "ulang 5 detik" = memutar ulang klip. |
| 9 | 2026-11-11 | Di ujian, naskah TTS dikirim **hanya** lewat endpoint `audio-play` (yang menghitung batas putar) | Naskah = jawaban dikte; tidak ditaruh di payload soal. |
| 10 | 2026-11-11 | Aturan pool ujian: `question_type` kosong = **hanya tipe objektif**; speaking/writing diambil lewat aturan bertipe eksplisit | Lesson Quiz & Unit Exam seed tidak tertahan `pending_review`; Level Exam A1 memuat 1 speaking + 1 writing. |
| 11 | 2026-11-11 | Seed Unit Exam A1 = 22 soal (grammar 5, vocab 6, reading 4, listening 4, speaking/writing objektif 3), Level Exam = 60 | Pool per unit belum ≥ 3× untuk semua skill; jumlah dibatasi otomatis sesuai pool (ExamProvisioner). Admin bisa menambah soal & menaikkan jumlah. |
| 12 | 2026-11-11 | Remedial: lesson di unit/level ujian yang **skill-nya** di bawah minimum (atau di bawah passing bila semua skill lolos minimum) | Pemetaan "topik lemah" paling sederhana yang konsisten dengan skor per skill. Lesson Quiz tidak membuat remedial (cooldown 0). |
| 13 | 2026-11-11 | Remedial selesai = lulus ulang quiz lesson tsb (≥ 80%); retake quiz lesson yang sudah lulus tidak pernah menurunkan status | PRD 5.3 & 5.4. |
| 14 | 2026-11-11 | Bila AI grader aktif, skor AI hanya **draf** di antrean review; attempt tetap `pending_review` sampai reviewer menyimpan | Konten berikutnya tidak terbuka berdasarkan skor yang belum dikonfirmasi manusia. |
| 15 | 2026-11-11 | Rekaman kosong / esai kosong dinilai 0 otomatis (tidak masuk antrean) | Menghindari ujian tertahan tanpa ada yang bisa dinilai. |
| 16 | 2026-11-11 | Placement: tahap 5 soal per level mulai A1, naik bila ≥ 4/5, berhenti di level pertama yang gagal (turun ke Pre-A1 bila A1 gagal); rekomendasi dibatasi ke level yang sudah dipublish | Adaptif sederhana; konten MVP baru A1. |
| 17 | 2026-11-11 | Tenses Master: 16 tense, soal checkpoint **digenerate** dari mesin konjugasi (16 frame × 16 tense), pengecoh dipilih agar hanya satu jawaban benar | Pool 16 soal per tense untuk checkpoint 15 soal — admin dapat menambah soal bertag `tense-<key>`. |
| 18 | 2026-11-11 | Soal kosakata tambahan (arti + dikte) digenerate otomatis dari daftar kata tiap unit | Memperbesar pool Unit/Level Exam tanpa menulis manual. |
| 19 | 2026-11-11 | Admin bank soal mengedit `payload`/`answer_key` sebagai JSON (dengan bantuan format) | Satu form untuk 11 tipe soal; format didokumentasikan di `docs/content-format.md`. |
| 20 | 2026-11-11 | Driver AI grader: `null` (default) dan `anthropic` (SDK resmi `anthropic-ai/sdk`, model `claude-opus-5-5`, structured output JSON). Driver `openai` belum dibuat; nilai tak dikenal → `null` | PRD 15.3 belum memutuskan provider. |
| 21 | 2026-11-11 | Satu attempt aktif dijamin dengan kolom unik `active_key` (`user:exam`, NULL setelah selesai) + `lockForUpdate` | MySQL mengizinkan banyak NULL pada unique index. |
| 22 | 2026-11-11 | Bundle offline per unit hanya berisi lesson yang sudah terbuka; jawaban latihan offline disinkronkan sebagai batch dan hanya memperbarui `practice_best_score` (nilai terbaik menang) | Status mastery tetap hanya berubah lewat quiz online. |
| 23 | 2026-11-11 | Kolom tambahan di luar PRD: `users.learning_goal/onboarded_at/streak_freeze_used_at`, `user_progress.fail_count/practice_best_score`, `exam_question_rules.question_type`, `submissions.inline_feedback`, `certificates.score`, `badges.description` | Dibutuhkan fitur yang ada di PRD. |
