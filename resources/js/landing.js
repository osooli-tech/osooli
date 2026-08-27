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
