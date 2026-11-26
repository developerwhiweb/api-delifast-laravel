@extends('admin.core.auth')

@section('content')
    <div class="auth-eyebrow">
        <span class="auth-eyebrow-label">Área administrativa</span>
        <span class="auth-secure-badge"><i class="ri-shield-check-line" aria-hidden="true"></i> Acesso protegido</span>
    </div>

    <header class="auth-heading">
        <h1>Boas-vindas!</h1>
        <p>Entre na sua conta para cuidar do seu delivery.</p>
    </header>

    @if ($errors->any())
        <div class="auth-alert" role="alert" aria-live="polite">
            @foreach ($errors->all() as $error)
                <p><i class="ri-error-warning-line me-1" aria-hidden="true"></i>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form class="auth-form" action="{{ route('admin.user.authenticate') }}" method="POST">
        @csrf

        <div class="mb-3">
            <label for="emailaddress" class="form-label">E-mail</label>
            <input
                class="form-control"
                type="email"
                id="emailaddress"
                name="email"
                autocomplete="username"
                required
                placeholder="seu@email.com"
                value="{{ old('email') }}"
            >
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Senha</label>
            <div class="auth-password-group">
                <input
                    class="form-control"
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    placeholder="Digite sua senha"
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

        <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="checkbox-signin" name="remember">
                <label class="form-check-label" for="checkbox-signin">Manter conectado</label>
            </div>
            <a class="auth-link text-nowrap" href="{{ route('password.request') }}">Esqueceu a senha?</a>
        </div>

        <div class="d-grid">
            <button class="btn auth-submit" type="submit">
                Entrar no painel <i class="ri-arrow-right-line ms-1" aria-hidden="true"></i>
            </button>
        </div>
    </form>

    <div class="auth-footer"><i class="ri-lock-2-line me-1" aria-hidden="true"></i> Seus dados são protegidos e usados apenas para acesso ao painel.</div>
@endsection
