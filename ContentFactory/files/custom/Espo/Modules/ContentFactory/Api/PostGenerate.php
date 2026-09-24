<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Generation\GenerationException;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Generation\GenerationService;
use stdClass;

final readonly class PostGenerate implements Action
{
    public function __construct(private GenerationService $service)
    {}

    public function process(Request $request): Response
    {
        $body = $request->getParsedBody();

        if (!$body instanceof stdClass) {
            return $this->error(new GenerationException(
                'INVALID_REQUEST',
                400,
                'Datele trimise către generator nu sunt valide.',
            ));
        }

        try {
            return ResponseComposer::json($this->service->generate($body))
                ->setHeader('Cache-Control', 'no-store, private');
        } catch (GenerationException $exception) {
            return $this->error($exception);
        }
    }

    private function error(GenerationException $exception): Response
    {
        return ResponseComposer::json([
            'success' => false,
            'error' => [
                'code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            ],
        ])
            ->setStatus($exception->getHttpStatus())
            ->setHeader('Cache-Control', 'no-store, private');
    }
}
