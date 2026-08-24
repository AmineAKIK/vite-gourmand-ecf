<?php

namespace App\Integrations;

final class JsonHttpClient
{
    /** @var array<string,array{failures:int,opened_at:int}> */
    private static array $circuits = [];

    public static function get(string $url, array $headers = [], int $timeoutSeconds = 3): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        self::assertCircuitClosed($host);

        $attempts = 2;
        $lastError = null;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $result = self::request($url, $headers, $timeoutSeconds);
                self::recordSuccess($host);

                return $result;
            } catch (ExternalServiceUnavailableException $e) {
                $lastError = $e;
                if ($attempt < $attempts) {
                    usleep(100000 * $attempt);
                }
            }
        }

        self::recordFailure($host);
        throw $lastError ?? new ExternalServiceUnavailableException('Service externe indisponible.');
    }

    private static function request(string $url, array $headers, int $timeoutSeconds): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new ExternalServiceUnavailableException('Impossible d’initialiser le client HTTP.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(2, $timeoutSeconds),
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $errno !== 0) {
            throw new ExternalServiceUnavailableException('Échec réseau externe: ' . ($error !== '' ? $error : 'erreur inconnue'));
        }
        if ($status === 429 || $status >= 500) {
            throw new ExternalServiceUnavailableException('Service externe indisponible (HTTP ' . $status . ').');
        }
        if ($status < 200 || $status >= 300) {
            throw new \UnexpectedValueException('Réponse externe refusée (HTTP ' . $status . ').');
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ExternalServiceUnavailableException('Réponse JSON externe invalide.', 0, $e);
        }
        if (!is_array($decoded)) {
            throw new ExternalServiceUnavailableException('Réponse externe inattendue.');
        }

        return $decoded;
    }

    private static function assertCircuitClosed(string $host): void
    {
        if ($host === '' || !isset(self::$circuits[$host])) {
            return;
        }

        $state = self::$circuits[$host];
        if ($state['failures'] < 3) {
            return;
        }
        if ((time() - $state['opened_at']) >= 30) {
            unset(self::$circuits[$host]);
            return;
        }

        throw new ExternalServiceUnavailableException('Service externe temporairement isolé après plusieurs échecs.');
    }

    private static function recordSuccess(string $host): void
    {
        if ($host !== '') {
            unset(self::$circuits[$host]);
        }
    }

    private static function recordFailure(string $host): void
    {
        if ($host === '') {
            return;
        }
        $state = self::$circuits[$host] ?? ['failures' => 0, 'opened_at' => 0];
        $state['failures']++;
        if ($state['failures'] >= 3 && $state['opened_at'] === 0) {
            $state['opened_at'] = time();
        }
        self::$circuits[$host] = $state;
    }
}
