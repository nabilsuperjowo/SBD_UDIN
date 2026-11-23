<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function cents(string|int $value): int
    {
        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', (string) $value)) {
            throw new InvalidArgumentException('Nominal tidak valid.');
        }
        $parts = explode('.', (string) $value);
        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public static function decimal(int $cents): string
    {
        if ($cents < 0 || $cents > 999999999999) { throw new InvalidArgumentException('Nominal di luar batas DECIMAL(12,2).'); }
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function rupiah(string|int $value): string
    {
        $parts = explode('.', (string) $value);
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $parts[0]);
        $fraction = str_pad($parts[1] ?? '', 2, '0');
        return 'Rp '. $whole.($fraction === '00' ? '' : ','.$fraction);
    }
}
