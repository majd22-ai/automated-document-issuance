<?php

// Local equivalent of public/.htaccess static caching, used by artisan serve.
// Never bootstrap Laravel or apply public cache headers to user responses.
$publicPath = realpath(__DIR__.'/public');
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$path = str_contains($uri, "\0") ? false : realpath($publicPath.$uri);
$types = ['css' => 'text/css', 'js' => 'text/javascript', 'webp' => 'image/webp', 'avif' => 'image/avif', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'woff2' => 'font/woff2'];
$extension = $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';
if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)
    && preg_match('#^/(?:assets|build|css|js)/#', $uri)
    && $path && str_starts_with($path, $publicPath.DIRECTORY_SEPARATOR)
    && is_file($path) && isset($types[$extension])) {
    $mtime = filemtime($path);
    $size = filesize($path);
    $etag = 'W/"'.dechex($mtime).'-'.dechex($size).'"';
    $hashed = str_starts_with($uri, '/build/assets/') && preg_match('/-[A-Za-z0-9_-]{8,}\.(css|js)$/', $uri);
    $versioned = in_array($extension, ['css', 'js'], true) && (string) ($_GET['v'] ?? '') === (string) $mtime;
    $policy = $hashed ? 'public, max-age=31536000, immutable' : (($versioned || ! in_array($extension, ['css', 'js'], true)) ? 'public, max-age=86400' : 'public, max-age=0, must-revalidate');
    header('Cache-Control: '.$policy);
    header('ETag: '.$etag);
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT');
    header('Content-Type: '.$types[$extension]);
    header('X-Content-Type-Options: nosniff');
    $notModified = isset($_SERVER['HTTP_IF_NONE_MATCH'])
        ? in_array($etag, array_map('trim', explode(',', $_SERVER['HTTP_IF_NONE_MATCH'])), true) || trim($_SERVER['HTTP_IF_NONE_MATCH']) === '*'
        : isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime;
    if ($notModified) {
        http_response_code(304);
    } else {
        header('Content-Length: '.$size);
        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            readfile($path);
        }
    }

    return true;
}
if ($uri !== '/' && $path && str_starts_with($path, $publicPath.DIRECTORY_SEPARATOR) && is_file($path) && ! in_array($extension, ['php'], true)) {
    return false;
}
require $publicPath.'/index.php';
