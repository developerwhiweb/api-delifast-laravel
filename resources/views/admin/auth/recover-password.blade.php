@extends('admin.core.auth')

@section('content')
    <div class="auth-eyebrow">
        <span class="auth-eyebrow-label">Recuperação segura</span>
        <span class="auth-secure-badge"><i class="ri-mail-lock-line" aria-hidden="true"></i> Link protegido</span>
    </div>

    <header class="auth-heading">
        <div class="auth-status-icon" aria-hidden="true"><i class="ri-mail-send-line"></i></div>
        <h1>Recuperar acesso</h1>
        <p>Informe o e-mail da sua conta. Enviaremos um link para criar uma nova senha.</p>
    </header>

    @if ($errors->any())
        <div class="auth-alert" role="alert" aria-live="polite">
            @foreach ($errors->all() as $error)
                <p><i class="ri-error-warning-line me-1" aria-hidden="true"></i>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if (session('success'))
        <div class="auth-alert auth-alert-success" role="status">
            <i class="ri-checkbox-circle-line me-1" aria-hidden="true"></i>{{ session('success') }}
        </div>
    @endif

    <form class="auth-form" action="{{ route('password.email') }}" method="POST">
        @csrf

        <div class="mb-4">
            <label for="emailaddress" class="form-label">E-mail cadastrado</label>
            <input
                class="form-control"
                name="email"
                type="email"
                id="emailaddress"
                autocomplete="email"
                required
                placeholder="seu@email.com"
                value="{{ old('email') }}"
            >
        </div>

        <div class="d-grid">
            <button class="btn auth-submit" type="submit">
                Enviar link de recuperação <i class="ri-send-plane-line ms-1" aria-hidden="true"></i>
            </button>
        </div>
    </form>

    <div class="auth-note">
        <i class="ri-time-line" aria-hidden="true"></i>
        <span>Por segurança, o link de redefinição expira após 60 minutos.</span>
    </div>

    <a class="auth-back-link" href="{{ route('admin.login') }}">
        <i class="ri-arrow-left-line" aria-hidden="true"></i> Voltar para o login
    </a>
@endsection
