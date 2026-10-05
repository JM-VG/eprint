<?php

namespace App\Firebase;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Mints short-lived OAuth2 access tokens from a Google service account.
 *
 * The Firebase Admin SDK normally does this, but its dependency chain
 * (firebase/php-jwt) is currently blocked by a security advisory. The flow is
 * simple enough to implement directly:
 *
 *   1. Build a signed RS256 JWT assertion for the service account.
 *   2. Exchange it at the token endpoint for an access token.
 *
 * Only OpenSSL and the already-installed HTTP client are used.
 */
class ServiceAccountTokenProvider
{
    public const SCOPE = 'https://www.googleapis.com/auth/firebase.database https://www.googleapis.com/auth/userinfo.email';

    public const DEFAULT_TOKEN_URI = 'https://oauth2.googleapis.com/token';

    public function __construct(
        private array|string $credentials,
        private bool $useCache = true,
    ) {}

    /**
     * Return a valid access token, caching it with the file store until it is
     * close to expiry.
     */
    public function token(): string
    {
        if (! $this->useCache) {
            return $this->fetch();
        }

        return (string) Cache::store('file')->remember(
            'firebase.access_token.'.md5($this->clientEmail()),
            3300,
            fn () => $this->fetch(),
        );
    }

    /**
     * Build the JWT assertion and exchange it for an access token.
     */
    public function fetch(): string
    {
        $credentials = $this->credentialsArray();
        $tokenUri = $credentials['token_uri'] ?? self::DEFAULT_TOKEN_URI;

        $response = Http::asForm()->post($tokenUri, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $this->assertion($credentials),
        ]);

        $response->throw();

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Firebase token endpoint did not return an access token.');
        }

        return $token;
    }

    /**
     * Build a signed RS256 JWT assertion for the given credentials.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function assertion(array $credentials): string
    {
        $now = time();

        return $this->sign([
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => $credentials['token_uri'] ?? self::DEFAULT_TOKEN_URI,
            'iat' => $now,
            'exp' => $now + 3600,
        ], $credentials['private_key']);
    }

    /**
     * Sign the given claims as an RS256 JWT using the service account key.
     *
     * @param  array<string, mixed>  $claims
     */
    public function sign(array $claims, string $privateKey): string
    {
        $segments = [
            $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR)),
        ];

        $signingInput = implode('.', $segments);
        $signature = '';

        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign the Firebase service account assertion.');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public function clientEmail(): string
    {
        return (string) ($this->credentialsArray()['client_email'] ?? 'unknown');
    }

    /**
     * @return array<string, mixed>
     */
    public function credentialsArray(): array
    {
        if (is_array($this->credentials)) {
            return $this->assertCredentialShape($this->credentials);
        }

        $candidate = trim($this->credentials);

        if ($candidate !== '' && str_starts_with($candidate, '{')) {
            return $this->assertCredentialShape($this->decode($candidate));
        }

        if ($candidate !== '' && is_file($candidate)) {
            return $this->assertCredentialShape($this->decode((string) file_get_contents($candidate)));
        }

        throw new RuntimeException('Firebase service account credentials were not found. Set FIREBASE_CREDENTIALS to the JSON file path or FIREBASE_CREDENTIALS_JSON.');
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Firebase service account credentials are not valid JSON.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    private function assertCredentialShape(array $credentials): array
    {
        foreach (['client_email', 'private_key'] as $key) {
            if (! isset($credentials[$key]) || ! is_string($credentials[$key]) || $credentials[$key] === '') {
                throw new RuntimeException("Firebase service account credentials are missing [{$key}].");
            }
        }

        return $credentials;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
