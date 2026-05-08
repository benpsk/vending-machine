<?php

declare(strict_types=1);

namespace App\Products;

use App\Auth\Attributes\RequiresAuth;
use App\Auth\Attributes\RequiresRole;
use App\Auth\Storage\SessionStorageInterface;
use App\Database\Mysql\ProductRepository;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Products\Exceptions\InvalidQuantityException;
use App\Products\Exceptions\OutOfStockException;
use App\Products\Exceptions\ProductNotFoundException;
use App\Routing\Route;
use App\Support\Csrf;
use App\Users\Role;
use App\Users\User;
use App\Validation\ValidationException;
use App\Validation\Validator;
use InvalidArgumentException;

final class ProductsController
{
    public function __construct(
        private readonly View $view,
        private readonly ProductRepository $repo,
        private readonly Validator $validator,
        private readonly SessionStorageInterface $session,
        private readonly PurchaseService $purchases,
    ) {
    }

    #[Route('/products', methods: ['GET'], name: 'products.index')]
    #[RequiresAuth]
    public function index(Request $request): Response
    {
        return $this->renderList($request, '/products', showAdminActions: false);
    }

    #[Route('/products/{id}', methods: ['GET'], name: 'products.show')]
    #[RequiresAuth]
    public function show(Request $request, int $id): Response
    {
        $product = $this->repo->findById($id);
        if ($product === null) {
            return Response::html('<!doctype html><h1>404 Not Found</h1>', 404);
        }

        return Response::html($this->view->renderInLayout('products/show', [
            'title' => $product->name,
            'product' => $product,
            'currentUser' => $this->currentUser($request),
            'csrf' => Csrf::token($this->session),
        ], 'layouts/public'));
    }

    #[Route('/products/{id}/purchase', methods: ['GET'], name: 'products.purchase.form')]
    #[RequiresAuth]
    public function purchaseForm(Request $request, int $id): Response
    {
        $product = $this->repo->findById($id);
        if ($product === null) {
            return Response::html('<!doctype html><h1>404 Not Found</h1>', 404);
        }

        return $this->renderPurchaseForm($request, $product, quantity: '1', error: null);
    }

    #[Route('/products/{id}/purchase', methods: ['POST'], name: 'products.purchase')]
    #[RequiresAuth]
    public function purchase(Request $request, int $id): Response
    {
        $product = $this->repo->findById($id);
        if ($product === null) {
            return Response::html('<!doctype html><h1>404 Not Found</h1>', 404);
        }

        $user = $this->currentUser($request);
        if ($user === null) {
            return Response::redirect('/login?next=' . rawurlencode($request->path));
        }

        $rawQty = trim((string)($request->body['quantity'] ?? ''));
        $quantity = ctype_digit($rawQty) ? (int)$rawQty : 0;

        try {
            $transaction = $this->purchases->purchase($user->id, $id, $quantity);
        } catch (InvalidQuantityException) {
            return $this->renderPurchaseForm(
                $request,
                $product,
                quantity: $rawQty,
                error: 'Quantity must be at least 1.',
            );
        } catch (OutOfStockException $e) {
            $latest = $this->repo->findById($id) ?? $product;
            return $this->renderPurchaseForm(
                $request,
                $latest,
                quantity: $rawQty,
                error: "Out of stock: only {$e->available} available.",
            );
        } catch (ProductNotFoundException) {
            return Response::html('<!doctype html><h1>404 Not Found</h1>', 404);
        }

        return Response::html($this->view->renderInLayout('products/purchase-receipt', [
            'title' => 'Purchase complete',
            'transaction' => $transaction,
            'product' => $product,
            'currentUser' => $user,
            'csrf' => Csrf::token($this->session),
        ], 'layouts/public'));
    }

    #[Route('/admin', methods: ['GET'], name: 'admin.root')]
    #[RequiresRole(Role::Admin)]
    public function adminRoot(): Response
    {
        return Response::redirect('/admin/products');
    }

    #[Route('/admin/products', methods: ['GET'], name: 'admin.products.index')]
    #[RequiresRole(Role::Admin)]
    public function adminIndex(Request $request): Response
    {
        return $this->renderList($request, '/admin/products', showAdminActions: true);
    }

    #[Route('/admin/products/create', methods: ['GET'], name: 'admin.products.create')]
    #[RequiresRole(Role::Admin)]
    public function create(Request $request): Response
    {
        return $this->renderForm(
            template: 'products/admin/create',
            title: 'Create product',
            request: $request,
            old: ['name' => '', 'price' => '', 'quantity_available' => ''],
            errors: [],
        );
    }

    #[Route('/admin/products', methods: ['POST'], name: 'admin.products.store')]
    #[RequiresRole(Role::Admin)]
    public function store(Request $request): Response
    {
        $input = $this->stringInput($request);

        try {
            $this->validator->validate($input, ProductValidationRules::rules());
        } catch (ValidationException $e) {
            return $this->renderForm(
                template: 'products/admin/create',
                title: 'Create product',
                request: $request,
                old: $input,
                errors: $e->errors,
            );
        }

        $this->repo->create(
            name: $input['name'],
            price: $input['price'],
            quantityAvailable: (int)$input['quantity_available'],
        );

        return Response::redirect('/admin/products');
    }

