{{-- Security: the parcels sit inside the shield, not beside it — the point is
     that governance wraps the data. Around it, the audit entries it produces. --}}
<svg class="art" viewBox="0 0 520 360" role="img" aria-label="{{ __('landing.trust_art_alt') }}">
    <circle cx="260" cy="180" r="158" fill="#EFF4FF"/>

    <g transform="translate(260 176)">
        <path d="M0-108 -78-72v70C-78 44-42 96 0 110 42 96 78 44 78-2v-70z" fill="#002444"/>
        <path d="M0-92 -63-63v56C-63 34-34 78 0 90 34 78 63 34 63-7v-56z" fill="none"
              stroke="rgba(230,195,100,.4)" stroke-width="2" stroke-dasharray="5 6"/>

        <g transform="translate(-46 -46)">
            <path class="pcl" style="stroke-width:1.8" d="M0 0 h40 v34 h-40 z"/>
            <path class="pcl-hi" style="stroke-width:2.2" d="M52 0 h40 v34 h-40 z"/>
            <path class="pcl" style="stroke-width:1.8" d="M0 46 h40 v34 h-40 z"/>
            <path class="pcl" style="stroke-width:1.8" d="M52 46 h40 v34 h-40 z"/>
        </g>

        <g transform="translate(0 58)">
            <rect x="-17" y="-6" width="34" height="26" rx="7" fill="#E6C364"/>
            <path d="M-9-6v-8a9 9 0 0 1 18 0v8" fill="none" stroke="#E6C364" stroke-width="4"/>
            <circle cx="0" cy="6" r="3.6" fill="#002444"/>
        </g>
    </g>

    <g transform="translate(20 62)">
        <rect x="0" y="0" width="156" height="50" rx="12" fill="#fff" stroke="#DCE3EE" stroke-width="2"/>
        <circle cx="28" cy="25" r="13" fill="rgba(4,107,78,.12)"/>
        <path d="M24.4 25.4 27 28l5-5.2" stroke="#046B4E" stroke-width="2.2" fill="none" stroke-linecap="round"/>
        <text x="140" y="22" fill="#0B1C30" font-family="IBM Plex Sans Arabic" font-size="11"
              font-weight="600" text-anchor="end">تعديل معتمد</text>
        <text x="140" y="38" fill="#6B7A8C" font-family="IBM Plex Sans Arabic" font-size="9.5"
              text-anchor="end">مدير الأصول · قبل ٤ دقائق</text>
    </g>

    <g transform="translate(344 78)">
        <rect x="0" y="0" width="156" height="50" rx="12" fill="#fff" stroke="#DCE3EE" stroke-width="2"/>
        <circle cx="28" cy="25" r="13" fill="rgba(196,154,60,.16)"/>
        <path d="M28 18v8M28 31v.6" stroke="#C49A3C" stroke-width="2.4" stroke-linecap="round"/>
        <text x="140" y="22" fill="#0B1C30" font-family="IBM Plex Sans Arabic" font-size="11"
              font-weight="600" text-anchor="end">طلب تعديل معلّق</text>
        <text x="140" y="38" fill="#6B7A8C" font-family="IBM Plex Sans Arabic" font-size="9.5"
              text-anchor="end">بانتظار الاعتماد</text>
    </g>

    <g transform="translate(36 250)">
        <rect x="0" y="0" width="156" height="50" rx="12" fill="#fff" stroke="#DCE3EE" stroke-width="2"/>
        <circle cx="28" cy="25" r="13" fill="rgba(0,36,68,.09)"/>
        <path d="M28 17.5a5 5 0 1 1 0 10 5 5 0 0 1 0-10M20 34c1.6-3 4.6-4.6 8-4.6s6.4 1.6 8 4.6"
              fill="none" stroke="#002444" stroke-width="2"/>
        <text x="140" y="22" fill="#0B1C30" font-family="IBM Plex Sans Arabic" font-size="11"
              font-weight="600" text-anchor="end">صلاحيات محدّدة</text>
        <text x="140" y="38" fill="#6B7A8C" font-family="IBM Plex Sans Arabic" font-size="9.5"
              text-anchor="end">٥ أدوار · ٢٤ صلاحية</text>
    </g>
</svg>
