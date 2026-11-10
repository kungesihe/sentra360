<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Every email/SMS goes through here and is written to the notifications table.
 *
 * No email or SMS provider is integrated yet. When MAIL_PROVIDER / SMS_PROVIDER
 * are empty, messages are stored with status 'not_configured' and are NOT
 * delivered. The app must never tell a user "we sent you an SMS" in that case.
 * In development, the Developer Outbox (/dev/outbox) shows these messages so
 * flows can be tested.
 */
final class Notifier
{
    public const DELIVERED = 'sent';
    public const NOT_CONFIGURED = 'not_configured';

    public static function email(string $to, string $subject, string $body, ?int $tenantId = null, ?int $userId = null): string
    {
        return self::dispatch('email', $to, $subject, $body, $tenantId, $userId, config('mail_provider'));
    }

    public static function sms(string $to, string $body, ?int $tenantId = null, ?int $userId = null): string
    {
        return self::dispatch('sms', $to, null, $body, $tenantId, $userId, config('sms_provider'));
    }

    public static function emailConfigured(): bool
    {
        return (bool) config('mail_provider');
    }

    public static function smsConfigured(): bool
    {
        return (bool) config('sms_provider');
    }

    private static function dispatch(string $channel, string $to, ?string $subject, string $body, ?int $tenantId, ?int $userId, ?string $provider): string
    {
        $status = self::NOT_CONFIGURED;
        $error = null;
        if ($provider) {
            // Provider adapters (e.g. SMTP, Clickatell, BulkSMS) are added in a later phase.
            // Until one exists for the configured name, we record a failure rather than pretend.
            $status = 'failed';
            $error = "No adapter implemented for provider '$provider'";
        }
        DB::insert('notifications', [
            'tenant_id' => $tenantId,
            'user_id'   => $userId,
            'channel'   => $channel,
            'recipient' => $to,
            'subject'   => $subject,
            'body'      => $body,
            'status'    => $status,
            'provider'  => $provider,
            'error'     => $error,
        ]);
        return $status;
    }
}
