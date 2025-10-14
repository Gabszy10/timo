<?php

declare(strict_types=1);

/**
 * Helper to render consistent SEO and social sharing meta tags across the site.
 *
 * @param array{
 *     title?: string,
 *     description?: string,
 *     url?: string,
 *     image?: string,
 *     type?: string
 * } $overrides
 */
function render_site_meta(array $overrides = []): void
{
    $defaults = [
        'title' => 'St. John the Baptist Parish | Tiaong, Quezon',
        'description' => 'Discover the worship schedule, sacraments, ministries, and community events at St. John the Baptist Parish in Tiaong, Quezon.',
        'type' => 'website',
        'image' => '/img/banner/bradcam3.jpg',
    ];

    $meta = array_merge($defaults, array_filter($overrides, static fn($value) => $value !== null && $value !== ''));

    $meta['url'] = $overrides['url'] ?? current_page_url();

    $canonicalUrl = filter_var($meta['url'], FILTER_SANITIZE_URL) ?: current_page_url();

    $imageUrl = $meta['image'];
    if (!preg_match('/^https?:\/\//i', $imageUrl)) {
        $imageUrl = absolute_url($imageUrl);
    }

    $escapedTitle = htmlspecialchars($meta['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escapedDescription = htmlspecialchars($meta['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escapedUrl = htmlspecialchars($canonicalUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escapedImage = htmlspecialchars($imageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $ogType = htmlspecialchars($meta['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo "    <title>{$escapedTitle}</title>\n";
    echo "    <meta name=\"description\" content=\"{$escapedDescription}\">\n";
    echo "    <link rel=\"canonical\" href=\"{$escapedUrl}\">\n";
    echo "    <meta property=\"og:site_name\" content=\"St. John the Baptist Parish\">\n";
    echo "    <meta property=\"og:type\" content=\"{$ogType}\">\n";
    echo "    <meta property=\"og:title\" content=\"{$escapedTitle}\">\n";
    echo "    <meta property=\"og:description\" content=\"{$escapedDescription}\">\n";
    echo "    <meta property=\"og:url\" content=\"{$escapedUrl}\">\n";
    echo "    <meta property=\"og:image\" content=\"{$escapedImage}\">\n";
    echo "    <meta name=\"twitter:card\" content=\"summary_large_image\">\n";
    echo "    <meta name=\"twitter:title\" content=\"{$escapedTitle}\">\n";
    echo "    <meta name=\"twitter:description\" content=\"{$escapedDescription}\">\n";
    echo "    <meta name=\"twitter:image\" content=\"{$escapedImage}\">\n";
}

function current_page_url(): string
{
    $https = $_SERVER['HTTPS'] ?? '';
    $isSecure = $https === 'on' || $https === '1';
    $scheme = $isSecure ? 'https' : 'http';

    $host = $_SERVER['HTTP_HOST'] ?? 'stjohnbaptistparish.com';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    return rtrim($scheme . '://' . $host, '/') . $uri;
}

function absolute_url(string $path): string
{
    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    $base = current_page_url();
    $parsed = parse_url($base);

    if ($parsed === false) {
        return $path;
    }

    $scheme = $parsed['scheme'] ?? 'http';
    $host = $parsed['host'] ?? 'stjohnbaptistparish.com';
    $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

    return rtrim("{$scheme}://{$host}{$port}", '/') . '/' . ltrim($path, '/');
}
