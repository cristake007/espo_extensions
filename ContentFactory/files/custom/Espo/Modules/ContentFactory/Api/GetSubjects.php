<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Subject\SubjectLookupException;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Subject\SubjectLookupService;

final readonly class GetSubjects implements Action
{
    public function __construct(private SubjectLookupService $service)
    {}

    public function process(Request $request): Response
    {
        $type = $request->getQueryParam('type') ?? '';
        $query = $request->getQueryParam('q') ?? '';
        $limitValue = $request->getQueryParam('limit') ?? '20';
        $limit = filter_var($limitValue, FILTER_VALIDATE_INT);

        try {
            $result = $this->service->search(
                is_string($type) ? $type : '',
                is_string($query) ? $query : '',
                is_int($limit) ? $limit : 0,
            );

            return ResponseComposer::json($result)
                ->setHeader('Cache-Control', 'no-store, private');
        } catch (SubjectLookupException $exception) {
            return ResponseComposer::json([
                'error' => [
                    'code' => $exception->getErrorCode(),
                    'message' => $exception->getMessage(),
                ],
            ])
                ->setStatus($exception->getHttpStatus())
                ->setHeader('Cache-Control', 'no-store, private');
        }
    }
}
