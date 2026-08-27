{{-- Parcels: a light plat with corner nodes and dimension callouts on one
     parcel, and the area the platform computes from those coordinates. --}}
<svg class="art" viewBox="0 0 520 400" role="img" aria-label="{{ __('landing.parcels_art_alt') }}">
    <circle cx="270" cy="200" r="182" fill="#EFF4FF"/>
    <circle cx="112" cy="88" r="52" fill="rgba(230,195,100,.2)"/>

    <g transform="translate(56 46)">
        <rect x="0" y="0" width="396" height="304" rx="16" fill="#fff" stroke="#DCE3EE" stroke-width="2"/>
        <path class="rd-d" d="M0 112 H396 M0 208 H396 M136 0 V304 M268 0 V304"/>
        <path class="pcl-d" d="M16 14 h104 v82 h-104 z"/>
        <path class="pcl-d" d="M152 14 h100 v82 h-100 z"/>
        <path class="pcl-d" d="M284 14 h96 v82 h-96 z"/>
        <path class="pcl-d" d="M16 126 h104 v68 h-104 z"/>
        <path class="pcl-dhi" d="M152 126 h100 v68 h-100 z"/>
        <path class="pcl-d" d="M284 126 h96 v68 h-96 z"/>
        <path class="pcl-d" d="M16 222 h104 v68 h-104 z"/>
        <path class="pcl-d" d="M152 222 h100 v68 h-100 z"/>
        <path class="pcl-d" d="M284 222 h96 v68 h-96 z"/>

        {{-- corner coordinates --}}
        <g fill="#C49A3C">
            <circle cx="152" cy="126" r="4.6"/><circle cx="252" cy="126" r="4.6"/>
            <circle cx="252" cy="194" r="4.6"/><circle cx="152" cy="194" r="4.6"/>
        </g>

        {{-- edge lengths --}}
        <path class="dim" style="stroke:#C49A3C" d="M152 116 H252 M152 111 V121 M252 111 V121"/>
        <text x="202" y="106" fill="#8A6A1F" class="n" font-size="12"
              text-anchor="middle">50.0 m</text>
        <path class="dim" style="stroke:#C49A3C" d="M262 126 V194 M257 126 H267 M257 194 H267"/>
        <text x="286" y="164" fill="#8A6A1F" class="n" font-size="12"
              text-anchor="middle">25.0 m</text>
    </g>

    <g transform="translate(300 296)">
        <rect x="0" y="0" width="164" height="60" rx="12" fill="#002444"/>
        <text x="148" y="24" fill="#8FA6C0" font-size="11.5"
              text-anchor="end">المساحة المحسوبة</text>
        <text x="148" y="46" fill="#E6C364" class="n" font-size="19"
              font-weight="600" text-anchor="end">1,250 m²</text>
    </g>
</svg>
