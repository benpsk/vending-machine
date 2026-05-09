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

        $seo = [
            'title'       => 'Vending Machine — Products & Purchases',
            'description' => 'Browse the vending machine inventory, top up your balance, and buy snacks.'
                . ' Server-rendered web UI plus a JSON REST API for integrations.',
            'canonical'   => '/',
            'jsonLd'      => [
                '@context' => 'https://schema.org',
                '@type'    => 'WebSite',
                'name'     => 'Vending Machine',
                'url'      => (string)($_ENV['APP_URL'] ?? '/'),
                'potentialAction' => [
                    '@type'       => 'SearchAction',
                    'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => '/products?q={query}'],
                    'query-input' => 'required name=query',
                ],
            ],
        ];

        return Response::html($this->view->renderInLayout('home', [
            'title'       => 'Vending Machine',
            'currentUser' => $user instanceof User ? $user : null,
            'csrf'        => Csrf::token($this->session),
            'seo'         => $seo,
        ], 'layouts/public'));
    }
}
