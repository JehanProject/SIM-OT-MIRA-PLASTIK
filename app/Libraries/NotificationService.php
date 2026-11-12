<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class NotificationService
{
    /**
     * @param list<string> $roleCodes
     */
    public static function notifyRoles(
        BaseConnection $db,
        array $roleCodes,
        string $type,
        string $title,
        string $message,
        ?string $targetUrl = null,
    ): void {
        if ($roleCodes === []) {
            return;
        }

        $users = $db->table('pengguna AS u')
            ->select('u.user_id')
            ->join('peran AS r', 'r.role_id = u.role_id')
            ->where('u.is_active', true)
            ->whereIn('r.role_code', array_values(array_unique($roleCodes)))
            ->get()
            ->getResultArray();
        foreach ($users as $user) {
            if (! $db->table('notifikasi_pengguna')->insert([
                'user_id' => (int) $user['user_id'],
                'notification_type' => $type,
                'title' => $title,
                'message' => $message,
                'target_url' => $targetUrl,
            ])) {
                throw new RuntimeException('Gagal menyimpan notifikasi pengguna.');
            }
        }
    }

    /**
     * @param list<int> $productIds
     */
    public static function notifyLowStock(BaseConnection $db, array $productIds): void
    {
        $storeAlerts = [];
        $totalAlerts = [];
        foreach (array_unique($productIds) as $productId) {
            $stock = $db->query(
                "SELECT b.product_code, b.product_name, b.min_stock, u.unit_symbol,
                        COALESCE(SUM(CASE WHEN l.location_code = 'TOKO' THEN balance.quantity ELSE 0 END), 0) AS store_stock,
                        COALESCE(SUM(CASE WHEN l.location_code = 'GUDANG' THEN balance.quantity ELSE 0 END), 0) AS warehouse_stock
                 FROM barang AS b
                 JOIN satuan AS u ON u.unit_id = b.unit_id
                 LEFT JOIN saldo_persediaan AS balance ON balance.product_id = b.product_id
                 LEFT JOIN lokasi_persediaan AS l ON l.location_id = balance.location_id
                 WHERE b.product_id = ? AND b.is_active = 1
                 GROUP BY b.product_id, b.product_code, b.product_name, b.min_stock, u.unit_symbol",
                [(int) $productId],
            )->getRowArray();
            if ($stock === null) {
                continue;
            }

            $storeStock = (float) $stock['store_stock'];
            $warehouseStock = (float) $stock['warehouse_stock'];
            $minimumStock = (float) $stock['min_stock'];
            $productName = $stock['product_code'] . ' · ' . $stock['product_name'];
            $unit = $stock['unit_symbol'];
            if ($storeStock <= $minimumStock) {
                $storeAlerts[] = $productName . ' (' . self::formatQuantity($storeStock) . ' ' . $unit . ')';
            }
            if (($storeStock + $warehouseStock) <= $minimumStock) {
                $totalAlerts[] = $productName . ' (' . self::formatQuantity($storeStock + $warehouseStock) . ' ' . $unit . ')';
            }
        }
        if ($storeAlerts !== []) {
            self::notifyRoles(
                $db,
                ['OWNER', 'KASIR'],
                'LOW_STORE_STOCK',
                'Stok Toko perlu diperiksa',
                self::stockAlertMessage($storeAlerts),
                'stok',
            );
        }
        if ($totalAlerts !== []) {
            self::notifyRoles(
                $db,
                ['OWNER', 'STAF_GUDANG'],
                'LOW_TOTAL_STOCK',
                'Stok barang menipis atau habis',
                self::stockAlertMessage($totalAlerts),
                'stok',
            );
        }
    }

    /**
     * @param list<string> $items
     */
    private static function stockAlertMessage(array $items): string
    {
        $visibleItems = array_slice($items, 0, 5);
        $remaining = count($items) - count($visibleItems);
        if ($remaining > 0) {
            $visibleItems[] = 'dan ' . $remaining . ' barang lainnya';
        }

        return mb_substr(count($items) . ' barang berada pada atau di bawah batas stok: ' . implode(', ', $visibleItems) . '.', 0, 500);
    }

    private static function formatQuantity(float $quantity): string
    {
        [$whole, $fraction] = explode(',', number_format($quantity, 6, ',', '.'));
        $fraction = rtrim($fraction, '0');

        return $fraction === '' ? $whole : $whole . ',' . $fraction;
    }
}
