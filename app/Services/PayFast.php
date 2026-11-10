<?php
declare(strict_types=1);

namespace App\Services;

/**
 * PayFast (South Africa) integration framework.
 *
 * NOT ACTIVE until PAYFAST_MERCHANT_ID and PAYFAST_MERCHANT_KEY are set.
 * Card details never touch this app: the customer pays on PayFast's own page,
 * and PayFast confirms the payment to /payments/payfast/notify (ITN), which is
 * verified (signature + server-to-server validation + amount) before the
 * payment is marked paid.
 */
final class PayFast
{
    public static function configured(): bool
    {
        return (bool) env('PAYFAST_MERCHANT_ID') && (bool) env('PAYFAST_MERCHANT_KEY');
    }

    public static function sandbox(): bool
    {
        return env('PAYFAST_SANDBOX', '1') === '1';
    }

    public static function processUrl(): string
    {
        return self::sandbox() ? 'https://sandbox.payfast.co.za/eng/process' : 'https://www.payfast.co.za/eng/process';
    }

    private static function validateUrl(): string
    {
        return self::sandbox() ? 'https://sandbox.payfast.co.za/eng/query/validate' : 'https://www.payfast.co.za/eng/query/validate';
    }

    /** Signature: MD5 of the url-encoded fields in order, plus the passphrase if set. */
    public static function signature(array $fields): string
    {
        $pairs = [];
        foreach ($fields as $k => $v) {
            if ($k === 'signature' || $v === '' || $v === null) {
                continue;
            }
            $pairs[] = $k . '=' . urlencode(trim((string) $v));
        }
        $str = implode('&', $pairs);
        if ($pass = env('PAYFAST_PASSPHRASE')) {
            $str .= '&passphrase=' . urlencode(trim($pass));
        }
        return md5($str);
    }

    /** Hidden form fields for redirecting the customer to PayFast. */
    public static function checkoutFields(array $payment, array $customer, string $itemName): array
    {
        $fields = [
            'merchant_id'   => env('PAYFAST_MERCHANT_ID'),
            'merchant_key'  => env('PAYFAST_MERCHANT_KEY'),
            'return_url'    => app_url('/account/payments?paid=' . urlencode($payment['reference'])),
            'cancel_url'    => app_url('/account/payments?cancelled=1'),
            'notify_url'    => app_url('/payments/payfast/notify'),
            'name_first'    => $customer['first_name'],
            'name_last'     => $customer['surname'],
            'email_address' => $customer['email'],
            'm_payment_id'  => $payment['reference'],
            'amount'        => number_format($payment['amount_cents'] / 100, 2, '.', ''),
            'item_name'     => mb_substr($itemName, 0, 100),
        ];
        $fields['signature'] = self::signature($fields);
        return $fields;
    }

    /** Verify an Instant Transaction Notification. Returns null if valid, otherwise the reason. */
    public static function verifyItn(array $post, int $expectedCents): ?string
    {
        if (!self::configured()) {
            return 'PayFast is not configured';
        }
        if (!hash_equals(self::signature($post), (string) ($post['signature'] ?? ''))) {
            return 'Bad signature';
        }
        if (abs((float) ($post['amount_gross'] ?? 0) - $expectedCents / 100) > 0.01) {
            return 'Amount mismatch';
        }
        $body = http_build_query(array_diff_key($post, ['signature' => 1]));
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $body, 'timeout' => 15]]);
        $resp = @file_get_contents(self::validateUrl(), false, $ctx);
        if (trim((string) $resp) !== 'VALID') {
            return 'PayFast did not confirm the notification';
        }
        return null;
    }
}
