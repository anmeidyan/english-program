// Shared answer-state helpers for every question type (PRD 4.8), used by the lesson player,
// the exam runner and the placement test. Answers are plain JSON; grading is server-side only.
export const questionMixin = {
    answers: {},

    locked() {
        return false;
    },

    getAnswer(q) {
        return this.answers[q.id];
    },

    setAnswer(q, value) {
        this.answers[q.id] = value;
        this.onAnswerChanged?.(q, value);
    },

    hasAnswer(q) {
        const a = this.answers[q.id];
        if (a === undefined || a === null || a === '') return false;
        if (Array.isArray(a)) return a.some((v) => v !== '' && v !== null);
        if (typeof a === 'object') return Object.keys(a).length > 0;
        return true;
    },

    // mcq_multi
    toggleMulti(q, key) {
        const current = Array.isArray(this.answers[q.id]) ? [...this.answers[q.id]] : [];
        const i = current.indexOf(key);
        i >= 0 ? current.splice(i, 1) : current.push(key);
        this.setAnswer(q, current);
    },
    isPicked(q, key) {
        const a = this.answers[q.id];
        return Array.isArray(a) ? a.includes(key) : a === key;
    },

    // fill_blank
    promptParts(q) {
        return String(q.prompt).split('___');
    },
    setBlank(q, index, value) {
        const current = Array.isArray(this.answers[q.id]) ? [...this.answers[q.id]] : Array(q.blanks).fill('');
        current[index] = value;
        this.setAnswer(q, current);
    },
    blankValue(q, index) {
        const a = this.answers[q.id];
        return Array.isArray(a) ? (a[index] ?? '') : '';
    },

    // word_order: answer = ordered list of token indexes ("i:word") so duplicates work
    orderPicked(q) {
        return Array.isArray(this.answers[q.id]) ? this.answers[q.id] : [];
    },
    orderAvailable(q) {
        const used = new Set(this.orderPicked(q).map((t) => t.split(':')[0]));
        return q.tokens.map((w, i) => ({ id: `${i}:${w}`, word: w })).filter((t) => !used.has(String(t.id.split(':')[0])));
    },
    orderAdd(q, token) {
        this.setAnswer(q, [...this.orderPicked(q), token]);
    },
    orderRemove(q, index) {
        const next = [...this.orderPicked(q)];
        next.splice(index, 1);
        this.setAnswer(q, next);
    },
    orderWords(q) {
        return this.orderPicked(q).map((t) => t.split(':').slice(1).join(':'));
    },

    // matching: {leftKey: rightKey}
    matchValue(q, left) {
        return this.answers[q.id]?.[left] ?? '';
    },
    setMatch(q, left, right) {
        const current = { ...(this.answers[q.id] || {}) };
        if (right) current[left] = right; else delete current[left];
        this.setAnswer(q, current);
    },

    // writing
    wordCount(text) {
        return String(text || '').trim().split(/\s+/).filter(Boolean).length;
    },

    // value actually sent to the server
    serialize(q) {
        const a = this.answers[q.id];
        if (q.type === 'word_order') return this.orderWords(q);
        return a ?? null;
    },

    tfLabel(v) {
        return { true: 'True', false: 'False', not_given: 'Not Given' }[v] ?? v;
    },
};
