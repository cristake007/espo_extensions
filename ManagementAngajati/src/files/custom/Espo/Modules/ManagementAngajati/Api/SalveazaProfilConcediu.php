<?php

namespace Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\ProfiluriConcediu;

class SalveazaProfilConcediu implements Action
{
    public function __construct(private Acl $acl, private ProfiluriConcediu $profiluri) {}
    public function process(Request $cerere): Response
    {
        if (!$this->acl->checkScope('GestionareAngajati')) {
            throw new Forbidden();
        }

        $date = (array) $cerere->getParsedBody();
        $rezultat = ($date['initializare'] ?? false) === true ?
            $this->profiluri->initializeaza($date) :
            $this->profiluri->actualizeazaSetari($date);

        return ResponseComposer::json($rezultat);
    }
}
