@extends('admin.core.auth')

@section('content')
    <div class="auth-eyebrow">
        <span class="auth-eyebrow-label">Proteja sua conta</span>
        <span class="auth-secure-badge"><i class="ri-shield-keyhole-line" aria-hidden="true"></i> Redefinição segura</span>
    </div>

    <header class="auth-heading">
        <div class="auth-status-icon" aria-hidden="true"><i class="ri-lock-password-line"></i></div>
        <h1>Crie uma nova senha</h1>
        <p>Escolha uma senha forte para voltar a acessar o painel.</p>
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

    <form class="auth-form" action="{{ route('password.update') }}" method="POST">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input
                class="form-control"
                name="email"
                type="email"
                id="email"
                autocomplete="email"
                required
                placeholder="seu@email.com"
                value="{{ old('email') }}"
            >
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Nova senha</label>
            <div class="auth-password-group">
                <input
                    class="form-control"
                    name="password"
                    type="password"
                    required
                    id="password"
                    autocomplete="new-password"
                    minlength="8"
                    placeholder="Pelo menos 8 caracteres"
                >
                <button
                    type="button"
                    class="auth-password-toggle"
                    data-password-toggle
                    data-target="password"
                    aria-label="Mostrar senha"
                    aria-pressed="false"
                >
                    <i class="ri-eye-line" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirme a nova senha</label>
            <div class="auth-password-group">
                <input
                    class="form-control"
                    name="password_confirmation"
                    type="password"
                    required
                    id="password_confirmation"
                    autocomplete="new-password"
                    minlength="8"
                    placeholder="Digite a senha novamente"
                >
                <button
                    type="button"
                    class="auth-password-toggle"
                    data-password-toggle
                    data-target="password_confirmation"
                    aria-label="Mostrar confirmação da senha"
                    aria-pressed="false"
                >
                    <i class="ri-eye-line" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="d-grid">
            <button class="btn auth-submit" type="submit">
                Salvar nova senha <i class="ri-lock-line ms-1" aria-hidden="true"></i>
            </button>
        </div>
    </form>

    <a class="auth-back-link" href="{{ route('admin.login') }}">
        <i class="ri-arrow-left-line" aria-hidden="true"></i> Voltar para o login
    </a>
@endsection
