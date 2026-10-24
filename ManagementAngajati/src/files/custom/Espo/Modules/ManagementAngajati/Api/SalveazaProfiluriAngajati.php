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
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\ProfiluriAngajati;

class SalveazaProfiluriAngajati implements Action
{
    public function __construct(
        private Acl $acl,
        private ProfiluriAngajati $profiluriAngajati,
    ) {}

    public function process(Request $cerere): Response
    {
        if (!$this->acl->checkScope('GestionareAngajati')) {
            throw new Forbidden();
        }

        return ResponseComposer::json(
            $this->profiluriAngajati->salveaza(
                $this->extrageAngajati($cerere->getParsedBody()->angajati ?? null)
            )
        );
    }

    /** @return list<object> */
    private function extrageAngajati(mixed $valoare): array
    {
        if (!is_array($valoare) || !array_is_list($valoare)) {
            $this->aruncaDateInvalide();
        }

        $rezultat = [];

        foreach ($valoare as $dateAngajat) {
            if (!is_object($dateAngajat)) {
                $this->aruncaDateInvalide();
            }

            $rezultat[] = $dateAngajat;
        }

        return $rezultat;
    }

    /** @return never */
    private function aruncaDateInvalide(): void
    {
        throw BadRequest::createWithBody(
            'profiluriAngajatiInvalide',
            Body::create()->withMessageTranslation(
                'profiluriAngajatiInvalide',
                'GestionareAngajati'
            )
        );
    }
}
