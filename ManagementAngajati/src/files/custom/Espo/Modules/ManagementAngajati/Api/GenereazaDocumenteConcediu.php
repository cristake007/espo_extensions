<?php

namespace Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\DocumenteConcediu;

class GenereazaDocumenteConcediu implements Action
{
    public function __construct(private DocumenteConcediu $documente) {}

    public function process(Request $cerere): Response
    {
        $id = $cerere->getRouteParam('id');
        if (!is_string($id)) {
            throw new BadRequest();
        }
        return ResponseComposer::json($this->documente->genereaza($id));
    }
}
