<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\Storage\SessionStorageInterface;
use App\Routing\Route;
use App\Support\Csrf;
use App\Users\User;

final class HomeController
{
    public function __construct(
        private readonly View $view,
        private readonly SessionStorageInterface $session,
    ) {
    }

    #[Route('/', methods: ['GET'], name: 'home')]
    public function index(Request $request): Response
    {
        $user = $request->attribute('user');

        return Response::html($this->view->renderInLayout('home', [
            'title' => 'Vending Machine',
            'currentUser' => $user instanceof User ? $user : null,
            'csrf' => Csrf::token($this->session),
        ], 'layouts/public'));
    }
}
