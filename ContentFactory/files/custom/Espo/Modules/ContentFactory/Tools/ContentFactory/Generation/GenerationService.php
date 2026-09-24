<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Tools\ContentFactory\Generation;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Utils\Log;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Config\SettingsProvider;
use Espo\Modules\ContentFactory\Tools\ContentFactory\N8n\N8nClient;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Persistence\ContentHistoryWriter;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Validation\RequestValidator;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Validation\ResponseValidator;
use stdClass;

final readonly class GenerationService
{
    public function __construct(
        private Acl $acl,
        private RequestValidator $requestValidator,
        private SettingsProvider $settingsProvider,
        private N8nClient $n8nClient,
        private ResponseValidator $responseValidator,
        private ContentHistoryWriter $historyWriter,
        private Log $log,
    ) {}

    /** @return array{success: true, record: array{id: string, name: string, generatedAt: string}, generation: array<string, mixed>} */
    public function generate(stdClass $body): array
    {
        $this->requireAccess();
        $request = $this->requestValidator->validate($body);
        $settings = $this->settingsProvider->get();

        if (!$settings->enabled || $settings->webhookUrl === '') {
            throw new GenerationException(
                'CONFIGURATION_ERROR',
                503,
                'Serviciul de generare nu este configurat.',
            );
        }

        $requestId = bin2hex(random_bytes(16));
        $rawResponse = $this->n8nClient->generate(
            $request->toN8nPayload($requestId),
            $requestId,
            $settings,
        );
        try {
            $generation = $this->responseValidator->validate($rawResponse, $request, $requestId);
        } catch (GenerationException $exception) {
            $content = $rawResponse['content'] ?? null;
            $assets = $rawResponse['assets'] ?? null;

            $diagnostic = $this->responseDiagnostic($rawResponse, $requestId);
            $diagnosticJson = json_encode(
                $diagnostic,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );

            $this->log->warning(sprintf(
                'ContentFactory n8n response rejected. diagnostic=%s',
                is_string($diagnosticJson) ? $diagnosticJson : '{"encodingError":true}',
            ), [
                'requestId' => $requestId,
                'errorCode' => $exception->getErrorCode(),
                'exceptionClass' => $exception::class,
                'exceptionMessage' => $exception->getMessage(),
                'topLevelKeys' => array_keys($rawResponse),
                'contentKeys' => is_array($content) && !array_is_list($content)
                    ? array_keys($content)
                    : [],
                'assetsImageCount' => is_array($assets) && is_array($assets['images'] ?? null)
                    ? count($assets['images'])
                    : null,
            ]);

            throw $exception;
        }
        $record = $this->historyWriter->write($request, $generation);

        return [
            'success' => true,
            'record' => $record,
            'generation' => $generation,
        ];
    }

    /**
     * Produces a bounded structural fingerprint without logging generated copy,
     * image bytes, credentials, or request headers.
     *
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function responseDiagnostic(array $response, string $requestId): array
    {
        $content = $this->mapOrEmpty($response['content'] ?? null);
        $post = $this->mapOrEmpty($content['post'] ?? null);
        $image = $this->mapOrEmpty($post['image'] ?? null);
        $assetLocations = [
            'root' => $response['assets'] ?? null,
            'content' => $content['assets'] ?? null,
            'post' => $post['assets'] ?? null,
            'image' => $image['assets'] ?? null,
        ];
        $assetsLocation = null;
        $assets = [];

        foreach ($assetLocations as $location => $candidate) {
            if (is_array($candidate) && !array_is_list($candidate)) {
                $assetsLocation = $location;
                $assets = $candidate;
                break;
            }
        }

        $images = is_array($assets['images'] ?? null) && array_is_list($assets['images'])
            ? $assets['images']
            : [];
        $firstImage = $this->mapOrEmpty($images[0] ?? null);
        $dataField = null;
        $dataLength = null;

        foreach (['imageDataUrl', 'dataUrl', 'dataBase64', 'base64', 'data', 'imageUrl', 'url'] as $field) {
            if (is_string($firstImage[$field] ?? null)) {
                $dataField = $field;
                $dataLength = strlen($firstImage[$field]);
                break;
            }
        }

        return [
            'topLevelKeys' => array_keys($response),
            'schemaVersion' => $response['schemaVersion'] ?? null,
            'requestIdMatches' => ($response['requestId'] ?? null) === $requestId,
            'success' => $response['success'] ?? null,
            'platform' => $response['platform'] ?? null,
            'format' => $response['format'] ?? null,
            'locale' => $response['locale'] ?? null,
            'contentKeys' => array_keys($content),
            'postKeys' => array_keys($post),
            'imageKeys' => array_keys($image),
            'safeArea' => $this->mapOrEmpty($image['safeArea'] ?? null),
            'assetsLocation' => $assetsLocation,
            'assetsKeys' => array_keys($assets),
            'assetsImageCount' => count($images),
            'firstImageKeys' => array_keys($firstImage),
            'firstImageMimeType' => $firstImage['mimeType'] ?? $firstImage['contentType'] ?? null,
            'firstImageDataField' => $dataField,
            'firstImageDataLength' => $dataLength,
        ];
    }

    /** @return array<string, mixed> */
    private function mapOrEmpty(mixed $value): array
    {
        return is_array($value) && !array_is_list($value) ? $value : [];
    }

    private function requireAccess(): void
    {
        if (
            !$this->acl->checkScope('ContentFactoryGenerator') ||
            !$this->acl->checkScope('ContentFactoryContent', Table::ACTION_CREATE)
        ) {
            throw new GenerationException(
                'FORBIDDEN',
                403,
                'Nu aveți permisiunea de a genera conținut.',
            );
        }
    }
}
