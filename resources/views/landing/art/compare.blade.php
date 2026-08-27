{{-- Compare: two parcel cards with the same rows in the same order, and the
     difference between them called out in the middle. --}}
<svg class="art" viewBox="0 0 520 360" role="img" aria-label="{{ __('landing.compare_art_alt') }}">
    <circle cx="260" cy="180" r="164" fill="#EFF4FF"/>

    {{-- parcel A --}}
    <g transform="translate(38 40)">
        <rect x="0" y="0" width="196" height="280" rx="14" fill="#fff" stroke="#DCE3EE" stroke-width="2"/>
        <rect x="14" y="14" width="168" height="88" rx="10" fill="#02243D"/>
        <path class="pcl" style="stroke-width:1.6" d="M52 30 h60 v56 h-60 z"/>
        <circle cx="98" cy="58" r="4" fill="#E6C364"/>
        <rect x="14" y="112" width="34" height="20" rx="6" fill="rgba(4,107,78,.13)"/>
        <text x="31" y="126" fill="#046B4E" font-family="IBM Plex Sans" font-size="12"
              font-weight="600" text-anchor="middle">A</text>
        <text x="182" y="127" fill="#0B1C30" font-family="IBM Plex Sans Arabic" font-size="13"
              font-weight="600" text-anchor="end">قطعة 452/ب</text>

        <g font-family="IBM Plex Sans Arabic" font-size="11">
            <text x="182" y="156" fill="#6B7A8C" text-anchor="end">المساحة</text>
            <text x="16" y="156" fill="#0B1C30" font-family="IBM Plex Sans" font-size="12" font-weight="600">1,250 m²</text>
            <line x1="16" y1="168" x2="182" y2="168" stroke="#EEF1F6" stroke-width="1.4"/>
            <text x="182" y="188" fill="#6B7A8C" text-anchor="end">حالة الصك</text>
            <text x="16" y="188" fill="#046B4E" font-size="11.5" font-weight="600">محدّث</text>
            <line x1="16" y1="200" x2="182" y2="200" stroke="#EEF1F6" stroke-width="1.4"/>
            <text x="182" y="220" fill="#6B7A8C" text-anchor="end">المخطط</text>
            <text x="16" y="220" fill="#0B1C30" font-family="IBM Plex Sans" font-size="12" font-weight="600">1207/A</text>
            <line x1="16" y1="232" x2="182" y2="232" stroke="#EEF1F6" stroke-width="1.4"/>
            <text x="182" y="252" fill="#6B7A8C" text-anchor="end">نوع الأصل</text>
            <text x="16" y="252" fill="#0B1C30" font-size="11.5" font-weight="600">سكني</text>
        </g>
    </g>

    {{-- parcel B --}}
    <g transform="translate(286 40)">
        <rect x="0" y="0" width="196" height="280" rx="14" fill="#fff" stroke="#DCE3EE" stroke-width="2"/>
        <rect x="14" y="14" width="168" height="88" rx="10" fill="#02243D"/>
        <path class="pcl-hi" style="stroke-width:2" d="M66 36 h48 v44 h-48 z"/>
        <circle cx="90" cy="58" r="4" fill="#E6C364"/>
        <rect x="14" y="112" width="34" height="20" rx="6" fill="rgba(196,154,60,.2)"/>
        <text x="31" y="126" fill="#8A6A1F" font-family="IBM Plex Sans" font-size="12"
              font-weight="600" text-anchor="middle">B</text>
        <text x="182" y="127" fill="#0B1C30" font-family="IBM Plex Sans Arabic" font-size="13"
              font-weight="600" text-anchor="end">قطعة 208</text>

        <g font-family="IBM Plex Sans Arabic" font-size="11">
            <text x="182" y="156" fill="#6B7A8C" text-anchor="end">المساحة</text>
            <text x="16" y="156" fill="#0B1C30" font-family="IBM Plex Sans" font-size="12" font-weight="600">940 m²</text>
            <line x1="16" y1="168" x2="182" y2="168" stroke="#EEF1F6" stroke-width="1.4"/>
            <text x="182" y="188" fill="#6B7A8C" text-anchor="end">حالة الصك</text>
            <text x="16" y="188" fill="#8A6A1F" font-size="11.5" font-weight="600">قديم</text>
            <line x1="16" y1="200" x2="182" y2="200" stroke="#EEF1F6" stroke-width="1.4"/>
            <text x="182" y="220" fill="#6B7A8C" text-anchor="end">المخطط</text>
            <text x="16" y="220" fill="#0B1C30" font-family="IBM Plex Sans" font-size="12" font-weight="600">894</text>
            <line x1="16" y1="232" x2="182" y2="232" stroke="#EEF1F6" stroke-width="1.4"/>
            <text x="182" y="252" fill="#6B7A8C" text-anchor="end">نوع الأصل</text>
            <text x="16" y="252" fill="#0B1C30" font-size="11.5" font-weight="600">تجاري</text>
        </g>
    </g>

    {{-- the difference --}}
    <g transform="translate(216 148)">
        <rect x="0" y="0" width="88" height="64" rx="14" fill="#002444"/>
        <text x="44" y="26" fill="#8FA6C0" font-family="IBM Plex Sans Arabic" font-size="10.5"
              text-anchor="middle">الفرق</text>
        <text x="44" y="48" fill="#E6C364" font-family="IBM Plex Sans" font-size="17"
              font-weight="600" text-anchor="middle">+310</text>
    </g>
</svg>
