<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Owner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends a portal sign-in code over WhatsApp through UltraMsg — a stopgap
 * until a Saudi SMS sender name is registered, which needs the commercial
 * registration. Switched on by MOBILE_OTP_CHANNEL=whatsapp; the code itself
 * is never logged.
 */
class WhatsAppOtpSender
{
    private const TIMEOUT_SECONDS = 10;

    /** Whether the code reached UltraMsg; a failure is logged, never thrown at the person signing in. */
    public function send(string $phone, string $code, int $minutes): bool
    {
        $instance = (string) config('services.ultramsg.instance');
        $token = (string) config('services.ultramsg.token');
        $national = Owner::normalisePhone($phone);

        if ($instance === '' || $token === '' || $national === '') {
            Log::error('WhatsApp OTP not sent: UltraMsg is not configured or the phone is empty.');

            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(rtrim((string) config('services.ultramsg.url'), '/').'/'.$instance.'/messages/chat', [
                    'token' => $token,
                    'to' => '+966'.$national,
                    'body' => __('portal.otp_whatsapp_message', ['code' => $code, 'minutes' => $minutes], 'ar'),
                ]);
        } catch (ConnectionException $e) {
            Log::error('WhatsApp OTP not sent: UltraMsg unreachable.', ['error' => $e->getMessage()]);

            return false;
        }

        // UltraMsg answers 200 with {"sent":"true",...} on success, and an "error" key otherwise.
        $sent = $response->successful() && in_array($response->json('sent'), ['true', true], true);

        if (! $sent) {
            Log::error('WhatsApp OTP not sent: UltraMsg refused it.', ['status' => $response->status(), 'error' => $response->json('error')]);
        }

        return $sent;
    }
}
