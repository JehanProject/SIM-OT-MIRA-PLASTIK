<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f8f5">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="mira-api-base" content="<?= esc(rtrim(base_url('api'), '/'), 'attr') ?>">
    <title>Toko Mira Plastik | Sistem Operasional</title>
    <link rel="stylesheet" href="<?= esc('assets/css/mira.css?v=14', 'attr') ?>">
</head>
<body>
    <main id="app" aria-live="polite"></main>
    <div id="toast" class="toast" role="status"></div>
    <script type="module" src="<?= esc('assets/js/modules/core/mira.js?v=18', 'attr') ?>"></script>
</body>
</html>
