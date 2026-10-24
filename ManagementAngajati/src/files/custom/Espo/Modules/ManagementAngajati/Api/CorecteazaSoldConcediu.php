<?php

namespace Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Language;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\SolduriConcediu;

class CorecteazaSoldConcediu implements Action
{
    public function __construct(
        private Acl $acl,
        private SolduriConcediu $solduri,
        private Language $limba,
    ) {}

    public function process(Request $cerere): Response
    {
        if (!$this->acl->checkScope('GestionareAngajati')) {
            throw new Forbidden();
        }

        $idAngajat = $cerere->getRouteParam('idAngajat');
        $corp = $cerere->getParsedBody();
        $diferenta = $corp->diferenta ?? null;
        $motiv = $corp->motiv ?? null;

        if (!is_string($idAngajat) || $idAngajat === '' ||
            !is_int($diferenta) || !is_string($motiv)) {
            throw BadRequest::createWithBody(
                'corectieSoldInvalida',
                Body::create()->withMessage(
                    $this->limba->translateLabel(
                        'corectieSoldInvalida',
                        'messages',
                        'ProfilConcediu'
                    )
                )
            );
        }

        return ResponseComposer::json(
            $this->solduri->corecteaza($idAngajat, $diferenta, $motiv)
        );
    }
}
