<?php

namespace Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\GenerationService;

class PostGenerate implements Action
{
    public function __construct(private GenerationService $generationService) {}

    public function process(Request $request): Response
    {
        $data = $request->getParsedBody() ?? (object) [];
        $id = $data->id ?? null;

        if (!is_string($id) || trim($id) === '') {
            throw new BadRequest('Inregistrarea GeneratorPerioadeCursuri este obligatorie.');
        }

        return ResponseComposer::json($this->generationService->generate($id, $data));
    }
}
