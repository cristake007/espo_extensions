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
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ResetatorDateTestCondica;

class ReseteazaDateTestCondica implements Action
{
    public function __construct(
        private Acl $acl,
        private ResetatorDateTestCondica $resetator,
    ) {}

    public function process(Request $cerere): Response
    {
        if (!$this->acl->checkScope('GestionareAngajati')) {
            throw new Forbidden();
        }

        $corp = $cerere->getParsedBody();

        if (($corp->confirmare ?? null) !== ResetatorDateTestCondica::CONFIRMARE) {
            throw BadRequest::createWithBody(
                'confirmareResetCondicaInvalida',
                Body::create()->withMessageTranslation(
                    'confirmareResetCondicaInvalida',
                    'GestionareAngajati'
                )
            );
        }

        return ResponseComposer::json($this->resetator->reseteaza());
    }
}
