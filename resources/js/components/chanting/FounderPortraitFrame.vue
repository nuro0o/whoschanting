<template>
    <span class="founder-regalia" aria-hidden="true">
        <span class="founder-regalia__metal"></span>
        <span class="founder-regalia__light"></span>
        <svg class="founder-regalia__crest" viewBox="0 0 48 30" fill="none">
            <path
                d="m4 9 10 5L24 2l10 12 10-5-4 18H8Z"
                fill="#573e20"
                stroke="#ecd29a"
                stroke-width="1.5"
                stroke-linejoin="round"
            />
            <path d="m9 12 7 6 8-12 8 12 7-6-2 11H11Z" fill="#c39b51" />
            <path d="m16 18 8-12 8 12-8-4Z" fill="#ffe5a5" />
            <path d="M10 25h28" stroke="#f4dca0" stroke-width="2" />
            <path d="m24 14 5 6-5 7-5-7Z" fill="#153f33" stroke="#f6d99b" />
            <path
                class="founder-regalia__gem"
                d="m24 16 3 4-3 4-3-4Z"
                fill="#8de5b4"
            />
            <path d="m24 16 3 4h-3Z" fill="#e0ffe0" />
        </svg>
        <span
            class="founder-regalia__clasp founder-regalia__clasp--left"
        ></span>
        <span
            class="founder-regalia__clasp founder-regalia__clasp--right"
        ></span>
        <span class="founder-regalia__seal"></span>
    </span>
</template>

<style scoped>
.founder-regalia {
    position: absolute;
    z-index: 3;
    inset: -5.5%;
    border: 1px solid #d1ad68;
    border-radius: inherit;
    box-shadow:
        0 1px 0 1px #292718,
        0 0 0 2px #a47d3c80,
        inset 0 0 0 1px #fff0b88c,
        inset 0 0 0 clamp(2px, 3cqi, 4px) #1b493c,
        inset 0 0 0 clamp(3px, 4.5cqi, 6px) #c5a76c;
    pointer-events: none;
}
.founder-regalia__metal,
.founder-regalia__light {
    position: absolute;
    inset: 0;
    padding: clamp(2px, 3.5cqi, 5px);
    border-radius: inherit;
    mask:
        linear-gradient(#fff 0 0) content-box,
        linear-gradient(#fff 0 0);
    mask-composite: exclude;
}
.founder-regalia__metal {
    background: linear-gradient(
        140deg,
        #fff1bc 0%,
        #bd954d 15%,
        #344b31 29%,
        #1b382d 44%,
        #ddba70 61%,
        #594826 72%,
        #f2d894 87%,
        #76602f 100%
    );
}
.founder-regalia__metal::after {
    content: '';
    position: absolute;
    inset: 0;
    border: 1px dashed #fff4c257;
    border-radius: inherit;
}
.founder-regalia__light {
    overflow: hidden;
}
.founder-regalia__light::after {
    content: '';
    position: absolute;
    inset: -100%;
    background: linear-gradient(
        115deg,
        transparent 42%,
        #fff8d4b3 49%,
        #fffef2 50%,
        #fff8d433 53%,
        transparent 60%
    );
    animation: founder-metal-pass 9s ease-in-out infinite;
}
.founder-regalia__crest {
    position: absolute;
    top: -15%;
    left: 31%;
    width: 38%;
    height: 27%;
    overflow: visible;
    filter: drop-shadow(0 1px 1px #071810cc);
}
.founder-regalia__gem {
    animation: founder-jewel-light 6s ease-in-out infinite;
}
.founder-regalia__clasp {
    position: absolute;
    top: 45%;
    width: 7%;
    height: 16%;
    background: linear-gradient(
        125deg,
        #ffebb2,
        #bd9955 35%,
        #59613a 48%,
        #d2ae69 65%,
        #fff0b6
    );
    clip-path: polygon(
        50% 0,
        100% 30%,
        70% 50%,
        100% 70%,
        50% 100%,
        0 70%,
        30% 50%,
        0 30%
    );
}
.founder-regalia__clasp--left {
    left: -2.5%;
}
.founder-regalia__clasp--right {
    right: -2.5%;
}
.founder-regalia__seal {
    position: absolute;
    bottom: -3.5%;
    left: 45.5%;
    width: 9%;
    aspect-ratio: 1;
    border: 1px solid #e4c484;
    background: conic-gradient(
        from 45deg,
        #75bc92,
        #143e31,
        #537f55,
        #a9e5b7,
        #75bc92
    );
    transform: rotate(45deg);
    box-shadow: 1px 1px 2px #071810;
}
:global(button:hover .founder-regalia__light),
:global(button:focus-visible .founder-regalia__light) {
    filter: brightness(1.35);
}
@keyframes founder-metal-pass {
    0%,
    52% {
        transform: translateX(-65%);
        opacity: 0;
    }
    58% {
        opacity: 0.8;
    }
    77% {
        transform: translateX(65%);
        opacity: 0.8;
    }
    82%,
    100% {
        transform: translateX(65%);
        opacity: 0;
    }
}
@keyframes founder-jewel-light {
    0%,
    100% {
        opacity: 0.55;
    }
    50% {
        opacity: 1;
    }
}
@media (prefers-reduced-motion: reduce) {
    .founder-regalia__light::after,
    .founder-regalia__gem {
        animation: none;
    }
    .founder-regalia__light::after {
        opacity: 0;
    }
}
</style>
