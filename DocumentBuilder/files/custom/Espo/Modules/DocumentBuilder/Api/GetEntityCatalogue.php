<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueItem;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityCatalogueService;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;

final readonly class GetEntityCatalogue implements Action
{
    public function __construct(private EntityCatalogueService $service)
    {}

    public function process(Request $request): Response
    {
        try {
            return ResponseComposer::json([
                'list' => array_map(
                    static fn (EntityCatalogueItem $item): array => $item->toArray(),
                    $this->service->get(),
                ),
            ]);
        } catch (PermissionDenied) {
            throw new Forbidden('Document Builder entity catalogue access denied.');
        }
    }
}
