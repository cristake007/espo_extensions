<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityMetadataTreeService;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;
use InvalidArgumentException;

final readonly class GetEntityMetadataTree implements Action
{
    public function __construct(private EntityMetadataTreeService $service)
    {}

    public function process(Request $request): Response
    {
        $entityType = $request->getRouteParam('entityType');
        $rawPath = $request->getQueryParam('path') ?? '';

        if ($entityType === null || strlen($rawPath) > 400) {
            throw new BadRequest('Entity metadata request is invalid.');
        }

        $path = $rawPath === '' ? [] : explode('.', $rawPath);

        try {
            return ResponseComposer::json($this->service->get($entityType, $path)->toArray());
        } catch (InvalidArgumentException) {
            throw new BadRequest('Entity metadata request is invalid.');
        } catch (PermissionDenied) {
            throw new Forbidden('Document Builder entity metadata access denied.');
        }
    }
}
