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
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CorectorLunaCondica;

class CorecteazaLunaCondica implements Action
{
    public function __construct(
        private Acl $acl,
        private CorectorLunaCondica $corector,
    ) {}

    public function process(Request $cerere): Response
    {
        if (!$this->acl->checkScope('CondicaPrezenta')) {
            throw new Forbidden();
        }

        $corp = $cerere->getParsedBody();
        $luna = $this->sir($corp->luna ?? null);
        $idAngajat = $this->sir($corp->idAngajat ?? null);
        $data = $this->sir($corp->data ?? null);
        $cod = $this->sir($corp->cod ?? null);
        $motiv = $this->sir($corp->motiv ?? null);

        if (!$luna || !$idAngajat || !$data || !$cod || !$motiv) {
            throw BadRequest::createWithBody(
                'corectieCondicaInvalida',
                Body::create()->withMessageTranslation('corectieCondicaInvalida', 'CondicaPrezenta')
            );
        }

        return ResponseComposer::json(
            $this->corector->corecteaza($luna, $idAngajat, $data, $cod, $motiv)
        );
    }

    private function sir(mixed $valoare): ?string
    {
        return is_string($valoare) && trim($valoare) !== '' ? $valoare : null;
    }
}
