<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Auth\JwtAuthenticator;
use App\Auth\Middleware\AuthJwtMiddleware;
use App\Auth\PasswordHasher;
use App\Auth\SessionAuthenticator;
use App\Auth\Storage\ArraySessionStorage;
use App\Auth\Storage\SessionStorageInterface;
use App\Bootstrap;
use App\Database\Mysql\LoginAttemptRepository;
use App\Database\Mysql\ProductRepository;
use App\Database\Mysql\TransactionRepository;
use App\Database\Mysql\UserRepository;
use App\Http\Kernel;
use App\Http\Request;
use App\Http\Response;
use App\Products\PurchaseService;
use App\Routing\RouteCollector;
use App\Routing\Router;
use App\Support\Container;
use App\Support\Logger\LoggerInterface;
use App\Support\Logger\NullLogger;
use PDO;

/**
 * Test variant of {@see Bootstrap} that:
 * - swaps {@see ArraySessionStorage} in for the real PHP session,
 * - optionally re-points DB-backed repos at a caller-supplied PDO so DML
 *   stays inside a transaction the caller controls (test isolation).
 */
final class TestKernel
{
    public readonly Container $container;
    public readonly ArraySessionStorage $session;
    public readonly Kernel $kernel;

    /**
     * @param list<class-string> $extraControllers
     */
    public function __construct(
        string $projectRoot,
        array $extraControllers = [],
        ?PDO $pdo = null,
        bool $isProduction = false,
        bool $debug = true,
    ) {
        $container = Bootstrap::buildContainer($projectRoot);
        $container->singleton(LoggerInterface::class, new NullLogger());

        if ($pdo !== null) {
            $container->singleton(PDO::class, $pdo);
            $userRepo = new UserRepository($pdo);
            $container->singleton(UserRepository::class, $userRepo);
            $container->singleton(LoginAttemptRepository::class, new LoginAttemptRepository($pdo));
            $productRepo = new ProductRepository($pdo);
            $transactionRepo = new TransactionRepository($pdo);
            $container->singleton(ProductRepository::class, $productRepo);
            $container->singleton(TransactionRepository::class, $transactionRepo);
            $container->singleton(
                PurchaseService::class,
                new PurchaseService($pdo, $productRepo, $transactionRepo),
            );
            // Re-bind AuthJwtMiddleware so it loads users via the test PDO.
            $container->singleton(
                AuthJwtMiddleware::class,
                new AuthJwtMiddleware($container->get(JwtAuthenticator::class), $userRepo),
            );
        }

        $session = new ArraySessionStorage();
        $container->singleton(SessionStorageInterface::class, $session);

        // SessionAuthenticator was eagerly built in buildContainer with the prod PDO
        // and PhpSessionStorage; rebuild it now that both have been swapped.
        $container->singleton(
            SessionAuthenticator::class,
            new SessionAuthenticator(
                $container->get(UserRepository::class),
                $container->get(PasswordHasher::class),
                $session,
            ),
        );

        $middleware = Bootstrap::buildGlobalMiddleware($container, $isProduction);

        $controllers = [...Bootstrap::controllers(), ...$extraControllers];
        $router = new Router((new RouteCollector())->collect($controllers));
        $container->singleton(Router::class, $router);

        $kernel = new Kernel(
            $container,
            $router,
            $middleware,
            $container->get(LoggerInterface::class),
            $debug,
        );
        $container->singleton(Kernel::class, $kernel);

        $this->container = $container;
        $this->session = $session;
        $this->kernel = $kernel;
    }

    public function handle(Request $request): Response
    {
        return $this->kernel->handle($request);
    }
}
