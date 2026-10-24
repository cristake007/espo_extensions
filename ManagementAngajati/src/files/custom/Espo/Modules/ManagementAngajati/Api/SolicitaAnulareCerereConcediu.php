<?php

namespace Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\CereriConcediu;

class SolicitaAnulareCerereConcediu implements Action
{
    public function __construct(private CereriConcediu $cereri) {}

    public function process(Request $cerere): Response
    {
        $id = $cerere->getRouteParam('id');
        $corp = (array) $cerere->getParsedBody();

        if (!is_string($id)) {
            throw new BadRequest();
        }

        return ResponseComposer::json($this->cereri->solicitaAnulare(
            $id,
            is_string($corp['motiv'] ?? null) ? $corp['motiv'] : null,
        ));
    }
}
