<?php

namespace Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\TrimitatorReminderePrezenta;

class TrimiteReminderePrezenta implements Action
{
    public function __construct(
        private Acl $acl,
        private TrimitatorReminderePrezenta $trimitator,
    ) {}

    public function process(Request $cerere): Response
    {
        if (
            !$this->acl->checkScope('CondicaPrezenta') ||
            !$this->acl->checkScope('Prezenta', Table::ACTION_READ)
        ) {
            throw new Forbidden();
        }

        $corp = $cerere->getParsedBody();
        $luna = is_string($corp->luna ?? null) ? $corp->luna : '';

        if ($luna === '') {
            throw BadRequest::createWithBody(
                'lunaPrezentaInvalida',
                Body::create()->withMessageTranslation('lunaPrezentaInvalida', 'Prezenta')
            );
        }

        return ResponseComposer::json($this->trimitator->trimite($luna));
    }
}
