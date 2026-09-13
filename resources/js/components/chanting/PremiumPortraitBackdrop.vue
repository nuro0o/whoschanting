<script setup lang="ts">
defineProps<{ background: string }>();
</script>

<template>
    <span
        class="premium-backdrop"
        :class="`premium-backdrop--${background}`"
        aria-hidden="true"
    >
        <span class="premium-backdrop__engraving"></span>
        <span class="premium-backdrop__light"></span>
        <span
            class="premium-backdrop__fleck premium-backdrop__fleck--one"
        ></span>
        <span
            class="premium-backdrop__fleck premium-backdrop__fleck--two"
        ></span>
        <span
            class="premium-backdrop__flourish premium-backdrop__flourish--left"
        ></span>
        <span
            class="premium-backdrop__flourish premium-backdrop__flourish--right"
        ></span>
    </span>
</template>

<style scoped>
.premium-backdrop {
    --mat-light: #e7ce91;
    --mat-metal: #ae8a4b;
    --mat-shadow: #17362c;
    position: absolute;
    inset: 0;
    padding: 8%;
    overflow: hidden;
    border-radius: inherit;
    background: linear-gradient(135deg, #102e2680, transparent 40%, #040f15a6);
    mask:
        linear-gradient(#fff 0 0) content-box,
        linear-gradient(#fff 0 0);
    mask-composite: exclude;
    pointer-events: none;
}
.premium-backdrop__engraving {
    position: absolute;
    inset: 2.5%;
    border: 1px solid var(--mat-metal);
    border-radius: inherit;
    box-shadow:
        0 1px 0 #fff3,
        inset 0 1px 2px var(--mat-shadow);
    opacity: 0.85;
}
.premium-backdrop__light {
    position: absolute;
    inset: -100%;
    background: linear-gradient(
        120deg,
        transparent 40%,
        var(--mat-light) 50%,
        transparent 59%
    );
    opacity: 0;
    animation: backdrop-light-pass 13s ease-in-out infinite;
}
.premium-backdrop__flourish {
    position: absolute;
    bottom: 2%;
    width: 13%;
    height: 4%;
    background: linear-gradient(
        90deg,
        var(--mat-metal),
        var(--mat-light),
        var(--mat-metal)
    );
    clip-path: polygon(
        0 50%,
        35% 25%,
        50% 0,
        65% 25%,
        100% 50%,
        65% 75%,
        50% 100%,
        35% 75%
    );
}
.premium-backdrop__flourish--left {
    left: 19%;
}
.premium-backdrop__flourish--right {
    right: 19%;
}
.premium-backdrop__fleck {
    position: absolute;
    width: 3%;
    aspect-ratio: 1;
    border-radius: 50%;
    background: var(--mat-light);
    box-shadow: 0 0 4px var(--mat-light);
    animation: backdrop-fleck-drift 7s ease-in-out infinite;
}
.premium-backdrop__fleck--one {
    top: 31%;
    left: 3%;
}
.premium-backdrop__fleck--two {
    bottom: 21%;
    right: 3%;
    animation-delay: -3.5s;
}
.premium-backdrop--founders_hall {
    background:
        repeating-linear-gradient(
            45deg,
            transparent 0 5px,
            #edd29a24 5px 6px,
            transparent 6px 10px
        ),
        linear-gradient(
            145deg,
            #af8c49a6,
            #193c2acc 28%,
            #091f18b3 65%,
            #a48448a6
        );
}
.premium-backdrop--founders_hall .premium-backdrop__engraving {
    border-style: double;
    border-width: 3px;
}
.premium-backdrop--founders_hall .premium-backdrop__fleck {
    border-radius: 0;
    transform: rotate(45deg);
    animation: backdrop-jewel-glow 7s ease-in-out infinite;
}
.premium-backdrop--moonlit_coven {
    --mat-light: #e5e9ff;
    --mat-metal: #a9a7d8;
    --mat-shadow: #242649;
    background:
        radial-gradient(circle at 22% 9%, #fff 0 0.6px, transparent 1px),
        radial-gradient(circle at 74% 5%, #dee3ff 0 0.8px, transparent 1.4px),
        radial-gradient(circle at 88% 83%, #fff 0 0.7px, transparent 1.2px),
        linear-gradient(
            135deg,
            #b1b6e480,
            #22274499 30%,
            #3b335599 65%,
            #adb8e066
        );
}
.premium-backdrop--moonlit_coven .premium-backdrop__fleck {
    width: 5%;
    border-radius: 0;
    clip-path: polygon(
        50% 0,
        60% 39%,
        100% 50%,
        60% 61%,
        50% 100%,
        40% 61%,
        0 50%,
        40% 39%
    );
}
.premium-backdrop--moonlit_coven .premium-backdrop__flourish {
    width: 8%;
    height: 8%;
    bottom: 0;
    border-radius: 50%;
    clip-path: none;
    background: transparent;
    box-shadow: inset 2px -1px 0 var(--mat-light);
}
.premium-backdrop--harvest_glow {
    --mat-light: #ffd396;
    --mat-metal: #d2965e;
    --mat-shadow: #492c20;
    background:
        repeating-linear-gradient(
            65deg,
            transparent 0 7px,
            #f7c2861a 7px 8px,
            transparent 8px 12px
        ),
        linear-gradient(
            135deg,
            #ce965a99,
            #673c2580 32%,
            #352014b3 67%,
            #d3984e99
        );
}
.premium-backdrop--harvest_glow .premium-backdrop__light {
    animation-delay: -4s;
}
.premium-backdrop--harvest_glow .premium-backdrop__flourish,
.premium-backdrop--wildwood .premium-backdrop__flourish {
    width: 16%;
    height: 6%;
    bottom: 0;
    clip-path: polygon(
        0 50%,
        20% 20%,
        40% 45%,
        50% 0,
        60% 45%,
        80% 20%,
        100% 50%,
        80% 85%,
        55% 60%,
        50% 100%,
        45% 60%,
        20% 85%
    );
}
.premium-backdrop--moonlit {
    --mat-light: #d5f6ff;
    --mat-metal: #8abecb;
    --mat-shadow: #163842;
    background:
        repeating-radial-gradient(
            ellipse at 10% 100%,
            transparent 0 7px,
            #b4e2e438 8px,
            transparent 9px 16px
        ),
        linear-gradient(
            135deg,
            #b3dce080,
            #2b536680 45%,
            #12323b99 70%,
            #a3cdd380
        );
}
.premium-backdrop--moonlit .premium-backdrop__light {
    animation-duration: 17s;
    animation-delay: -6s;
}
.premium-backdrop--moonlit .premium-backdrop__flourish {
    height: 7%;
    width: 16%;
    background: transparent;
    border-bottom: 1px solid var(--mat-light);
    border-radius: 50%;
    clip-path: none;
}
.premium-backdrop--wildwood {
    --mat-light: #d5e89a;
    --mat-metal: #97b07c;
    --mat-shadow: #173425;
    background:
        repeating-linear-gradient(
            115deg,
            transparent 0 6px,
            #b4cb7e21 7px,
            transparent 8px 13px
        ),
        linear-gradient(
            140deg,
            #98b57c73,
            #203f2899 30%,
            #112b20a6 68%,
            #82995880
        );
}
.premium-backdrop--wildwood .premium-backdrop__engraving {
    border-color: #94ae797d;
}
.premium-backdrop--wildwood .premium-backdrop__light {
    animation-duration: 19s;
    animation-delay: -8s;
}
.premium-backdrop--wildwood .premium-backdrop__fleck {
    animation-duration: 9s;
}
@keyframes backdrop-light-pass {
    0%,
    56% {
        transform: translateX(-65%);
        opacity: 0;
    }
    62% {
        opacity: 0.35;
    }
    79% {
        transform: translateX(65%);
        opacity: 0.35;
    }
    84%,
    100% {
        transform: translateX(65%);
        opacity: 0;
    }
}
@keyframes backdrop-fleck-drift {
    0%,
    100% {
        transform: translateY(3px);
        opacity: 0.15;
    }
    50% {
        transform: translateY(-3px);
        opacity: 0.85;
    }
}
@keyframes backdrop-jewel-glow {
    0%,
    100% {
        opacity: 0.35;
    }
    50% {
        opacity: 0.85;
    }
}
@media (prefers-reduced-motion: reduce) {
    .premium-backdrop__light,
    .premium-backdrop__fleck {
        animation: none;
    }
    .premium-backdrop__light {
        opacity: 0;
    }
    .premium-backdrop__fleck {
        opacity: 0.65;
    }
}
</style>
