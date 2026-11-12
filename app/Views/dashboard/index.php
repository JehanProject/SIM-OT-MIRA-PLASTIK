<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Dashboard - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/dashboard.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main class="dashboard-page">
    <?php
    $dashboardDate = new DateTimeImmutable($today);
    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $formattedDashboardDate = $dashboardDate->format('d') . ' '
        . $monthNames[(int) $dashboardDate->format('n') - 1] . ' ' . $dashboardDate->format('Y');
    $dashboardDisplayName = mb_convert_case($fullName, MB_CASE_TITLE, 'UTF-8');
    ?>
    <section class="dashboard-welcome">
        <p class="dashboard-date">RINGKASAN TOKO <span aria-hidden="true">·</span> <?= esc($formattedDashboardDate) ?></p>
        <div class="dashboard-welcome-row">
            <div>
                <h1><?= esc($greeting) ?>, <?= esc($dashboardDisplayName) ?></h1>
                <p class="dashboard-subtitle">
                    Ringkasan kinerja toko hari ini.
                    <?php if (($metrics['pendingAdjustments'] ?? 0) > 0): ?>
                        Ada <?= esc($metrics['pendingAdjustments']) ?> pengajuan menunggu persetujuan.
                    <?php endif ?>
                </p>
            </div>
            <?php if ($roleCode === 'OWNER'): ?>
                <a class="dashboard-action" href="<?= site_url('laporan/penjualan') . '?from=' . esc($today) . '&to=' . esc($today) . '&format=csv' ?>">Unduh Ringkasan</a>
            <?php endif ?>
        </div>
    </section>

    <section class="dashboard-metrics" aria-label="Ringkasan operasional">
        <?php if ($canViewFinancials): ?>
            <article class="dashboard-card">
                <span class="dashboard-card-icon dashboard-icon-sales" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"></circle><path d="M15.5 8.5c-.7-.7-1.6-1-3.2-1-1.8 0-3 .9-3 2.1s.9 1.9 3 2.3 3 .9 3 2.2-1.2 2.2-3.1 2.2c-1.4 0-2.6-.5-3.4-1.3M12 5.5v13"></path></svg>
                </span>
                <div class="dashboard-card-content">
                    <div class="dashboard-card-heading">Penjualan hari ini</div>
                    <strong class="dashboard-card-value">Rp <?= esc(number_format($metrics['salesToday'], 0, ',', '.')) ?></strong>
                    <p class="dashboard-card-detail"><?= esc($metrics['retailCount']) ?> eceran <span>·</span> <?= esc($metrics['wholesaleCount']) ?> grosir</p>
                </div>
            </article>
        <?php endif ?>

        <?php if (isset($metrics['cashBalance'])): ?>
            <article class="dashboard-card">
                <span class="dashboard-card-icon dashboard-icon-green" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="6" width="18" height="13" rx="2"></rect><path d="M3 10h18m-13 5h4"></path></svg>
                </span>
                <div class="dashboard-card-content">
                    <div class="dashboard-card-heading">Saldo kas</div>
                    <strong class="dashboard-card-value">Rp <?= esc(number_format($metrics['cashBalance'], 0, ',', '.')) ?></strong>
                    <p class="dashboard-card-detail">Saldo awal ditambah mutasi kas tercatat.</p>
                </div>
            </article>
        <?php endif ?>

        <?php if ($canViewFinancials): ?>
            <article class="dashboard-card">
                <span class="dashboard-card-icon dashboard-icon-receivables" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M4 5h16v15H4z"></path><path d="M8 9h8m-8 4h8m-8 4h4"></path></svg>
                </span>
                <div class="dashboard-card-content">
                    <div class="dashboard-card-heading">Total piutang</div>
                    <strong class="dashboard-card-value">Rp <?= esc(number_format($metrics['outstandingReceivables'], 0, ',', '.')) ?></strong>
                    <p class="dashboard-card-detail"><?= esc($metrics['overdueCount']) ?> piutang lewat tempo</p>
                </div>
            </article>
            <article class="dashboard-card">
                <span class="dashboard-card-icon dashboard-icon-approvals" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"></path><path d="m9 12 2 2 4-4"></path></svg>
                </span>
                <div class="dashboard-card-content">
                    <div class="dashboard-card-heading">Perlu persetujuan</div>
                    <strong class="dashboard-card-value"><?= esc($metrics['pendingAdjustments']) ?> pengajuan</strong>
                    <p class="dashboard-card-detail"><?= esc($metrics['pendingVoids']) ?> Void <span>·</span> <?= esc($metrics['pendingReturns']) ?> Retur</p>
                </div>
            </article>
        <?php else: ?>
            <article class="dashboard-card">
                <span class="dashboard-card-icon dashboard-icon-stock" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="m12 3 9 5-9 5-9-5 9-5Z"></path><path d="m3 12 9 5 9-5M3 16l9 5 9-5"></path></svg>
                </span>
                <div class="dashboard-card-content">
                    <div class="dashboard-card-heading">Stok perlu restock</div>
                    <strong class="dashboard-card-value"><?= esc($metrics['needsRestock']) ?> <span class="dashboard-card-unit">barang</span></strong>
                    <p class="dashboard-card-detail"><?= esc($metrics['outOfStock']) ?> barang saat ini habis.</p>
                </div>
            </article>
        <?php endif ?>
    </section>

    <section class="dashboard-overview">
    <?php if ($canViewFinancials): ?>
        <section class="dashboard-panel dashboard-sales-trend" aria-labelledby="sales-trend-title">
            <div class="dashboard-panel-heading">
                <div>
                    <h2 id="sales-trend-title">Penjualan 7 hari terakhir</h2>
                    <p class="dashboard-panel-subtitle">Nilai penjualan bersih setelah retur yang disetujui.</p>
                </div>
                <strong class="dashboard-trend-total">Rp <?= esc(number_format($metrics['salesTrendTotal'], 0, ',', '.')) ?></strong>
            </div>
            <div class="dashboard-chart" role="img" aria-label="Grafik penjualan bersih selama tujuh hari">
                <?php foreach ($metrics['salesTrend'] as $trendDay): ?>
                    <div class="dashboard-chart-day" aria-label="<?= esc($trendDay['label'] . ' ' . $trendDay['day'] . ': Rp ' . number_format($trendDay['amount'], 0, ',', '.')) ?>">
                        <span class="dashboard-chart-value"><?= esc(number_format($trendDay['amount'] / 1000000, 1, ',', '.')) ?> jt</span>
                        <span class="dashboard-chart-track">
                            <span class="dashboard-chart-bar" style="height: <?= esc((string) $trendDay['height']) ?>%"></span>
                        </span>
                        <span class="dashboard-chart-label"><?= esc($trendDay['label']) ?><br><?= esc($trendDay['day']) ?></span>
                    </div>
                <?php endforeach ?>
            </div>
            <table class="visually-hidden">
                <caption>Nilai penjualan bersih tujuh hari terakhir</caption>
                <thead><tr><th>Tanggal</th><th>Penjualan bersih</th></tr></thead>
                <tbody>
                <?php foreach ($metrics['salesTrend'] as $trendDay): ?>
                    <tr><th><?= esc($trendDay['date']) ?></th><td>Rp <?= esc(number_format($trendDay['amount'], 2, ',', '.')) ?></td></tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </section>
    <?php endif ?>
        <section class="dashboard-panel dashboard-attention" aria-labelledby="attention-title">
            <div class="dashboard-panel-heading">
                <div>
                    <h2 id="attention-title">Perlu perhatian</h2>
                    <p class="dashboard-panel-subtitle">Tindakan prioritas hari ini</p>
                </div>
            </div>
            <div class="dashboard-attention-list">
                <?php if ($canViewFinancials && $metrics['pendingAdjustments'] > 0): ?>
                    <a class="dashboard-attention-item" href="<?= site_url('persetujuan') ?>">
                        <span class="dashboard-attention-icon is-green" aria-hidden="true">!</span>
                        <span><strong><?= esc($metrics['pendingAdjustments']) ?> pengajuan Void/Retur</strong><small>Menunggu persetujuan Anda</small></span>
                        <span class="dashboard-attention-arrow" aria-hidden="true">›</span>
                    </a>
                <?php endif ?>
                <?php if ($canViewFinancials && $metrics['overdueCount'] > 0): ?>
                    <a class="dashboard-attention-item" href="<?= site_url('piutang') ?>">
                        <span class="dashboard-attention-icon is-amber" aria-hidden="true">!</span>
                        <span><strong><?= esc($metrics['overdueCount']) ?> piutang lewat tempo</strong><small>Periksa daftar piutang yang terlambat</small></span>
                        <span class="dashboard-attention-arrow" aria-hidden="true">›</span>
                    </a>
                <?php endif ?>
                <?php if ($metrics['needsRestock'] > 0): ?>
                    <a class="dashboard-attention-item" href="<?= site_url('stok') ?>">
                        <span class="dashboard-attention-icon is-orange" aria-hidden="true">!</span>
                        <span><strong><?= esc($metrics['needsRestock']) ?> stok perlu restock</strong><small><?= esc($metrics['outOfStock']) ?> barang sudah habis</small></span>
                        <span class="dashboard-attention-arrow" aria-hidden="true">›</span>
                    </a>
                <?php endif ?>
                <?php if ((! $canViewFinancials || $metrics['pendingAdjustments'] === 0)
                    && (! $canViewFinancials || $metrics['overdueCount'] === 0)
                    && $metrics['needsRestock'] === 0): ?>
                    <p class="dashboard-attention-empty">Tidak ada tindakan prioritas saat ini.</p>
                <?php endif ?>
            </div>
        </section>
    </section>

    <?php if ($roleCode === 'OWNER'): ?>
        <section class="dashboard-panel dashboard-activity" aria-labelledby="activity-title">
            <div class="dashboard-panel-heading">
                <div>
                    <h2 id="activity-title">Aktivitas terbaru</h2>
                    <p class="dashboard-panel-subtitle">Jejak aktivitas seluruh pengguna</p>
                </div>
                <a class="dashboard-action" href="<?= site_url('aktivitas') ?>">Buka log</a>
            </div>
            <div class="table-wrap">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Waktu</th><th>Pengguna</th><th>Peran</th><th>Aktivitas</th><th>Modul</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentActivity as $activity): ?>
                        <?php
                        $activityLabels = [
                            'LOGIN' => 'Masuk ke sistem',
                            'POST_SALE' => 'Transaksi penjualan',
                            'REQUEST_SALE_ADJUSTMENT' => 'Pengajuan Void/Retur',
                            'APPROVE_SALE_ADJUSTMENT' => 'Persetujuan Void/Retur',
                            'REJECT_SALE_ADJUSTMENT' => 'Penolakan Void/Retur',
                            'RECORD_SALE_REFUND' => 'Pencatatan refund',
                            'RECEIVE_RECEIVABLE_PAYMENT' => 'Pembayaran piutang',
                        ];
                        $activityLabel = $activityLabels[$activity['action']]
                            ?? ucfirst(strtolower(str_replace('_', ' ', $activity['action'])));
                        ?>
                        <tr>
                            <td><?= esc(date('d/m H:i', strtotime($activity['created_at']))) ?></td>
                            <td><?= esc($activity['full_name']) ?></td>
                            <td><?= esc($activity['role_name']) ?></td>
                            <td><?= esc($activityLabel) ?></td>
                            <td><?= esc($activity['entity_type']) ?></td>
                        </tr>
                    <?php endforeach ?>
                    <?php if ($recentActivity === []): ?><tr><td colspan="5" class="dashboard-empty">Belum ada aktivitas yang tercatat.</td></tr><?php endif ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif ?>
</main>
</div>
</div>
</body>
</html>
