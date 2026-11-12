<?php

namespace App\Libraries;

use DateTimeImmutable;

final class ReceivableReminderPolicy
{
    public const H3_DAYS = 3;

    /**
     * @return 'overdue'|'due_today'|'upcoming'|null
     */
    public static function classify(?string $dueDate, float $outstandingAmount, DateTimeImmutable $today): ?string
    {
        if ($dueDate === null || $outstandingAmount <= 0) {
            return null;
        }

        $todayDate = $today->format('Y-m-d');

        if ($dueDate < $todayDate) {
            return 'overdue';
        }
        if ($dueDate === $todayDate) {
            return 'due_today';
        }

        $h3Cutoff = $today->modify('+' . self::H3_DAYS . ' days')->format('Y-m-d');

        return $dueDate <= $h3Cutoff ? 'upcoming' : null;
    }
}
