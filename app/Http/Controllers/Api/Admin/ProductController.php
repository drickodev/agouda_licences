<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreProductRequest;
use App\Http\Requests\Api\Admin\UpdateProductRequest;
use App\Http\Resources\Admin\ProductResource;
use App\Models\Product;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Produits')]
class ProductController extends Controller
{
    #[OA\Get(
        path: '/api/admin/products',
        summary: 'Liste les produits',
        security: [['sanctum' => []]],
        tags: ['Admin - Produits'],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
            properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Product'))],
        ))],
    )]
    public function index()
    {
        return ProductResource::collection(
            Product::query()->orderBy('name')->paginate()
        );
    }

    #[OA\Post(
        path: '/api/admin/products',
        summary: 'Crée un produit',
        security: [['sanctum' => []]],
        tags: ['Admin - Produits'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'slug', 'active'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'AGOUDA SYSCOHADA'),
                new OA\Property(property: 'slug', type: 'string', example: 'agouda_syscohada'),
                new OA\Property(property: 'edition', type: 'string', nullable: true),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'active', type: 'boolean'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Créé', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Product'),
            ])),
            new OA\Response(response: 422, description: 'Validation', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(StoreProductRequest $request)
    {
        $product = Product::query()->create($request->validated());

        return ProductResource::make($product)->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/admin/products/{product}',
        summary: 'Détail d\'un produit',
        security: [['sanctum' => []]],
        tags: ['Admin - Produits'],
        parameters: [new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', ref: '#/components/schemas/Product'),
        ]))],
    )]
    public function show(Product $product)
    {
        return ProductResource::make($product);
    }

    #[OA\Put(
        path: '/api/admin/products/{product}',
        summary: 'Modifie un produit',
        security: [['sanctum' => []]],
        tags: ['Admin - Produits'],
        parameters: [new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'slug', 'active'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'slug', type: 'string'),
                new OA\Property(property: 'edition', type: 'string', nullable: true),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'active', type: 'boolean'),
            ],
        )),
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', ref: '#/components/schemas/Product'),
        ]))],
    )]
    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return ProductResource::make($product);
    }

    #[OA\Delete(
        path: '/api/admin/products/{product}',
        summary: 'Supprime un produit',
        security: [['sanctum' => []]],
        tags: ['Admin - Produits'],
        parameters: [new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Supprimé')],
    )]
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(status: 204);
    }
}
