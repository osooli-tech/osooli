{{-- Survey decisions: the decision document on the left, the plan it belongs
     to on the right, and the gold link that ties them — the whole point of
     the section drawn as one connector. --}}
<svg class="art" viewBox="0 0 520 400" role="img" aria-label="{{ __('landing.decisions_art_alt') }}">
    <ellipse cx="260" cy="200" rx="215" ry="180" fill="rgba(34,197,94,.07)"/>

    {{-- the plan sheet --}}
    <g transform="translate(232 40) rotate(6 130 150)">
        <rect x="0" y="0" width="260" height="300" rx="14" fill="#02243D"
              stroke="rgba(171,201,242,.26)" stroke-width="2"/>
        <text x="238" y="34" fill="#E6C364" font-family="IBM Plex Sans Arabic" font-size="13"
              font-weight="600" text-anchor="end">مخطط 1207/أ</text>
        <line x1="22" y1="48" x2="238" y2="48" stroke="rgba(171,201,242,.2)" stroke-width="1.6"/>
        <path class="rd" style="stroke-width:2" d="M22 148 H238 M130 62 V284"/>
        <path class="pcl" style="stroke-width:1.6" d="M28 62 h96 v78 h-96 z"/>
        <path class="pcl-hi" style="stroke-width:2.2" d="M136 62 h96 v78 h-96 z"/>
        <path class="pcl" style="stroke-width:1.6" d="M28 156 h96 v72 h-96 z"/>
        <path class="pcl" style="stroke-width:1.6" d="M136 156 h96 v72 h-96 z"/>
        <path class="pcl" style="stroke-width:1.6" d="M28 240 h204 v44 h-204 z"/>
        <path class="dim" d="M136 54 H232 M136 49 V59 M232 49 V59"/>
    </g>

    {{-- the decision itself --}}
    <g transform="translate(30 118)">
        <rect x="0" y="0" width="244" height="212" rx="14" fill="#fff"/>
        <rect x="0" y="0" width="244" height="46" rx="14" fill="#046B4E"/>
        <rect x="0" y="32" width="244" height="14" fill="#046B4E"/>
        <text x="224" y="29" fill="#fff" font-family="IBM Plex Sans Arabic" font-size="13.5"
              font-weight="600" text-anchor="end">قرار مساحي</text>
        <rect x="18" y="15" width="16" height="16" rx="4" fill="#E6C364"/>

        <text x="224" y="74" fill="#6B7A8C" font-family="IBM Plex Sans Arabic" font-size="11.5"
              text-anchor="end">رقم القرار</text>
        <text x="20" y="74" fill="#0B1C30" font-family="IBM Plex Sans" font-size="12.5"
              font-weight="600">QM-4417</text>
        <line x1="20" y1="86" x2="224" y2="86" stroke="#EEF1F6" stroke-width="1.4"/>

        <text x="224" y="106" fill="#6B7A8C" font-family="IBM Plex Sans Arabic" font-size="11.5"
              text-anchor="end">التاريخ</text>
        <text x="20" y="106" fill="#0B1C30" font-family="IBM Plex Sans" font-size="12.5"
              font-weight="600">1447/07/24</text>
        <line x1="20" y1="118" x2="224" y2="118" stroke="#EEF1F6" stroke-width="1.4"/>

        <text x="224" y="138" fill="#6B7A8C" font-family="IBM Plex Sans Arabic" font-size="11.5"
              text-anchor="end">القطعة المرتبطة</text>
        <text x="20" y="138" fill="#0B1C30" font-family="IBM Plex Sans" font-size="12.5"
              font-weight="600">452/B</text>

        <rect x="20" y="154" width="204" height="42" rx="10" fill="#F8F9FF"/>
        <g transform="translate(196 168)">
            <rect x="0" y="0" width="16" height="16" rx="3" fill="rgba(4,107,78,.14)"/>
            <path d="M8 3.4v9.2M8 12.6 4.8 9.4M8 12.6l3.2-3.2" stroke="#046B4E" stroke-width="1.7"
                  fill="none" stroke-linecap="round"/>
        </g>
        <text x="184" y="172" fill="#0B1C30" font-family="IBM Plex Sans Arabic" font-size="11.5"
              font-weight="600" text-anchor="end">القرار الأصلي.pdf</text>
        <text x="184" y="187" fill="#6B7A8C" font-family="IBM Plex Sans Arabic" font-size="10"
              text-anchor="end">جاهز للتنزيل · 2.4 م.ب</text>
    </g>

    {{-- the link between them --}}
    <path d="M274 190 C316 190 316 152 352 152" stroke="#E6C364" stroke-width="2.4"
          stroke-dasharray="6 6" fill="none" stroke-linecap="round"/>
    <circle cx="274" cy="190" r="6" fill="#E6C364"/>
    <circle cx="352" cy="152" r="6" fill="#E6C364"/>
</svg>
