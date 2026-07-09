<?php

namespace Espo\Modules\Planificari\Tools\Planificari\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\Planificari\Tools\Planificari\WordConversionService;

class PostPreviewWordConversion implements Action
{
    public function __construct(private WordConversionService $service) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');

        if (!is_string($id) || $id === '') {
            throw new BadRequest('Inregistrarea este obligatorie.');
        }

        return ResponseComposer::json($this->service->preview($id));
    }
}
