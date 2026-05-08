<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;
use Throwable;

final class View
{
    public function __construct(private readonly string $templateRoot)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $path = $this->templateRoot . '/' . ltrim($template, '/') . '.php';

        if (!is_file($path)) {
            throw new RuntimeException("Template not found: {$template} ({$path})");
        }

        ob_start();
        try {
            (function (string $__path, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__path;
            })($path, $data);
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string)ob_get_clean();
    }

    /**
     * Renders a body-only template into a layout. The body template's output
     * becomes `$content` inside the layout. `title`, `currentUser`, `csrf` are
     * forwarded to the layout for the <title> tag and nav. Each call site picks
     * its layout explicitly — `layouts/public` for the storefront, `layouts/admin`
     * for the console — so the chrome cannot leak across surfaces by accident.
     *
     * @param array<string, mixed> $data
     */
    public function renderInLayout(string $template, array $data = [], string $layout = 'layouts/public'): string
    {
        $content = $this->render($template, $data);
        return $this->render($layout, [
            'title' => $data['title'] ?? 'Vending Machine',
            'currentUser' => $data['currentUser'] ?? null,
            'csrf' => $data['csrf'] ?? null,
            'content' => $content,
        ]);
    }
}
