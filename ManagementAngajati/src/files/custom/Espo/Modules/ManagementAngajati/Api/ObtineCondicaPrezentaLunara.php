<?php

namespace Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CondicaPrezentaLunara;

class ObtineCondicaPrezentaLunara implements Action
{
    public function __construct(
        private Acl $acl,
        private CondicaPrezentaLunara $condicaPrezentaLunara,
    ) {}

    public function process(Request $cerere): Response
    {
        if (
            !$this->acl->checkScope('CondicaPrezenta') ||
            !$this->acl->checkScope('Prezenta', Table::ACTION_READ)
        ) {
            throw new Forbidden();
        }

        return ResponseComposer::json(
            $this->condicaPrezentaLunara->obtine((string) $cerere->getQueryParam('luna'))
        );
    }
}
