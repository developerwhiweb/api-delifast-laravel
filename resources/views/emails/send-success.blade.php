@extends('admin.core.auth')

@section('content')
    <div class="auth-eyebrow">
        <span class="auth-eyebrow-label">Próximo passo</span>
        <span class="auth-secure-badge"><i class="ri-mail-check-line" aria-hidden="true"></i> Verifique seu e-mail</span>
    </div>

    <div class="auth-status-icon auth-status-icon-success" aria-hidden="true">
        <i class="ri-mail-send-line"></i>
    </div>

    <header class="auth-heading">
        <h1>Confira sua caixa de entrada</h1>
        <p>
            @if ($email)
                Enviamos as instruções para <strong>{{ $email }}</strong>. Abra a mensagem e use o link para criar uma nova senha.
            @else
                Se o endereço informado estiver cadastrado, você receberá instruções para criar uma nova senha.
            @endif
        </p>
    </header>

    <div class="auth-note">
        <i class="ri-information-line" aria-hidden="true"></i>
        <span>Não encontrou a mensagem? Confira também a pasta de spam. O link é válido por 60 minutos.</span>
    </div>

    <div class="d-grid mt-4">
        <a class="btn auth-submit d-flex align-items-center justify-content-center" href="{{ route('admin.login') }}">
            Voltar para o login <i class="ri-arrow-right-line ms-1" aria-hidden="true"></i>
        </a>
    </div>
@endsection
