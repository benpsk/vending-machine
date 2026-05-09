<?php
/**
 * Centralised <head> metadata. Layouts pass a $seo array; this partial
 * fills in defaults and emits the tags. Keep description ≤ 160 chars and
 * the OG image absolute (some crawlers refuse relative URLs).
 *
 * @var array{
 *     title?: string,
 *     description?: string,
 *     canonical?: string,
 *     image?: string,
 *     robots?: string,
 *     type?: string,
 *     siteName?: string,
 *     locale?: string,
 *     themeColor?: string,
 *     jsonLd?: array<string, mixed>|null
 * } $seo
 */

$siteName     = $seo['siteName']    ?? 'Vending Machine';
$title        = $seo['title']       ?? $siteName;
$description  = $seo['description'] ?? 'A small PHP app for managing products and processing purchases. Web UI for humans, REST API for machines.';
$robots       = $seo['robots']      ?? 'index,follow';
$ogType       = $seo['type']        ?? 'website';
$locale       = $seo['locale']      ?? 'en_US';
$themeColor   = $seo['themeColor']  ?? '#0F766E';
$jsonLd       = $seo['jsonLd']      ?? null;

$baseUrl = (string)($_ENV['APP_URL'] ?? '');
if ($baseUrl === '') {
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $baseUrl = $scheme . '://' . $host;
}
$baseUrl = rtrim($baseUrl, '/');

$absolute = static function (string $path) use ($baseUrl): string {
    if (preg_match('#^https?://#i', $path) === 1) {
        return $path;
    }
    return $baseUrl . '/' . ltrim($path, '/');
};

$canonical = $absolute($seo['canonical'] ?? ($_SERVER['REQUEST_URI'] ?? '/'));
$image     = $absolute($seo['image'] ?? '/assets/img/og.png');
?>
<meta name="description" content="<?= e($description) ?>">
<meta name="robots" content="<?= e($robots) ?>">
<meta name="theme-color" content="<?= e($themeColor) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">

<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon-16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/img/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">

<meta property="og:type" content="<?= e($ogType) ?>">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($image) ?>">
<meta property="og:locale" content="<?= e($locale) ?>">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($title) ?>">
<meta name="twitter:description" content="<?= e($description) ?>">
<meta name="twitter:image" content="<?= e($image) ?>">

<?php if (is_array($jsonLd) && $jsonLd !== []): ?>
<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
