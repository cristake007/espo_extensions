<?php

namespace Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\UtilizatoriCalendarConcedii;

class ObtineUtilizatoriCalendarConcedii implements Action
{
    public function __construct(
        private UtilizatoriCalendarConcedii $utilizatoriCalendar,
    ) {}

    public function process(Request $cerere): Response
    {
        return ResponseComposer::json([
            'iduriUtilizatori' => $this->utilizatoriCalendar->obtineIduri(),
        ]);
    }
}
