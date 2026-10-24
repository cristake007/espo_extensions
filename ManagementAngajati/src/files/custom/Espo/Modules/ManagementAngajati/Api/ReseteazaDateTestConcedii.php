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
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\ResetatorDateTestConcedii;

class ReseteazaDateTestConcedii implements Action
{
    public function __construct(
        private Acl $acl,
        private ResetatorDateTestConcedii $resetator,
        private Language $limba,
    ) {}

    public function process(Request $cerere): Response
    {
        if (!$this->acl->checkScope('GestionareAngajati')) {
            throw new Forbidden();
        }

        if (($cerere->getParsedBody()->confirmare ?? null) !== ResetatorDateTestConcedii::CONFIRMARE) {
            throw BadRequest::createWithBody(
                'confirmareResetConcediiInvalida',
                Body::create()->withMessage(
                    $this->limba->translateLabel(
                        'confirmareResetConcediiInvalida',
                        'messages',
                        'GestionareAngajati'
                    )
                )
            );
        }

        return ResponseComposer::json($this->resetator->reseteaza());
    }
}
