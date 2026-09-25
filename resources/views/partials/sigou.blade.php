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
            <ellipse cx="50" cy="99" rx="9" ry="14" fill="#e8b597"/>
            <ellipse cx="150" cy="99" rx="9" ry="14" fill="#e8b597"/>
            <path d="M48 93 q5 6 0 12 M152 93 q-5 6 0 12" stroke="#cc8f70" stroke-width="2.5" fill="none" stroke-linecap="round"/>

            {{-- face: a long oval, not a round one --}}
            <path d="M100 32 C131 32 150 57 150 92 C150 126 135 153 100 155 C65 153 50 126 50 92 C50 57 69 32 100 32 Z" fill="url(#{{ $u }}skin)"/>
            <ellipse cx="68" cy="110" rx="10" ry="5.5" fill="#e58f7a" opacity=".2"/>
            <ellipse cx="132" cy="110" rx="10" ry="5.5" fill="#e58f7a" opacity=".2"/>

            {{-- hair: dense, dark, short at the sides, textured on top, a
                 fairly straight hairline --}}
            <path d="M50 86 C47 66 51 50 60 41 C61 33 67 27 75 27 C78 20 86 17 93 20 C98 14 107 14 111 19 C118 15 127 19 131 25 C138 25 144 31 144 39 C150 47 153 64 150 86 C149 76 146 67 142 61 C138 56 131 54 124 53 C117 51 108 52 100 52 C92 52 83 51 76 53 C69 54 62 56 58 61 C54 67 51 76 50 86 Z" fill="url(#{{ $u }}hair)"/>
            <g stroke="#4d3a2d" stroke-width="2.2" fill="none" stroke-linecap="round" opacity=".7">
                <path d="M68 42 q6 -7 13 -6"/><path d="M86 33 q7 -5 14 -3"/><path d="M106 31 q8 -3 14 3"/>
                <path d="M124 40 q6 1 10 6"/><path d="M78 47 q5 -4 10 -3"/><path d="M100 43 q6 -4 12 -1"/>
            </g>

            {{-- eyebrows: thick, dark, straight, sitting low on the frames --}}
            <g class="sg-brows">
                <path class="sg-brow sg-brow--l" d="M59 72 Q76 66 94 69.5" stroke="#241911" stroke-width="7.5" fill="none" stroke-linecap="round"/>
                <path class="sg-brow sg-brow--r" d="M106 69.5 Q124 66 141 72" stroke="#241911" stroke-width="7.5" fill="none" stroke-linecap="round"/>
            </g>

            {{-- eyes, heavy-lidded: his "are you serious" look --}}
            <g class="sg-eyes">
                <ellipse class="sg-white" cx="77" cy="88" rx="8.5" ry="7.5" fill="#fff"/>
                <ellipse class="sg-white" cx="123" cy="88" rx="8.5" ry="7.5" fill="#fff"/>
                <g class="sg-pupils">
                    <circle cx="77" cy="89" r="4.6" fill="#5e3b24"/>
                    <circle cx="123" cy="89" r="4.6" fill="#5e3b24"/>
                    <circle class="sg-dot" cx="77" cy="89" r="2.4" fill="#140d09"/>
                    <circle class="sg-dot" cx="123" cy="89" r="2.4" fill="#140d09"/>
                    <circle cx="78.8" cy="87.3" r="1.2" fill="#fff"/>
                    <circle cx="124.8" cy="87.3" r="1.2" fill="#fff"/>
                </g>
                <g class="sg-lids">
                    <path d="M67.5 86.5 Q77 82.5 86.5 86.5 L86.5 79 L67.5 79 Z" fill="#efc7aa"/>
                    <path d="M113.5 86.5 Q123 82.5 132.5 86.5 L132.5 79 L113.5 79 Z" fill="#efc7aa"/>
                    <path d="M67.5 86.5 Q77 82.5 86.5 86.5 M113.5 86.5 Q123 82.5 132.5 86.5" stroke="#4a3326" stroke-width="2" fill="none" stroke-linecap="round"/>
                </g>
            </g>

            {{-- nose: a proper one, broad at the tip --}}
            <path d="M99 88 C98 99 95 106 93 111" stroke="#cf9476" stroke-width="2.8" fill="none" stroke-linecap="round"/>
            <path d="M93 111 C90 115 92 120 97 120 C99 121.5 103 121.5 105 120 C110 120 112 115 109 111" stroke="#cf9476" stroke-width="2.8" fill="none" stroke-linecap="round"/>
            <ellipse cx="102" cy="114" rx="6" ry="4" fill="#fbe2d0" opacity=".45"/>

            {{-- black rectangular frames, wide and shallow, heavier on top --}}
            <g class="sg-glasses">
                <g stroke="#15110f" stroke-width="3.6" fill="rgba(205,225,255,.12)" stroke-linejoin="round">
                    <rect x="54" y="77" width="44" height="22" rx="3.5"/>
                    <rect x="102" y="77" width="44" height="22" rx="3.5"/>
                </g>
                <path d="M55 78.5 H97 M103 78.5 H145" stroke="#15110f" stroke-width="3.4" stroke-linecap="round"/>
                <path d="M98 83 Q100 80 102 83 M54 83 L46 87 M146 83 L154 87" stroke="#15110f" stroke-width="3.4" fill="none" stroke-linecap="round"/>
                <path d="M61 95 L71 81 M109 95 L119 81" stroke="#fff" stroke-width="2.2" opacity=".28" stroke-linecap="round"/>
            </g>

            {{-- beard: full, warm dark brown, up the cheeks and under the chin --}}
            <path d="M50 98 C50 134 70 159 100 161 C130 159 150 134 150 98 C147 107 142 112 136 114 C129 117 123 121 117 123 C111 124.5 106 123.5 100 123.5 C94 123.5 89 124.5 83 123 C77 121 71 117 64 114 C58 112 53 107 50 98 Z" fill="url(#{{ $u }}beard)"/>
            <g stroke="#5c4535" stroke-width="1.8" stroke-linecap="round" opacity=".55">
                <path d="M58 122 l3 6 M66 135 l2 6 M78 146 l1 5 M122 146 l-1 5 M134 135 l-2 6 M142 122 l-3 6 M100 153 v5 M89 151 v5 M111 151 v5"/>
            </g>

            {{-- mouths: one is shown per state --}}
            <g class="sg-mouths">
                <g class="sg-m sg-m--flat">
                    <path d="M89 137 Q100 144 111 137 Q100 140 89 137 Z" fill="#c98372"/>
                    <path d="M88 136 Q100 138.5 112 136" stroke="#9c5a4b" stroke-width="2.4" fill="none" stroke-linecap="round"/>
                </g>
                <g class="sg-m sg-m--grin">
                    <path d="M84 132 Q100 154 116 132 Z" fill="#5c2420"/>
                    <path d="M87 133 Q100 140 113 133 L112 136 Q100 142 88 136 Z" fill="#fff"/>
                </g>
                <ellipse class="sg-m sg-m--o" cx="100" cy="139" rx="6" ry="8" fill="#4d1d19"/>
                <path class="sg-m sg-m--sad" d="M88 141 Q100 133 112 141" stroke="#b86c5c" stroke-width="4.5" fill="none" stroke-linecap="round"/>
                <ellipse class="sg-m sg-m--talk" cx="100" cy="138" rx="8" ry="4" fill="#4d1d19"/>
            </g>
            {{-- moustache, over the mouth --}}
            <path d="M77 128 C86 120 114 120 123 128 C117 132 108 130.5 100 130 C92 130.5 83 132 77 128 Z" fill="#3e2d22"/>

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
