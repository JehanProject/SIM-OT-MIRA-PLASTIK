<?php

if (! function_exists('format_quantity')) {
    function format_quantity(float|int|string $quantity, int $precision = 3): string
    {
        $precision = max(0, min(6, $precision));
        [$whole, $fraction] = explode(',', number_format((float) $quantity, $precision, ',', '.'));
        $fraction = rtrim($fraction, '0');

        return $fraction === '' ? $whole : $whole . ',' . $fraction;
    }
}
