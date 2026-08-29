<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Services\Clients\ClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClientController extends Controller
{
    public function __construct(private ClientService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        return ClientResource::collection(
            $this->service->paginate((int) $request->integer('per_page', 15), $request->string('q')->toString()),
        )->response();
    }

    public function store(ClientRequest $request): JsonResponse
    {
        $this->authorize('create', Client::class);

        return response()->json([
            'data' => ClientResource::make($this->service->store($request->validated(), $request->user())),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $client = $this->service->findOrFail($id);
        $this->authorize('view', $client);

        return response()->json(['data' => ClientResource::make($client)]);
    }

    public function update(ClientRequest $request, int $id): JsonResponse
    {
        $client = $this->service->findOrFail($id);
        $this->authorize('update', $client);

        return response()->json([
            'data' => ClientResource::make($this->service->update($client, $request->validated(), $request->user())),
        ]);
    }
}
