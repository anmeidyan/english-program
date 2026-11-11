import { questionMixin } from './questions';
import { queuePractice } from './offline';

// Lesson player (PRD 4): content blocks + practice with instant feedback, unlimited retries.
export default (config) => ({
    ...questionMixin,
    answers: {},
    questions: config.questions || [],
    results: {},
    checking: {},
    completing: false,
    completed: config.completed || false,
    quizUrl: config.quizUrl || null,
    queued: 0,
    recordings: {},

    init() {
        this.$watch('$store.net.online', (online) => online && this.flushQueued?.());
    },

    get answeredCount() {
        return Object.keys(this.results).length;
    },
    get correctCount() {
        return Object.values(this.results).filter((r) => r.is_correct).length;
    },

    async check(q) {
        if (q.type === 'speaking') return this.checkSpeaking(q);
        const answer = this.serialize(q);
        if (!navigator.onLine) {
            await queuePractice(config.slug, { question_id: q.id, answer });
            this.queued++;
            this.results[q.id] = { offline: true };
            return;
        }
        this.checking[q.id] = true;
        try {
            const { data } = await axios.post(config.practiceUrl, { question_id: q.id, answer });
            this.results[q.id] = data;
        } catch (e) {
            this.results[q.id] = { error: e?.response?.data?.message || 'Gagal memeriksa jawaban.' };
        } finally {
            this.checking[q.id] = false;
        }
    },

    async checkSpeaking(q) {
        const transcript = this.recordings[q.id]?.transcript || '';
        this.checking[q.id] = true;
        try {
            const { data } = await axios.post(config.practiceUrl, { question_id: q.id, answer: { transcript } });
            this.results[q.id] = data;
        } finally {
            this.checking[q.id] = false;
        }
    },

    onRecorded(q, detail) {
        this.recordings[q.id] = detail;
        this.answers[q.id] = { transcript: detail.transcript };
    },

    locked(q) {
        return !!this.results[q.id];
    },

    retry(q) {
        delete this.results[q.id];
        delete this.answers[q.id];
    },

    async complete() {
        this.completing = true;
        try {
            const { data } = await axios.post(config.completeUrl);
            this.completed = true;
            this.quizUrl = data.quiz_url;
            this.$store.pwa.markEligible();
        } catch (e) {
            alert(e?.response?.data?.message || 'Gagal menyimpan progres.');
        } finally {
            this.completing = false;
        }
    },

    formatCorrect(q, r) {
        const c = r.correct_answer;
        if (c === null || c === undefined) return '';
        if (q.type === 'mcq_single') return q.options.find((o) => o.key === c)?.text ?? c;
        if (q.type === 'mcq_multi') return q.options.filter((o) => c.includes(o.key)).map((o) => o.text).join(', ');
        if (q.type === 'true_false_ng') return this.tfLabel(c);
        if (q.type === 'matching') return q.left.map((l) => `${l.text} → ${q.right.find((x) => x.key === c[l.key])?.text}`).join('; ');
        if (Array.isArray(c)) return c.join(' / ');
        return String(c);
    },
});
