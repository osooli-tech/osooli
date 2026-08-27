{{-- Deeds and owners: a stack of deeds with the front one opened to its
     ownership shares, plus the count of deeds that have gone stale. --}}
<svg class="art" viewBox="0 0 520 400" role="img" aria-label="{{ __('landing.deeds_art_alt') }}">
    <circle cx="250" cy="200" r="176" fill="#EFF4FF"/>

    {{-- the rest of the stack --}}
    <rect x="118" y="52" width="256" height="300" rx="14" fill="#fff" stroke="#DCE3EE"
          stroke-width="2" transform="rotate(-7 246 202)" opacity=".55"/>
    <rect x="132" y="60" width="256" height="300" rx="14" fill="#fff" stroke="#DCE3EE"
          stroke-width="2" transform="rotate(-3.5 260 210)" opacity=".8"/>

    <g transform="translate(146 68)">
        <rect x="0" y="0" width="256" height="300" rx="14" fill="#fff" stroke="#DCE3EE" stroke-width="2"/>
        <rect x="0" y="0" width="256" height="52" rx="14" fill="#002444"/>
        <rect x="0" y="38" width="256" height="14" fill="#002444"/>
        <text x="236" y="32" fill="#fff" font-size="14"
              font-weight="500" text-anchor="end">صك ملكية مشترك</text>
        <rect x="18" y="17" width="17" height="17" rx="4" fill="#E6C364"/>

        <text x="236" y="82" fill="#0B1C30" font-size="12.5"
              font-weight="500" text-anchor="end">الملّاك</text>

        <g font-size="11.5">
            <rect x="18" y="94" width="220" height="38" rx="9" fill="#F8F9FF"/>
            <circle cx="216" cy="113" r="12" fill="rgba(4,107,78,.13)"/>
            <text x="216" y="117" fill="#046B4E" font-size="11" text-anchor="middle" font-weight="500">م.أ</text>
            <text x="196" y="110" fill="#0B1C30" text-anchor="end" font-weight="500">المالك الأول</text>
            <text x="196" y="125" fill="#6B7A8C" font-size="10" text-anchor="end">حصة الملكية</text>
            <text x="30" y="119" fill="#046B4E" class="n" font-size="15" font-weight="600">50%</text>

            <rect x="18" y="140" width="220" height="38" rx="9" fill="#F8F9FF"/>
            <circle cx="216" cy="159" r="12" fill="rgba(196,154,60,.18)"/>
            <text x="216" y="163" fill="#8A6A1F" font-size="11" text-anchor="middle" font-weight="500">س.ع</text>
            <text x="196" y="156" fill="#0B1C30" text-anchor="end" font-weight="500">المالك الثاني</text>
            <text x="196" y="171" fill="#6B7A8C" font-size="10" text-anchor="end">حصة الملكية</text>
            <text x="30" y="165" fill="#8A6A1F" class="n" font-size="15" font-weight="600">30%</text>

            <rect x="18" y="186" width="220" height="38" rx="9" fill="#F8F9FF"/>
            <circle cx="216" cy="205" r="12" fill="rgba(0,36,68,.1)"/>
            <text x="216" y="209" fill="#002444" font-size="11" text-anchor="middle" font-weight="500">ن.ح</text>
            <text x="196" y="202" fill="#0B1C30" text-anchor="end" font-weight="500">المالك الثالث</text>
            <text x="196" y="217" fill="#6B7A8C" font-size="10" text-anchor="end">حصة الملكية</text>
            <text x="30" y="211" fill="#002444" class="n" font-size="15" font-weight="600">20%</text>
        </g>

        <line x1="18" y1="242" x2="238" y2="242" stroke="#EEF1F6" stroke-width="1.6"/>

        <g transform="translate(52 262)">
            <circle cx="0" cy="0" r="21" fill="rgba(230,195,100,.16)" stroke="#C49A3C"
                    stroke-width="1.6" stroke-dasharray="3 3.5"/>
            <path d="M0-9 -7.5-4.5v11L0 12l7.5-5.5v-11z" fill="#C49A3C"/>
        </g>
        <text x="238" y="258" fill="#6B7A8C" font-size="11"
              text-anchor="end">تم التحقق من الحدود المكانية</text>
        <text x="238" y="276" fill="#046B4E" font-size="11.5"
              font-weight="500" text-anchor="end">مطابق للقرار المساحي ✓</text>
    </g>

    <g transform="translate(46 262)">
        <rect x="0" y="0" width="150" height="58" rx="12" fill="#fff" stroke="#DCE3EE" stroke-width="2"/>
        <circle cx="30" cy="29" r="15" fill="rgba(196,154,60,.16)"/>
        <path d="M30 21v9M30 35.5v.6" stroke="#C49A3C" stroke-width="2.6" stroke-linecap="round"/>
        <text x="134" y="25" fill="#0B1C30" font-size="11.5"
              font-weight="500" text-anchor="end">صكوك تحتاج تحديث</text>
        <text x="134" y="43" fill="#8A6A1F" class="n" font-size="15"
              font-weight="600" text-anchor="end">1,094</text>
    </g>
</svg>
