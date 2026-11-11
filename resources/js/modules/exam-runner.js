import { questionMixin } from './questions';

// Exam runner (PRD 5.5): server timer, autosave on every change, audio play limits,
// integrity events (tab switch, copy/paste). Never receives the answer key.
export default (config) => ({
    ...questionMixin,
    attempt: config.attempt,
    questions: config.attempt.questions,
    answers: {},
    index: 0,
    remaining: config.attempt.seconds_remaining,
    saving: {},
    saved: {},
    failed: {},
    submitting: false,
    error: null,
    uploads: {},
    _timers: {},
    _tick: null,

    init() {
        for (const q of this.questions) {
            if (q.saved_answer !== null && q.saved_answer !== undefined) {
                this.answers[q.id] = q.type === 'word_order' && Array.isArray(q.saved_answer)
                    ? this.restoreOrder(q, q.saved_answer)
                    : q.saved_answer;
                this.saved[q.id] = true;
            }
        }
        if (this.remaining !== null) {
            const deadline = Date.now() + this.remaining * 1000;
            this._tick = setInterval(() => {
                this.remaining = Math.max(0, Math.round((deadline - Date.now()) / 1000));
                if (this.remaining === 0) {
                    clearInterval(this._tick);
                    this.submit(true);
                }
            }, 1000);
        }
        document.addEventListener('visibilitychange', () => this.log(document.hidden ? 'visibility_hidden' : 'visibility_visible'));
        document.addEventListener('copy', () => this.log('copy'));
        document.addEventListener('paste', () => this.log('paste'));
        window.addEventListener('beforeunload', (e) => {
            if (!this.submitting && Object.values(this.saving).some(Boolean)) e.preventDefault();
        });
    },

    restoreOrder(q, words) {
        const used = new Set();
        return words.map((w) => {
            const i = q.tokens.findIndex((t, idx) => t === w && !used.has(idx));
            used.add(i);
            return `${i}:${w}`;
        });
    },

    get current() {
        return this.questions[this.index];
    },
    get answeredCount() {
        return this.questions.filter((q) => this.hasAnswer(q) || this.uploads[q.id] === 'done').length;
    },
    get clock() {
        if (this.remaining === null) return '∞';
        const m = Math.floor(this.remaining / 60);
        const s = String(this.remaining % 60).padStart(2, '0');
        return `${m}:${s}`;
    },

    go(i) {
        this.index = Math.max(0, Math.min(this.questions.length - 1, i));
        this.$nextTick(() => document.getElementById('question-heading')?.focus());
    },

    onAnswerChanged(q) {
        this.saved[q.id] = false;
        clearTimeout(this._timers[q.id]);
        this._timers[q.id] = setTimeout(() => this.save(q), q.type === 'writing' ? 1200 : 300);
    },

    async save(q) {
        this.saving[q.id] = true;
        this.failed[q.id] = false;
        try {
            await axios.patch(`${config.apiBase}/answers/${q.id}`, { answer: this.serialize(q) });
            this.saved[q.id] = true;
        } catch (e) {
            this.failed[q.id] = true;
            this.error = e?.response?.data?.message || 'Jawaban belum tersimpan — periksa koneksi.';
            setTimeout(() => this.failed[q.id] && this.save(q), 4000);
        } finally {
            this.saving[q.id] = false;
        }
    },

    fetchAudio(q) {
        return async () => {
            const { data } = await axios.post(`${config.apiBase}/audio-play/${q.id}`);
            q.audio_plays = data.plays;
            return data;
        };
    },

    async uploadRecording(q, detail) {
        this.uploads[q.id] = 'uploading';
        const form = new FormData();
        const ext = detail.blob.type.includes('mp4') ? 'm4a' : detail.blob.type.includes('ogg') ? 'ogg' : 'webm';
        form.append('audio', detail.blob, `answer.${ext}`);
        form.append('transcript', detail.transcript || '');
        try {
            await axios.post(`${config.apiBase}/recordings/${q.id}`, form);
            this.uploads[q.id] = 'done';
            this.answers[q.id] = { recorded: true };
        } catch (e) {
            this.uploads[q.id] = 'error';
            this.error = e?.response?.data?.message || 'Rekaman gagal diunggah.';
        }
    },

    log(type) {
        axios.post(`${config.apiBase}/events`, { type }).catch(() => {});
    },

    async submit(auto = false) {
        if (this.submitting) return;
        if (!auto) {
            const left = this.questions.length - this.answeredCount;
            const msg = left > 0 ? `Masih ada ${left} soal belum dijawab (bernilai 0). Kumpulkan sekarang?` : 'Kumpulkan jawaban sekarang?';
            if (!confirm(msg)) return;
        }
        this.submitting = true;
        await Promise.all(this.questions.filter((q) => this.saved[q.id] === false).map((q) => this.save(q)));
        try {
            const { data } = await axios.post(`${config.apiBase}/submit`);
            window.location.href = data.result_url;
        } catch (e) {
            const redirect = e?.response?.data?.redirect;
            if (redirect) {
                window.location.href = redirect;
                return;
            }
            this.error = e?.response?.data?.message || 'Gagal mengumpulkan. Coba lagi.';
            this.submitting = false;
        }
    },
});
