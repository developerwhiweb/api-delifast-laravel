<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ClientSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientSecurityController extends Controller
{
    public function __construct(private ClientSecurityService $security) {}

    public function requestEmailVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ]);

        $this->security->requestEmailVerification(
            $this->client($request),
            $validated['email'] ?? null,
            $request
        );

        return response()->json([
            'message' => 'Código de confirmação enviado para o e-mail informado.',
            'expires_in' => 300,
        ]);
    }

    public function confirmEmailVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $client = $this->security->confirmEmailVerification(
            $this->client($request),
            $validated['code']
        );

        return response()->json([
            'message' => 'E-mail confirmado com sucesso.',
            'data' => $client,
        ]);
    }

    public function requestSensitiveAccessCode(Request $request): JsonResponse
    {
        $this->security->requestSensitiveAccessCode($this->client($request));

        return response()->json([
            'message' => 'Código enviado para o e-mail confirmado da conta.',
            'expires_in' => 300,
        ]);
    }

    public function confirmSensitiveAccessCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        return response()->json([
            'message' => 'Identidade confirmada.',
            'data' => $this->security->confirmSensitiveAccessCode(
                $this->client($request),
                $validated['code']
            ),
        ]);
    }

    private function client(Request $request): Client
    {
        $client = $request->user();
        abort_unless($client instanceof Client, 401, 'Não autenticado.');

        return $client;
    }
}
