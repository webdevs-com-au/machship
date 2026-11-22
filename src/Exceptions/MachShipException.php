<?php

namespace WebdevsComAu\MachShip\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class MachShipException extends RuntimeException
{
    /**
     * @param  array<int, array{validationType?: mixed, memberNames?: array<int, string>, errorMessage?: string}>  $errors
     */
    public function __construct(
        string $message = '',
        protected array $errors = [],
        protected ?Response $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $response?->status() ?? 0, $previous);
    }

    /**
     * Raw MachShip validation results ({validationType, memberNames, errorMessage}).
     *
     * @return array<int, array<string, mixed>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Just the error messages.
     *
     * @return array<int, string>
     */
    public function messages(): array
    {
        return array_values(array_filter(array_map(
            fn (array $error) => $error['errorMessage'] ?? null,
            $this->errors,
        )));
    }

    public function response(): ?Response
    {
        return $this->response;
    }

    public function status(): ?int
    {
        return $this->response?->status();
    }
}
