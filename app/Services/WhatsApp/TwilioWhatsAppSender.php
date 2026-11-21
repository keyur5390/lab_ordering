<?php

namespace App\Services\WhatsApp;

use App\Services\Sms\SmsException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp through Twilio (B-31): sends an approved Content Template with its variables.
 * TWILIO_WHATSAPP_FROM is the WhatsApp sender number (or a Messaging Service SID, MG…).
 */
class TwilioWhatsAppSender
{
    public static function isConfigured(): bool
    {
        return filled(config('services.twilio.sid')) && filled(config('services.twilio.token'))
            && filled(config('services.twilio.whatsapp_from'));
    }

    /**
     * @param  array<string, string>  $variables  e.g. ["1" => "Asha", "2" => "ORD-000123"]
     *
     * @throws SmsException so the queued job is retried
     */
    public function send(string $to, string $contentSid, array $variables): void
    {
        $sid = (string) config('services.twilio.sid');
        $from = (string) config('services.twilio.whatsapp_from');

        try {
            Http::baseUrl(config('services.twilio.base_url'))
                ->withBasicAuth($sid, (string) config('services.twilio.token'))
                ->asForm()
                ->acceptJson()
                ->timeout(15)
                ->post("/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => "whatsapp:{$to}",
                    'ContentSid' => $contentSid,
                    'ContentVariables' => json_encode((object) $variables, JSON_UNESCAPED_UNICODE),
                    ...(str_starts_with($from, 'MG') ? ['MessagingServiceSid' => $from] : ['From' => str_starts_with($from, 'whatsapp:') ? $from : "whatsapp:{$from}"]),
                ])
                ->throw();
        } catch (RequestException|ConnectionException $e) {
            throw new SmsException('Twilio WhatsApp request failed.', previous: $e);
        }
    }
}
