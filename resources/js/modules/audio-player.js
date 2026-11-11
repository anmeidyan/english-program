import { speak, stopSpeaking } from './speech';

// Custom player (PRD 4.4): play/pause, 0.75/1/1.25x, back 5 s, optional play limit.
// `source` = { url, tts_text, accent }. For exams, `fetchSource()` asks the server (which counts plays).
export default (opts = {}) => ({
    url: opts.url || null,
    tts: opts.tts || null,
    accent: opts.accent || 'en-US',
    rate: 1,
    playing: false,
    plays: opts.plays || 0,
    maxPlays: opts.maxPlays || 0,
    fetchSource: opts.fetchSource || null,
    error: null,
    audio: null,

    get limitReached() {
        return this.maxPlays > 0 && this.plays >= this.maxPlays;
    },

    async toggle() {
        if (this.playing) return this.stop();
        if (this.fetchSource) {
            if (this.limitReached) return;
            try {
                const src = await this.fetchSource();
                this.plays = src.plays ?? this.plays + 1;
                this.url = src.url || null;
                this.tts = src.tts_text || null;
            } catch (e) {
                this.error = e?.response?.data?.message || 'Audio tidak dapat diputar.';
                if (e?.response?.status === 429) this.plays = this.maxPlays;
                return;
            }
        } else {
            this.plays++;
        }
        this.start();
    },

    start() {
        this.playing = true;
        if (this.url) {
            this.audio ??= new Audio(this.url);
            this.audio.playbackRate = this.rate;
            this.audio.onended = () => (this.playing = false);
            this.audio.play().catch(() => (this.playing = false));
        } else if (!speak(this.tts, { lang: this.accent, rate: this.rate, onend: () => (this.playing = false) })) {
            this.playing = false;
            this.error = 'Browser ini tidak mendukung audio teks-ke-suara.';
        }
    },

    stop() {
        this.playing = false;
        if (this.audio) this.audio.pause();
        stopSpeaking();
    },

    back5() {
        if (this.audio) {
            this.audio.currentTime = Math.max(0, this.audio.currentTime - 5);
        } else if (this.playing) {
            // speech synthesis cannot seek: restart the clip
            this.start();
        }
    },

    setRate(r) {
        this.rate = r;
        if (this.audio) this.audio.playbackRate = r;
    },
});
