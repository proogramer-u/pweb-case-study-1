<?php

// Harga disimpan sebagai integer sen selama perhitungan agar tidak terkena pembulatan float.
function moneyCents(string $amount): int {
    if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $amount)) {
        throw new InvalidArgumentException('Harga tidak valid.');
    }

    $parts = explode('.', $amount);
    $rupiah = (int) $parts[0];
    $cents = (int) str_pad($parts[1] ?? '', 2, '0');

    return ($rupiah * 100) + $cents;
}

// Ubah integer sen kembali menjadi format DECIMAL untuk disimpan ke MySQL.
function decimalMoney(int $cents): string {
    $rupiah = intdiv($cents, 100);
    $remainingCents = str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

    return $rupiah . '.' . $remainingCents;
}

function quantity(mixed $value): int {
    if (!is_scalar($value)) {
        throw new InvalidArgumentException('Jumlah harus 1–99.');
    }

    $validatedQuantity = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 99]
    ]);

    if ($validatedQuantity === false) {
        throw new InvalidArgumentException('Jumlah harus 1–99.');
    }

    return (int) $value;
}

function orderTotals(array $items, ?string $role): array {
    $subtotal = 0;

    foreach ($items as $item) {
        $unitPrice = moneyCents((string) $item['price']);
        $itemQuantity = quantity($item['quantity']);
        $subtotal += $unitPrice * $itemQuantity;
    }

    $discount = 0;

    if ($role === 'customer') {
        // Diskon 10%, dibulatkan half-up ke rupiah penuh, lalu dikembalikan ke sen.
        $discount = intdiv($subtotal + 500, 1000) * 100;
    }

    return [
        'subtotal' => $subtotal,
        'discount' => $discount,
        'total' => $subtotal - $discount
    ];
}

function canTransition(string $role, string $from, string $to): bool {
    if (!in_array($role, ['owner', 'cashier'], true)) {
        return false;
    }

    // Owner hanya boleh membatalkan pesanan yang belum mencapai status akhir.
    if ($role === 'owner' && $to === 'cancelled') {
        return in_array($from, ['pending', 'processing', 'ready'], true);
    }

    $nextStatuses = [
        'pending' => 'processing',
        'processing' => 'ready',
        'ready' => 'completed'
    ];

    return ($nextStatuses[$from] ?? null) === $to;
}

function safeSpreadsheetText(string $text): string {
    // Excel dapat menganggap teks dengan awalan ini sebagai formula.
    if (preg_match('/^[\s\x00-\x1f]*[=+@-]/u', $text)) {
        return "'" . $text;
    }

    return $text;
}

function validateOrderStorage(array $totals): void {
    // Batas nilai kolom DECIMAL(12,2), dinyatakan dalam sen.
    $maximumAmount = 999_999_999_999;

    if ($totals['subtotal'] > $maximumAmount) {
        throw new InvalidArgumentException(
            'Total pesanan melebihi batas penyimpanan. Kurangi jumlah atau item.'
        );
    }
}
