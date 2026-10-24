<?php

namespace Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\SetariConcedii;

class SalveazaSetariConcedii implements Action
{
    public function __construct(private Acl $acl, private SetariConcedii $setari) {}
    public function process(Request $cerere): Response
    {
        if (!$this->acl->checkScope('GestionareAngajati')) {
            throw new Forbidden();
        }

        $corp = (array) $cerere->getParsedBody();
        $ids = $corp['aprobatoriIds'] ?? null;

        if (!is_array($ids)) {
            throw new BadRequest();
        }

        $idsValide = [];

        foreach ($ids as $id) {
            if (!is_string($id)) {
                throw new BadRequest();
            }

            $idsValide[] = $id;
        }

        $this->setari->salveaza(
            ($corp['necesitaAmbiiManageri'] ?? false) === true,
            ($corp['acordareAnualaAutomata'] ?? false) === true,
            $idsValide
        );
        return ResponseComposer::json(['success' => true]);
    }
}
