<?php

declare(strict_types=1);

namespace App\Support\ClinicBot;

final class ClinicBotPhoneMatcher
{
    /**
     * @return list<string>
     */
    public function variants(string $phone): array
    {
        if (str_starts_with($phone, 'lid:')) {
            return [];
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if ($digits === '') {
            return [];
        }

        $variants = [$digits];

        if (strlen($digits) === 11 && str_starts_with($digits, '51')) {
            $variants[] = substr($digits, 2);
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            $variants[] = '51'.$digits;
        }

        if (strlen($digits) > 9) {
            $variants[] = substr($digits, -9);
        }

        return array_values(array_unique(array_filter($variants)));
    }

    public function digitsOnly(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '';
        }

        return preg_replace('/\D/', '', $phone) ?? '';
    }
}
