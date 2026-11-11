import { db, enqueue, flush, queuedCount } from './queue';

const PAGES_CACHE = 'pages';
const MEDIA_CACHE = 'lesson-media';

function csrf() {
    return document.head.querySelector('meta[name="csrf-token"]')?.content || '';
}

async function registerSync() {
    try {
        const reg = await navigator.serviceWorker?.ready;
        await reg?.sync?.register('ep-sync');
    } catch (e) { /* Background Sync unsupported: we flush on app open */ }
}

export async function queuePractice(slug, item) {
    await enqueue({ url: `/app/api/lessons/${slug}/practice`, body: { items: [item] }, csrf: csrf() });
    await registerSync();
}

export async function queueFlashcardReview({ id, rating }) {
    await enqueue({ url: `/app/api/flashcards/${id}/review`, body: { rating }, csrf: csrf() });
    await registerSync();
}

export async function flushQueue() {
    if (!navigator.onLine) return 0;
    return flush();
}

// "Unduh untuk offline" per unit: bundle JSON → IndexedDB, lesson page + media → Cache Storage.
export async function downloadUnit(unitId, onProgress = () => {}) {
    const { data } = await axios.get(`/app/api/offline/units/${unitId}/bundle`);
    const store = await db();
    const pages = await caches.open(PAGES_CACHE);
    const media = await caches.open(MEDIA_CACHE);
    let done = 0;
    for (const bundle of data.lessons) {
        await store.put('bundles', bundle);
        await pages.add(new Request(bundle.page_url, { credentials: 'include' })).catch(() => {});
        for (const url of bundle.media) {
            await media.add(url).catch(() => {});
        }
        onProgress(++done, data.lessons.length);
    }
    return data.lessons.length;
}

export async function downloadedSlugs() {
    try {
        return (await (await db()).getAllKeys('bundles')).map(String);
    } catch (e) {
        return [];
    }
}

export { queuedCount };
