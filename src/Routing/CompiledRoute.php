<?php

declare(strict_types=1);

namespace App\Routing;

final class CompiledRoute
{
    /**
     * @param list<string> $methods    HTTP methods (always uppercase, e.g. ['GET'])
     * @param list<string> $paramNames Names of path placeholders, in declaration order
     * @param class-string $controller
     */
    public function __construct(
        public readonly string $path,
        public readonly string $regex,
        public readonly array $methods,
        public readonly array $paramNames,
        public readonly string $controller,
        public readonly string $action,
        public readonly ?string $name = null,
    ) {
    }
}
