{{-- Hero: a plat sheet with one parcel called out, a pin on it, and the deed
     that parcel belongs to. The three objects the platform joins together. --}}
<svg class="art" viewBox="0 0 560 460" role="img" aria-label="{{ __('landing.hero_art_alt') }}">
    <ellipse cx="300" cy="250" rx="240" ry="205" fill="rgba(34,197,94,.08)"/>
    <ellipse cx="150" cy="130" rx="120" ry="105" fill="rgba(230,195,100,.07)"/>

    {{-- plat sheet --}}
    <g transform="translate(52 66) rotate(-3 240 180)">
        <rect x="0" y="0" width="400" height="330" rx="16" fill="#02243D"
              stroke="rgba(171,201,242,.24)" stroke-width="2"/>
        <path class="rd" d="M0 118 H400 M0 226 H400 M138 0 V330 M272 0 V330"/>
        <path class="pcl" d="M18 16 h104 v88 h-104 z"/>
        <path class="pcl" d="M154 16 h102 v88 h-102 z"/>
        <path class="pcl" d="M288 16 h94 v88 h-94 z"/>
        <path class="pcl" d="M18 132 h104 v80 h-104 z"/>
        <path class="pcl-hi" d="M154 132 h102 v80 h-102 z"/>
        <path class="pcl" d="M288 132 h94 v80 h-94 z"/>
        <path class="pcl" d="M18 240 h48 v74 h-48 z"/>
        <path class="pcl" d="M76 240 h46 v74 h-46 z"/>
        <path class="pcl" d="M154 240 h102 v74 h-102 z"/>
        <path class="pcl" d="M288 240 h94 v74 h-94 z"/>
        <path class="dim" d="M154 122 H256 M154 117 V127 M256 117 V127"/>
        <text x="205" y="112" fill="#E6C364" font-family="IBM Plex Sans" font-size="13"
              text-anchor="middle">50.0 m</text>
    </g>

    {{-- pin on the called-out parcel --}}
    <g transform="translate(238 178)">
        <circle cx="0" cy="0" r="30" fill="rgba(230,195,100,.16)"/>
        <circle cx="0" cy="0" r="19" fill="rgba(230,195,100,.28)"/>
        <path d="M0-16c-7.7 0-14 6.3-14 14 0 10.5 14 22 14 22s14-11.5 14-22c0-7.7-6.3-14-14-14"
              fill="#E6C364"/>
        <circle cx="0" cy="-2" r="5.4" fill="#002444"/>
    </g>

    {{-- the deed for that parcel --}}
    <g transform="translate(300 268)">
        <rect x="0" y="0" width="238" height="152" rx="14" fill="#fff"/>
        <rect x="0" y="0" width="238" height="42" rx="14" fill="#002444"/>
        <rect x="0" y="30" width="238" height="12" fill="#002444"/>
        <rect x="16" y="14" width="15" height="15" rx="3" fill="#E6C364"/>
        <text x="42" y="26" fill="#fff" font-family="IBM Plex Sans Arabic" font-size="13"
              font-weight="600">صك ملكية</text>
        <text x="222" y="26" fill="#E6C364" font-family="IBM Plex Sans" font-size="11"
              text-anchor="end">310125004521</text>

        <text x="222" y="70" fill="#6B7A8C" font-family="IBM Plex Sans Arabic" font-size="11.5"
              text-anchor="end">رقم القطعة</text>
        <text x="16" y="70" fill="#0B1C30" font-family="IBM Plex Sans" font-size="12.5"
              font-weight="600">452/B</text>
        <line x1="16" y1="82" x2="222" y2="82" stroke="#EEF1F6" stroke-width="1.4"/>

        <text x="222" y="102" fill="#6B7A8C" font-family="IBM Plex Sans Arabic" font-size="11.5"
              text-anchor="end">المساحة</text>
        <text x="16" y="102" fill="#0B1C30" font-family="IBM Plex Sans" font-size="12.5"
              font-weight="600">1,250 m²</text>
        <line x1="16" y1="114" x2="222" y2="114" stroke="#EEF1F6" stroke-width="1.4"/>

        <text x="222" y="134" fill="#6B7A8C" font-family="IBM Plex Sans Arabic" font-size="11.5"
              text-anchor="end">القرار المساحي</text>
        <g transform="translate(16 124)">
            <circle cx="6" cy="5" r="6" fill="rgba(4,107,78,.14)"/>
            <path d="M3.4 5.2 5.2 7 8.8 3.2" stroke="#046B4E" stroke-width="1.8" fill="none"
                  stroke-linecap="round"/>
            <text x="18" y="9.5" fill="#046B4E" font-family="IBM Plex Sans Arabic" font-size="11.5"
                  font-weight="600">مرتبط</text>
        </g>
    </g>
</svg>
