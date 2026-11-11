# Format Konten (Import JSON / CSV)

Import dilakukan di **Admin → Kurikulum → Import konten** (job queue, laporan error per baris), atau lewat seeder (`database/seeders/CurriculumSeeder.php` memakai format yang sama dalam bentuk array PHP).

## 1. Kurikulum (JSON)

```json
{
  "level": "A1",
  "modules": [
    {
      "title": "A1-M1: Hello, Family & Home",
      "slug": "a1-m1-hello-family-home",
      "description": "…",
      "units": [
        {
          "title": "Hello! Nice to Meet You",
          "slug": "a1-u1-hello",
          "description": "…",
          "vocabulary": [
            ["hello", "exclamation", "/həˈloʊ/", "/həˈləʊ/", "halo", "a word you say when you meet someone", "Hello, how are you?"]
          ],
          "lessons": [
            {
              "title": "Verb \"to be\": am, is, are",
              "type": "grammar",
              "skill": "grammar",
              "minutes": 15,
              "vocab": false,
              "passage": "… (reading: disalin ke payload.passage semua soal)",
              "audio": "… (listening: naskah TTS + transkrip semua soal non-dikte)",
              "blocks": [ { "type": "heading", "data": { "text": "…" } } ],
              "questions": [ { "type": "mcq_single", "prompt": "I ___ a student.", "payload": {…}, "answer_key": {…}, "explanation": "…", "difficulty": 1, "points": 1 } ]
            }
          ]
        }
      ]
    }
  ]
}
```

- `level`: `PRE_A1`, `A1` … `C2` (level harus sudah ada).
- **Anti-hafalan** (soal latihan menampilkan jawaban, jadi quiz/ujian sebaiknya memakai soal lain):
  - `"quiz_questions": [...]` — soal khusus quiz/ujian, **tidak** muncul di latihan. Untuk reading/listening sertakan `"quiz_passage"` / `"quiz_audio"` (teks/audio berbeda dari latihan).
  - `"generator": {"topic": "to_be", "count": 40}` — buat varian otomatis dari template (khusus quiz/ujian). Topik: `to_be`, `possessives`, `there_is_are`, `present_simple`, `do_does`, `present_continuous`, `functional_language`, `writing_mechanics`.
  - Kosakata unit otomatis menghasilkan 3–4 varian per kata (arti, arti terbalik, dikte, isian dari kalimat contoh) untuk pool quiz lesson kosakata dan ujian.
- `vocabulary`: array `[word, part_of_speech, ipa_us, ipa_uk, meaning_id, definition_en, example]` atau objek dengan kunci tersebut. `"vocab": true` pada lesson menautkan kosakata unit ke lesson (masuk flashcard saat lesson selesai).
- Import idempoten per slug: lesson yang sudah punya soal tidak ditambah soal lagi (`skipped`).
- Tag otomatis: `<level>` (mis. `a1`), slug unit, slug lesson. Ujian dibuat/diperbarui otomatis: Lesson Quiz (8 soal dari tag lesson), Unit Exam (per skill dari tag unit), Level Exam (per skill dari tag level + 1 speaking + 1 writing).

### Tipe blok lesson

| type | data |
|---|---|
| `heading` | `text` |
| `rich_text` | `html` (disanitasi) |
| `tense_formula` | `positive`, `negative`, `question`, `note?` |
| `example_list` | `title?`, `items: [{en, id?}]` |
| `table` | `headers: []`, `rows: [[]]`, `caption?` |
| `reading_passage` | `title`, `text` (kata bisa diklik → glosarium) |
| `audio` | `caption`, `url?` (file), `tts_text?`, `accent` (`en-US`/`en-GB`/`en-AU`), `transcript?` |
| `image` | `url`, `alt` |
| `video_embed` | `url` |
| `tip` | `text` |
| `common_mistake` | `wrong`, `right`, `note` |
| `timeline` | `label`, `position` (`past`, `now`, `future`, `repeated`, `past_to_now`, `before_past`) |

### Tipe soal: `payload` (dikirim ke pelajar) & `answer_key` (tidak pernah dikirim)

| type | payload | answer_key |
|---|---|---|
| `mcq_single` | `{"options":[{"key":"a","text":"am"}],"max_options":4}` — boleh > 4 opsi; tiap tampil: jawaban benar + pengecoh acak sampai `max_options` | `{"correct":"a"}` |
| `mcq_multi` | sama | `{"correct":["a","c"],"partial":true}` |
| `true_false_ng` | `{"options":["true","false","not_given"]}` | `{"correct":"not_given"}` |
| `fill_blank` | `{"blanks":2}` (prompt memakai `___`) | `{"blanks":[["are"],["door","window"]]}` |
| `word_order` | `{"tokens":["is","She","a","teacher"],"end_punctuation":"."}` | `{"accepted":["She is a teacher."]}` |
| `matching` | `{"left":[{"key":"l1","text":"…"}],"right":[{"key":"r1","text":"…"}]}` | `{"pairs":{"l1":"r1"}}` |
| `dictation` | `{"tts_text":"…"}` (atau `media_path`) | `{"text":"…"}` — toleransi 1 typo untuk kata > 5 huruf |
| `error_correction` | `{"sentence":"I is from Bandung."}` | `{"accepted":["I am from Bandung."]}` |
| `transformation` | `{}` | `{"accepted":["They are not doctors.","They aren't doctors."]}` |
| `speaking` | `{"task":"read_aloud|repeat|answer_question|describe","target_text?":"…","max_seconds":60}` | `{}` |
| `writing` | `{"min_words":40,"max_words":80,"time_limit_minutes":15}` | `{}` |

Perbandingan kalimat mengabaikan huruf besar/kecil, tanda baca, dan menyamakan singkatan (`aren't` = `are not`).

## 2. Bank soal (CSV)

Header wajib: `type,skill,level,difficulty,prompt,options,answer,explanation,tags,points`

```csv
type,skill,level,difficulty,prompt,options,answer,explanation,tags,points
mcq_single,grammar,A2,2,Yesterday I ___ to the cinema.,go|went|gone|going,b,Simple Past,a2|a2-u1-travel,1
fill_blank,grammar,A1,1,She ___ a doctor.,,is,He/She/It → is,a1,1
true_false_ng,reading,A1,2,Rina is a teacher.,,false,,a1-u1-hello,1
```

- `options` dan `answer`/`tags` dipisah `|`. Untuk mcq, `answer` = huruf opsi.
- `matching` belum didukung via CSV (pakai JSON).
