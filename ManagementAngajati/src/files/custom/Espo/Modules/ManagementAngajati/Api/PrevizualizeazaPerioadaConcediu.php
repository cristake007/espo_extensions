<?php

namespace Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\CereriConcediu;

/** Previzualizeaza numarul real de zile lucratoare al unei cereri. */
class PrevizualizeazaPerioadaConcediu implements Action
{
    public function __construct(private CereriConcediu $cereri) {}

    public function process(Request $cerere): Response
    {
        return ResponseComposer::json(
            $this->cereri->previzualizeazaPerioada((array) $cerere->getParsedBody())
        );
    }
}
