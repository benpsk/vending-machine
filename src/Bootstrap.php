<?php

declare(strict_types=1);

namespace App;

use App\Auth\AuthApiController;
use App\Auth\AuthController;
use App\Auth\JwtAuthenticator;
use App\Auth\Middleware\AuthJwtMiddleware;
use App\Auth\Middleware\AuthSessionMiddleware;
use App\Auth\PasswordHasher;
use App\Auth\SessionAuthenticator;
use App\Auth\Storage\PhpSessionStorage;
use App\Auth\Storage\SessionStorageInterface;
use App\Database\Mysql\Connection;
use App\Database\Mysql\LoginAttemptRepository;
use App\Database\Mysql\ProductRepository;
use App\Database\Mysql\TransactionRepository;
use App\Database\Mysql\UserRepository;
use App\Http\HomeController;
use App\Http\Kernel;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\Middleware\MiddlewareInterface;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\SessionStartMiddleware;
use App\Http\View;
use App\Products\ProductsApiController;
use App\Products\ProductsController;
use App\Products\PurchaseService;
use App\Transactions\TransactionsController;
use App\Routing\RouteCollector;
use App\Routing\Router;
use App\Support\Clock\ClockInterface;
use App\Support\Clock\SystemClock;
use App\Support\Container;
use App\Support\Logger\ErrorLogger;
use App\Support\Logger\LoggerInterface;
use App\Validation\Validator;
use PDO;
use RuntimeException;

final class Bootstrap
{
    /**
     * @return list<class-string>
     */
    public static function controllers(): array
    {
        return [
            HomeController::class,
            AuthController::class,
            ProductsController::class,
            TransactionsController::class,
            AuthApiController::class,
            ProductsApiController::class,
        ];
    }

    public static function create(string $projectRoot): Kernel
    {
        $isProduction = ((string)($_ENV['APP_ENV'] ?? 'local')) === 'production';
        $debug = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

        $container = self::buildContainer($projectRoot);
        $middleware = self::buildGlobalMiddleware($container, $isProduction);

        $routes = (new RouteCollector())->collect(self::controllers());
        $router = new Router($routes);
        $container->singleton(Router::class, $router);

        $logger = $container->get(LoggerInterface::class);
        $kernel = new Kernel($container, $router, $middleware, $logger, $debug);
        $container->singleton(Kernel::class, $kernel);

        return $kernel;
    }

    public static function buildContainer(string $projectRoot): Container
    {
        if (!extension_loaded('bcmath')) {
            throw new RuntimeException(
                'The bcmath PHP extension is required for currency math.'
                . ' Install it with: sudo apt install php8.4-bcmath'
            );
        }

        $jwtSecret = (string)($_ENV['JWT_SECRET'] ?? '');
        if ($jwtSecret === '') {
            throw new RuntimeException(
                'JWT_SECRET env var is empty. Generate one with:'
                . " php -r \"echo bin2hex(random_bytes(32));\" and set it in .env"
            );
        }

        $container = new Container();

        $container->singleton(LoggerInterface::class, new ErrorLogger());

        $container->singleton(View::class, new View($projectRoot . '/templates'));

        $pdo = self::pdoFromEnv();
        $container->singleton(PDO::class, $pdo);

        $userRepo = new UserRepository($pdo);
        $loginAttemptRepo = new LoginAttemptRepository($pdo);
        $productRepo = new ProductRepository($pdo);
        $transactionRepo = new TransactionRepository($pdo);
        $container->singleton(UserRepository::class, $userRepo);
        $container->singleton(LoginAttemptRepository::class, $loginAttemptRepo);
        $container->singleton(ProductRepository::class, $productRepo);
        $container->singleton(TransactionRepository::class, $transactionRepo);

        $container->singleton(
            PurchaseService::class,
            new PurchaseService($pdo, $productRepo, $transactionRepo),
        );

        $hasher = new PasswordHasher();
        $container->singleton(PasswordHasher::class, $hasher);

        $container->singleton(Validator::class, new Validator());

        $session = new PhpSessionStorage(
            name: (string)($_ENV['SESSION_NAME'] ?? 'VENDING_SID'),
            secure: ((string)($_ENV['APP_ENV'] ?? 'local')) === 'production',
        );
        $container->singleton(SessionStorageInterface::class, $session);

        $authenticator = new SessionAuthenticator($userRepo, $hasher, $session);
        $container->singleton(SessionAuthenticator::class, $authenticator);

        $clock = new SystemClock();
        $container->singleton(ClockInterface::class, $clock);

        $jwt = new JwtAuthenticator(
            secret: $jwtSecret,
            ttlSeconds: (int)($_ENV['JWT_TTL_SECONDS'] ?? 900),
            clock: $clock,
        );
        $container->singleton(JwtAuthenticator::class, $jwt);
        $container->singleton(AuthJwtMiddleware::class, new AuthJwtMiddleware($jwt, $userRepo));

        return $container;
    }

    /**
     * @return list<MiddlewareInterface>
     */
    public static function buildGlobalMiddleware(Container $container, bool $isProduction): array
    {
        $session = $container->get(SessionStorageInterface::class);
        $userRepo = $container->get(UserRepository::class);

        return [
            new SecurityHeadersMiddleware($isProduction),
            new SessionStartMiddleware($session),
            new CsrfMiddleware($session),
            new AuthSessionMiddleware($session, $userRepo),
        ];
    }

    private static function pdoFromEnv(): PDO
    {
        return Connection::open(
            host: (string)($_ENV['DB_HOST'] ?? '127.0.0.1'),
            port: (int)($_ENV['DB_PORT'] ?? 3306),
            user: (string)($_ENV['DB_USER'] ?? ''),
            password: (string)($_ENV['DB_PASSWORD'] ?? ''),
            database: (string)($_ENV['DB_NAME'] ?? 'vending'),
        );
    }
}
