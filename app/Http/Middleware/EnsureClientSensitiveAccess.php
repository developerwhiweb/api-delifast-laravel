<?php

namespace App\Http\Middleware;

use App\Models\Client;
use App\Services\ClientSecurityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientSensitiveAccess
{
    public function __construct(private ClientSecurityService $security) {}

    public function handle(Request $request, Closure $next): Response
    {
        $client = Auth::guard('sanctum')->user();

        if (! $client instanceof Client) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        if (! $client->email_verified_at) {
            return response()->json([
                'code' => 'client_email_verification_required',
                'message' => 'Confirme um e-mail para visualizar os dados protegidos.',
            ], 403);
        }

        if (! $this->security->hasSensitiveAccess($request, $client)) {
            return response()->json([
                'code' => 'client_verification_required',
                'message' => 'Confirme sua identidade para visualizar os dados protegidos.',
            ], 403);
        }

        return $next($request);
    }
}
