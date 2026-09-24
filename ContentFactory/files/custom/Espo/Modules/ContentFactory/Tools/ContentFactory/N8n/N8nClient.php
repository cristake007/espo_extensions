<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Tools\ContentFactory\N8n;

use Espo\Modules\ContentFactory\Tools\ContentFactory\Config\Settings;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Generation\GenerationException;
use JsonException;

final readonly class N8nClient
{
    public function __construct(
        private N8nHttpTransport $transport,
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function generate(array $payload, string $requestId, Settings $settings): array
    {
        try {
            $requestBody = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException) {
            throw new GenerationException(
                'INVALID_REQUEST',
                400,
                'Datele trimise către generator nu sunt valide.',
            );
        }

        $response = $this->transport->post($settings, $requestBody, $requestId);

        try {
            $decoded = json_decode($response['body'], true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new GenerationException(
                'N8N_RESPONSE_INVALID',
                502,
                'Răspunsul serviciului de generare nu este valid.',
            );
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new GenerationException(
                'N8N_RESPONSE_INVALID',
                502,
                'Răspunsul serviciului de generare nu este valid.',
            );
        }

        return $decoded;
    }
}
