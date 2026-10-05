<?php

namespace App\Firebase;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Firebase Realtime Database client backed by the REST API.
 *
 * https://firebase.google.com/docs/reference/rest/database
 *
 * Authentication uses an OAuth2 access token minted from the service account
 * by {@see ServiceAccountTokenProvider}, sent as a Bearer token.
 */
class FirebaseDatabaseClient implements DatabaseClient
{
    public function __construct(
        private string $databaseUrl,
        private ServiceAccountTokenProvider $tokens,
        private int $maxTransactionRetries = 5,
    ) {}

    public function get(string $path): mixed
    {
        $response = $this->request()->get($this->url($path));

        if ($response->status() === 404) {
            return null;
        }

        $response->throw();

        return $this->decode($response->body());
    }

    public function set(string $path, mixed $value): void
    {
        $this->put('PUT', $path, $value);
    }

    public function update(string $path, array $values): void
    {
        $this->put('PATCH', $path, $values);
    }

    public function push(string $path, mixed $value): string
    {
        $response = $this->request()->withBody($this->encode($value))->send('POST', $this->url($path));
        $response->throw();

        $key = $this->decode($response->body())['name'] ?? null;

        if (! is_string($key) || $key === '') {
            throw FirebaseException::forPath($path, 'the server did not return a generated key');
        }

        return $key;
    }

    public function remove(string $path): void
    {
        $response = $this->request()->send('DELETE', $this->url($path));
        $response->throw();
    }

    public function exists(string $path): bool
    {
        return $this->get($path) !== null;
    }

    public function increment(string $path, int $delta = 1): int
    {
        return (int) $this->transaction($path, fn ($current) => ((int) ($current ?? 0)) + $delta);
    }

    public function transaction(string $path, callable $callback): mixed
    {
        for ($attempt = 0; $attempt < $this->maxTransactionRetries; $attempt++) {
            $snapshot = $this->request()
                ->withHeaders(['X-Firebase-ETag' => 'true'])
                ->get($this->url($path));

            if ($snapshot->status() === 404) {
                $current = null;
                $etag = null;
            } else {
                $snapshot->throw();
                $current = $this->decode($snapshot->body());
                $etag = $snapshot->header('ETag');
            }

            $next = $callback($current);

            $write = $this->request()
                ->withHeaders(array_filter(['if-match' => $etag]))
                ->withBody($this->encode($next))
                ->send('PUT', $this->url($path));

            if ($write->status() === 412) {
                usleep(50000 * ($attempt + 1));

                continue;
            }

            $write->throw();

            return $next;
        }

        throw FirebaseException::forPath($path, 'the transaction could not be committed after '.$this->maxTransactionRetries.' attempts');
    }

    private function put(string $method, string $path, mixed $value): void
    {
        $response = $this->request()->withBody($this->encode($value))->send($method, $this->url($path));
        $response->throw();
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->tokens->token())
            ->acceptJson()
            ->withHeaders(['Content-Type' => 'application/json']);
    }

    private function url(string $path): string
    {
        return rtrim($this->databaseUrl, '/').'/'.trim($path, '/').'.json';
    }

    private function encode(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function decode(string $body): mixed
    {
        $trimmed = trim($body);

        if ($trimmed === '' || $trimmed === 'null') {
            return null;
        }

        return json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
    }
}
