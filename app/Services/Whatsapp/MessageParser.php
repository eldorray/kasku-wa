<?php

namespace App\Services\Whatsapp;

class MessageParser
{
    /**
     * Keyword → category slug map. First match wins.
     */
    private const CATEGORY_KEYWORDS = [
        'food' => ['kopi', 'makan', 'gofood', 'grabfood', 'warteg', 'bakmi', 'sushi', 'sarapan', 'nasi', 'padang', 'indomaret', 'alfamart', 'superindo', 'jajan', 'snack', 'minum'],
        'transport' => ['gojek', 'grab', 'bensin', 'pertamina', 'shopee food', 'transport', 'taksi', 'parkir', 'tol', 'mrt', 'krl', 'bus', 'angkot', 'ojek'],
        'shop' => ['tokopedia', 'shopee', 'lazada', 'bukalapak', 'baju', 'sepatu', 'belanja', 'keyboard', 'mouse', 'gadget'],
        'bill' => ['pln', 'listrik', 'pdam', 'air', 'indihome', 'internet', 'wifi', 'tagihan', 'pulsa', 'bpjs'],
        'health' => ['apotek', 'k24', 'guardian', 'dokter', 'rumah sakit', 'rs ', 'obat', 'klinik', 'vitamin'],
        'fun' => ['cgv', 'xxi', 'bioskop', 'spotify', 'netflix', 'disney', 'youtube', 'game', 'steam'],
        'work' => ['invoice', 'klien', 'project', 'gaji', 'bonus', 'fee'],
        'save' => ['tabungan', 'saving', 'deposito', 'reksadana', 'saham'],
    ];

    /**
     * @return array{type:string,amount:int,category:string,merchant:?string,note:string,account_label:?string}|null
     */
    public function parse(string $message): ?array
    {
        $raw = trim($message);
        if ($raw === '') {
            return null;
        }

        $type = 'expense';
        $body = $raw;

        if (preg_match('#^/expense\s+(.+)$#i', $body, $m)) {
            $body = $m[1];
            $type = 'expense';
        } elseif (preg_match('#^/income\s+(.+)$#i', $body, $m)) {
            $body = $m[1];
            $type = 'income';
        } elseif (preg_match('#\b(masuk|terima|gaji|gajian|bonus|invoice|fee|refund)\b#i', $body)) {
            $type = 'income';
        }

        $amount = $this->extractAmount($body);
        if ($amount === null || $amount <= 0) {
            return null;
        }

        $accountLabel = $this->extractAccountLabel($body);
        $bodyWithoutAccount = $this->removeAccountLabel($body);
        $merchant = $this->extractMerchant($bodyWithoutAccount);
        $category = $this->detectCategory(mb_strtolower($bodyWithoutAccount));

        return [
            'type' => $type,
            'amount' => $type === 'expense' ? -$amount : $amount,
            'category' => $category,
            'merchant' => $merchant,
            'note' => $raw,
            'account_label' => $accountLabel,
        ];
    }

    /**
     * Extract amount as integer rupiah. Avoids float intermediate to prevent precision loss.
     */
    private function extractAmount(string $text): ?int
    {
        // Order matters: most specific units first.
        $patterns = [
            ['#(\d+(?:[.,]\d+)?)\s*(?:jt|juta)\b#i', 1_000_000],
            ['#(\d+(?:[.,]\d+)?)\s*(?:rb|ribu|k)\b#i', 1_000],
            ['#rp\s*([\d.,]+)#i', 1],
            ['#(\d{4,})#', 1],
        ];

        foreach ($patterns as [$pattern, $multiplier]) {
            if (! preg_match($pattern, $text, $m)) {
                continue;
            }

            if ($multiplier === 1) {
                // bare integer or rp-prefixed: strip thousand separators
                $digits = preg_replace('/[^\d]/', '', $m[1]);
                if ($digits === '') {
                    continue;
                }

                return (int) $digits;
            }

            // jt/rb path: support up to 3 decimal digits without float.
            $raw = str_replace(',', '.', $m[1]);
            if (! preg_match('/^(\d+)(?:\.(\d+))?$/', $raw, $parts)) {
                continue;
            }
            $whole = (int) $parts[1];
            $frac = $parts[2] ?? '';
            $value = $whole * $multiplier;
            if ($frac !== '') {
                // shift fractional digits up to multiplier scale, then truncate.
                $scale = strlen((string) $multiplier) - 1;
                $fracPadded = substr(str_pad($frac, $scale, '0'), 0, $scale);
                $value += (int) $fracPadded;
            }

            if ($value > 0) {
                return $value;
            }
        }

        return null;
    }

    private function extractAccountLabel(string $text): ?string
    {
        if (! preg_match('#\b(?:akun|dompet|wallet|rekening)\s+(.+)$#i', $text, $m)) {
            return null;
        }

        $label = preg_replace('#\s+#', ' ', trim($m[1]));
        $label = trim($label, " \t\n\r.,/");

        return $label === '' ? null : $label;
    }

    private function removeAccountLabel(string $text): string
    {
        $clean = preg_replace('#\b(?:akun|dompet|wallet|rekening)\s+.+$#i', '', $text);

        return trim($clean ?? $text);
    }

    private function extractMerchant(string $text): ?string
    {
        $clean = preg_replace('#(\d+(?:[.,]\d+)?)\s*(jt|juta|rb|ribu|k)\b#i', '', $text);
        $clean = preg_replace('#rp\s*[\d.,]+#i', '', $clean);
        $clean = preg_replace('#\s+#', ' ', trim($clean));
        $clean = preg_replace('#\b(ke|di|untuk|buat|dari)\s+.*$#i', '', $clean);
        $clean = trim($clean, " \t\n\r.,/");

        if ($clean === '' || mb_strlen($clean) < 2) {
            return null;
        }

        return ucwords(mb_strtolower($clean));
    }

    private function detectCategory(string $textLower): string
    {
        foreach (self::CATEGORY_KEYWORDS as $slug => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($textLower, $kw)) {
                    return $slug;
                }
            }
        }

        return 'other';
    }
}
