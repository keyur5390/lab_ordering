<?php

namespace App\Services\Sms;

use RuntimeException;

/** The SMS provider couldn't be reached or refused the message. */
class SmsException extends RuntimeException {}
