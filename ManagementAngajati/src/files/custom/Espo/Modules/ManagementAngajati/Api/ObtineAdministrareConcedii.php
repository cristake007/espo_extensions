<?php

namespace Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\CereriConcediu;

class ObtineAdministrareConcedii implements Action
{
    public function __construct(private CereriConcediu $cereri) {}
    public function process(Request $cerere): Response
    {
        $luna = $cerere->getQueryParam('luna');

        return ResponseComposer::json(
            $this->cereri->listaManageriala(is_string($luna) ? $luna : null)
        );
    }
}
