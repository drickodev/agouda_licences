<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreCustomerRequest;
use App\Http\Requests\Api\Admin\UpdateCustomerRequest;
use App\Http\Resources\Admin\CustomerResource;
use App\Models\Customer;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Clients')]
class CustomerController extends Controller
{
    #[OA\Get(
        path: '/api/admin/customers',
        summary: 'Liste les clients',
        security: [['sanctum' => []]],
        tags: ['Admin - Clients'],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
            properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Customer'))],
        ))],
    )]
    public function index()
    {
        return CustomerResource::collection(
            Customer::query()->orderBy('name')->paginate()
        );
    }

    #[OA\Post(
        path: '/api/admin/customers',
        summary: 'Crée un client',
        security: [['sanctum' => []]],
        tags: ['Admin - Clients'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'email', type: 'string', nullable: true),
                new OA\Property(property: 'phone', type: 'string', nullable: true),
                new OA\Property(property: 'company', type: 'string', nullable: true),
                new OA\Property(property: 'notes', type: 'string', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Créé', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Customer'),
            ])),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::query()->create($request->validated());

        return CustomerResource::make($customer)->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/admin/customers/{customer}',
        summary: "Détail d'un client",
        security: [['sanctum' => []]],
        tags: ['Admin - Clients'],
        parameters: [new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', ref: '#/components/schemas/Customer'),
        ]))],
    )]
    public function show(Customer $customer)
    {
        return CustomerResource::make($customer);
    }

    #[OA\Put(
        path: '/api/admin/customers/{customer}',
        summary: 'Modifie un client',
        security: [['sanctum' => []]],
        tags: ['Admin - Clients'],
        parameters: [new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'email', type: 'string', nullable: true),
                new OA\Property(property: 'phone', type: 'string', nullable: true),
                new OA\Property(property: 'company', type: 'string', nullable: true),
                new OA\Property(property: 'notes', type: 'string', nullable: true),
            ],
        )),
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', ref: '#/components/schemas/Customer'),
        ]))],
    )]
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        return CustomerResource::make($customer);
    }

    #[OA\Delete(
        path: '/api/admin/customers/{customer}',
        summary: 'Supprime un client',
        security: [['sanctum' => []]],
        tags: ['Admin - Clients'],
        parameters: [new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Supprimé')],
    )]
    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->json(status: 204);
    }
}
