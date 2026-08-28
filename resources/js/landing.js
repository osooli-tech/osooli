/**
 * Owner app tour.
 *
 * The markup is already a working carousel without this: the track is a
 * scroll-snap strip, so it swipes on touch and scrolls with a trackpad. What
 * follows layers on the arrows, the tick rail, keyboard control and the caption
 * swap, and keeps them all in step with whatever the track is actually showing.
 */
(function () {
    const tour = document.querySelector('[data-tour]');
    if (! tour) return;

    const track = tour.querySelector('[data-tour-track]');
    const slides = [...tour.querySelectorAll('[data-tour-slide]')];
    const prev = tour.querySelector('[data-tour-prev]');
    const next = tour.querySelector('[data-tour-next]');
    const captions = [...document.querySelectorAll('[data-tour-caption]')];
    const ticks = [...document.querySelectorAll('[data-tour-tick]')];
    if (slides.length < 2) return;

    // Under RTL the track scrolls to negative offsets, so compare centres
    // rather than doing arithmetic on scrollLeft.
    const nearestToCentre = () => {
        const mid = track.getBoundingClientRect().left + track.clientWidth / 2;
        let best = 0;
        let bestGap = Infinity;
        slides.forEach((slide, i) => {
            const r = slide.getBoundingClientRect();
            const gap = Math.abs(r.left + r.width / 2 - mid);
            if (gap < bestGap) { bestGap = gap; best = i; }
        });
        return best;
    };

    let current = -1;

    const paint = (index) => {
        if (index === current) return;
        current = index;

        slides.forEach((s, i) => s.classList.toggle('is-current', i === index));
        captions.forEach((c, i) => c.classList.toggle('is-current', i === index));
        ticks.forEach((t, i) => {
            t.classList.toggle('is-current', i === index);
            t.setAttribute('aria-selected', i === index ? 'true' : 'false');
        });

        // The arrows are the only control that can run out of room.
        if (prev) prev.disabled = index === 0;
        if (next) next.disabled = index === slides.length - 1;
    };

    const goTo = (index) => {
        const clamped = Math.max(0, Math.min(slides.length - 1, index));
        slides[clamped].scrollIntoView({
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
                ? 'auto' : 'smooth',
            block: 'nearest',
            inline: 'center',
        });
        paint(clamped);
    };

    let settle;
    track.addEventListener('scroll', () => {
        window.clearTimeout(settle);
        settle = window.setTimeout(() => paint(nearestToCentre()), 90);
    }, { passive: true });

    prev && prev.addEventListener('click', () => goTo(current - 1));
    next && next.addEventListener('click', () => goTo(current + 1));
    ticks.forEach((t) => t.addEventListener('click', () => goTo(+t.dataset.index)));

    // Arrow keys follow reading order: in RTL, "left" advances.
    track.addEventListener('keydown', (e) => {
        const rtl = document.documentElement.dir === 'rtl';
        if (e.key === 'ArrowRight') { e.preventDefault(); goTo(current + (rtl ? -1 : 1)); }
        if (e.key === 'ArrowLeft') { e.preventDefault(); goTo(current + (rtl ? 1 : -1)); }
        if (e.key === 'Home') { e.preventDefault(); goTo(0); }
        if (e.key === 'End') { e.preventDefault(); goTo(slides.length - 1); }
    });

    // A screenshot that fails to load should leave an empty frame, not the
    // browser's broken-image glyph on a marketing page.
    tour.querySelectorAll('.phone img').forEach((img) => {
        const blank = () => img.closest('.phone').classList.add('is-empty');
        if (img.complete && img.naturalWidth === 0) blank();
        img.addEventListener('error', blank);
    });

    paint(0);
    // Images arrive late, which moves the slides; re-read once they settle.
    window.addEventListener('load', () => paint(nearestToCentre()), { once: true });
}());

/**
 * Demo request modal: open/close chrome plus the fetch() submit.
 *
 * Plain JS on purpose — this page loads its own light bundle with no
 * Livewire or Alpine, so the "#contact" CTA button drives a hand-rolled
 * dialog instead of a framework component.
 */
(function () {
    const modal = document.querySelector('[data-demo-modal]');
    if (! modal) return;

    const openers = document.querySelectorAll('[data-open-demo-modal]');
    const closers = modal.querySelectorAll('[data-demo-close]');
    const form = modal.querySelector('[data-demo-form]');
    const status = modal.querySelector('[data-demo-status]');
    const submitBtn = modal.querySelector('[data-demo-submit]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const open = () => {
        modal.hidden = false;
        modal.querySelector('input[name="name"]')?.focus();
    };

    const close = () => {
        modal.hidden = true;
        form.reset();
        status.textContent = '';
        status.removeAttribute('data-state');
        form.querySelectorAll('[data-touched]').forEach((el) => el.removeAttribute('data-touched'));
    };

    openers.forEach((btn) => btn.addEventListener('click', open));
    closers.forEach((btn) => btn.addEventListener('click', close));

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && ! modal.hidden) close();
    });

    form.addEventListener('blur', (e) => {
        if (e.target instanceof HTMLElement) e.target.setAttribute('data-touched', '');
    }, true);

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (! form.checkValidity()) {
            form.querySelectorAll('input, textarea').forEach((el) => el.setAttribute('data-touched', ''));
            return;
        }

        const data = new FormData(form);
        submitBtn.disabled = true;
        status.removeAttribute('data-state');
        status.textContent = form.dataset.sending || '';

        try {
            const response = await fetch(form.action || '/presentation-requests', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: data,
            });

            if (response.status === 201) {
                status.dataset.state = 'success';
                status.textContent = form.dataset.success || '';
                form.reset();
                window.setTimeout(close, 1800);
                return;
            }

            if (response.status === 422) {
                const body = await response.json();
                const firstError = Object.values(body.errors || {})[0]?.[0];
                status.dataset.state = 'error';
                status.textContent = firstError || form.dataset.error || '';
                return;
            }

            throw new Error('unexpected status ' + response.status);
        } catch (err) {
            status.dataset.state = 'error';
            status.textContent = form.dataset.error || '';
        } finally {
            submitBtn.disabled = false;
        }
    });
}());

/**
 * Landing page behaviour: reveal sections as they scroll into view.
 *
 * The reveal is opt-in from the stylesheet's side — `.rv` only starts hidden
 * once this script adds `.js` to <html>. That ordering matters: if the bundle
 * fails to load, the visitor still gets the whole page instead of a blank one.
 */
(function () {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Nothing to animate: leave every section in its visible default state.
    if (reduceMotion || ! ('IntersectionObserver' in window)) {
        return;
    }

    document.documentElement.classList.add('js');

    const items = document.querySelectorAll('.rv');
    const show = (el) => el.classList.add('in');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                show(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });

    items.forEach((el) => observer.observe(el));

    // Safety net for the cases the observer never reports on — a background
    // tab, a browser that throttles compositing. Reveal rather than withhold.
    window.setTimeout(() => items.forEach(show), 2500);
}());
