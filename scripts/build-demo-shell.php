<?php

// Offline UI build: reuse the platform's Blade header/footer in the static preview.
// Does not bootstrap Laravel or read environment/database configuration.
$root = dirname(__DIR__);

function asset(string $path): string
{
    return '../'.ltrim($path, '/');
}

function url(string $path): string
{
    return '../'.ltrim($path, '/');
}

function route(string $name): string
{
    $paths = [
        'home' => '',
        'services.identity' => 'services/identity',
        'services.passports' => 'services/passports',
        'services.driving-licenses' => 'services/driving-licenses',
        'appointments.book' => 'appointments/book',
        'inquiry.reference' => 'inquiry/request',
        'inquiry.mine' => 'inquiry/my-requests',
        'contact.index' => 'contact',
        'contact.report' => 'contact/report',
        'contact.complaint' => 'contact/complaint',
    ];
    if (!array_key_exists($name, $paths)) {
        throw new RuntimeException('Unknown preview route: '.$name);
    }
    return '../'.$paths[$name];
}

function request(): object
{
    return new class {
        public function routeIs(string $name): bool
        {
            return false;
        }
    };
}

function renderSharedComponent(string $file, array $data = []): string
{
    extract($data, EXTR_SKIP);
    $source = file_get_contents($file);
    // The standalone demo is an unauthenticated preview.
    $source = preg_replace('/@auth\b.*?@endauth/s', '', $source);
    $source = preg_replace_callback('/<x-icon name="([a-z-]+)"\s*\/>/', function (array $match): string {
        return '<svg class="site-icon" width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="../assets/icons/outline.svg#'.$match[1].'"/></svg>';
    }, $source);
    $source = str_replace(['@php', '@endphp'], ['<?php', '?>'], $source);
    $source = preg_replace('/@foreach\((.*)\)/', '<?php foreach ($1): ?>', $source);
    $source = preg_replace('/@if\((.*)\)/', '<?php if ($1): ?>', $source);
    $source = str_replace(['@endforeach', '@endif'], ['<?php endforeach; ?>', '<?php endif; ?>'], $source);
    $source = preg_replace('/{{\s*(.*?)\s*}}/s', '<?= htmlspecialchars((string) ($1), ENT_QUOTES, "UTF-8") ?>', $source);
    ob_start();
    try {
        eval('?>'.$source);
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

$header = renderSharedComponent($root.'/resources/views/components/public-header.blade.php');
$banner = renderSharedComponent($root.'/resources/views/components/page-banner.blade.php', ['bannerHome' => false, 'bannerTitle' => 'نظرة عامة']);
$footer = renderSharedComponent($root.'/resources/views/components/public-footer.blade.php');
$html = <<<HTML
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>معاينة الواجهات — منصة الإصدار الآلي</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&amp;family=Inter:wght@400;500;600&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/portal-demo-base.css">
  <link rel="stylesheet" href="../css/portal-theme.css">
  <link rel="stylesheet" href="../css/portal-demo.css">
  <link rel="stylesheet" href="../css/icons.css">
  <script src="../js/portal-theme.js" defer></script>
  <script src="../js/portal-demo.js" defer></script>
</head>
<body class="gov-inner-page">
  <!-- Generated from the shared Blade components by scripts/build-demo-shell.php. -->
  {$banner}
  {$header}
  <main id="main-content" data-portal-demo data-screen="dashboard">
    <noscript>يرجى تفعيل JavaScript لتجربة الواجهات.</noscript>
  </main>
  {$footer}
</body>
</html>
HTML;
$html = preg_replace('/[ \t]+(?=\r?$)/m', '', $html);
file_put_contents($root.'/public/demo/index.html', $html.PHP_EOL);
echo "Static preview rebuilt from shared header and footer.".PHP_EOL;
