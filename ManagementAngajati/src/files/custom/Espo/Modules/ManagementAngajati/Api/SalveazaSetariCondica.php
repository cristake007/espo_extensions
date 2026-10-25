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
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\SalvatorSetariCondica;

class SalveazaSetariCondica implements Action
{
    private const string SCOPE_GESTIONARE = 'GestionareAngajati';

    public function __construct(
        private Acl $acl,
        private SalvatorSetariCondica $salvatorSetari,
    ) {}

    public function process(Request $cerere): Response
    {
        if (!$this->acl->checkScope(self::SCOPE_GESTIONARE)) {
            throw new Forbidden();
        }

        $corp = $cerere->getParsedBody();
        $mod = is_string($corp->modAplicareRemote ?? null) ? $corp->modAplicareRemote : '';
        $numarLuni = is_int($corp->numarLuniAnterioareEditabile ?? null) ?
            $corp->numarLuniAnterioareEditabile :
            -1;
        $permiteExportIncomplet = $corp->permiteExportCondicaIncompleta ?? null;

        if (!is_bool($permiteExportIncomplet)) {
            $this->aruncaSetariInvalide();
        }

        $angajati = $this->extrageAngajati($corp->angajati ?? null);

        return ResponseComposer::json(
            $this->salvatorSetari->salveaza(
                $mod,
                $numarLuni,
                $permiteExportIncomplet,
                $angajati
            )
        );
    }

    /** @return list<object> */
    private function extrageAngajati(mixed $valoare): array
    {
        if (!is_array($valoare) || !array_is_list($valoare)) {
            $this->aruncaSetariInvalide();
        }

        $rezultat = [];

        foreach ($valoare as $dateAngajat) {
            if (!is_object($dateAngajat)) {
                $this->aruncaSetariInvalide();
            }

            $rezultat[] = $dateAngajat;
        }

        return $rezultat;
    }

    /** @return never */
    private function aruncaSetariInvalide(): void
    {
        throw BadRequest::createWithBody(
            'setariCondicaInvalide',
            Body::create()->withMessageTranslation(
                'setariCondicaInvalide',
                'GestionareAngajati'
            )
        );
    }
}
