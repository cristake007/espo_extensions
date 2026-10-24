<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;

/**
 * Invariante de business care nu sunt furnizate de infrastructura de inregistrari EspoCRM.
 */
class ReguliPrezenta
{
    private const string SCOPE_TRADUCERI = 'Prezenta';

    /** @throws BadRequest */
    public function valideazaDataPrezenta(string $dataPrezenta, string $dataCurenta): void
    {
        if ($dataPrezenta > $dataCurenta) {
            throw BadRequest::createWithBody(
                'dataPrezentaViitoare',
                Body::create()->withMessageTranslation('dataPrezentaViitoare', self::SCOPE_TRADUCERI)
            );
        }
    }

    /** @throws BadRequest */
    public function valideazaDataAngajare(?string $dataAngajare, string $dataPrezenta): void
    {
        if ($dataAngajare === null || $dataPrezenta < $dataAngajare) {
            throw BadRequest::createWithBody(
                'dataAnterioaraAngajarii',
                Body::create()->withMessageTranslation(
                    'dataAnterioaraAngajarii',
                    self::SCOPE_TRADUCERI
                )
            );
        }
    }

    /**
     * @throws BadRequest
     * @throws Conflict
     */
    public function valideazaEligibilitatea(
        bool $esteZiLucratoare,
        bool $esteSarbatoareLegala,
        bool $esteDuplicat,
    ): void {
        if ($esteSarbatoareLegala) {
            throw BadRequest::createWithBody(
                'ziSarbatoareLegala',
                Body::create()->withMessageTranslation('ziSarbatoareLegala', self::SCOPE_TRADUCERI)
            );
        }

        if (!$esteZiLucratoare) {
            throw BadRequest::createWithBody(
                'ziNelucratoare',
                Body::create()->withMessageTranslation('ziNelucratoare', self::SCOPE_TRADUCERI)
            );
        }

        if ($esteDuplicat) {
            throw Conflict::createWithBody(
                'prezentaDuplicata',
                Body::create()->withMessageTranslation('prezentaDuplicata', self::SCOPE_TRADUCERI)
            );
        }
    }
}
