<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a payment gateway webhook fails signature validation.
 * The controller catches this and returns 200 (to prevent gateway retries)
 * while logging the event for security monitoring.
 */
class InvalidWebhookSignatureException extends Exception {}
