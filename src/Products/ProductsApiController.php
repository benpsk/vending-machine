<?php

declare(strict_types=1);

namespace App\Products;

use App\Auth\Attributes\RequiresJwt;
use App\Auth\Attributes\RequiresRole;
use App\Database\Mysql\ProductRepository;
use App\Http\JsonEnvelope;
use App\Http\Request;
use App\Http\Response;
use App\Products\Exceptions\InvalidQuantityException;
use App\Products\Exceptions\OutOfStockException;
use App\Products\Exceptions\ProductNotFoundException;
use App\Routing\Route;
use App\Users\Role;
use App\Users\User;
use App\Validation\ValidationException;
use App\Validation\Validator;
use InvalidArgumentException;

final class ProductsApiController
{
    private const PER_PAGE_DEFAULT = 20;

    public function __construct(
        private readonly ProductRepository $repo,
        private readonly Validator $validator,
        private readonly PurchaseService $purchases,
    ) {
    }

    #[Route('/api/products', methods: ['GET'], name: 'api.products.index')]
    #[RequiresJwt]
    public function index(Request $request): Response
    {
        $page = max(1, (int)($request->query['page'] ?? 1));
        $perPage = (int)($request->query['perPage'] ?? self::PER_PAGE_DEFAULT);
        $sort = is_string($request->query['sort'] ?? null) ? (string)$request->query['sort'] : 'id';
        $dir = is_string($request->query['dir'] ?? null) ? (string)$request->query['dir'] : 'asc';

        try {
            $result = $this->repo->paginate($page, $perPage, $sort, $dir);
        } catch (InvalidArgumentException $e) {
            return JsonEnvelope::error('bad_request', $e->getMessage(), 400);
        }

        return JsonEnvelope::success(
            data: array_map(static fn ($p) => self::serialize($p), $result['items']),
            meta: [
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'total' => $result['total'],
            ],
        );
    }

    #[Route('/api/products/{id}', methods: ['GET'], name: 'api.products.show')]
    #[RequiresJwt]
    public function show(Request $request, int $id): Response
    {
        $product = $this->repo->findById($id);
        if ($product === null) {
            return JsonEnvelope::error('not_found', "Product {$id} not found.", 404);
        }
        return JsonEnvelope::success(self::serialize($product));
    }

    #[Route('/api/products', methods: ['POST'], name: 'api.products.store')]
    #[RequiresJwt]
    #[RequiresRole(Role::Admin)]
    public function store(Request $request): Response
    {
        $input = $this->stringInput($request);

        try {
            $this->validator->validate($input, ProductValidationRules::rules());
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        }

        $id = $this->repo->create(
            name: $input['name'],
            price: $input['price'],
            quantityAvailable: (int)$input['quantity_available'],
        );

        return JsonEnvelope::success([
            'id' => $id,
            'name' => $input['name'],
            'price' => $input['price'],
            'quantity_available' => (int)$input['quantity_available'],
        ], status: 201);
    }

    #[Route('/api/products/{id}', methods: ['PUT'], name: 'api.products.update')]
    #[RequiresJwt]
    #[RequiresRole(Role::Admin)]
    public function update(Request $request, int $id): Response
    {
        if ($this->repo->findById($id) === null) {
            return JsonEnvelope::error('not_found', "Product {$id} not found.", 404);
        }

        $input = $this->stringInput($request);

        try {
            $this->validator->validate($input, ProductValidationRules::rules());
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        }

        $this->repo->update(
            id: $id,
            name: $input['name'],
            price: $input['price'],
            quantityAvailable: (int)$input['quantity_available'],
        );

        return JsonEnvelope::success([
            'id' => $id,
            'name' => $input['name'],
            'price' => $input['price'],
            'quantity_available' => (int)$input['quantity_available'],
        ]);
    }

    #[Route('/api/products/{id}', methods: ['DELETE'], name: 'api.products.destroy')]
    #[RequiresJwt]
    #[RequiresRole(Role::Admin)]
    public function destroy(Request $request, int $id): Response
    {
        if ($this->repo->findById($id) === null) {
            return JsonEnvelope::error('not_found', "Product {$id} not found.", 404);
        }

        $this->repo->delete($id);
        return new Response(status: 204);
    }

    #[Route('/api/products/{id}/purchase', methods: ['POST'], name: 'api.products.purchase')]
    #[RequiresJwt]
    public function purchase(Request $request, int $id): Response
    {
        $user = $request->attribute('user');
        if (!$user instanceof User) {
            return JsonEnvelope::error('invalid_token', 'Authenticated user missing.', 401);
        }

        $rawQty = (string)($request->body['quantity'] ?? '');
        $quantity = ctype_digit($rawQty) ? (int)$rawQty : 0;

        try {
            $transaction = $this->purchases->purchase($user->id, $id, $quantity);
        } catch (InvalidQuantityException) {
            return JsonEnvelope::error('invalid_quantity', 'Quantity must be at least 1.', 422);
        } catch (OutOfStockException $e) {
            return JsonEnvelope::error(
                'out_of_stock',
                "Product {$e->productId} has only {$e->available} available.",
                422,
            );
        } catch (ProductNotFoundException) {
            return JsonEnvelope::error('not_found', "Product {$id} not found.", 404);
        }

        return JsonEnvelope::success([
            'id' => $transaction->id,
            'user_id' => $transaction->userId,
            'product_id' => $transaction->productId,
            'quantity' => $transaction->quantity,
            'unit_price' => $transaction->unitPrice,
            'total_amount' => $transaction->totalAmount,
            'created_at' => $transaction->createdAt->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function stringInput(Request $request): array
    {
        return [
            'name' => trim((string)($request->body['name'] ?? '')),
            'price' => trim((string)($request->body['price'] ?? '')),
            'quantity_available' => trim((string)($request->body['quantity_available'] ?? '')),
        ];
    }

    private function validationErrorResponse(ValidationException $e): Response
    {
        return JsonEnvelope::error(
            code: 'validation_failed',
            message: 'Validation failed.',
            status: 422,
            extra: ['fields' => $e->errors],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function serialize(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'quantity_available' => $product->quantityAvailable,
            'created_at' => $product->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $product->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
