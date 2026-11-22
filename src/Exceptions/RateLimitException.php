<?php

namespace WebdevsComAu\MachShip\Exceptions;

/**
 * MachShip returned 429 Too Many Requests.
 */
class RateLimitException extends MachShipException
{
    /**
     * Seconds to wait before retrying, when MachShip sends a Retry-After header.
     */
    public function retryAfter(): ?int
    {
        $value = $this->response?->header('Retry-After');

        return is_numeric($value) ? (int) $value : null;
    }
}
