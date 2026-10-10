<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#08090a">
    <meta name="description" content="Discover Aurora GT, a high-performance machine engineered to exhilarate.">
    <title>Velocity — Aurora GT</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>

    <header class="site-header">
        <a class="logo" href="#performance" aria-label="Velocity home">
            <span class="logo-mark" aria-hidden="true">V</span>
            <span>VELOCITY</span>
        </a>

        <nav class="primary-nav" aria-label="Primary navigation">
            <a href="#performance">Performance</a>
            <a href="#technology">Technology</a>
            <a href="#vehicle">Design</a>
            <a class="nav-cta" href="#technology">
                Explore Aurora
                <span aria-hidden="true">↗</span>
            </a>
        </nav>
    </header>

    <main id="main-content">
        <section class="hero" id="performance" aria-labelledby="hero-title">
            <div class="hero-grid" aria-hidden="true"></div>
            <div class="ambient-glow ambient-glow-one" aria-hidden="true"></div>
            <div class="ambient-glow ambient-glow-two" aria-hidden="true"></div>

            <div class="hero-layout">
                <div class="hero-copy">
                    <p class="eyebrow">
                        <span class="eyebrow-dot" aria-hidden="true"></span>
                        Introducing the Aurora GT
                    </p>

                    <h1 id="hero-title">
                        Engineered to
                        <span>exhilarate.</span>
                    </h1>

                    <p class="hero-description">
                        A new expression of grand touring performance. Sculpted by air,
                        sharpened by precision, and powered for the road ahead.
                    </p>

                    <div class="hero-actions">
                        <a class="button button-primary" href="#technology">
                            Explore performance
                            <span aria-hidden="true">↗</span>
                        </a>
                        <a class="text-link" href="#vehicle">
                            Discover the design
                            <span aria-hidden="true">→</span>
                        </a>
                    </div>
                </div>

                <figure class="car-stage" id="vehicle" aria-labelledby="car-title">
                    <div class="stage-meta" aria-hidden="true">
                        <span>AV / 01</span>
                        <span>GRAND TOURER</span>
                    </div>

                    <div class="speed-lines" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>

                    <svg
                        class="car-art"
                        viewBox="0 0 900 480"
                        role="img"
                        aria-labelledby="car-title car-description"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <title id="car-title">Aurora GT sports car</title>
                        <desc id="car-description">
                            A streamlined silver grand touring car with Aurora GT branding.
                        </desc>

                        <defs>
                            <linearGradient id="body-paint" x1="160" y1="150" x2="720" y2="390" gradientUnits="userSpaceOnUse">
                                <stop offset="0" stop-color="#f4f5f7"/>
                                <stop offset="0.3" stop-color="#8d9298"/>
                                <stop offset="0.58" stop-color="#3d4248"/>
                                <stop offset="0.82" stop-color="#16191d"/>
                                <stop offset="1" stop-color="#08090a"/>
                            </linearGradient>

                            <linearGradient id="body-highlight" x1="300" y1="170" x2="580" y2="320" gradientUnits="userSpaceOnUse">
                                <stop offset="0" stop-color="#ffffff" stop-opacity="0.85"/>
                                <stop offset="0.48" stop-color="#d8dce0" stop-opacity="0.12"/>
                                <stop offset="1" stop-color="#ffffff" stop-opacity="0"/>
                            </linearGradient>

                            <linearGradient id="window-glass" x1="380" y1="155" x2="490" y2="270" gradientUnits="userSpaceOnUse">
                                <stop offset="0" stop-color="#52606d"/>
                                <stop offset="0.45" stop-color="#161d24"/>
                                <stop offset="1" stop-color="#07090b"/>
                            </linearGradient>

                            <radialGradient id="wheel-metal" cx="40%" cy="35%" r="65%">
                                <stop offset="0" stop-color="#d9dde1"/>
                                <stop offset="0.5" stop-color="#656b72"/>
                                <stop offset="1" stop-color="#171a1e"/>
                            </radialGradient>

                            <filter id="car-glow" x="-20%" y="-40%" width="140%" height="180%">
                                <feGaussianBlur stdDeviation="9"/>
                            </filter>
                        </defs>

                        <ellipse cx="455" cy="390" rx="370" ry="24" fill="#ff4d2e" opacity="0.16" filter="url(#car-glow)"/>
                        <ellipse cx="455" cy="386" rx="365" ry="19" fill="#000000" opacity="0.85"/>

                        <g class="car">
                            <path
                                d="M63 323C99 286 170 274 248 260L336 178C356 159 384 151 417 151H558C598 151 623 163 658 195L726 257C786 263 822 283 840 315L844 343C826 353 801 357 775 354C765 300 730 270 684 270C632 270 594 304 588 357H302C296 306 260 273 215 273C165 273 126 306 121 354L91 350C72 345 61 335 63 323Z"
                                fill="url(#body-paint)"
                                stroke="#c8cdd2"
                                stroke-opacity="0.34"
                                stroke-width="1.5"
                            />

                            <path
                                d="M67 319C177 284 255 280 351 266H725C779 270 817 288 838 315C743 300 662 296 584 299H244C166 300 110 308 67 319Z"
                                fill="url(#body-highlight)"
                            />

                            <path
                                d="M279 252L355 185C371 171 390 166 418 166H477V252H279Z"
                                fill="url(#window-glass)"
                                stroke="#e3e7ea"
                                stroke-opacity="0.22"
                            />

                            <path
                                d="M490 166H555C587 166 608 176 638 203L684 252H490V166Z"
                                fill="url(#window-glass)"
                                stroke="#e3e7ea"
                                stroke-opacity="0.22"
                            />

                            <path d="M484 174V250" stroke="#090b0e" stroke-width="7"/>
                            <path d="M309 251C421 259 559 259 677 252" fill="none" stroke="#f4f5f7" stroke-opacity="0.28"/>

                            <path
                                d="M335 261C333 289 333 325 338 351M604 260C608 291 609 324 604 352"
                                fill="none"
                                stroke="#0a0c0f"
                                stroke-opacity="0.65"
                            />

                            <path d="M374 284H457" stroke="#e5e7eb" stroke-opacity="0.3" stroke-width="2"/>
                            <path d="M538 284H575" stroke="#e5e7eb" stroke-opacity="0.45" stroke-width="3" stroke-linecap="round"/>

                            <g class="car-brand" aria-hidden="true">
                                <text
                                    x="410"
                                    y="322"
                                    fill="#f7f7f8"
                                    fill-opacity="0.78"
                                    font-family="Arial, Helvetica, sans-serif"
                                    font-size="20"
                                    font-weight="700"
                                    font-style="italic"
                                    letter-spacing="5"
                                >AURORA</text>
                                <text
                                    x="553"
                                    y="322"
                                    fill="#ff4d2e"
                                    font-family="Arial, Helvetica, sans-serif"
                                    font-size="16"
                                    font-weight="800"
                                    font-style="italic"
                                    letter-spacing="1"
                                >GT</text>
                            </g>

                            <path
                                d="M765 277C793 278 813 287 828 302L784 298L765 294V277Z"
                                fill="#fff1d6"
                            />
                            <path d="M765 278L824 296" stroke="#ffb43d" stroke-width="3"/>

                            <path d="M80 314L128 302L154 308L111 322Z" fill="#ff4d2e"/>
                            <path d="M86 315L129 307" stroke="#ffb19f" stroke-width="2"/>

                            <path d="M638 244L668 245L682 257L648 257L632 251Z" fill="#9fa5aa"/>
                            <path d="M639 246L666 247" stroke="#ffffff" stroke-opacity="0.5"/>

                            <path d="M92 342C113 352 126 356 143 357M589 354H772" stroke="#ff4d2e" stroke-width="3" stroke-linecap="round"/>

                            <g class="wheel">
                                <circle cx="215" cy="343" r="66" fill="#070809" stroke="#282c31" stroke-width="3"/>
                                <circle cx="215" cy="343" r="49" fill="#111316" stroke="#383d43" stroke-width="2"/>
                                <circle cx="215" cy="343" r="37" fill="url(#wheel-metal)"/>
                                <circle cx="215" cy="343" r="15" fill="#15181b" stroke="#aeb4ba" stroke-width="2"/>
                                <path d="M215 310V333M215 353V376M182 343H205M225 343H248M192 320L208 337M222 349L238 366M192 366L208 349M222 337L238 320" stroke="#202429" stroke-width="5"/>
                                <path d="M244 316A35 35 0 0 1 248 364" fill="none" stroke="#ff4d2e" stroke-width="6"/>
                            </g>

                            <g class="wheel">
                                <circle cx="684" cy="343" r="66" fill="#070809" stroke="#282c31" stroke-width="3"/>
                                <circle cx="684" cy="343" r="49" fill="#111316" stroke="#383d43" stroke-width="2"/>
                                <circle cx="684" cy="343" r="37" fill="url(#wheel-metal)"/>
                                <circle cx="684" cy="343" r="15" fill="#15181b" stroke="#aeb4ba" stroke-width="2"/>
                                <path d="M684 310V333M684 353V376M651 343H674M694 343H717M661 320L677 337M691 349L707 366M661 366L677 349M691 337L707 320" stroke="#202429" stroke-width="5"/>
                                <path d="M713 316A35 35 0 0 1 717 364" fill="none" stroke="#ff4d2e" stroke-width="6"/>
                            </g>

                            <path d="M94 359C131 380 174 382 214 375" fill="none" stroke="#090a0c" stroke-width="13" stroke-linecap="round"/>
                            <path d="M589 374C632 382 678 380 710 367" fill="none" stroke="#090a0c" stroke-width="13" stroke-linecap="round"/>
                        </g>
                    </svg>

                    <figcaption class="vehicle-label">
                        <span>Aurora</span>
                        <strong>GT</strong>
                    </figcaption>

                    <div class="edition-badge">
                        <span aria-hidden="true"></span>
                        Launch edition / 2026
                    </div>
                </figure>
            </div>

            <div class="performance-specs" id="technology" aria-label="Aurora GT performance specifications">
                <div class="spec-item">
                    <span>0–60 mph</span>
                    <strong><b>3.8</b> sec</strong>
                </div>
                <div class="spec-item">
                    <span>Peak power</span>
                    <strong><b>510</b> hp</strong>
                </div>
                <div class="spec-item">
                    <span>Top speed</span>
                    <strong><b>198</b> mph</strong>
                </div>
                <div class="spec-note">
                    <span class="spec-pulse" aria-hidden="true"></span>
                    Precision-tuned dynamics
                </div>
            </div>
        </section>
    </main>
</body>
</html>
