<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Twilio Programmable Messaging. TWILIO_FROM is a sender number or a Messaging Service SID (MG…).
 */
class TwilioSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        $sid = (string) config('services.twilio.sid');
        $from = (string) config('services.twilio.from');

        try {
            Http::baseUrl(config('services.twilio.base_url'))
                ->withBasicAuth($sid, (string) config('services.twilio.token'))
                ->asForm()
                ->acceptJson()
                ->timeout(15)
                ->post("/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => $to,
                    'Body' => $message,
                    ...(str_starts_with($from, 'MG') ? ['MessagingServiceSid' => $from] : ['From' => $from]),
                ])
                ->throw();
        } catch (RequestException|ConnectionException $e) {
            throw new SmsException('Twilio request failed.', previous: $e);
        }
    }
}
