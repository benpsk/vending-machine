<?php

declare(strict_types=1);

namespace App\Transactions;

use App\Auth\Attributes\RequiresAuth;
use App\Auth\Attributes\RequiresRole;
use App\Auth\Storage\SessionStorageInterface;
use App\Database\Mysql\ProductRepository;
use App\Database\Mysql\TransactionRepository;
use App\Database\Mysql\UserRepository;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Routing\Route;
use App\Support\Csrf;
use App\Users\Role;
use App\Users\User;

final class TransactionsController
{
    private const PER_PAGE_DEFAULT = 20;

    public function __construct(
        private readonly View $view,
        private readonly SessionStorageInterface $session,
        private readonly TransactionRepository $transactions,
        private readonly ProductRepository $products,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('/purchases', methods: ['GET'], name: 'purchases.index')]
    #[RequiresAuth]
    public function userIndex(Request $request): Response
    {
        $user = $request->attribute('user');
        if (!$user instanceof User) {
            return Response::redirect('/login?next=' . rawurlencode($request->path));
        }

        $page = max(1, (int)($request->query['page'] ?? 1));
        $perPage = (int)($request->query['perPage'] ?? self::PER_PAGE_DEFAULT);
        $result = $this->transactions->paginateForUser($user->id, $page, $perPage);

        $productIds = array_values(array_unique(
            array_map(static fn (Transaction $t) => $t->productId, $result['items'])
        ));
        $productMap = $this->products->findManyById($productIds);

        return Response::html($this->view->renderInLayout('transactions/index', [
            'title' => 'My purchases',
            'transactions' => $result['items'],
            'productMap' => $productMap,
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'total' => $result['total'],
            'baseUrl' => '/purchases',
            'currentUser' => $user,
            'csrf' => Csrf::token($this->session),
        ], 'layouts/public'));
    }

    #[Route('/admin/transactions', methods: ['GET'], name: 'admin.transactions.index')]
    #[RequiresRole(Role::Admin)]
    public function adminIndex(Request $request): Response
    {
        $page = max(1, (int)($request->query['page'] ?? 1));
        $perPage = (int)($request->query['perPage'] ?? self::PER_PAGE_DEFAULT);
        $result = $this->transactions->paginateAll($page, $perPage);

        $productIds = array_values(array_unique(
            array_map(static fn (Transaction $t) => $t->productId, $result['items'])
        ));
        $userIds = array_values(array_unique(
            array_map(static fn (Transaction $t) => $t->userId, $result['items'])
        ));
        $productMap = $this->products->findManyById($productIds);
        $userMap = $this->users->findManyById($userIds);

        return Response::html($this->view->renderInLayout('transactions/admin/index', [
            'title' => 'All transactions',
            'transactions' => $result['items'],
            'productMap' => $productMap,
            'userMap' => $userMap,
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'total' => $result['total'],
            'baseUrl' => '/admin/transactions',
            'currentUser' => $request->attribute('user'),
            'csrf' => Csrf::token($this->session),
        ], 'layouts/admin'));
    }
}
