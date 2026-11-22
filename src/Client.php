<?php

namespace WebdevsComAu\MachShip;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use WebdevsComAu\MachShip\Data\Responses\Document;
use WebdevsComAu\MachShip\Exceptions\AuthenticationException;
use WebdevsComAu\MachShip\Exceptions\ConfigurationException;
use WebdevsComAu\MachShip\Exceptions\MachShipException;
use WebdevsComAu\MachShip\Exceptions\RateLimitException;
use WebdevsComAu\MachShip\Exceptions\ValidationException;
use WebdevsComAu\MachShip\Support\Query;
use Throwable;

/**
 * Low-level HTTP client for the MachShip API v2.
 *
 * Every JSON response is wrapped in an envelope: {"object": ..., "errors": [...]}.
 * This client unwraps `object` and turns error results into exceptions.
 */
class Client
{
    public const API_PREFIX = '/apiv2/';

    /**
     * @param  array{timeout?: int, connect_timeout?: int, retry?: array{times?: int, sleep_ms?: int}}  $options
     */
    public function __construct(
        protected HttpFactory $http,
        protected ?string $token,
        protected string $baseUrl = 'https://live.machship.com',
        protected array $options = [],
    ) {
    }

    public function withToken(string $token): static
    {
        $clone = clone $this;
        $clone->token = $token;

        return $clone;
    }

    public function baseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * Full URL for an endpoint, e.g. url('consignments/getConsignment').
     *
     * @param  array<string, mixed>  $query
     */
    public function url(string $endpoint, array $query = []): string
    {
        $url = $this->baseUrl().self::API_PREFIX.ltrim($endpoint, '/');
        $qs = Query::build($query);

        return $qs === '' ? $url : $url.'?'.$qs;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $endpoint, array $query = []): mixed
    {
        return $this->unwrap($this->send('GET', $endpoint, $query));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function post(string $endpoint, mixed $body = null, array $query = []): mixed
    {
        return $this->unwrap($this->send('POST', $endpoint, $query, $body));
    }

    /**
     * Fetch a binary file (PDF / ZIP). MachShip returns JSON errors on failure,
     * which are converted to exceptions just like any other call.
     *
     * @param  array<string, mixed>  $query
     */
    public function download(string $method, string $endpoint, array $query = [], mixed $body = null, ?string $fallbackName = null): Document
    {
        $response = $this->send($method, $endpoint, $query, $body);

        if ($this->isJson($response)) {
            // A JSON body on a file endpoint means something went wrong.
            $this->unwrap($response);

            throw new MachShipException('MachShip returned JSON instead of a file.', [], $response);
        }

        return Document::fromResponse($response, $fallbackName);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function send(string $method, string $endpoint, array $query = [], mixed $body = null): Response
    {
        $options = [];

        if ($body !== null) {
            $options['body'] = json_encode($body, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        }

        try {
            $response = $this->request($method)->send($method, $this->url($endpoint, $query), $options);
        } catch (ConnectionException $e) {
            throw new MachShipException('Could not connect to MachShip: '.$e->getMessage(), [], null, $e);
        } catch (RequestException $e) {
            $response = $e->response;
        }

        $this->guardStatus($response);

        return $response;
    }

    protected function request(string $method): PendingRequest
    {
        if (blank($this->token)) {
            throw ConfigurationException::missingToken();
        }

        $retry = $this->options['retry'] ?? [];
        $times = strtoupper($method) === 'GET' ? (int) ($retry['times'] ?? 0) : 0;

        $request = $this->http
            ->withHeaders(['token' => $this->token])
            ->acceptJson()
            ->contentType('application/json')
            ->timeout((int) ($this->options['timeout'] ?? 30))
            ->connectTimeout((int) ($this->options['connect_timeout'] ?? 10));

        if ($times > 0) {
            $request = $request->retry(
                $times + 1,
                (int) ($retry['sleep_ms'] ?? 500),
                fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && ($e->response->serverError() || $e->response->status() === 429)),
                throw: false,
            );
        }

        return $request;
    }

    protected function guardStatus(Response $response): void
    {
        $errors = $this->isJson($response) ? $this->errorsFrom($response->json()) : [];

        $message = fn (string $default) => $errors !== []
            ? implode(' ', array_map(fn ($e) => $e['errorMessage'] ?? '', $errors))
            : $default;

        match (true) {
            $response->status() === 401, $response->status() === 403 => throw new AuthenticationException(
                $message('MachShip rejected the API token (HTTP '.$response->status().').'), $errors, $response,
            ),
            $response->status() === 429 => throw new RateLimitException(
                $message('MachShip rate limit exceeded.'), $errors, $response,
            ),
            $response->failed() && $errors !== [] => throw new ValidationException($message(''), $errors, $response),
            $response->failed() => throw new MachShipException(
                'MachShip request failed with HTTP '.$response->status().'.', [], $response,
            ),
            default => null,
        };
    }

    protected function unwrap(Response $response): mixed
    {
        $payload = $response->json();

        if (! is_array($payload) || ! array_key_exists('object', $payload)) {
            return $payload;
        }

        $errors = $this->errorsFrom($payload);

        if ($errors !== []) {
            throw new ValidationException(
                implode(' ', array_map(fn ($e) => $e['errorMessage'] ?? '', $errors)),
                $errors,
                $response,
            );
        }

        return $payload['object'];
    }

    /**
     * Only results flagged as errors count; MachShip can also return
     * info/warning results alongside a successful payload.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function errorsFrom(mixed $payload): array
    {
        $results = is_array($payload) ? ($payload['errors'] ?? null) : null;

        if (! is_array($results)) {
            return [];
        }

        return array_values(array_filter($results, function ($result) {
            if (! is_array($result)) {
                return false;
            }

            $type = $result['validationType'] ?? 'Error';

            // MachshipValidationType: 0 Success, 1 Error, 2 Info, 3 Warning
            return $type === 1 || $type === '1' || (is_string($type) && strcasecmp($type, 'Error') === 0);
        }));
    }

    protected function isJson(Response $response): bool
    {
        return str_contains(strtolower($response->header('Content-Type')), 'json');
    }
}
