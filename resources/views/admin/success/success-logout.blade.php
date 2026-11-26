@extends('admin.core.auth')

@section('content')
    <div class="auth-eyebrow">
        <span class="auth-eyebrow-label">Sessão encerrada</span>
        <span class="auth-secure-badge"><i class="ri-shield-check-line" aria-hidden="true"></i> Até a próxima</span>
    </div>

    <div class="auth-status-icon auth-status-icon-success" aria-hidden="true">
        <i class="ri-logout-box-r-line"></i>
    </div>

    <header class="auth-heading">
        <h1>Você saiu com segurança.</h1>
        <p>Sua sessão foi encerrada. Obrigado por cuidar da sua operação com a DeliFast.</p>
    </header>

    <div class="d-grid">
        <a class="btn auth-submit d-flex align-items-center justify-content-center" href="{{ route('admin.login') }}">
            Entrar novamente <i class="ri-arrow-right-line ms-1" aria-hidden="true"></i>
        </a>
    </div>

    <p class="auth-countdown">Voltando ao login em <strong id="contador">3</strong> segundos.</p>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let seconds = 3;
            const counter = document.getElementById('contador');
            const loginUrl = @json(route('admin.login'));

            const interval = window.setInterval(function () {
                seconds -= 1;
                if (counter) {
                    counter.textContent = seconds;
                }

                if (seconds <= 0) {
                    window.clearInterval(interval);
                    window.location.href = loginUrl;
                }
            }, 1000);
        });
    </script>
@endsection
