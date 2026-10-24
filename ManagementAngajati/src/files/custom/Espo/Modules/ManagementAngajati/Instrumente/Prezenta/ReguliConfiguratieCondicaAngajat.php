<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;

class ReguliConfiguratieCondicaAngajat
{
    private const string SCOPE_TRADUCERI = 'ConfiguratieCondicaAngajat';

    /** @throws BadRequest */
    public function valideazaPerioadaCic(
        bool $cicActiv,
        ?string $dataInceput,
        ?string $dataSfarsit,
        ?string $dataAngajare,
    ): void {
        if (!$cicActiv) {
            return;
        }

        if (!$dataInceput || !$dataSfarsit) {
            throw BadRequest::createWithBody(
                'perioadaCicObligatorie',
                Body::create()->withMessageTranslation(
                    'perioadaCicObligatorie',
                    self::SCOPE_TRADUCERI
                )
            );
        }

        if (!$this->esteDataValida($dataInceput) ||
            !$this->esteDataValida($dataSfarsit) ||
            $dataSfarsit < $dataInceput
        ) {
            throw BadRequest::createWithBody(
                'perioadaCicInvalida',
                Body::create()->withMessageTranslation(
                    'perioadaCicInvalida',
                    self::SCOPE_TRADUCERI
                )
            );
        }

        if (!$dataAngajare || !$this->esteDataValida($dataAngajare)) {
            throw BadRequest::createWithBody(
                'dataAngajareLipsaPentruCic',
                Body::create()->withMessageTranslation(
                    'dataAngajareLipsaPentruCic',
                    self::SCOPE_TRADUCERI
                )
            );
        }

        if ($dataInceput < $dataAngajare) {
            throw BadRequest::createWithBody(
                'perioadaCicInainteDeAngajare',
                Body::create()->withMessageTranslation(
                    'perioadaCicInainteDeAngajare',
                    self::SCOPE_TRADUCERI
                )
            );
        }
    }

    /** @throws BadRequest */
    public function valideazaPerioadaRemote(
        bool $remoteActiv,
        string $modAplicare,
        ?string $dataInceput,
        ?string $dataSfarsit,
    ): void {
        if (!$remoteActiv || $modAplicare !== ConfiguratiiCondicaAngajati::MOD_REMOTE_INTERVAL) {
            return;
        }

        if (!$dataInceput || !$dataSfarsit) {
            throw BadRequest::createWithBody(
                'perioadaRemoteObligatorie',
                Body::create()->withMessageTranslation(
                    'perioadaRemoteObligatorie',
                    self::SCOPE_TRADUCERI
                )
            );
        }

        if (!$this->esteDataValida($dataInceput) ||
            !$this->esteDataValida($dataSfarsit) ||
            $dataSfarsit < $dataInceput
        ) {
            throw BadRequest::createWithBody(
                'perioadaRemoteInvalida',
                Body::create()->withMessageTranslation(
                    'perioadaRemoteInvalida',
                    self::SCOPE_TRADUCERI
                )
            );
        }
    }

    /**
     * @param list<string> $dateConflictuale
     * @throws Conflict
     */
    public function valideazaFaraPrezenteConflictuale(array $dateConflictuale): void
    {
        if ($dateConflictuale === []) {
            return;
        }

        throw Conflict::createWithBody(
            'conflictePerioadaCic',
            Body::create()->withMessageTranslation(
                'conflictePerioadaCic',
                self::SCOPE_TRADUCERI
            )
        );
    }

    private function esteDataValida(string $valoare): bool
    {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valoare);

        return $data !== false && $data->format('Y-m-d') === $valoare;
    }
}
