{{-- Sigou, the assistant's mascot. Inline SVG, so it is sharp at any size and
     costs no request. The script in agent-assistant brings him to life: he
     blinks, follows the cursor, and changes pose through data-state
     (idle | typing | thinking | happy | shocked | sad).

     @include('partials.sigou', ['size' => 40, 'class' => '']) --}}
@php($u = 'sg' . substr(md5(uniqid('', true)), 0, 6))
<svg class="th-sigou {{ $class ?? '' }}" data-state="idle" width="{{ $size ?? 40 }}" height="{{ $size ?? 40 }}"
     viewBox="0 0 200 200" role="img" aria-label="Sigou">
    <defs>
        <radialGradient id="{{ $u }}skin" cx="50%" cy="42%" r="62%">
            <stop offset="0" stop-color="#f8dcc6"/>
            <stop offset=".72" stop-color="#eec4a6"/>
            <stop offset="1" stop-color="#d9a386"/>
        </radialGradient>
        <linearGradient id="{{ $u }}beard" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#46342a"/>
            <stop offset="1" stop-color="#2c2019"/>
        </linearGradient>
        <linearGradient id="{{ $u }}hair" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#34271f"/>
            <stop offset="1" stop-color="#211812"/>
        </linearGradient>
        {{-- His shirt: the cream plaid flannel he always wears. --}}
        <pattern id="{{ $u }}plaid" width="22" height="22" patternUnits="userSpaceOnUse" patternTransform="rotate(-3)">
            <rect width="22" height="22" fill="#ebe4d4"/>
            <rect y="6" width="22" height="7" fill="#c7b594" opacity=".55"/>
            <rect x="6" width="7" height="22" fill="#c7b594" opacity=".55"/>
            <rect y="9" width="22" height="1.4" fill="#3f332a" opacity=".85"/>
            <rect x="9" width="1.4" height="22" fill="#3f332a" opacity=".85"/>
            <rect y="17" width="22" height=".8" fill="#7a6552" opacity=".7"/>
            <rect x="17" width=".8" height="22" fill="#7a6552" opacity=".7"/>
        </pattern>
        <linearGradient id="{{ $u }}shade" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0" stop-color="#5a4a3c" stop-opacity=".28"/>
            <stop offset=".3" stop-color="#5a4a3c" stop-opacity="0"/>
            <stop offset=".7" stop-color="#5a4a3c" stop-opacity="0"/>
            <stop offset="1" stop-color="#5a4a3c" stop-opacity=".28"/>
        </linearGradient>
        <linearGradient id="{{ $u }}mary" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#e8413a"/>
            <stop offset=".5" stop-color="#f58a3c"/>
            <stop offset="1" stop-color="#f7d34a"/>
        </linearGradient>
        {{-- Vapour: soft puffs pushed through a noise field, so they curl and
             wisp as they drift instead of reading as circles. --}}
        <filter id="{{ $u }}soft" x="-60%" y="-60%" width="220%" height="220%">
            <feTurbulence type="fractalNoise" baseFrequency=".045" numOctaves="2" seed="7" result="n"/>
            <feDisplacementMap in="SourceGraphic" in2="n" scale="14" xChannelSelector="R" yChannelSelector="G" result="d"/>
            <feGaussianBlur in="d" stdDeviation="1.8"/>
        </filter>
        <clipPath id="{{ $u }}clip"><circle cx="100" cy="100" r="100"/></clipPath>
    </defs>

    <circle class="sg-bg" cx="100" cy="100" r="100" fill="#f1e2b8"/>

    <g clip-path="url(#{{ $u }}clip)">
        <g class="sg-body">
            {{-- cream plaid flannel shirt, open at the neck --}}
            <path d="M22 214 C28 168 60 152 100 152 C140 152 172 168 178 214 Z" fill="url(#{{ $u }}plaid)"/>
            <path d="M22 214 C28 168 60 152 100 152 C140 152 172 168 178 214 Z" fill="url(#{{ $u }}shade)"/>
            {{-- the open neck --}}
            <path d="M84 152 L100 176 L116 152 Z" fill="#d9a37c"/>
            {{-- placket and buttons --}}
            <path d="M100 176 V214" stroke="#b9aa8e" stroke-width="5"/>
            <path d="M102.5 176 V214" stroke="#8c7b65" stroke-width="1" opacity=".7"/>
            <circle cx="100" cy="189" r="2.1" fill="#f3eee3" stroke="#8c7b65" stroke-width=".8"/>
            <circle cx="100" cy="204" r="2.1" fill="#f3eee3" stroke="#8c7b65" stroke-width=".8"/>
            {{-- collar --}}
            <path d="M66 153 C74 150 82 150 86 151 L99 176 C90 172 80 166 72 162 Z" fill="url(#{{ $u }}plaid)" stroke="#a8977b" stroke-width="1.4" stroke-linejoin="round"/>
            <path d="M134 153 C126 150 118 150 114 151 L101 176 C110 172 120 166 128 162 Z" fill="url(#{{ $u }}plaid)" stroke="#a8977b" stroke-width="1.4" stroke-linejoin="round"/>
        </g>

        <g class="sg-head">
            {{-- ears --}}
            <ellipse cx="48" cy="100" rx="9" ry="13" fill="#e8b597"/>
            <ellipse cx="152" cy="100" rx="9" ry="13" fill="#e8b597"/>
            <path d="M46 94 q5 6 0 12 M154 94 q-5 6 0 12" stroke="#cc8f70" stroke-width="2.5" fill="none" stroke-linecap="round"/>

            {{-- face --}}
            <path d="M100 38 C132 38 153 60 153 94 C153 126 138 152 100 154 C62 152 47 126 47 94 C47 60 68 38 100 38 Z" fill="url(#{{ $u }}skin)"/>
            <ellipse cx="68" cy="112" rx="11" ry="6" fill="#e58f7a" opacity=".22"/>
            <ellipse cx="132" cy="112" rx="11" ry="6" fill="#e58f7a" opacity=".22"/>

            {{-- hair: short at the sides, thick and messy on top, a wavy fringe --}}
            <path d="M48 86 C46 68 50 55 58 46 C60 36 70 28 82 26 C88 18 100 15 109 19 C119 15 131 19 137 28 C147 32 153 44 151 56 C154 66 154 76 152 86 C150 75 147 66 143 60 C141 55 136 52 130 52 C126 47 120 48 116 51 C111 46 104 47 100 51 C95 46 88 47 84 51 C79 47 72 49 69 54 C62 55 57 60 55 64 C51 70 49 78 48 86 Z" fill="url(#{{ $u }}hair)"/>
            <g stroke="#4d3a2d" stroke-width="2.2" fill="none" stroke-linecap="round" opacity=".75">
                <path d="M70 40 q6 -8 14 -7"/><path d="M88 31 q7 -6 15 -3"/><path d="M108 28 q8 -3 15 3"/>
                <path d="M124 38 q7 1 11 7"/><path d="M78 46 q5 -5 11 -4"/><path d="M104 42 q6 -4 12 -1"/>
                <path d="M93 38 q3 -3 7 -2"/>
            </g>
            <path d="M109 19 C114 12 124 11 128 16 C122 16 117 18 114 22 Z" fill="#2a1f18"/>
            {{-- short sides --}}
            <path d="M49 86 C49 76 51 67 55 60 L58 62 C55 69 53 77 52 86 Z M151 86 C151 76 149 67 145 60 L142 62 C145 69 147 77 148 86 Z" fill="#3a2c23" opacity=".85"/>

            {{-- eyebrows: thick, and they do most of the acting --}}
            <g class="sg-brows">
                <path class="sg-brow sg-brow--l" d="M62 69 Q77 62 93 66" stroke="#2a1d15" stroke-width="7" fill="none" stroke-linecap="round"/>
                <path class="sg-brow sg-brow--r" d="M107 66 Q123 62 138 69" stroke="#2a1d15" stroke-width="7" fill="none" stroke-linecap="round"/>
            </g>

            {{-- eyes --}}
            <g class="sg-eyes">
                <ellipse class="sg-white" cx="79" cy="89" rx="9.5" ry="9" fill="#fff"/>
                <ellipse class="sg-white" cx="121" cy="89" rx="9.5" ry="9" fill="#fff"/>
                <path d="M69 85 Q79 77 89 85 M111 85 Q121 77 131 85" stroke="#5a3a28" stroke-width="2.4" fill="none" stroke-linecap="round"/>
                <g class="sg-pupils">
                    <circle cx="79" cy="90" r="5.2" fill="#6a4328"/>
                    <circle cx="121" cy="90" r="5.2" fill="#6a4328"/>
                    <circle class="sg-dot" cx="79" cy="90" r="2.7" fill="#140d09"/>
                    <circle class="sg-dot" cx="121" cy="90" r="2.7" fill="#140d09"/>
                    <circle cx="81" cy="88" r="1.4" fill="#fff"/>
                    <circle cx="123" cy="88" r="1.4" fill="#fff"/>
                </g>
            </g>

            {{-- nose --}}
            <path d="M100 92 C96 104 90 111 93 116 C96 121 106 121 109 116" stroke="#c98b6b" stroke-width="3.2" fill="none" stroke-linecap="round"/>
            <ellipse cx="101" cy="113" rx="8" ry="4.5" fill="#dc9f80" opacity=".35"/>

            {{-- black rectangular frames, the heavier rim on top --}}
            <g class="sg-glasses">
                <g stroke="#15110f" stroke-width="3.8" fill="rgba(205,225,255,.12)" stroke-linejoin="round">
                    <rect x="57" y="77" width="41" height="25" rx="4"/>
                    <rect x="102" y="77" width="41" height="25" rx="4"/>
                </g>
                <path d="M58 78.5 H97 M103 78.5 H142" stroke="#15110f" stroke-width="3.2" stroke-linecap="round"/>
                <path d="M98 84 Q100 81 102 84 M57 84 L47 88 M143 84 L153 88" stroke="#15110f" stroke-width="3.6" fill="none" stroke-linecap="round"/>
                <path d="M64 98 L75 81 M109 98 L120 81" stroke="#fff" stroke-width="2.4" opacity=".28" stroke-linecap="round"/>
            </g>

            {{-- beard --}}
            <path d="M48 98 C48 136 70 162 100 162 C130 162 152 136 152 98 C150 110 145 118 138 122 C131 126 124 124 118 125 C112 126 107 124 100 124 C93 124 88 126 82 125 C76 124 69 126 62 122 C55 118 50 110 48 98 Z" fill="url(#{{ $u }}beard)"/>
            <g stroke="#5a4535" stroke-width="1.8" stroke-linecap="round" opacity=".6">
                <path d="M60 128 l3 6 M70 142 l2 6 M84 152 l1 5 M116 152 l-1 5 M130 142 l-2 6 M140 128 l-3 6 M100 154 v5 M92 156 l0 4 M108 156 l0 4"/>
            </g>

            {{-- mouths: one is shown per state --}}
            <g class="sg-mouths">
                <path class="sg-m sg-m--flat" d="M88 135 Q100 138 112 134" stroke="#c47a68" stroke-width="5" fill="none" stroke-linecap="round"/>
                <g class="sg-m sg-m--grin">
                    <path d="M83 131 Q100 154 117 131 Z" fill="#5c2420"/>
                    <path d="M86 132 Q100 139 114 132 L113 135 Q100 141 87 135 Z" fill="#fff"/>
                </g>
                <ellipse class="sg-m sg-m--o" cx="100" cy="138" rx="6" ry="8" fill="#4d1d19"/>
                <path class="sg-m sg-m--sad" d="M87 140 Q100 131 113 140" stroke="#b86c5c" stroke-width="5" fill="none" stroke-linecap="round"/>
                <ellipse class="sg-m sg-m--talk" cx="100" cy="137" rx="8" ry="4" fill="#4d1d19"/>
            </g>
            {{-- moustache, over the mouth --}}
            <path d="M76 127 C86 118 114 118 124 127 C118 131 108 129 100 128.5 C92 129 82 131 76 127 Z" fill="#3a2b21"/>

            {{-- sweat, for when it goes wrong --}}
            <path class="sg-sweat" d="M146 62 C141 71 140 76 146 78 C152 76 151 71 146 62 Z" fill="#8ec5f2"/>
        </g>

        {{-- vaping: a Lost Mary BM600, triple mango. He always is. --}}
        <g class="sg-vape">
            <g class="sg-cloud-set" filter="url(#{{ $u }}soft)">
                <circle class="sg-cloud sg-cloud--1" cx="98" cy="134" r="12" fill="#f7f9fb"/>
                <circle class="sg-cloud sg-cloud--2" cx="92" cy="132" r="10" fill="#eef2f6"/>
                <circle class="sg-cloud sg-cloud--3" cx="104" cy="131" r="9" fill="#fbfcfd"/>
                <circle class="sg-cloud sg-cloud--4" cx="98" cy="128" r="12" fill="#f3f6f9"/>
                <circle class="sg-cloud sg-cloud--5" cx="95" cy="133" r="8" fill="#ffffff"/>
                <circle class="sg-cloud sg-cloud--6" cx="101" cy="130" r="7" fill="#eaeef3"/>
            </g>
            <g class="sg-vape-arm">
                <path d="M156 230 C156 202 148 182 136 166" stroke="#9a8a72" stroke-width="29" fill="none" stroke-linecap="round"/>
                <path d="M156 230 C156 202 148 182 136 166" stroke="url(#{{ $u }}plaid)" stroke-width="24" fill="none" stroke-linecap="round"/>
                <path d="M146 214 C145 198 140 186 132 176" stroke="#8c7b65" stroke-width="2" fill="none" stroke-linecap="round" opacity=".6"/>
                <path d="M143 173 l-10 -10" stroke="#b9aa8e" stroke-width="6" stroke-linecap="round"/>
                <g transform="translate(108 140) rotate(-58)">
                    <rect x="-4.6" y="-9" width="9.2" height="11" rx="3" fill="#f1e38a"/>
                    <rect x="-11" y="0" width="22" height="33" rx="5.5" fill="url(#{{ $u }}mary)"/>
                    <rect x="-9" y="2" width="4" height="29" rx="2" fill="#fff" opacity=".18"/>
                    <rect x="-1.4" y="5" width="2.8" height="19" rx="1" fill="#fff" opacity=".8"/>
                    <circle class="sg-led" cx="0" cy="30" r="2.4" fill="#fff6c9"/>
                </g>
                <ellipse cx="133" cy="159" rx="12" ry="10" fill="#eab990"/>
                <path d="M125 156 q7 -4 15 0 M124 161 q8 -3 16 1" stroke="#c98a63" stroke-width="2" fill="none" stroke-linecap="round"/>
                <ellipse cx="123" cy="154" rx="5" ry="3.3" fill="#eab990" transform="rotate(-30 123 154)"/>
            </g>
        </g>

        {{-- rubbing hands, for a good result --}}
        <g class="sg-rub">
            <path d="M28 214 C34 188 54 172 76 166 L84 186 C66 192 54 202 50 214 Z" fill="url(#{{ $u }}plaid)" stroke="#a8977b" stroke-width="1.2"/>
            <path d="M172 214 C166 188 146 172 124 166 L116 186 C134 192 146 202 150 214 Z" fill="url(#{{ $u }}plaid)" stroke="#a8977b" stroke-width="1.2"/>
            <g class="sg-hand sg-hand--l">
                <path d="M72 164 C80 156 100 156 104 166 C106 176 96 186 84 186 C74 186 66 176 72 164 Z" fill="#e8b48c"/>
                <path d="M80 164 q10 -3 20 2 M78 171 q11 -3 23 2 M78 178 q10 -2 20 1" stroke="#c98a63" stroke-width="2.2" fill="none" stroke-linecap="round"/>
            </g>
            <g class="sg-hand sg-hand--r">
                <path d="M128 162 C120 154 100 154 96 164 C94 174 104 184 116 184 C126 184 134 174 128 162 Z" fill="#f0c29c"/>
                <path d="M120 162 q-10 -3 -20 2 M122 169 q-11 -3 -23 2 M122 176 q-10 -2 -20 1" stroke="#c98a63" stroke-width="2.2" fill="none" stroke-linecap="round"/>
            </g>
        </g>
    </g>
</svg>
