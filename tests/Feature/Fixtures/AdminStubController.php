<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures;

use App\Auth\Attributes\RequiresRole;
use App\Http\Response;
use App\Routing\Route;
use App\Users\Role;

final class AdminStubController
{
    #[Route('/admin/stub', methods: ['GET'], name: 'admin.stub')]
    #[RequiresRole(Role::Admin)]
    public function index(): Response
    {
        return Response::html('<!doctype html><h1>Admin Area</h1>');
    }
}
