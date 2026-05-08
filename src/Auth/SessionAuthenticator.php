<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Storage\SessionStorageInterface;
use App\Database\Mysql\UserRepository;
use App\Users\User;

final class SessionAuthenticator
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly SessionStorageInterface $session,
    ) {
    }

    public function login(string $username, string $password): ?User
    {
        $user = $this->users->findByUsername($username);

        // Always run a verify, even when the user is missing, so the wall-clock
        // cost of the missing-user branch matches the wrong-password branch.
        // Defeats user enumeration via timing.
        if ($user === null) {
            $this->hasher->verifyDummy($password);
            return null;
        }

        if (!$this->hasher->verify($password, $user->passwordHash)) {
            return null;
        }

        $this->session->regenerateId();
        $this->session->set('user_id', $user->id);
        $this->session->set('role', $user->role->value);
        return $user;
    }

    public function logout(): void
    {
        $this->session->clear();
    }

    public function currentUserId(): ?int
    {
        $value = $this->session->get('user_id');
        return is_int($value) ? $value : null;
    }
}
