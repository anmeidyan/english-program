import './bootstrap';
import audioPlayer from './modules/audio-player';
import recorder from './modules/recorder';
import lessonPlayer from './modules/lesson-player';
import examRunner from './modules/exam-runner';
import flashcards from './modules/flashcards';
import speakingPractice from './modules/speaking-practice';
import { questionMixin } from './modules/questions';
import { pwaStore, subscribePush } from './modules/pwa';
import { downloadUnit, downloadedSlugs } from './modules/offline';
import { speak } from './modules/speech';

window.EnglishPath = { downloadUnit, downloadedSlugs, subscribePush, speak };

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;
    pwaStore(Alpine);
    Alpine.data('audioPlayer', audioPlayer);
    Alpine.data('recorder', recorder);
    Alpine.data('lessonPlayer', lessonPlayer);
    Alpine.data('examRunner', examRunner);
    Alpine.data('flashcards', flashcards);
    Alpine.data('speakingPractice', speakingPractice);
    Alpine.data('placementQuestion', () => ({ ...questionMixin, answers: {} }));

    // glossary popup (PRD 4.3): click a word in a reading passage
    Alpine.data('glossary', () => ({
        open: false,
        loading: false,
        word: null,
        raw: '',
        added: false,
        x: 0,
        y: 0,
        async lookup(event) {
            const el = event.target.closest('[data-word]');
            if (!el) return;
            const rect = el.getBoundingClientRect();
            this.x = rect.left + window.scrollX;
            this.y = rect.bottom + window.scrollY + 6;
            this.raw = el.dataset.word;
            this.open = true;
            this.loading = true;
            this.added = false;
            try {
                const { data } = await axios.get('/app/api/glossary', { params: { word: this.raw } });
                this.word = data.found ? data.word : null;
            } catch (e) {
                this.word = null;
            } finally {
                this.loading = false;
            }
        },
        async add() {
            if (!this.word) return;
            await axios.post(`/app/api/flashcards/words/${this.word.id}`);
            this.added = true;
        },
    }));

    Alpine.data('offlineUnit', (unitId) => ({
        state: 'idle',
        progress: '',
        async download() {
            this.state = 'busy';
            try {
                const count = await downloadUnit(unitId, (d, t) => (this.progress = `${d}/${t}`));
                this.state = 'done';
                this.progress = `${count} lesson`;
            } catch (e) {
                this.state = 'error';
            }
        },
    }));
});
