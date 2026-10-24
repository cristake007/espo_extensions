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
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\MarcatorPrezentaCondica;

class MarcheazaPrezentaCondica implements Action
{
    public function __construct(
        private Acl $acl,
        private MarcatorPrezentaCondica $marcator,
    ) {}

    public function process(Request $cerere): Response
    {
        if (!$this->acl->checkScope('CondicaPrezenta')) {
            throw new Forbidden();
        }

        $corp = $cerere->getParsedBody();
        $idAngajat = $this->sir($corp->idAngajat ?? null);
        $data = $this->sir($corp->data ?? null);
        $cod = $this->sir($corp->cod ?? null);

        if (!$idAngajat || !$data || !$cod) {
            throw BadRequest::createWithBody(
                'marcarePrezentaInvalida',
                Body::create()->withMessageTranslation(
                    'marcarePrezentaInvalida',
                    'CondicaPrezenta'
                )
            );
        }

        return ResponseComposer::json($this->marcator->marcheaza($idAngajat, $data, $cod));
    }

    private function sir(mixed $valoare): ?string
    {
        return is_string($valoare) && trim($valoare) !== '' ? $valoare : null;
    }
}
