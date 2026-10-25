<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;

/** Valideaza si exporta condica potrivit regulii administrative configurate. */
class ExportatorCondica
{
    public function __construct(
        private CondicaPrezentaLunara $condica,
        private GeneratorExportCondica $generator,
    ) {}

    /**
     * @param array<string, string> $culori
     * @return array{nume: string, continut: string, tip: string}
     */
    public function exporta(string $luna, string $format, array $culori = []): array
    {
        if (!in_array($format, ['xlsx', 'pdf'], true)) {
            throw new BadRequest();
        }

        $date = $this->condica->obtine($luna);
        $permiteExportIncomplet =
            ($date['cicluLunar']['permiteExportCondicaIncompleta'] ?? false) === true;

        if (!$permiteExportIncomplet &&
            ($date['cicluLunar']['esteInchisa'] ?? false) !== true) {
            throw Conflict::createWithBody(
                'exportCondicaNecesitaLunaInchisa',
                Body::create()->withMessageTranslation(
                    'exportCondicaNecesitaLunaInchisa',
                    'CondicaPrezenta'
                )
            );
        }

        if (!$permiteExportIncomplet && ($date['statistici']['zileLipsa'] ?? 1) !== 0) {
            throw Conflict::createWithBody(
                'exportCondicaIncompleta',
                Body::create()->withMessageTranslation('exportCondicaIncompleta', 'CondicaPrezenta')
            );
        }

        return $format === 'xlsx' ?
            $this->generator->genereazaXlsx($date, $culori) :
            $this->generator->genereazaPdf($date, $culori);
    }
}
