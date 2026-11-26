<!DOCTYPE html>
<html lang="pt-BR" data-layout-mode="default">
    <head>
        <meta charset="utf-8" />
        <title>{{ env('APP_NAME') }} - Acesso ao painel</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="Acesso seguro ao painel de gerenciamento DeliFast">
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <link rel="shortcut icon" href="{{ asset('build/admin/images/favicon.png') }}">
        <link href="{{ asset('build/admin/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ asset('build/admin/css/app.min.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ asset('build/admin/css/icons.min.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ asset('build/admin/css/custom.css') }}" rel="stylesheet" type="text/css" />
        <script src="{{ asset('build/admin/js/head.js') }}"></script>
        <style>
            html,
            body {
                min-height: 100%;
            }

            body.authentication-bg {
                min-height: 100vh;
                margin: 0;
                overflow-x: hidden;
                background: #021127 !important;
                color: #021127;
            }

            .auth-page {
                min-height: 100vh;
                padding: clamp(16px, 4vw, 48px);
                display: flex;
                align-items: center;
                justify-content: center;
                background:
                    radial-gradient(ellipse at 12% 12%, rgba(251, 108, 4, 0.16), transparent 34%),
                    #021127;
            }

            .auth-shell {
                width: min(1120px, 100%);
                min-height: min(680px, calc(100vh - 48px));
                display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(380px, 0.88fr);
                overflow: hidden;
                border: 1px solid rgba(253, 243, 228, 0.1);
                border-radius: 28px;
                background: #fff9ef;
                box-shadow: 0 32px 90px rgba(0, 0, 0, 0.3);
            }

            .auth-showcase {
                position: relative;
                isolation: isolate;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                min-height: 100%;
                overflow: hidden;
                padding: clamp(28px, 5vw, 58px);
                color: #fdf3e4;
                background:
                    radial-gradient(circle at 88% 12%, rgba(251, 108, 4, 0.25), transparent 25%),
                    linear-gradient(145deg, #0b1a31, #021127 72%);
            }

            .auth-showcase::before,
            .auth-showcase::after {
                position: absolute;
                z-index: -1;
                content: "";
                pointer-events: none;
            }

            .auth-showcase::before {
                right: -130px;
                bottom: -180px;
                width: 420px;
                height: 420px;
                border: 1px solid rgba(253, 243, 228, 0.11);
                border-radius: 50%;
                box-shadow: 0 0 0 34px rgba(253, 243, 228, 0.025), 0 0 0 70px rgba(253, 243, 228, 0.02);
            }

            .auth-showcase::after {
                top: 46%;
                right: -8%;
                left: 12%;
                height: 1px;
                border-top: 1px dashed rgba(251, 108, 4, 0.48);
                transform: rotate(-16deg);
            }

            .auth-brand-plate {
                display: inline-flex;
                align-items: center;
                width: fit-content;
                padding: 10px 15px;
                border-radius: 14px;
                background: #fdf3e4;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            }

            .auth-brand-plate img {
                display: block;
                width: clamp(150px, 18vw, 220px);
                height: auto;
            }

            .auth-showcase-copy {
                max-width: 430px;
                margin: auto 0;
                padding: 56px 0 38px;
            }

            .auth-kicker {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: #ffb27e;
                font-size: 0.72rem;
                font-weight: 800;
                letter-spacing: 0.14em;
                text-transform: uppercase;
            }

            .auth-showcase h2 {
                max-width: 420px;
                margin: 18px 0 14px;
                color: #fdf3e4;
                font-size: clamp(2rem, 4vw, 3.2rem);
                font-weight: 800;
                letter-spacing: -0.045em;
                line-height: 1.08;
            }

            .auth-showcase-copy p {
                max-width: 370px;
                margin: 0;
                color: #bdc9d8;
                font-size: 1rem;
                line-height: 1.7;
            }

            .auth-route-card {
                position: relative;
                z-index: 1;
                display: flex;
                align-items: center;
                gap: 16px;
                width: min(355px, 100%);
                padding: 17px 19px;
                border: 1px solid rgba(253, 243, 228, 0.14);
                border-radius: 18px;
                background: rgba(15, 34, 58, 0.88);
                box-shadow: 0 18px 45px rgba(0, 0, 0, 0.2);
                backdrop-filter: blur(12px);
            }

            .auth-route-icon {
                display: grid;
                width: 48px;
                height: 48px;
                flex: 0 0 48px;
                place-items: center;
                border-radius: 15px;
                background: #fb6c04;
                color: #021127;
                font-size: 1.55rem;
            }

            .auth-route-copy strong,
            .auth-route-copy span {
                display: block;
            }

            .auth-route-copy strong {
                color: #fdf3e4;
                font-size: 0.9rem;
            }

            .auth-route-copy span {
                margin-top: 4px;
                color: #aebdd0;
                font-size: 0.78rem;
            }

            .auth-showcase-footer {
                position: relative;
                z-index: 1;
                color: #8292a8;
                font-size: 0.75rem;
                letter-spacing: 0.04em;
            }

            .auth-main {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: clamp(28px, 5vw, 64px);
                background: #fff9ef;
                color: #293244;
            }

            .auth-content {
                width: min(100%, 410px);
            }

            .auth-mobile-brand {
                display: none;
                width: fit-content;
                margin-bottom: 32px;
                padding: 8px 12px;
                border-radius: 12px;
                background: #fdf3e4;
            }

            .auth-mobile-brand img {
                width: 170px;
                height: auto;
            }

            .auth-heading {
                margin-bottom: 30px;
            }

            .auth-eyebrow {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 20px;
            }

            .auth-eyebrow-label {
                color: #9a4100;
                font-size: 0.7rem;
                font-weight: 800;
                letter-spacing: 0.12em;
                text-transform: uppercase;
            }

            .auth-secure-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                color: #59677a;
                font-size: 0.75rem;
                white-space: nowrap;
            }

            .auth-secure-badge i {
                color: #fb6c04;
                font-size: 1rem;
            }

            .auth-heading h1 {
                margin: 0 0 10px;
                color: #021127;
                font-size: clamp(1.8rem, 3vw, 2.25rem);
                font-weight: 800;
                letter-spacing: -0.04em;
                line-height: 1.15;
            }

            .auth-heading p {
                margin: 0;
                color: #59677a;
                font-size: 0.94rem;
                line-height: 1.6;
            }

            .auth-form .form-label {
                margin-bottom: 8px;
                color: #293244;
                font-size: 0.84rem;
                font-weight: 700;
            }

            .auth-form .form-control {
                min-height: 50px;
                border: 1px solid #e8d9c6;
                border-radius: 12px;
                background: #fffdf9;
                color: #021127;
                font-size: 0.93rem;
                box-shadow: none;
                transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            }

            .auth-form .form-control::placeholder {
                color: #8b96a5;
            }

            .auth-form .form-control:focus {
                border-color: #fb6c04;
                background: #fff;
                box-shadow: 0 0 0 4px rgba(251, 108, 4, 0.13);
            }

            .auth-password-group {
                position: relative;
            }

            .auth-password-group .form-control {
                padding-right: 54px;
            }

            .auth-password-toggle {
                position: absolute;
                top: 50%;
                right: 7px;
                display: grid;
                width: 38px;
                height: 38px;
                place-items: center;
                transform: translateY(-50%);
                border: 0;
                border-radius: 9px;
                background: transparent;
                color: #59677a;
                font-size: 1.15rem;
            }

            .auth-password-toggle:hover,
            .auth-password-toggle:focus-visible {
                background: #f5e8d6;
                color: #9a4100;
            }

            .auth-form .form-check-input {
                width: 1.05rem;
                height: 1.05rem;
                margin-top: 0.15em;
                border-color: #c9b9a5;
            }

            .auth-form .form-check-input:checked {
                border-color: #fb6c04;
                background-color: #fb6c04;
            }

            .auth-form .form-check-label {
                color: #59677a;
                font-size: 0.83rem;
            }

            .auth-link {
                color: #9a4100;
                font-size: 0.83rem;
                font-weight: 700;
                text-decoration: none;
            }

            .auth-link:hover {
                color: #fb6c04;
                text-decoration: underline;
                text-underline-offset: 3px;
            }

            .auth-submit {
                min-height: 52px;
                border: 0;
                border-radius: 12px;
                background: #fb6c04;
                color: #021127;
                font-size: 0.95rem;
                font-weight: 800;
                box-shadow: 0 9px 20px rgba(251, 108, 4, 0.2);
                transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
            }

            .auth-submit:hover,
            .auth-submit:focus-visible {
                transform: translateY(-1px);
                background: #ff812a;
                color: #021127;
                box-shadow: 0 13px 25px rgba(251, 108, 4, 0.26);
            }

            .auth-back-link {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                margin-top: 22px;
                color: #59677a;
                font-size: 0.85rem;
                font-weight: 700;
                text-decoration: none;
            }

            .auth-back-link:hover {
                color: #9a4100;
            }

            .auth-note {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                margin-top: 22px;
                padding: 13px 14px;
                border: 1px solid #e8d9c6;
                border-radius: 12px;
                background: #f5e8d6;
                color: #59677a;
                font-size: 0.79rem;
                line-height: 1.5;
            }

            .auth-note i {
                margin-top: 1px;
                color: #9a4100;
                font-size: 1.05rem;
            }

            .auth-alert {
                margin-bottom: 20px;
                padding: 12px 14px;
                border: 1px solid #efb4aa;
                border-radius: 12px;
                background: #fff0ed;
                color: #8a2f24;
                font-size: 0.84rem;
            }

            .auth-alert p:last-child {
                margin-bottom: 0;
            }

            .auth-alert-success {
                border-color: #a8d7b6;
                background: #edf8ef;
                color: #245c35;
            }

            .auth-footer {
                margin-top: 28px;
                color: #8792a1;
                font-size: 0.75rem;
                text-align: center;
            }

            .auth-status-icon {
                display: grid;
                width: 72px;
                height: 72px;
                margin: 0 0 24px;
                place-items: center;
                border: 1px solid rgba(251, 108, 4, 0.26);
                border-radius: 22px;
                background: #f5e8d6;
                color: #9a4100;
                font-size: 2rem;
            }

            .auth-status-icon-success {
                border-color: #a8d7b6;
                background: #edf8ef;
                color: #27623a;
            }

            .auth-countdown {
                margin-top: 18px;
                color: #59677a;
                font-size: 0.8rem;
                text-align: center;
            }

            html[data-bs-theme="dark"] body.authentication-bg {
                color: #fdf3e4;
            }

            html[data-bs-theme="dark"] .auth-page {
                background:
                    radial-gradient(ellipse at 12% 12%, rgba(251, 108, 4, 0.12), transparent 34%),
                    #021127;
            }

            html[data-bs-theme="dark"] .auth-shell {
                border-color: #273851;
                background: #0b1a31;
            }

            html[data-bs-theme="dark"] .auth-main {
                background: #0b1a31;
                color: #fdf3e4;
            }

            html[data-bs-theme="dark"] .auth-heading h1,
            html[data-bs-theme="dark"] .auth-form .form-label {
                color: #fdf3e4;
            }

            html[data-bs-theme="dark"] .auth-heading p,
            html[data-bs-theme="dark"] .auth-secure-badge,
            html[data-bs-theme="dark"] .auth-form .form-check-label,
            html[data-bs-theme="dark"] .auth-countdown {
                color: #a4b2c5;
            }

            html[data-bs-theme="dark"] .auth-eyebrow-label,
            html[data-bs-theme="dark"] .auth-link {
                color: #ffb27e;
            }

            html[data-bs-theme="dark"] .auth-form .form-control {
                border-color: #34445c;
                background: #14243b;
                color: #fdf3e4;
            }

            html[data-bs-theme="dark"] .auth-form .form-control::placeholder {
                color: #8798ad;
            }

            html[data-bs-theme="dark"] .auth-form .form-control:focus {
                border-color: #fb6c04;
                background: #172941;
                box-shadow: 0 0 0 4px rgba(251, 108, 4, 0.18);
            }

            html[data-bs-theme="dark"] .auth-password-toggle {
                color: #a4b2c5;
            }

            html[data-bs-theme="dark"] .auth-password-toggle:hover,
            html[data-bs-theme="dark"] .auth-password-toggle:focus-visible {
                background: #273851;
                color: #ffb27e;
            }

            html[data-bs-theme="dark"] .auth-note {
                border-color: #34445c;
                background: #14243b;
                color: #c6d1df;
            }

            html[data-bs-theme="dark"] .auth-note i {
                color: #ffb27e;
            }

            html[data-bs-theme="dark"] .auth-alert {
                border-color: #79433e;
                background: #351f25;
                color: #ffc0b5;
            }

            html[data-bs-theme="dark"] .auth-alert-success {
                border-color: #356b4a;
                background: #173426;
                color: #b7e6c4;
            }

            html[data-bs-theme="dark"] .auth-back-link,
            html[data-bs-theme="dark"] .auth-footer {
                color: #a4b2c5;
            }

            html[data-bs-theme="dark"] .auth-back-link:hover {
                color: #ffb27e;
            }

            html[data-bs-theme="dark"] .auth-status-icon {
                border-color: #794b32;
                background: #2b1d13;
                color: #ffb27e;
            }

            html[data-bs-theme="dark"] .auth-status-icon-success {
                border-color: #356b4a;
                background: #173426;
                color: #b7e6c4;
            }

            .auth-toast .toast-body {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 14px 16px;
                border: 1px solid #a8d7b6;
                border-radius: 12px;
                background: #edf8ef;
                color: #245c35;
            }

            .auth-toast .toast-body > i {
                font-size: 1.35rem;
            }

            html[data-bs-theme="dark"] .auth-toast .toast-body {
                border-color: #356b4a;
                background: #173426;
                color: #b7e6c4;
            }

            @media (max-width: 820px) {
                .auth-page {
                    padding: 18px;
                }

                .auth-shell {
                    grid-template-columns: minmax(0, 0.9fr) minmax(340px, 1fr);
                    min-height: calc(100vh - 36px);
                }

                .auth-showcase {
                    padding: 30px;
                }

                .auth-main {
                    padding: 32px;
                }
            }

            @media (max-width: 680px) {
                .auth-page {
                    min-height: 100vh;
                    padding: 0;
                }

                .auth-shell {
                    width: 100%;
                    min-height: 100vh;
                    grid-template-columns: 1fr;
                    border: 0;
                    border-radius: 0;
                }

                .auth-showcase {
                    display: none;
                }

                .auth-main {
                    align-items: flex-start;
                    padding: 32px 24px;
                }

                .auth-mobile-brand {
                    display: block;
                }

                .auth-eyebrow {
                    align-items: flex-start;
                }
            }

            @media (prefers-reduced-motion: reduce) {
                *,
                *::before,
                *::after {
                    scroll-behavior: auto !important;
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                }
            }
        </style>
    </head>

    <body class="authentication-bg">
        <main class="auth-page">
            <div class="auth-shell">
                <aside class="auth-showcase" aria-label="DeliFast, gestão de delivery">
                    <a class="auth-brand-plate" href="{{ route('admin.login') }}" aria-label="DeliFast">
                        <img src="{{ asset('build/admin/images/whi-green-horizontal.png') }}" alt="DeliFast Sistema de Delivery">
                    </a>

                    <div class="auth-showcase-copy">
                        <span class="auth-kicker"><i class="ri-flashlight-line" aria-hidden="true"></i> DeliFast painel</span>
                        <h2>Seu delivery no ritmo certo.</h2>
                        <p>Entre para acompanhar pedidos, organizar sua operação e manter cada entrega em movimento.</p>
                    </div>

                    <div class="auth-route-card" aria-hidden="true">
                        <div class="auth-route-icon"><i class="ri-e-bike-2-line"></i></div>
                        <div class="auth-route-copy">
                            <strong>Da cozinha até a porta</strong>
                            <span>Uma operação simples, em um só lugar.</span>
                        </div>
                    </div>

                    <div class="auth-showcase-footer">GESTÃO DE DELIVERY · {{ date('Y') }}</div>
                </aside>

                <section class="auth-main">
                    <div class="auth-content">
                        <a class="auth-mobile-brand" href="{{ route('admin.login') }}" aria-label="DeliFast">
                            <img src="{{ asset('build/admin/images/whi-green-horizontal.png') }}" alt="DeliFast Sistema de Delivery">
                        </a>
                        @yield('content')
                    </div>
                </section>
            </div>
        </main>

        @if (session('success'))
            <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
                <div id="successToast" class="toast auth-toast border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-body">
                        <i class="ri-checkbox-circle-line" aria-hidden="true"></i>
                        <div class="flex-grow-1">
                            <strong class="d-block mb-1">{{ $responseSuccessName ?? 'Sucesso!' }}</strong>
                            <span class="small">{{ session('success') }}</span>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Fechar"></button>
                    </div>
                </div>
            </div>
        @endif

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        const input = document.getElementById(button.dataset.target);
                        const icon = button.querySelector('i');

                        if (!input || !icon) {
                            return;
                        }

                        const showPassword = input.type === 'password';
                        input.type = showPassword ? 'text' : 'password';
                        button.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
                        button.setAttribute('aria-label', showPassword ? 'Ocultar senha' : 'Mostrar senha');
                        icon.className = showPassword ? 'ri-eye-off-line' : 'ri-eye-line';
                    });
                });

                const rememberCheckbox = document.getElementById('checkbox-signin');
                if (rememberCheckbox) {
                    rememberCheckbox.checked = localStorage.getItem('remember_login') === 'true';
                    rememberCheckbox.addEventListener('change', function () {
                        localStorage.setItem('remember_login', this.checked);
                    });
                }

                const successToast = document.getElementById('successToast');
                if (successToast && typeof bootstrap !== 'undefined') {
                    new bootstrap.Toast(successToast, { delay: 5000 }).show();
                }
            });
        </script>
    </body>
</html>
