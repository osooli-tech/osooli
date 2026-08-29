{{--
    The brand lockup: the Sakuki mark beside the wordmark, as the mobile app
    shows it.

    The mark keeps its own colours — 44% of its artwork sits within 60/255 of
    the header navy, so it needs a light plate under it to stay legible rather
    than a recoloured copy of a brand asset.

    alt is empty on purpose: the word next to it already names the brand, so a
    described image would make a screen reader say "صكوكي" twice.
--}}
<a class="logo" href="{{ url('/') }}">
    <span class="logo-mk">
        <img src="{{ asset('images/sakuki-mark.png') }}" alt="" width="136" height="128">
    </span>
    {{ __('nav.app_name') }}
</a>
