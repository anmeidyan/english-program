// Text-to-speech fallback for lesson audio when no recorded file exists (see docs/decisions.md).
export function speak(text, { lang = 'en-US', rate = 1, onend = null } = {}) {
    if (!('speechSynthesis' in window) || !text) {
        onend?.();
        return false;
    }
    window.speechSynthesis.cancel();
    const utter = new SpeechSynthesisUtterance(text.replace(/\s+—\s+/g, '. '));
    utter.lang = lang;
    utter.rate = rate;
    const voice = window.speechSynthesis.getVoices().find((v) => v.lang === lang) || window.speechSynthesis.getVoices().find((v) => v.lang?.startsWith('en'));
    if (voice) utter.voice = voice;
    utter.onend = () => onend?.();
    utter.onerror = () => onend?.();
    window.speechSynthesis.speak(utter);
    return true;
}

export function stopSpeaking() {
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
}

// Speech-to-text in the browser (Web Speech API), used for stage-1 speaking scores.
export function createRecognizer(lang = 'en-US') {
    const Ctor = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!Ctor) return null;
    const rec = new Ctor();
    rec.lang = lang;
    rec.continuous = true;
    rec.interimResults = false;
    return rec;
}
