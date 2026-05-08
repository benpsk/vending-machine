<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use App\Auth\PasswordHasher;
use App\Auth\SessionAuthenticator;
use App\Auth\Storage\ArraySessionStorage;
use App\Database\Mysql\UserRepository;
use App\Users\Role;
use Tests\Support\DatabaseTestCase;

final class SessionAuthenticatorTest extends DatabaseTestCase
{
    private UserRepository $users;
    private PasswordHasher $hasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new UserRepository($this->pdo);
        $this->hasher = new PasswordHasher();
    }

    public function testCorrectCredentialsLoginSucceedsAndPopulatesSession(): void
    {
        $this->seedAdminUser('alice', 'right-password');

        $session = new ArraySessionStorage();
        $auth = new SessionAuthenticator($this->users, $this->hasher, $session);

        $user = $auth->login('alice', 'right-password');

        $this->assertNotNull($user);
        $this->assertSame('alice', $user->username);
        $this->assertSame($user->id, $session->get('user_id'));
        $this->assertSame('admin', $session->get('role'));
    }

    public function testWrongPasswordReturnsNullAndLeavesSessionEmpty(): void
    {
        $this->seedAdminUser('bob', 'real-password');

        $session = new ArraySessionStorage();
        $auth = new SessionAuthenticator($this->users, $this->hasher, $session);

        $result = $auth->login('bob', 'wrong-password');

        $this->assertNull($result);
        $this->assertFalse($session->has('user_id'));
        $this->assertFalse($session->has('role'));
    }

    public function testMissingUserReturnsNullAndStillRunsVerifyDummy(): void
    {
        $session = new ArraySessionStorage();
        $auth = new SessionAuthenticator($this->users, $this->hasher, $session);

        $start = hrtime(true);
        $result = $auth->login('does-not-exist', 'whatever');
        $elapsedNs = hrtime(true) - $start;

        $this->assertNull($result);
        $this->assertFalse($session->has('user_id'));

        // verifyDummy must run a real bcrypt verify (which is intentionally slow); a no-op
        // path would complete in microseconds. Lower bound: 1 ms = 1_000_000 ns.
        $this->assertGreaterThan(1_000_000, $elapsedNs, 'verifyDummy should consume real bcrypt time');
    }

    public function testLoginRotatesSessionId(): void
    {
        $this->seedAdminUser('carol', 'pw');

        $session = new ArraySessionStorage();
        $originalId = $session->id;
        $auth = new SessionAuthenticator($this->users, $this->hasher, $session);

        $auth->login('carol', 'pw');

        $this->assertNotSame($originalId, $session->id);
    }

    public function testLogoutClearsSession(): void
    {
        $this->seedAdminUser('dave', 'pw');

        $session = new ArraySessionStorage();
        $auth = new SessionAuthenticator($this->users, $this->hasher, $session);
        $auth->login('dave', 'pw');

        $auth->logout();

        $this->assertFalse($session->has('user_id'));
        $this->assertFalse($session->has('role'));
    }

    public function testCurrentUserIdReadsSession(): void
    {
        $this->seedAdminUser('eve', 'pw');

        $session = new ArraySessionStorage();
        $auth = new SessionAuthenticator($this->users, $this->hasher, $session);

        $this->assertNull($auth->currentUserId());

        $user = $auth->login('eve', 'pw');
        $this->assertNotNull($user);
        $this->assertSame($user->id, $auth->currentUserId());
    }

    private function seedAdminUser(string $username, string $password): int
    {
        return $this->users->create(
            $username,
            "{$username}@example.com",
            $this->hasher->hash($password),
            Role::Admin,
        );
    }
}
