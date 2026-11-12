<?php
$appRoleCode = (string) session()->get('role_code');
$appUserId = (int) session()->get('user_id');
$appUnreadNotificationCount = db_connect()->table('notifikasi_pengguna')
    ->where('user_id', $appUserId)
    ->where('is_read', false)
    ->countAllResults();
$appCurrentPath = trim((string) service('uri')->getPath(), '/');
$appCurrentPath = preg_replace('#^index\.php/?#', '', $appCurrentPath) ?? $appCurrentPath;
$appSummaryNavigation = [
    [
        'label' => $appRoleCode === 'OWNER' ? 'Dashboard Owner' : 'Dashboard',
        'url' => 'dashboard',
        'match' => 'dashboard',
        'icon' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    ],
];
if (in_array($appRoleCode, ['OWNER', 'KASIR'], true)) {
    $appSummaryNavigation[] = [
        'label' => 'Persetujuan Void/Retur',
        'url' => 'persetujuan',
        'match' => 'persetujuan',
        'icon' => '<path d="M9 12l2 2 4-4"/><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/>',
    ];
    $appSummaryNavigation[] = [
        'label' => 'Piutang & Bon',
        'url' => 'piutang',
        'match' => 'piutang',
        'exact' => true,
        'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18m-13 4h4"/>',
    ];
}
$appNavigation = [];
if (in_array($appRoleCode, ['OWNER', 'KASIR'], true)) {
    $appNavigation[] = [
        'label' => 'Transaksi Penjualan',
        'url' => 'kasir',
        'match' => 'kasir',
        'icon' => '<path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h8.5a2 2 0 0 0 2-1.6L21 8H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>',
    ];
    $appNavigation[] = [
        'label' => 'Riwayat Transaksi',
        'url' => 'transaksi',
        'match' => 'transaksi',
        'icon' => '<path d="M3 12a9 9 0 1 0 2.6-6.4L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/>',
    ];
    $appNavigation[] = [
        'label' => 'Pelanggan Grosir',
        'url' => 'piutang/pelanggan',
        'match' => 'piutang/pelanggan',
        'icon' => '<path d="M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1"/><circle cx="9.5" cy="7" r="3.5"/><path d="M17 11h4m-2-2v4"/>',
    ];
}
$appNavigation[] = [
    'label' => 'Stok Toko & Gudang',
    'url' => 'stok',
    'match' => 'stok',
    'exact' => true,
    'icon' => '<path d="M4 7h16v13H4z"/><path d="M8 7V4h8v3M8 12h8m-8 4h5"/>',
];
$appNavigation[] = [
    'label' => 'Mutasi Stok',
    'url' => 'stok/mutasi',
    'match' => 'stok/mutasi',
    'icon' => '<path d="M7 7h13l-3-3m3 3-3 3M17 17H4l3 3m-3-3 3-3"/>',
];
$appNavigation[] = [
    'label' => 'Penyesuaian Stok',
    'url' => 'stok/penyesuaian',
    'match' => 'stok/penyesuaian',
    'icon' => '<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="2"/><circle cx="15" cy="17" r="2"/>',
];
$appNavigation[] = [
    'label' => 'Riwayat Pergerakan Stok',
    'url' => 'stok/riwayat',
    'match' => 'stok/riwayat',
    'icon' => '<path d="M4 7h16M4 12h11M4 17h7"/><circle cx="18" cy="16" r="3"/><path d="M18 14v2l1 1"/>',
];
$appReportNavigation = $appRoleCode === 'OWNER'
    ? [
        ['label' => 'Laporan Penjualan', 'url' => 'laporan/penjualan', 'match' => 'laporan/penjualan', 'icon' => '<path d="M4 19V5m0 14h16"/><path d="m7 15 3-4 3 2 5-7"/>'],
        ['label' => 'Arus Kas', 'url' => 'arus-kas', 'match' => 'arus-kas', 'icon' => '<path d="M4 7h16M4 12h10m-10 5h16"/><circle cx="18" cy="12" r="2"/>'],
        ['label' => 'Arsip Historis', 'url' => 'arsip', 'match' => 'arsip', 'icon' => '<path d="M4 4h16v4H4zM6 8v12h12V8m-8 4h4"/>'],
        ['label' => 'Laba/Rugi Tahunan', 'url' => 'laporan/laba-rugi', 'match' => 'laporan/laba-rugi', 'icon' => '<path d="M4 20V4m0 16h16"/><path d="m7 15 3-4 3 2 5-7"/>'],
    ]
    : [];
