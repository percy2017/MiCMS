<?php

namespace Modules\ChatBot\Services\OpenWa;

use Illuminate\Http\Request;

/**
 * Verificador de firma HMAC SHA-256 para webhooks de OpenWA.
 *
 * OpenWA envía `X-OpenWA-Signature: sha256=<hexdigest>` donde:
 *   hexdigest = HMAC_SHA256(secret, rawRequestBody)
 *
 * Se debe usar el body CRUDO (no el array parseado por Laravel).
 *
 * Esta clase es genérica: cualquier provider que envíe `X-OpenWA-Signature` o
 * cualquier variante de "sha256=<hex>" puede usarla cambiando solo el header.
 */
class OpenWaHmacVerifier
{
    public const DEFAULT_HEADER = 'X-OpenWA-Signature';

    public function __construct(
        private readonly string $headerName = self::DEFAULT_HEADER,
        private readonly string $algorithm = 'sha256',
    ) {}

    /**
     * Verifica la firma del request contra el secret.
     *
     * @return bool true si la firma es válida o si no hay secret configurado (skip)
     */
    public function verify(Request $request, ?string $secret): bool
    {
        if (! $secret || $secret === '') {
            return true;
        }

        $signature = $request->header($this->headerName);
        if (! $signature) {
            return false;
        }

        $expectedPrefix = $this->algorithm.'=';
        if (! str_starts_with($signature, $expectedPrefix)) {
            $expected = $this->compute($request, $secret);

            return $this->constantTimeEquals($signature, $expected);
        }

        $provided = substr($signature, strlen($expectedPrefix));
        $expected = hash_hmac($this->algorithm, $request->getContent(), $secret);

        return $this->constantTimeEquals($provided, $expected);
    }

    /**
     * Calcula la firma esperada (útil para tests y debugging).
     */
    public function compute(Request $request, string $secret): string
    {
        return $this->algorithm.'='.hash_hmac($this->algorithm, $request->getContent(), $secret);
    }

    private function constantTimeEquals(string $a, string $b): bool
    {
        if (function_exists('hash_equals')) {
            return hash_equals($a, $b);
        }

        if (strlen($a) !== strlen($b)) {
            return false;
        }

        $result = 0;
        for ($i = 0; $i < strlen($a); $i++) {
            $result |= ord($a[$i]) ^ ord($b[$i]);
        }

        return $result === 0;
    }
}
