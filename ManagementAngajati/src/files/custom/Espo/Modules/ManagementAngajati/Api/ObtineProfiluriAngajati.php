<?php

namespace Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\ProfiluriAngajati;

class ObtineProfiluriAngajati implements Action
{
    public function __construct(
        private Acl $acl,
        private ProfiluriAngajati $profiluriAngajati,
    ) {}

    public function process(Request $cerere): Response
    {
        if (!$this->acl->checkScope('GestionareAngajati')) {
            throw new Forbidden();
        }

        return ResponseComposer::json($this->profiluriAngajati->obtine());
    }
}
