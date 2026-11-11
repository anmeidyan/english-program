import { registerSW } from 'virtual:pwa-register';
import { flushQueue } from './offline';

export function pwaStore(Alpine) {
    Alpine.store('net', {
        online: navigator.onLine,
        init() {
            window.addEventListener('online', () => {
                this.online = true;
                flushQueue();
            });
            window.addEventListener('offline', () => (this.online = false));
        },
    });

    Alpine.store('theme', {
        toggle() {
            const root = document.documentElement;
            const isDark = root.dataset.theme
                ? root.dataset.theme === 'dark'
                : window.matchMedia('(prefers-color-scheme: dark)').matches;
            root.dataset.theme = isDark ? 'light' : 'dark';
            try { localStorage.setItem('ep-theme', root.dataset.theme); } catch (e) { /* storage blocked */ }
        },
    });

    let deferredPrompt = null;
    let updateSW = null;

    Alpine.store('pwa', {
        canInstall: false,
        updateReady: false,
        eligible: (() => { try { return localStorage.getItem('ep-first-lesson') === '1' && localStorage.getItem('ep-install-dismissed') !== '1'; } catch (e) { return false; } })(),
        init() {
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                this.canInstall = true;
            });
            window.addEventListener('appinstalled', () => (this.canInstall = false));
            if ('serviceWorker' in navigator && import.meta.env.PROD) {
                updateSW = registerSW({
                    onNeedRefresh: () => (this.updateReady = true),
                    onRegisteredSW: () => flushQueue(),
                });
            }
            flushQueue();
        },
        markEligible() {
            try { localStorage.setItem('ep-first-lesson', '1'); } catch (e) { /* noop */ }
            this.eligible = true;
        },
        async install() {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
            this.canInstall = false;
        },
        dismiss() {
            this.eligible = false;
            try { localStorage.setItem('ep-install-dismissed', '1'); } catch (e) { /* noop */ }
        },
        reload() {
            updateSW ? updateSW(true) : window.location.reload();
        },
    });
}

function urlBase64ToUint8Array(base64) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

export async function subscribePush() {
    const key = import.meta.env.VITE_VAPID_PUBLIC_KEY;
    if (!key || !('PushManager' in window)) return { ok: false, reason: 'unsupported' };
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return { ok: false, reason: 'denied' };
    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(key) });
    const json = sub.toJSON();
    await axios.post('/app/api/push/subscribe', { endpoint: json.endpoint, keys: json.keys, contentEncoding: 'aes128gcm' });
    return { ok: true };
}
