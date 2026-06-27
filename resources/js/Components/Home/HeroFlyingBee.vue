<template>
    <div class="hero-bee" aria-hidden="true">
        <div class="hero-bee__flight">
            <svg
                class="hero-bee__svg"
                viewBox="0 0 120 96"
                role="img"
                focusable="false"
            >
                <g class="hero-bee__shadow">
                    <ellipse cx="62" cy="72" rx="34" ry="8" />
                </g>

                <g class="hero-bee__bee">
                    <g class="hero-bee__wings">
                        <ellipse
                            class="hero-bee__wing hero-bee__wing--far"
                            cx="55"
                            cy="31"
                            rx="19"
                            ry="12"
                            transform="rotate(-24 55 31)"
                        />
                        <ellipse
                            class="hero-bee__wing hero-bee__wing--near"
                            cx="73"
                            cy="28"
                            rx="22"
                            ry="13"
                            transform="rotate(18 73 28)"
                        />
                    </g>

                    <path
                        class="hero-bee__body"
                        d="M30 54C30 34 45 23 65 27C87 31 101 46 96 62C91 78 70 82 50 76C37 72 30 64 30 54Z"
                    />
                    <path
                        class="hero-bee__stripe hero-bee__stripe--one"
                        d="M50 28C43 36 40 52 43 71"
                    />
                    <path
                        class="hero-bee__stripe hero-bee__stripe--two"
                        d="M67 28C59 39 56 57 60 78"
                    />
                    <path
                        class="hero-bee__stripe hero-bee__stripe--three"
                        d="M82 36C76 46 74 60 78 73"
                    />

                    <path
                        class="hero-bee__head"
                        d="M25 49C25 38 34 30 45 31C55 32 62 40 61 51C60 61 51 68 40 67C31 66 25 59 25 49Z"
                    />
                    <circle class="hero-bee__eye" cx="41" cy="44" r="3.5" />
                    <circle class="hero-bee__eye-shine" cx="42" cy="43" r="1.1" />

                    <path
                        class="hero-bee__smile"
                        d="M36 54C39 57 44 58 48 55"
                    />
                    <path
                        class="hero-bee__antenna"
                        d="M36 33C32 24 26 21 19 23"
                    />
                    <path
                        class="hero-bee__antenna"
                        d="M48 32C49 22 54 17 61 17"
                    />
                    <circle class="hero-bee__antenna-dot" cx="18" cy="23" r="2.4" />
                    <circle class="hero-bee__antenna-dot" cx="62" cy="17" r="2.4" />

                    <path
                        class="hero-bee__tail"
                        d="M96 58L108 64L96 68Z"
                    />
                </g>
            </svg>
        </div>
    </div>
</template>

<style scoped>
.hero-bee {
    /*
     * Настройки декоративной пчелы:
     * --bee-size: размер SVG;
     * --bee-flight-duration: скорость полного цикла;
     * --bee-opacity: прозрачность;
     * --bee-z-index: слой внутри hero;
     * --bee-area-* задают безопасную область полета.
     */
    --bee-size: clamp(54px, 5.4vw, 86px);
    --bee-flight-duration: 16s;
    --bee-opacity: 0.88;
    --bee-z-index: 1;
    --bee-area-top: 8%;
    --bee-area-right: 7%;
    --bee-area-width: min(44vw, 560px);
    --bee-area-height: min(34vh, 300px);

    position: absolute;
    top: var(--bee-area-top);
    right: var(--bee-area-right);
    width: var(--bee-area-width);
    height: var(--bee-area-height);
    z-index: var(--bee-z-index);
    opacity: 0;
    pointer-events: none;
    animation: hero-bee-arrive 0.8s ease 1s forwards;
}

.hero-bee__flight {
    width: var(--bee-size);
    height: var(--bee-size);
    opacity: var(--bee-opacity);
    transform-origin: center;
    will-change: transform;
    animation: hero-bee-flight var(--bee-flight-duration) cubic-bezier(0.44, 0, 0.32, 1) 1s infinite;
}

.hero-bee__svg {
    display: block;
    width: 100%;
    height: 100%;
    overflow: visible;
    filter: drop-shadow(0 10px 14px rgba(85, 52, 16, 0.18));
}

