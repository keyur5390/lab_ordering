<?php

namespace App\Services\Payments;

use RuntimeException;

/** The gateway couldn't be reached or refused the request. */
class PaymentGatewayException extends RuntimeException {}
