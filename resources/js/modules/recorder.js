import { createRecognizer } from './speech';

// MediaRecorder wrapper (PRD 4.5 / 11.4): webm/ogg, or audio/mp4 on iOS Safari; max N seconds.
export function pickMimeType() {
    if (typeof MediaRecorder === 'undefined') return null;
    for (const t of ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4']) {
        if (MediaRecorder.isTypeSupported?.(t)) return t;
    }
    return '';
}

export default (opts = {}) => ({
    supported: typeof MediaRecorder !== 'undefined' && !!navigator.mediaDevices?.getUserMedia,
    recording: false,
    seconds: 0,
    maxSeconds: opts.maxSeconds || 120,
    blob: null,
    blobUrl: null,
    transcript: '',
    error: null,
    _rec: null,
    _timer: null,
    _recognizer: null,

    async startRecording() {
        this.error = null;
        if (!this.supported) {
            this.error = 'Perekaman suara tidak didukung di browser ini.';
            return;
        }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const mimeType = pickMimeType();
            this._rec = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
            const chunks = [];
            this._rec.ondataavailable = (e) => e.data.size && chunks.push(e.data);
            this._rec.onstop = () => {
                stream.getTracks().forEach((t) => t.stop());
                this.blob = new Blob(chunks, { type: this._rec.mimeType || 'audio/webm' });
                if (this.blobUrl) URL.revokeObjectURL(this.blobUrl);
                this.blobUrl = URL.createObjectURL(this.blob);
                this.$dispatch('recorded', { blob: this.blob, transcript: this.transcript });
            };
            this.transcript = '';
            this._recognizer = createRecognizer();
            if (this._recognizer) {
                this._recognizer.onresult = (e) => {
                    this.transcript = Array.from(e.results).map((r) => r[0].transcript).join(' ').trim();
                };
                try { this._recognizer.start(); } catch (e) { /* already running */ }
            }
            this._rec.start();
            this.recording = true;
            this.seconds = 0;
            this._timer = setInterval(() => {
                this.seconds++;
                if (this.seconds >= this.maxSeconds) this.stopRecording();
            }, 1000);
        } catch (e) {
            this.error = 'Izin mikrofon ditolak atau tidak tersedia.';
        }
    },

    stopRecording() {
        clearInterval(this._timer);
        this.recording = false;
        try { this._recognizer?.stop(); } catch (e) { /* noop */ }
        // give the recognizer a moment to deliver its final result
        setTimeout(() => this._rec?.state !== 'inactive' && this._rec?.stop(), 400);
    },

    get clock() {
        const m = Math.floor(this.seconds / 60);
        const s = String(this.seconds % 60).padStart(2, '0');
        return `${m}:${s}`;
    },
});