.hero-bee__bee {
    transform-origin: 62px 50px;
    animation: hero-bee-bob 1.9s ease-in-out 1s infinite;
}

.hero-bee__shadow {
    fill: rgba(83, 61, 38, 0.12);
    transform-origin: 62px 72px;
    animation: hero-bee-shadow 1.9s ease-in-out 1s infinite;
}

.hero-bee__wing {
    fill: rgba(255, 255, 255, 0.72);
    stroke: rgba(95, 116, 108, 0.28);
    stroke-width: 2;
    transform-box: fill-box;
}

.hero-bee__wing--near {
    transform-origin: 15% 80%;
    animation: hero-bee-wing-near 0.22s ease-in-out 1s infinite alternate;
}

.hero-bee__wing--far {
    transform-origin: 85% 78%;
    animation: hero-bee-wing-far 0.26s ease-in-out 1s infinite alternate;
}

.hero-bee__body {
    fill: #f8c94b;
    stroke: #2f2516;
    stroke-width: 3;
}

.hero-bee__head,
.hero-bee__tail,
.hero-bee__stripe {
    fill: none;
    stroke: #2f2516;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 5;
}

.hero-bee__head,
.hero-bee__tail {
    fill: #2f2516;
}

.hero-bee__eye {
    fill: #fffaf0;
}

.hero-bee__eye-shine {
    fill: #2f2516;
}

.hero-bee__smile,
.hero-bee__antenna {
    fill: none;
    stroke: #2f2516;
    stroke-linecap: round;
    stroke-width: 3;
}

.hero-bee__antenna-dot {
    fill: #2f2516;
}

@keyframes hero-bee-arrive {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

/* Траекторию меняйте здесь: анимируются только transform/rotate/scale. */
@keyframes hero-bee-flight {
    0% {
        transform: translate3d(78%, 34%, 0) rotate(-8deg) scale(0.94);
    }

    18% {
        transform: translate3d(46%, 6%, 0) rotate(9deg) scale(1);
    }

    38% {
        transform: translate3d(8%, 28%, 0) rotate(-7deg) scale(0.96);
    }

    58% {
        transform: translate3d(34%, 64%, 0) rotate(10deg) scale(1.02);
    }

    78% {
        transform: translate3d(74%, 48%, 0) rotate(-4deg) scale(0.98);
    }

    100% {
        transform: translate3d(78%, 34%, 0) rotate(-8deg) scale(0.94);
    }
}

@keyframes hero-bee-bob {
    0%,
    100% {
        transform: translate3d(0, 0, 0) rotate(-1deg);
    }

    50% {
        transform: translate3d(0, -4px, 0) rotate(2deg);
    }
}

@keyframes hero-bee-shadow {
    0%,
    100% {
        transform: scaleX(0.92);
        opacity: 0.68;
    }

    50% {
        transform: scaleX(1.08);
        opacity: 0.38;
    }
}

@keyframes hero-bee-wing-near {
    from {
        transform: rotate(18deg) scaleY(1);
    }

    to {
        transform: rotate(34deg) scaleY(0.68);
    }
}

@keyframes hero-bee-wing-far {
    from {
        transform: rotate(-24deg) scaleY(0.92);
    }

    to {
        transform: rotate(-38deg) scaleY(0.64);
    }
}

@media (max-width: 960px) {
    .hero-bee {
        --bee-size: clamp(42px, 8vw, 62px);
        --bee-flight-duration: 18s;
        --bee-opacity: 0.72;
        --bee-area-top: 2%;
        --bee-area-right: 4%;
        --bee-area-width: min(50vw, 330px);
        --bee-area-height: 180px;
    }
}

@media (max-width: 600px) {
    .hero-bee {
        --bee-size: 42px;
        --bee-area-top: 1.5%;
        --bee-area-right: 5%;
        --bee-area-width: 180px;
        --bee-area-height: 116px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hero-bee {
        opacity: 1;
        animation: none;
    }

    .hero-bee__flight {
        animation: none;
        transform: translate3d(68%, 24%, 0) rotate(-6deg) scale(0.92);
    }

    .hero-bee__bee,
    .hero-bee__shadow,
    .hero-bee__wing--near,
    .hero-bee__wing--far {
        animation: none;
    }
}
</style>
