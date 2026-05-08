<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\View;
use RuntimeException;
use Tests\Support\TestCase;

final class ViewTest extends TestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpRoot = sys_get_temp_dir() . '/vending-views-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpRoot);
        parent::tearDown();
    }

    public function testRendersTemplateWithExtractedData(): void
    {
        file_put_contents(
            $this->tmpRoot . '/hello.php',
            '<?php /** @var string $name */ ?>Hi, <?= $name ?>!',
        );

        $view = new View($this->tmpRoot);

        $this->assertSame('Hi, world!', $view->render('hello', ['name' => 'world']));
    }

    public function testRendersNestedTemplatePath(): void
    {
        mkdir($this->tmpRoot . '/auth', 0o755, true);
        file_put_contents($this->tmpRoot . '/auth/login.php', '<form>login</form>');

        $view = new View($this->tmpRoot);

        $this->assertSame('<form>login</form>', $view->render('auth/login'));
    }

    public function testThrowsWhenTemplateMissing(): void
    {
        $view = new View($this->tmpRoot);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Template not found: missing/');

        $view->render('missing');
    }

    public function testRethrowsAndCleansBufferOnTemplateError(): void
    {
        file_put_contents(
            $this->tmpRoot . '/boom.php',
            '<?php throw new \RuntimeException("template-error");',
        );

        $view = new View($this->tmpRoot);

        $startingLevel = ob_get_level();

        try {
            $view->render('boom');
            $this->fail('Expected the template exception to propagate');
        } catch (RuntimeException $e) {
            $this->assertSame('template-error', $e->getMessage());
            $this->assertSame($startingLevel, ob_get_level(), 'output buffer should be cleaned up');
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
