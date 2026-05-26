<?php
/** Common <head> + Tailwind CDN + design tokens + JS bootstrap config */
$pageTitle = $pageTitle ?? ($GLOBALS['APP']['app_name'] ?? 'WhatsApp CRM');
$socketUrl = $GLOBALS['APP']['socket_url'] ?? '';
$publicCfg = [
    'socketUrl'    => $socketUrl,
    'apiBase'      => rtrim($GLOBALS['APP']['public_path'] ?? '/', '/') . '/api',
    'features'     => $GLOBALS['APP']['features'] ?? [],
    'brand'        => $GLOBALS['APP']['app_name'] ?? 'WhatsApp CRM',
    'tagline'      => $GLOBALS['APP']['app_tagline'] ?? '',
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<meta name="theme-color" content="#10B981"/>
<meta name="referrer" content="same-origin"/>
<title><?= h($pageTitle) ?> — <?= h($GLOBALS['APP']['app_name']) ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/logo.svg"/>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        brand: {
          50:  '#ECFDF5',
          100: '#D1FAE5',
          200: '#A7F3D0',
          300: '#6EE7B7',
          400: '#34D399',
          500: '#10B981',
          600: '#059669',
          700: '#047857',
          800: '#065F46',
          900: '#064E3B',
        },
        ink: {
          50:  '#F8FAFB',
          100: '#F1F5F4',
          200: '#E5E9E7',
          300: '#CBD2CF',
          500: '#5C6B68',
          700: '#2A3936',
          900: '#0A1F1C',
        },
      },
      fontFamily: {
        sans: ['Inter','-apple-system','BlinkMacSystemFont','Segoe UI','Roboto','Helvetica','Arial','sans-serif'],
      },
      boxShadow: {
        'soft': '0 1px 2px rgba(16,24,40,.04)',
        'card': '0 4px 12px rgba(16,24,40,.06)',
        'pop':  '0 12px 32px rgba(16,24,40,.10)',
      },
      borderRadius: { 'xl2': '14px' },
    }
  }
}
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css?v=<?= h($GLOBALS['APP']['app_version']) ?>"/>
<link rel="stylesheet" href="assets/css/chat.css?v=<?= h($GLOBALS['APP']['app_version']) ?>"/>
<script>window.__APP__ = <?= json_encode($publicCfg, JSON_UNESCAPED_SLASHES); ?>;</script>
</head>
<body class="bg-ink-50 text-ink-900 font-sans antialiased">
<div id="toast-root" class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none"></div>
