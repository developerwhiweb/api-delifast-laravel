<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Realtime de pedidos do painel

O painel administrativo recebe eventos de pedidos pelo Laravel Reverb e Laravel Echo. Os eventos são enviados somente ao canal privado `tenants.{tenantId}.orders`; usuários comuns só podem assinar o canal do próprio tenant, enquanto o papel `Super` pode assinar canais de qualquer tenant. Novos pedidos e mudanças de status são transmitidos após o commit. A tela sincroniza um fragmento HTML autoritativo ao reconectar e reconcilia os dados a cada 5 minutos com o WebSocket ativo ou a cada 60 segundos enquanto estiver indisponível.

Configure no `.env` os valores de `BROADCAST_CONNECTION=reverb`, `QUEUE_CONNECTION=database` e as variáveis `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`, `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT` e `VITE_REVERB_*`. Use credenciais fortes e iguais às cadastradas no Reverb. Em produção com proxy TLS, configure `VITE_REVERB_HOST` e `VITE_REVERB_SCHEME=https` para o endereço público de WebSocket; o host interno do backend pode continuar apontando para `127.0.0.1`.

Para desenvolvimento acessado por outro dispositivo na rede local, defina `APP_URL` como a origem do Laravel (por exemplo, `http://<IP-do-servidor>:8000`), e use o IP do servidor em `VITE_DEV_SERVER_HOST` e `VITE_REVERB_HOST`. Inicie o Laravel com `php artisan serve --host=0.0.0.0 --port=8000`; mantenha o Reverb ouvindo em `0.0.0.0` e permita os ports do Laravel, Vite e Reverb no firewall local.

Depois de preencher o `.env`, compile os assets e mantenha os dois processos persistentes em execução:

```sh
npm run build
php artisan reverb:start --host=0.0.0.0 --port=8080
php artisan queue:work --tries=3 --timeout=90
```

Em produção, mantenha Reverb e o worker sob um gerenciador de processos (por exemplo, Supervisor ou systemd), libere/encaminhe o tráfego WebSocket no proxy e reinicie o worker após deploys. A fila deve estar operacional para que eventos `ShouldBroadcast` sejam enviados; a tela permanece utilizável por sincronização periódica quando o Reverb estiver indisponível.

## Delivery client API

The delivery client API is tenant-scoped by the request domain and always returns JSON. `POST /api/client/login` accepts `name` and `phone` for both registration and login:

```http
POST /api/client/login
Content-Type: application/json

{
  "name": "Ana Cliente",
  "phone": "11999999999"
}
```

If the phone is new in the current tenant, the API creates an active account with those fields and returns `201`. If the phone already exists, the name must match; a matching active account receives a token, while a different name is rejected. Email and password can be added later from the profile.

The response contains a Sanctum token. Send it as `Authorization: Bearer <token>` to use the authenticated endpoints:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/client/me` | Read the authenticated account |
| `PUT` / `PATCH` | `/api/client/me` | Update `name`, `phone`, `email`, password, or profile image |
| `DELETE` | `/api/client/me` | Delete the authenticated account |
| `POST` | `/api/client/logout` | Revoke the current token |

These API routes are separate from the panel's existing tenant management and web views.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
