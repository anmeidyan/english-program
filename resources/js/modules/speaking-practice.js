import { speak, stopSpeaking } from './speech';

const norm = (w) => w.toLowerCase().replace(/[^\p{L}\p{N}']/gu, '');

/**
 * Word-level diff (LCS) between what the learner said and the corrected sentence.
 * Returns [{ text, type: 'same' | 'del' | 'add' }] for highlighting.
 */
export function wordDiff(original, corrected) {
    const a = original.trim().split(/\s+/).filter(Boolean);
    const b = corrected.trim().split(/\s+/).filter(Boolean);
    const n = a.length;
    const m = b.length;
    const dp = Array.from({ length: n + 1 }, () => new Array(m + 1).fill(0));
    for (let i = n - 1; i >= 0; i--) {
        for (let j = m - 1; j >= 0; j--) {
            dp[i][j] = norm(a[i]) === norm(b[j]) ? dp[i + 1][j + 1] + 1 : Math.max(dp[i + 1][j], dp[i][j + 1]);
        }
    }
    const out = [];
    let i = 0;
    let j = 0;
    while (i < n && j < m) {
        if (norm(a[i]) === norm(b[j])) {
            out.push({ text: b[j], type: 'same' });
            i++;
            j++;
        } else if (dp[i + 1][j] >= dp[i][j + 1]) {
            out.push({ text: a[i++], type: 'del' });
        } else {
            out.push({ text: b[j++], type: 'add' });
        }
    }
    while (i < n) out.push({ text: a[i++], type: 'del' });
    while (j < m) out.push({ text: b[j++], type: 'add' });
    return out;
}

// Daily speaking practice: speak → live transcript → coach corrects → coach asks the next question.
export default (config) => ({
    state: config.state,
    supported: !!(window.SpeechRecognition || window.webkitSpeechRecognition),
    listening: false,
    finalText: '',
    interim: '',
    draft: '',
    sending: false,
    error: null,
    celebrate: null,
    autoSend: true,
    voiceOn: true,
    accent: 'en-US',
    rec: null,

    init() {
        try {
            this.autoSend = localStorage.getItem('speaking.autoSend') !== '0';
            this.voiceOn = localStorage.getItem('speaking.voice') !== '0';
            this.accent = localStorage.getItem('speaking.accent') || 'en-US';
        } catch (e) {
            /* storage unavailable */
        }
        this.$watch('autoSend', (v) => this.store('speaking.autoSend', v ? '1' : '0'));
        this.$watch('voiceOn', (v) => this.store('speaking.voice', v ? '1' : '0'));
        this.$watch('accent', (v) => this.store('speaking.accent', v));
        this.$nextTick(() => this.scrollDown());
    },

    store(key, value) {
        try {
            localStorage.setItem(key, value);
        } catch (e) {
            /* ignore */
        }
    },

    get progress() {
        return Math.min(100, Math.round((this.state.today_turns / this.state.goal) * 100));
    },

    get lastQuestion() {
        const turns = this.state.turns;
        return turns.length ? turns[turns.length - 1].reply : this.state.topic.opener;
    },

    get live() {
        return (this.finalText + ' ' + this.interim).trim();
    },

    diff(turn) {
        return wordDiff(turn.transcript, turn.corrected);
    },

    say(text, rate = 0.95) {
        speak(text, { lang: this.accent, rate });
    },

    askAloud() {
        if (this.voiceOn) this.say(this.lastQuestion);
    },

    toggleMic() {
        this.listening ? this.stop() : this.start();
    },

    start() {
        if (!this.supported || this.sending) return;
        stopSpeaking();
        this.error = null;
        this.finalText = '';
        this.interim = '';
        this.draft = '';
        const Ctor = window.SpeechRecognition || window.webkitSpeechRecognition;
        const rec = new Ctor();
        rec.lang = this.accent;
        rec.continuous = true;
        rec.interimResults = true;
        rec.onresult = (e) => {
            let interim = '';
            for (let k = e.resultIndex; k < e.results.length; k++) {
                const r = e.results[k];
                if (r.isFinal) this.finalText = (this.finalText + ' ' + r[0].transcript).trim();
                else interim += r[0].transcript;
            }
            this.interim = interim;
            this.draft = this.live;
        };
        rec.onerror = (e) => {
            if (e.error === 'not-allowed' || e.error === 'service-not-allowed') {
                this.error = config.i18n.micDenied;
            } else if (e.error !== 'no-speech' && e.error !== 'aborted') {
                this.error = config.i18n.micError;
            }
        };
        // Chrome ends recognition after a pause; keep listening until the learner presses stop.
        rec.onend = () => {
            if (this.listening && this.rec === rec) {
                try {
                    rec.start();
                    return;
                } catch (e) {
                    /* fall through */
                }
            }
            this.finish();
        };
        this.rec = rec;
        this.listening = true;
        try {
            rec.start();
        } catch (e) {
            this.listening = false;
            this.error = config.i18n.micError;
        }
    },

    stop() {
        this.listening = false;
        this.rec?.stop();
    },

    finish() {
        this.listening = false;
        this.rec = null;
        this.draft = this.live || this.draft;
        this.interim = '';
        if (this.autoSend && this.draft.trim().length > 1) this.send();
    },

    async send() {
        const transcript = this.draft.trim();
        if (transcript.length < 2 || this.sending) return;
        this.sending = true;
        this.error = null;
        try {
            const { data } = await axios.post(config.turnUrl, { session_id: this.state.session_id, transcript });
            this.state.turns.push(data.turn);
            this.state.today_turns = data.today_turns;
            this.draft = '';
            this.finalText = '';
            if (data.goal_reached_now) this.celebrate = data.xp;
            this.$nextTick(() => this.scrollDown());
            if (this.voiceOn) this.say(data.turn.reply);
        } catch (e) {
            this.error = e.response?.data?.message || (navigator.onLine ? config.i18n.sendError : config.i18n.offline);
        } finally {
            this.sending = false;
        }
    },

    async changeTopic() {
        if (this.sending || this.listening) return;
        stopSpeaking();
        try {
            const { data } = await axios.post(config.topicUrl);
            this.state = data;
            this.draft = '';
            this.askAloud();
        } catch (e) {
            this.error = config.i18n.sendError;
        }
    },

    scrollDown() {
        this.$refs.bottom?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    },
});
