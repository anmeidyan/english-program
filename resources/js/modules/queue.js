import { openDB } from 'idb';

// Shared by the page and the service worker: IndexedDB stores for offline bundles and a request
// queue replayed by Background Sync (or when the app opens online). PRD 11.3.
export const DB_NAME = 'englishpath';

export function db() {
    return openDB(DB_NAME, 1, {
        upgrade(d) {
            d.createObjectStore('bundles', { keyPath: 'lesson.slug' });
            d.createObjectStore('queue', { keyPath: 'id', autoIncrement: true });
        },
    });
}

export async function enqueue(request) {
    const d = await db();
    await d.add('queue', { ...request, queuedAt: Date.now() });
}

export async function queuedCount() {
    return (await db()).count('queue');
}

// Replays queued requests in order; keeps failures (5xx/network) for later, drops 4xx.
export async function flush(fetcher = fetch) {
    const d = await db();
    const items = await d.getAll('queue');
    let sent = 0;
    for (const item of items) {
        try {
            const res = await fetcher(item.url, {
                method: item.method || 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': item.csrf || '',
                },
                body: JSON.stringify(item.body),
            });
            if (res.ok || (res.status >= 400 && res.status < 500 && res.status !== 419 && res.status !== 429)) {
                await d.delete('queue', item.id);
                sent++;
            }
        } catch (e) {
            break; // still offline
        }
    }
    return sent;
}
