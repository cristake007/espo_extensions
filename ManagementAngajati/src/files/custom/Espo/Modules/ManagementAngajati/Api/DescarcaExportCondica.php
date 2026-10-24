<?php

namespace Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ExportatorCondica;

class DescarcaExportCondica implements Action
{
    public function __construct(
        private Acl $acl,
        private ExportatorCondica $exportator,
    ) {}

    public function process(Request $cerere): Response
    {
        if (
            !$this->acl->checkScope('CondicaPrezenta') ||
            !$this->acl->checkScope('Prezenta', Table::ACTION_READ)
        ) {
            throw new Forbidden();
        }

        $fisier = $this->exportator->exporta(
            (string) $cerere->getQueryParam('luna'),
            (string) $cerere->getRouteParam('format'),
            $this->obtineCulori($cerere)
        );
        $nume = str_replace('"', '', $fisier['nume']);

        return ResponseComposer::empty()
            ->setHeader('Content-Disposition', 'attachment; filename="' . $nume . '"')
            ->setHeader('Content-Type', $fisier['tip'])
            ->setHeader('Content-Length', (string) strlen($fisier['continut']))
            ->writeBody($fisier['continut']);
    }

    /** @return array<string, string> */
    private function obtineCulori(Request $cerere): array
    {
        $rezultat = [];

        foreach (
            [
                'principala',
                'accent',
                'text',
                'contur',
                'prezent',
                'delegatie',
                'medical',
                'cic',
                'concediu',
            ]
            as $nume
        ) {
            $valoare = $cerere->getQueryParam('culoare' . ucfirst($nume));

            if (is_string($valoare) && preg_match('/^#[0-9a-fA-F]{6}$/', $valoare)) {
                $rezultat[$nume] = $valoare;
            }
        }

        return $rezultat;
    }
}
