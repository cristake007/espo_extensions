<?php

namespace Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\CorectorProgramAcordareConcediu;

class CorecteazaProgramAcordareConcediu implements Action
{
    public function __construct(
        private Acl $acl,
        private CorectorProgramAcordareConcediu $corector,
    ) {}

    public function process(Request $cerere): Response
    {
        if (!$this->acl->checkScope('GestionareAngajati')) {
            throw new Forbidden();
        }

        $idAngajat = $cerere->getRouteParam('idAngajat');
        $corp = $cerere->getParsedBody();
        $dataNoua = $corp->dataNoua ?? null;
        $motiv = $corp->motiv ?? null;

        if (!is_string($idAngajat) || !is_string($dataNoua) || !is_string($motiv)) {
            throw new BadRequest();
        }

        return ResponseComposer::json(
            $this->corector->corecteaza($idAngajat, $dataNoua, $motiv)
        );
    }
}
