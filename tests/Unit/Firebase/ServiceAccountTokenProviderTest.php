<?php

use App\Firebase\ServiceAccountTokenProvider;

/**
 * @return array{0: string, 1: string} [private key, public key]
 */
function serviceAccountKeysForTest(): array
{
    $options = [
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];

    // Windows PHP builds ship openssl.cnf outside the default search path.
    $config = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf';

    if (is_file($config)) {
        $options['config'] = $config;
    }

    $resource = openssl_pkey_new($options);

    $privateKey = null;
    openssl_pkey_export($resource, $privateKey, null, $options);

    return [(string) $privateKey, openssl_pkey_get_details($resource)['key']];
}

function base64UrlDecodeForTest(string $value): string
{
    $padded = str_pad($value, strlen($value) + (4 - strlen($value) % 4) % 4, '=');

    return (string) base64_decode(strtr($padded, '-_', '+/'));
}

it('builds a verifiable RS256 assertion', function () {
    [$privateKey, $publicKey] = serviceAccountKeysForTest();

    $provider = new ServiceAccountTokenProvider([
        'client_email' => 'svc@example.iam.gserviceaccount.com',
        'private_key' => $privateKey,
    ], useCache: false);

    $jwt = $provider->sign([
        'iss' => 'svc@example.iam.gserviceaccount.com',
        'scope' => 'scope',
        'aud' => 'aud',
        'iat' => time(),
        'exp' => time() + 3600,
    ], $privateKey);

    [$header, $payload, $signature] = explode('.', $jwt);

    expect(json_decode(base64UrlDecodeForTest($header), true)['alg'])->toBe('RS256')
        ->and(json_decode(base64UrlDecodeForTest($payload), true)['iss'])->toBe('svc@example.iam.gserviceaccount.com');

    $verified = openssl_verify(
        $header.'.'.$payload,
        base64UrlDecodeForTest($signature),
        $publicKey,
        OPENSSL_ALGO_SHA256,
    );

    expect($verified)->toBe(1);
});

it('builds an assertion with the firebase database scope', function () {
    [$privateKey] = serviceAccountKeysForTest();

    $provider = new ServiceAccountTokenProvider([
        'client_email' => 'svc@example.iam.gserviceaccount.com',
        'private_key' => $privateKey,
    ], useCache: false);

    $assertion = $provider->assertion([
        'client_email' => 'svc@example.iam.gserviceaccount.com',
        'private_key' => $privateKey,
    ]);

    $claims = json_decode(base64UrlDecodeForTest(explode('.', $assertion)[1]), true);

    expect($claims['scope'])->toContain('firebase.database')
        ->and($claims['aud'])->toBe(ServiceAccountTokenProvider::DEFAULT_TOKEN_URI);
});

it('accepts credentials as a JSON string', function () {
    [$privateKey] = serviceAccountKeysForTest();

    $provider = new ServiceAccountTokenProvider(json_encode([
        'client_email' => 'svc@example.iam.gserviceaccount.com',
        'private_key' => $privateKey,
    ]), useCache: false);

    expect($provider->clientEmail())->toBe('svc@example.iam.gserviceaccount.com');
});

it('rejects credentials that are missing required keys', function () {
    $provider = new ServiceAccountTokenProvider(['client_email' => 'svc@example.com'], useCache: false);

    expect(fn () => $provider->credentialsArray())->toThrow(RuntimeException::class);
});

it('rejects a credentials path that does not exist', function () {
    $provider = new ServiceAccountTokenProvider('/does/not/exist.json', useCache: false);

    expect(fn () => $provider->credentialsArray())->toThrow(RuntimeException::class);
});
