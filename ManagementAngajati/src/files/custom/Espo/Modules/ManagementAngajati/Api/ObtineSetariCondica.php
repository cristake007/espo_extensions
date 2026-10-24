<?php

namespace Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\SetariCondica;

class ObtineSetariCondica implements Action
{
    private const string SCOPE_GESTIONARE = 'GestionareAngajati';

    public function __construct(
        private Acl $acl,
        private SetariCondica $setariCondica,
    ) {}

    public function process(Request $cerere): Response
    {
        if (!$this->acl->checkScope(self::SCOPE_GESTIONARE)) {
            throw new Forbidden();
        }

        return ResponseComposer::json($this->setariCondica->obtine());
    }
}
