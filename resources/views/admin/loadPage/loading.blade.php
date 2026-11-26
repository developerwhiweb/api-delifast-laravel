<style>
    .loading-indicator {
        --loading-background: #021127;
        --loading-color: #FFFFFF;
        --loading-wheel-stroke: var(--loading-color);
        --loading-muted: rgba(255, 255, 255, 0.64);
        --loading-track: rgba(255, 255, 255, 0.22);
        --loading-surface: #0b1a31;
        --loading-surface-raised: #14243b;
        --loading-accent: #fb6c04;
        --loading-logo-filter: brightness(0) invert(1);
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-color: var(--loading-background);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 99999;
        font-family: 'Roboto', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    .loading-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 20px;
    }

    .delivery-scene {
        position: relative;
        width: min(360px, 90vw);
        height: 190px;
        margin-bottom: 12px;
        overflow: hidden;
    }

    .delivery-road {
        position: absolute;
        bottom: 24px;
        left: 0;
        width: 100%;
        height: 2px;
        background: repeating-linear-gradient(
            to right,
            var(--loading-track) 0 18px,
            transparent 18px 34px
        );
        animation: road-scroll 0.65s linear infinite;
    }

    .delivery-vehicle {
        position: absolute;
        bottom: 18px;
        left: 0;
        width: 270px;
        height: 150px;
        animation: delivery-drive 2.8s linear infinite;
    }

    .delivery-vehicle-body {
        position: relative;
        width: 100%;
        height: 100%;
        animation: delivery-bounce 0.3s ease-in-out infinite alternate;
    }

    .delivery-scooter-art {
        display: block;
        width: 100%;
        height: 100%;
        overflow: visible;
    }

    .delivery-speed-lines {
        fill: none;
        stroke: var(--loading-muted);
        stroke-width: 4;
        stroke-linecap: round;
    }

    .delivery-cargo {
        fill: var(--loading-surface);
        stroke: var(--loading-accent);
        stroke-width: 3;
    }

    .delivery-cargo-lid {
        fill: none;
        stroke: var(--loading-accent);
        stroke-width: 3;
        stroke-linecap: round;
    }

    .delivery-frame,
    .delivery-handlebar {
        fill: none;
        stroke: var(--loading-accent);
        stroke-width: 7;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .delivery-frame-detail,
    .delivery-seat {
        fill: none;
        stroke: var(--loading-color);
        stroke-width: 6;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .delivery-scooter-body {
        fill: var(--loading-accent);
    }

    .delivery-wheel {
        transform-box: fill-box;
        transform-origin: center;
        animation: wheel-spin 0.55s linear infinite;
    }

    .delivery-wheel-tire {
        fill: var(--loading-surface-raised);
        stroke: var(--loading-wheel-stroke);
        stroke-width: 5;
    }

    .delivery-wheel-spokes {
        fill: none;
        stroke: var(--loading-accent);
        stroke-width: 2;
        stroke-linecap: round;
    }

    .delivery-wheel-hub {
        fill: var(--loading-accent);
    }

    .delivery-brand-logo {
        position: absolute;
        top: 44px;
        left: 72px;
        width: 72px;
        height: auto;
        filter: var(--loading-logo-filter);
        z-index: 1;
    }

    /* Mensagem */
    .loading-title {
        color: var(--loading-color);
        font-size: 1.15rem;
        font-weight: 500;
        letter-spacing: 0.5px;
        margin: 0 0 10px 0;
        animation: pulse-text 2s ease-in-out infinite;
    }

    /* Pontos de carregamento abaixo do texto */
    .loading-dots {
        display: flex;
        gap: 6px;
        justify-content: center;
    }

    .loading-dots span {
        width: 6px;
        height: 6px;
        background-color: var(--loading-color);
        border-radius: 50%;
        animation: dots-bounce 1.4s infinite ease-in-out both;
    }

    .loading-dots span:nth-child(1) { animation-delay: -0.32s; }
    .loading-dots span:nth-child(2) { animation-delay: -0.16s; }

    html[data-bs-theme="light"] .loading-indicator {
        --loading-background: #fdf3e4;
        --loading-color: #021127;
        --loading-muted: rgba(2, 17, 39, 0.64);
        --loading-track: rgba(2, 17, 39, 0.22);
        --loading-surface: #f5e8d6;
        --loading-surface-raised: #fff9ef;
        --loading-wheel-stroke: #021127;
        --loading-logo-filter: none;
    }

    @keyframes delivery-drive {
        0% { transform: translateX(-280px); opacity: 0; }
        8% { opacity: 1; }
        88% { opacity: 1; }
        100% { transform: translateX(370px); opacity: 0; }
    }

    @keyframes delivery-bounce {
        from { transform: translateY(0); }
        to { transform: translateY(-2px); }
    }

    @keyframes wheel-spin {
        to { transform: rotate(360deg); }
    }

    @keyframes road-scroll {
        to { background-position: -34px 0; }
    }

    @keyframes pulse-text {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.75; }
    }

    @keyframes dots-bounce {
        0%, 80%, 100% { transform: scale(0.4); opacity: 0.3; }
        40% { transform: scale(1); opacity: 1; }
    }

    @media (prefers-reduced-motion: reduce) {
        .delivery-vehicle,
        .delivery-vehicle-body,
        .delivery-road,
        .loading-title,
        .loading-dots span {
            animation: none;
        }

        .delivery-vehicle {
            left: calc(50% - 135px);
            opacity: 1;
        }
    }
</style>

<div id="loading-indicator" class="loading-indicator" role="status" aria-live="polite">
    <div class="loading-content">
        <div class="delivery-scene" aria-hidden="true">
            <div class="delivery-road"></div>
            <div class="delivery-vehicle">
                <div class="delivery-vehicle-body">
                    <svg class="delivery-scooter-art" viewBox="0 0 270 150" xmlns="http://www.w3.org/2000/svg">
                        <path class="delivery-speed-lines" d="M12 91h28M24 105h18" />
                        <rect class="delivery-cargo" x="62" y="27" width="92" height="59" rx="9" />
                        <path class="delivery-cargo-lid" d="M73 27v-6h70v6" />
                        <path class="delivery-frame" d="M75 119h39l26-43h43l33 43" />
                        <path class="delivery-scooter-body" d="M103 91c3-12 11-18 25-18h12l15 24-19 18h-27c-8-4-10-13-6-24Z" />
                        <path class="delivery-frame-detail" d="M137 76h-18l-8-9M177 76h19l19 43" />
                        <path class="delivery-seat" d="M126 65h31" />
                        <path class="delivery-handlebar" d="m193 76 9-14h17m-11 0 8 8" />
                        <path class="delivery-frame-detail" d="M109 119h46" />
                        <g class="delivery-wheel">
                            <circle class="delivery-wheel-tire" cx="75" cy="119" r="20" />
                            <path class="delivery-wheel-spokes" d="M75 104v30m-15-15h30m-25-10 21 21m0-21-21 21" />
                            <circle class="delivery-wheel-hub" cx="75" cy="119" r="4" />
                        </g>
                        <g class="delivery-wheel">
                            <circle class="delivery-wheel-tire" cx="216" cy="119" r="20" />
                            <path class="delivery-wheel-spokes" d="M216 104v30m-15-15h30m-25-10 21 21m0-21-21 21" />
                            <circle class="delivery-wheel-hub" cx="216" cy="119" r="4" />
                        </g>
                    </svg>
                    <img class="delivery-brand-logo" src="{{ asset('build/admin/images/whi-green-horizontal.png') }}" alt="" />
                </div>
            </div>
        </div>

        <h4 class="loading-title">
            {{ __('dashboard.message_sinc_load') }}
        </h4>

        <div class="loading-dots">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
</div>