    #[Route('/admin/products/{id}/edit', methods: ['GET'], name: 'admin.products.edit')]
    #[RequiresRole(Role::Admin)]
    public function edit(Request $request, int $id): Response
    {
        $product = $this->repo->findById($id);
        if ($product === null) {
            return Response::html('<!doctype html><h1>404 Not Found</h1>', 404);
        }

        return $this->renderForm(
            template: 'products/admin/edit',
            title: "Edit {$product->name}",
            request: $request,
            old: [
                'name' => $product->name,
                'price' => $product->price,
                'quantity_available' => (string)$product->quantityAvailable,
            ],
            errors: [],
            extra: ['productId' => $product->id],
        );
    }

    #[Route('/admin/products/{id}', methods: ['POST'], name: 'admin.products.update')]
    #[RequiresRole(Role::Admin)]
    public function update(Request $request, int $id): Response
    {
        if ($this->repo->findById($id) === null) {
            return Response::html('<!doctype html><h1>404 Not Found</h1>', 404);
        }

        $input = $this->stringInput($request);

        try {
            $this->validator->validate($input, ProductValidationRules::rules());
        } catch (ValidationException $e) {
            return $this->renderForm(
                template: 'products/admin/edit',
                title: 'Edit product',
                request: $request,
                old: $input,
                errors: $e->errors,
                extra: ['productId' => $id],
            );
        }

        $this->repo->update(
            id: $id,
            name: $input['name'],
            price: $input['price'],
            quantityAvailable: (int)$input['quantity_available'],
        );

        return Response::redirect('/admin/products');
    }

    #[Route('/admin/products/{id}/delete', methods: ['GET'], name: 'admin.products.delete.confirm')]
    #[RequiresRole(Role::Admin)]
    public function confirmDestroy(Request $request, int $id): Response
    {
        $product = $this->repo->findById($id);
        if ($product === null) {
            return Response::html('<!doctype html><h1>404 Not Found</h1>', 404);
        }

        return Response::html($this->view->renderInLayout('products/admin/delete', [
            'title' => "Delete {$product->name}",
            'product' => $product,
            'currentUser' => $this->currentUser($request),
            'csrf' => Csrf::token($this->session),
        ], 'layouts/admin'));
    }

    #[Route('/admin/products/{id}/delete', methods: ['POST'], name: 'admin.products.destroy')]
    #[RequiresRole(Role::Admin)]
    public function destroy(Request $request, int $id): Response
    {
        $product = $this->repo->findById($id);
        if ($product === null) {
            return Response::html('<!doctype html><h1>404 Not Found</h1>', 404);
        }

        $this->repo->delete($id);
        return Response::redirect('/admin/products');
    }

    private function renderList(Request $request, string $baseUrl, bool $showAdminActions): Response
    {
        $page = max(1, (int)($request->query['page'] ?? 1));
        $perPage = (int)($request->query['perPage'] ?? 20);
        $sort = is_string($request->query['sort'] ?? null) ? (string)$request->query['sort'] : 'id';
        $dir = is_string($request->query['dir'] ?? null) ? (string)$request->query['dir'] : 'asc';

        try {
            $result = $this->repo->paginate(page: $page, perPage: $perPage, sort: $sort, direction: $dir);
        } catch (InvalidArgumentException $e) {
            return Response::html(
                '<!doctype html><h1>400 Bad Request</h1><p>' . e($e->getMessage()) . '</p>',
                400,
            );
        }

        return Response::html($this->view->renderInLayout('products/index', [
            'title' => $showAdminActions ? 'Manage products' : 'Products',
            'products' => $result['items'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'total' => $result['total'],
            'sort' => $sort,
            'dir' => strtolower($dir),
            'baseUrl' => $baseUrl,
            'showAdminActions' => $showAdminActions,
            'currentUser' => $this->currentUser($request),
            'csrf' => Csrf::token($this->session),
        ], $showAdminActions ? 'layouts/admin' : 'layouts/public'));
    }

    private function renderPurchaseForm(Request $request, Product $product, string $quantity, ?string $error): Response
    {
        $body = $this->view->renderInLayout('products/purchase', [
            'title' => "Buy {$product->name}",
            'product' => $product,
            'quantity' => $quantity,
            'error' => $error,
            'currentUser' => $this->currentUser($request),
            'csrf' => Csrf::token($this->session),
        ], 'layouts/public');
        return Response::html($body, $error === null ? 200 : 422);
    }

    /**
     * @param array<string, string> $old
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $extra
     */
    private function renderForm(
        string $template,
        string $title,
        Request $request,
        array $old,
        array $errors,
        array $extra = [],
    ): Response {
        $body = $this->view->renderInLayout($template, [
            'title' => $title,
            'old' => $old,
            'errors' => $errors,
            'currentUser' => $this->currentUser($request),
            'csrf' => Csrf::token($this->session),
            ...$extra,
        ], 'layouts/admin');
        return Response::html($body, $errors === [] ? 200 : 422);
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

    private function currentUser(Request $request): ?User
    {
        $user = $request->attribute('user');
        return $user instanceof User ? $user : null;
    }
}
