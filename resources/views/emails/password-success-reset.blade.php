@extends('admin.core.auth')

@section('content')
    <div class="auth-eyebrow">
        <span class="auth-eyebrow-label">Acesso atualizado</span>
        <span class="auth-secure-badge"><i class="ri-shield-check-line" aria-hidden="true"></i> Conta protegida</span>
    </div>

    <div class="auth-status-icon auth-status-icon-success" aria-hidden="true">
        <i class="ri-check-line"></i>
    </div>

    <header class="auth-heading">
        <h1>Senha alterada!</h1>
        <p>Sua nova senha foi salva com sucesso. Agora você já pode entrar novamente no painel.</p>
    </header>

    <div class="d-grid">
        <a class="btn auth-submit d-flex align-items-center justify-content-center" href="{{ route('admin.login') }}">
            Ir para o login <i class="ri-arrow-right-line ms-1" aria-hidden="true"></i>
        </a>
    </div>

    <p class="auth-countdown">Você será direcionado ao login em <strong id="contador">5</strong> segundos.</p>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let seconds = 5;
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
