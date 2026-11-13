<?php

namespace App\Services;

final class Money
{
    public static function cents($amount): int
    {
        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', (string) $amount)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'Số tiền phải không âm và có tối đa 2 chữ số thập phân.']);
        }
        $parts = explode('.', (string) $amount);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
