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
            <stop offset="0" stop-color="#f6d0ae"/>
            <stop offset=".72" stop-color="#eab990"/>
            <stop offset="1" stop-color="#d49a73"/>
        </radialGradient>
        <linearGradient id="{{ $u }}beard" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#2f231c"/>
            <stop offset="1" stop-color="#1b130f"/>
        </linearGradient>
        <linearGradient id="{{ $u }}hair" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#3a2b22"/>
            <stop offset="1" stop-color="#1f1612"/>
        </linearGradient>
        <linearGradient id="{{ $u }}knit" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#9a9ea6"/>
            <stop offset="1" stop-color="#7d8189"/>
        </linearGradient>
        <linearGradient id="{{ $u }}mary" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#f4e88e"/>
            <stop offset=".55" stop-color="#eeb88e"/>
            <stop offset="1" stop-color="#df6f82"/>
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
            {{-- grey cable-knit jumper --}}
            <path d="M22 214 C28 168 60 152 100 152 C140 152 172 168 178 214 Z" fill="url(#{{ $u }}knit)"/>
            <g stroke="#6f737b" stroke-width="3.2" fill="none" stroke-linecap="round" opacity=".75">
                <path d="M68 164 q8 7 0 14 q-8 7 0 14 q8 7 0 14"/>
                <path d="M76 164 q-8 7 0 14 q8 7 0 14 q-8 7 0 14"/>
                <path d="M124 164 q8 7 0 14 q-8 7 0 14 q8 7 0 14"/>
                <path d="M132 164 q-8 7 0 14 q8 7 0 14 q-8 7 0 14"/>
                <path d="M100 168 v40" stroke-dasharray="3 5"/>
            </g>
            <path d="M68 154 Q100 176 132 154" stroke="#7a7e86" stroke-width="9" fill="none" stroke-linecap="round"/>
        </g>

        <g class="sg-head">
            {{-- ears --}}
            <ellipse cx="49" cy="102" rx="9" ry="14" fill="#e2a883"/>
            <ellipse cx="151" cy="102" rx="9" ry="14" fill="#e2a883"/>
            <path d="M47 96 q5 6 0 12 M153 96 q-5 6 0 12" stroke="#c7865f" stroke-width="2.5" fill="none" stroke-linecap="round"/>

            {{-- face --}}
            <ellipse cx="100" cy="97" rx="51" ry="57" fill="url(#{{ $u }}skin)"/>
            <ellipse cx="70" cy="113" rx="10" ry="6" fill="#e58f7a" opacity=".28"/>
            <ellipse cx="130" cy="113" rx="10" ry="6" fill="#e58f7a" opacity=".28"/>

            {{-- hair: short sides, messy on top with a flick at the front --}}
            <path d="M49 88 C46 60 54 40 70 32 C74 24 84 20 92 24 C98 16 110 16 116 22 C124 18 136 24 138 32 C150 40 155 62 151 88 C150 74 147 62 142 52 C132 45 118 44 104 42 C90 44 74 45 62 50 C55 60 51 72 49 88 Z" fill="url(#{{ $u }}hair)"/>
            <path d="M86 44 C83 31 94 21 110 23 C103 27 99 33 98 43 Z" fill="#2c2019"/>
            <g stroke="#4a382c" stroke-width="2.4" fill="none" stroke-linecap="round" opacity=".75">
                <path d="M70 42 q8 -9 18 -9"/><path d="M112 30 q13 -2 21 9"/><path d="M60 58 q2 -8 8 -12"/><path d="M140 58 q-2 -8 -8 -12"/>
            </g>

            {{-- eyebrows: thick, and they do most of the acting --}}
            <g class="sg-brows">
                <path class="sg-brow sg-brow--l" d="M63 66 Q78 56 93 63" stroke="#21170f" stroke-width="7.5" fill="none" stroke-linecap="round"/>
                <path class="sg-brow sg-brow--r" d="M107 63 Q122 56 137 66" stroke="#21170f" stroke-width="7.5" fill="none" stroke-linecap="round"/>
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
            <path d="M100 94 C95 106 91 112 94 116 C97 120 105 120 108 116" stroke="#c4845f" stroke-width="3.2" fill="none" stroke-linecap="round"/>
            <ellipse cx="101" cy="112" rx="7" ry="4" fill="#d7976f" opacity=".35"/>

            {{-- thick black frames --}}
            <g class="sg-glasses">
                <g stroke="#111" stroke-width="6" fill="rgba(205,225,255,.14)" stroke-linejoin="round">
                    <rect x="59" y="75" width="39" height="28" rx="6"/>
                    <rect x="102" y="75" width="39" height="28" rx="6"/>
                </g>
                <path d="M98 85 Q100 81 102 85 M59 83 L48 88 M141 83 L152 88" stroke="#111" stroke-width="5" fill="none" stroke-linecap="round"/>
                <path d="M66 99 L78 79 M110 99 L122 79" stroke="#fff" stroke-width="3" opacity=".3" stroke-linecap="round"/>
            </g>

            {{-- beard --}}
            <path d="M49 100 C49 142 72 170 100 170 C128 170 151 142 151 100 C148 114 143 122 135 126 C125 120 75 120 65 126 C57 122 52 114 49 100 Z" fill="url(#{{ $u }}beard)"/>
            <g stroke="#3d2e25" stroke-width="2" stroke-linecap="round" opacity=".7">
                <path d="M62 136 l3 7 M74 150 l2 7 M88 158 l1 6 M112 158 l-1 6 M126 150 l-2 7 M138 136 l-3 7 M100 162 v6"/>
            </g>

            {{-- mouths: one is shown per state --}}
            <g class="sg-mouths">
                <path class="sg-m sg-m--flat" d="M86 135 Q100 140 114 134" stroke="#b86c5c" stroke-width="5" fill="none" stroke-linecap="round"/>
                <g class="sg-m sg-m--grin">
                    <path d="M83 131 Q100 154 117 131 Z" fill="#5c2420"/>
                    <path d="M86 132 Q100 139 114 132 L113 135 Q100 141 87 135 Z" fill="#fff"/>
                </g>
                <ellipse class="sg-m sg-m--o" cx="100" cy="138" rx="6" ry="8" fill="#4d1d19"/>
                <path class="sg-m sg-m--sad" d="M87 140 Q100 131 113 140" stroke="#b86c5c" stroke-width="5" fill="none" stroke-linecap="round"/>
                <ellipse class="sg-m sg-m--talk" cx="100" cy="137" rx="8" ry="4" fill="#4d1d19"/>
            </g>
            {{-- moustache, over the mouth --}}
            <path d="M74 126 C86 116 114 116 126 126 C119 132 108 129 100 128 C92 129 81 132 74 126 Z" fill="#241913"/>

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
                <path d="M156 230 C156 202 148 182 136 166" stroke="#61656c" stroke-width="29" fill="none" stroke-linecap="round"/>
                <path d="M156 230 C156 202 148 182 136 166" stroke="#a3a7ae" stroke-width="24" fill="none" stroke-linecap="round"/>
                <path d="M146 214 C145 198 140 186 132 176" stroke="#8a8e95" stroke-width="3" fill="none" stroke-linecap="round"/>
                <path d="M143 173 l-10 -10" stroke="#8a8e95" stroke-width="6" stroke-linecap="round"/>
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
            <path d="M28 214 C34 188 54 172 76 166 L84 186 C66 192 54 202 50 214 Z" fill="url(#{{ $u }}knit)"/>
            <path d="M172 214 C166 188 146 172 124 166 L116 186 C134 192 146 202 150 214 Z" fill="url(#{{ $u }}knit)"/>
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
