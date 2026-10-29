<?php

namespace App\Services\Sms;

interface SmsSender
{
    /**
     * @param  string  $to  international format, e.g. +919812345678
     *
     * @throws SmsException
     */
    public function send(string $to, string $message): void;
}
