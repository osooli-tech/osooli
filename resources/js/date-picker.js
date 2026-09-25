// ── Date picker (Hijri Umm al-Qura or Gregorian) ─────────────────────────────
// Dates are stored as plain "YYYY-MM-DD" strings in whichever calendar the
// field uses, so the picker only has to translate between that string and a
// day on the calendar. Hijri arithmetic is left to Intl's Umm al-Qura tables
// (the calendar Saudi deeds are dated in) rather than a hand-rolled formula
// that drifts a day either way from the official one.

const DAY_MS = 86400000;
const HIJRI_EPOCH = Math.floor(Date.UTC(622, 6, 19) / DAY_MS);

const formatters = new Map();

function formatter(locale, options) {
    const key = locale + JSON.stringify(options);
    if (!formatters.has(key)) {
        formatters.set(key, new Intl.DateTimeFormat(locale, { timeZone: 'UTC', ...options }));
    }
    return formatters.get(key);
}

// A day is counted as whole days since 1970-01-01; noon keeps it clear of any
// edge where a formatter might round into the neighbouring day.
const dateOf = (day) => new Date(day * DAY_MS + DAY_MS / 2);
const todayDay = () => {
    const now = new Date();
    return Math.floor(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()) / DAY_MS);
};

const calendars = {
    gregorian: {
        partsOf(day) {
            const d = dateOf(day);
            return { y: d.getUTCFullYear(), m: d.getUTCMonth() + 1, d: d.getUTCDate() };
        },
        dayOf(y, m, d) {
            const day = Math.floor(Date.UTC(y, m - 1, d) / DAY_MS);
            const p = this.partsOf(day);
            return p.y === y && p.m === m && p.d === d ? day : null;
        },
        localeTag: (lang) => `${lang}-u-ca-gregory-nu-latn`,
        range: (current) => [1900, current + 10],
    },
    hijri: {
        partsOf(day) {
            const p = {};
            for (const { type, value } of formatter('en-u-ca-islamic-umalqura-nu-latn', {
                year: 'numeric', month: 'numeric', day: 'numeric',
            }).formatToParts(dateOf(day))) {
                p[type] = value;
            }
            return { y: parseInt(p.year, 10), m: parseInt(p.month, 10), d: parseInt(p.day, 10) };
        },
        dayOf(y, m, d) {
            // Estimate from the mean month length, then walk onto the exact
            // day; a month of 29 days has no day 30, which never converges.
            let day = HIJRI_EPOCH + Math.floor((y - 1) * 354.36667 + (m - 1) * 29.5306 + (d - 1));
            for (let i = 0; i < 6; i++) {
                const p = this.partsOf(day);
                const diff = (y - p.y) * 354 + (m - p.m) * 29.5 + (d - p.d);
                if (diff === 0) return day;
                day += Math.round(diff) || Math.sign(diff);
            }
            return null;
        },
        localeTag: (lang) => `${lang}-u-ca-islamic-umalqura-nu-latn`,
        range: (current) => [1300, current + 10],
    },
};

const pad = (n) => String(n).padStart(2, '0');

function parse(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value ?? '');
    return match ? { y: +match[1], m: +match[2], d: +match[3] } : null;
}

window.datePicker = ({ value, calendar }) => ({
    value,
    calendar,
    open: false,
    viewY: 0,
    viewM: 1,

    get cal() { return calendars[this.calendar] ?? calendars.hijri; },
    get other() { return calendars[this.calendar === 'hijri' ? 'gregorian' : 'hijri']; },
    get lang() { return document.documentElement.lang || 'ar'; },

    init() {
        this.showSelectedMonth();
        this.$watch('value', () => { if (!this.open) this.showSelectedMonth(); });
    },

    selectedDay() {
        const p = parse(this.value);
        return p ? this.cal.dayOf(p.y, p.m, p.d) : null;
    },

    showSelectedMonth() {
        const p = parse(this.value) ?? this.cal.partsOf(todayDay());
        this.viewY = p.y;
        this.viewM = p.m;
    },

    toggle() {
        if (this.open) return this.close();
        this.showSelectedMonth();
        this.open = true;
    },
    close() { this.open = false; },

    shift(months) {
        let m = this.viewM + months;
        let y = this.viewY;
        while (m < 1) { m += 12; y--; }
        while (m > 12) { m -= 12; y++; }
        this.viewY = y;
        this.viewM = m;
    },

    get years() {
        const [from, to] = this.cal.range(this.cal.partsOf(todayDay()).y);
        const list = [];
        for (let y = to; y >= from; y--) list.push(y);
        if (!list.includes(this.viewY)) list.push(this.viewY);
        return list;
    },

    get months() {
        const fmt = formatter(this.cal.localeTag(this.lang), { month: 'long' });
        return Array.from({ length: 12 }, (_, i) => {
            const first = this.cal.dayOf(this.viewY, i + 1, 1);
            return { value: i + 1, label: first === null ? String(i + 1) : fmt.format(dateOf(first)) };
        });
    },

    get weekdays() {
        const fmt = formatter(this.lang, { weekday: 'narrow' });
        // 1970-01-04 was a Sunday; the Saudi week starts on Sunday.
        return Array.from({ length: 7 }, (_, i) => fmt.format(dateOf(3 + i)));
    },

    get cells() {
        const first = this.cal.dayOf(this.viewY, this.viewM, 1);
        if (first === null) return [];

        const selected = this.selectedDay();
        const today = todayDay();
        const blanks = (dateOf(first).getUTCDay() + 7) % 7;
        const cells = Array.from({ length: blanks }, (_, i) => ({ key: 'b' + i, blank: true }));

        for (let day = first; this.cal.partsOf(day).m === this.viewM; day++) {
            cells.push({
                key: day,
                day,
                label: this.cal.partsOf(day).d,
                selected: day === selected,
                today: day === today,
            });
        }
        return cells;
    },

    pick(day) {
        const p = this.cal.partsOf(day);
        this.value = `${p.y}-${pad(p.m)}-${pad(p.d)}`;
        this.close();
    },
    pickToday() { this.pick(todayDay()); },
    clear() { this.value = ''; this.close(); },

    // The same day in the other calendar, so a date read off a Gregorian
    // document can be checked against the Hijri one being entered.
    get equivalent() {
        const day = this.selectedDay();
        if (day === null) return '';
        const p = this.other.partsOf(day);
        return `${p.y}-${pad(p.m)}-${pad(p.d)}`;
    },
});
