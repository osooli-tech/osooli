{{--
    The busy / done / failed faces of a button, stacked over its label.

    Drop this inside any <button> whose text is wrapped in <span class="btn-label">,
    then drive it from landing.js with `busy(el)`, `settle(el, 'done')` or
    `settle(el, 'fail')`. The markup costs three small elements and no request;
    all three faces are hidden until a state attribute appears.
--}}
<span class="btn-busy" aria-hidden="true"><i></i><i></i><i></i></span>

<span class="btn-done" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
         stroke-linecap="round" stroke-linejoin="round" focusable="false">
        <path d="M20 6 9 17l-5-5"/>
    </svg>
</span>

<span class="btn-fail" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
         stroke-linecap="round" focusable="false">
        <path d="M18 6 6 18M6 6l12 12"/>
    </svg>
</span>
