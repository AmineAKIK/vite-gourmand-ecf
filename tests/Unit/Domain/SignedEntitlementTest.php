<?php

namespace Tests\Unit\Domain;

use App\Domain\SignedEntitlement;
use PHPUnit\Framework\TestCase;

final class SignedEntitlementTest extends TestCase
{
    private string $privateKey;
    private string $publicKey;

    protected function setUp(): void
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($resource);

        self::assertTrue(openssl_pkey_export($resource, $privateKey));
        $details = openssl_pkey_get_details($resource);
        self::assertIsArray($details);

        $this->privateKey = $privateKey;
        $this->publicKey = (string) $details['key'];
    }

    public function testValidSignedEntitlementIsAccepted(): void
    {
        $document = $this->signedDocument('traiteur.example', 'pro', '2030-01-01T00:00:00+00:00');

        $verified = SignedEntitlement::verify($document, $this->publicKey, 'https://traiteur.example/', 1_800_000_000);

        self::assertSame('pro', $verified['plan']);
        self::assertSame('traiteur.example', $verified['domain']);
    }

    public function testTamperedPlanIsRejected(): void
    {
        $document = json_decode($this->signedDocument('traiteur.example', 'pro', null), true);
        self::assertIsArray($document);
        $document['plan'] = 'premium';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Signature de licence non valide');
        SignedEntitlement::verify((string) json_encode($document), $this->publicKey, 'traiteur.example');
    }

    public function testWrongDomainIsRejected(): void
    {
        $document = $this->signedDocument('traiteur.example', 'pro', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('domaine');
        SignedEntitlement::verify($document, $this->publicKey, 'autre.example');
    }

    public function testExpiredEntitlementIsRejected(): void
    {
        $document = $this->signedDocument('traiteur.example', 'starter', '2026-01-01T00:00:00+00:00');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expirée');
        SignedEntitlement::verify($document, $this->publicKey, 'traiteur.example', 1_800_000_000);
    }

    public function testUnknownPlanIsRejectedEvenWhenSignatureIsValid(): void
    {
        $document = $this->signedDocument('traiteur.example', 'enterprise', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Plan de licence invalide');
        SignedEntitlement::verify($document, $this->publicKey, 'traiteur.example');
    }

    private function signedDocument(string $domain, string $plan, ?string $expiresAt): string
    {
        $payload = [
            'version' => 1,
            'license_id' => 'lic_test_001',
            'domain' => $domain,
            'plan' => $plan,
            'issued_at' => '2026-08-24T00:00:00+00:00',
            'expires_at' => $expiresAt,
        ];
        $signature = '';
        self::assertTrue(openssl_sign(
            SignedEntitlement::canonicalPayload($payload),
            $signature,
            $this->privateKey,
            OPENSSL_ALGO_SHA256,
        ));

        $payload['signature'] = base64_encode($signature);
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        self::assertIsString($json);
        return $json;
    }
}