$appManagementNavigation = $appRoleCode === 'OWNER'
    ? [
        ['label' => 'Data Barang', 'url' => 'barang', 'match' => 'barang', 'icon' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/>'],
        ['label' => 'Barang Masuk', 'url' => 'stok/penerimaan', 'match' => 'stok/penerimaan', 'icon' => '<path d="M4 7h16v13H4z"/><path d="M8 7V4h8v3m-4 4v5m-2-2 2 2 2-2"/>'],
        ['label' => 'Data Distributor', 'url' => 'distributor', 'match' => 'distributor', 'icon' => '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-5h6v5M8 9h1m6 0h1m-8 3h1m6 0h1"/>'],
        ['label' => 'Kelola Akun Pengguna', 'url' => 'pengguna', 'match' => 'pengguna', 'icon' => '<path d="M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1"/><circle cx="9.5" cy="7" r="3.5"/><path d="M17 11h4m-2-2v4"/>'],
        ['label' => 'Log Activity', 'url' => 'aktivitas', 'match' => 'aktivitas', 'icon' => '<path d="M4 7h16M4 12h10M4 17h16"/><circle cx="18" cy="12" r="2"/>'],
        ['label' => 'Backup & Restore', 'url' => 'backup', 'match' => 'backup', 'icon' => '<path d="M12 3v12m-4-4 4 4 4-4"/><path d="M4 17v4h16v-4"/>'],
        ['label' => 'Kebijakan tenor Bon', 'url' => 'piutang/kebijakan', 'match' => 'piutang/kebijakan', 'icon' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
    ]
    : ($appRoleCode === 'STAF_GUDANG'
        ? [
            ['label' => 'Data Barang', 'url' => 'barang', 'match' => 'barang', 'icon' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/>'],
            ['label' => 'Barang Masuk', 'url' => 'stok/penerimaan', 'match' => 'stok/penerimaan', 'icon' => '<path d="M4 7h16v13H4z"/><path d="M8 7V4h8v3m-4 4v5m-2-2 2 2 2-2"/>'],
            ['label' => 'Data Distributor', 'url' => 'distributor', 'match' => 'distributor', 'icon' => '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-5h6v5M8 9h1m6 0h1m-8 3h1m6 0h1"/>'],
        ]
        : []);

$appUserName = trim((string) session()->get('full_name'));
$appUserDisplayName = mb_convert_case($appUserName, MB_CASE_TITLE, 'UTF-8');
$appUserNameParts = preg_split('/\s+/', $appUserName) ?: [];
$appUserInitials = strtoupper(
    mb_substr($appUserNameParts[0] ?? '', 0, 1)
    . (count($appUserNameParts) > 1 ? mb_substr($appUserNameParts[1], 0, 1) : ''),
);
$appPageLabel = 'Dashboard ' . (string) (session()->get('role_name') ?: 'Toko');
$appIsActive = static function (string $match, bool $includeNested = true) use ($appCurrentPath): bool {
    return $appCurrentPath === $match || ($includeNested && str_starts_with($appCurrentPath, $match . '/'));
};
foreach (array_merge($appSummaryNavigation, $appNavigation, $appReportNavigation, $appManagementNavigation) as $appItem) {
    if ($appIsActive($appItem['match'], ! ($appItem['exact'] ?? false))) {
        $appPageLabel = $appItem['label'];
        break;
    }
}
if (preg_match('#^kasir/struk/\d+/penyesuaian$#', $appCurrentPath) === 1) {
    $appPageLabel = 'Ajukan Void/Retur';
} elseif (preg_match('#^kasir/struk/\d+$#', $appCurrentPath) === 1) {
    $appPageLabel = 'Struk transaksi';
} elseif ($appCurrentPath === 'profil') {
    $appPageLabel = 'Kelola Profil';
} elseif ($appCurrentPath === 'notifikasi') {
    $appPageLabel = 'Notifikasi';
}
?>
<div class="app-shell">
    <aside class="app-sidebar" id="app-sidebar" aria-label="Navigasi utama">
        <a class="app-brand" href="<?= site_url('dashboard') ?>">
            <span class="app-brand-mark" aria-hidden="true">M</span>
            <span><span class="app-brand-name">Toko Mira Plastik</span><span class="app-brand-caption">Operasional terintegrasi</span></span>
        </a>
        <div class="app-nav-label">Ringkasan</div>
        <nav class="app-nav" aria-label="Ringkasan">
            <?php foreach ($appSummaryNavigation as $item): ?>
                <a class="app-nav-link" href="<?= site_url($item['url']) ?>" <?= $appIsActive($item['match'], ! ($item['exact'] ?? false)) ? 'aria-current="page"' : '' ?>>
                    <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?= $item['icon'] ?></svg></span>
                    <span><?= esc($item['label']) ?></span>
                </a>
            <?php endforeach ?>
        </nav>
        <?php if ($appNavigation !== []): ?>
            <div class="app-nav-label">Operasional toko</div>
            <nav class="app-nav" aria-label="Operasional toko">
                <?php foreach ($appNavigation as $item): ?>
                    <a class="app-nav-link" href="<?= site_url($item['url']) ?>" <?= $appIsActive($item['match'], ! ($item['exact'] ?? false)) ? 'aria-current="page"' : '' ?>>
                        <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?= $item['icon'] ?></svg></span>
                        <span><?= esc($item['label']) ?></span>
                    </a>
                <?php endforeach ?>
            </nav>
        <?php endif ?>
        <?php if ($appReportNavigation !== []): ?>
            <div class="app-nav-label">Laporan</div>
            <nav class="app-nav" aria-label="Laporan">
                <?php foreach ($appReportNavigation as $item): ?>
                    <a class="app-nav-link" href="<?= site_url($item['url']) ?>" <?= $appIsActive($item['match']) ? 'aria-current="page"' : '' ?>>
                        <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?= $item['icon'] ?></svg></span>
                        <span><?= esc($item['label']) ?></span>
                    </a>
                <?php endforeach ?>
            </nav>
        <?php endif ?>
        <?php if ($appManagementNavigation !== []): ?>
            <div class="app-nav-label">Pengelolaan</div>
            <nav class="app-nav" aria-label="Pengelolaan">
                <?php foreach ($appManagementNavigation as $item): ?>
                    <a class="app-nav-link" href="<?= site_url($item['url']) ?>" <?= $appIsActive($item['match']) ? 'aria-current="page"' : '' ?>>
                        <span class="app-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?= $item['icon'] ?></svg></span>
                        <span><?= esc($item['label']) ?></span>
                    </a>
                <?php endforeach ?>
            </nav>
        <?php endif ?>
        <div class="app-sidebar-bottom">
            <span class="app-sidebar-status" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 20 6v5c0 5-3.5 8-8 10-4.5-2-8-5-8-10V6z"/><path d="m9 12 2 2 4-4"/></svg></span>
            <span><strong>Data terlindungi</strong><small>Backup harian dapat dijadwalkan</small></span>
        </div>
    </aside>
    <button class="app-sidebar-overlay" type="button" aria-label="Tutup menu"></button>
    <div class="app-main">
        <header class="app-topbar">
            <button class="app-menu-toggle" type="button" aria-label="Buka menu" aria-controls="app-sidebar" aria-expanded="false">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <nav class="app-breadcrumb" aria-label="Breadcrumb">
                <a href="<?= site_url('dashboard') ?>" class="app-breadcrumb-home">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7"/><path d="M5 9v11h14V9M9 20v-6h6v6"/></svg>
                    <span>Toko Mira Plastik</span>
                </a>
                <svg class="app-breadcrumb-separator" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                <span class="app-breadcrumb-current" aria-current="page"><?= esc($appPageLabel) ?></span>
            </nav>
            <div class="app-user-area">
                <a class="app-notification" href="<?= site_url('notifikasi') ?>" aria-label="Notifikasi<?= $appUnreadNotificationCount > 0 ? ': ' . $appUnreadNotificationCount . ' belum dibaca' : '' ?>" title="Notifikasi">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                    <?php if ($appUnreadNotificationCount > 0): ?><span class="app-notification-count"><?= esc((string) min($appUnreadNotificationCount, 99)) ?><?= $appUnreadNotificationCount > 99 ? '+' : '' ?></span><?php endif ?>
                </a>
                <a class="app-user app-user-profile-link" href="<?= site_url('profil') ?>" aria-label="Kelola profil">
                    <span class="app-user-avatar" aria-hidden="true"><?= esc($appUserInitials) ?></span>
                    <span><span class="app-user-name"><?= esc($appUserDisplayName) ?></span><span class="app-user-role"><?= esc(session()->get('role_name')) ?> · Profil</span></span>
                </a>
                <form method="post" action="<?= site_url('logout') ?>">
                    <?= csrf_field() ?>
                    <button class="app-logout" type="submit" aria-label="Keluar" title="Keluar">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
                    </button>
                </form>
            </div>
        </header>
