<?php

namespace Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\XlsxExportService;

class PostExportXlsx implements Action
{
    public function __construct(private XlsxExportService $service) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');

        if (!is_string($id) || $id === '') {
            throw new BadRequest('Inregistrarea GeneratorPerioadeCursuri este obligatorie.');
        }

        return ResponseComposer::json([
            'downloadUrl' => $this->service->getExportUrl($id),
        ]);
    }
}
