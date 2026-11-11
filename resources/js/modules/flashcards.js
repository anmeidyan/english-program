import { speak } from './speech';
import { queueFlashcardReview } from './offline';

// Daily SM-2 session (PRD 6.3). Works offline: reviews are queued and synced later.
export default (config) => ({
    cards: [],
    index: 0,
    flipped: false,
    loading: true,
    done: 0,
    error: null,

    async init() {
        try {
            const { data } = await axios.get(config.dueUrl);
            this.cards = data.cards;
        } catch (e) {
            this.error = 'Kartu tidak dapat dimuat. Coba lagi saat online.';
        } finally {
            this.loading = false;
        }
    },

    get card() {
        return this.cards[this.index] || null;
    },

    say(accent = 'en-US') {
        if (this.card) speak(this.card.word.word, { lang: accent, rate: 0.9 });
    },

    async rate(rating) {
        const card = this.card;
        if (!card) return;
        this.flipped = false;
        this.index++;
        this.done++;
        try {
            if (!navigator.onLine) throw new Error('offline');
            await axios.post(config.reviewUrl.replace('__ID__', card.id), { rating });
        } catch (e) {
            await queueFlashcardReview({ id: card.id, rating, at: Date.now() });
        }
    },
});
