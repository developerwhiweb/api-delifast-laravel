<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Modules\Client\Business\ClientService;
use App\Modules\Client\DTO\ClientData;
use App\Services\ClientSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function __construct(
        protected ClientService $service,
        protected ClientSecurityService $security
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->clientData($this->client($request)),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $client = $this->client($request);
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:15',
                Rule::unique('clients', 'phone')
                    ->where('tenant_id', $client->tenant_id)
                    ->ignore($client->id),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'path_image' => ['sometimes', 'nullable', 'file', 'image', 'max:2048'],
            'delete_path_image' => ['sometimes', 'boolean'],
        ]);

        if ($request->exists('email')) {
            return response()->json([
                'message' => 'O e-mail só pode ser cadastrado ou alterado pelo fluxo de confirmação.',
                'errors' => ['email' => ['Confirme a identidade e o novo e-mail antes de alterá-lo.']],
            ], 422);
        }

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Os dados informados são inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $changes = $validator->validated();

        if (! $changes && ! $request->hasFile('path_image')) {
            return response()->json([
                'message' => 'Informe ao menos um dado para atualizar.',
            ], 422);
        }

        $clientData = ClientData::fromArray(array_merge([
            'name' => $client->name,
            'phone' => $client->phone,
            'email' => $client->email,
            'active' => $client->active,
        ], $changes));

        $this->service->update(
            $client,
            $clientData,
            $request->file('path_image')
        );

        return response()->json([
            'message' => 'Dados atualizados com sucesso.',
            'data' => $this->clientData($client->fresh()),
        ]);
    }

    public function updateAddressStorage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address_storage_enabled' => ['required', 'boolean'],
        ]);

        return response()->json([
            'message' => 'Preferência de armazenamento atualizada.',
            'data' => $this->security->updateAddressStorage(
                $this->client($request),
                filter_var($validated['address_storage_enabled'], FILTER_VALIDATE_BOOLEAN)
            ),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $client = $this->client($request);
        $client->tokens()->delete();
        $this->service->delete($client);

        return response()->json(['message' => 'Conta excluída com sucesso.']);
    }

    private function client(Request $request): Client
    {
        $client = $request->user();

        abort_unless($client instanceof Client, 401, 'Não autenticado.');

        return $client;
    }

    private function clientData(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'phone' => $client->phone,
            'email' => $client->email,
            'masked_email' => $client->masked_email,
            'has_email' => (bool) $client->email,
            'email_verified' => (bool) $client->email_verified_at,
            'address_storage_enabled' => (bool) $client->address_storage_enabled,
            'can_bind_email' => ! $client->email
                && ! $client->orders()->exists()
                && ! $client->addresses()->exists(),
            'path_image' => $client->path_image,
        ];
    }
}
