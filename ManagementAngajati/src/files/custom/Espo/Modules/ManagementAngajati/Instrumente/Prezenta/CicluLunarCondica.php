<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Utils\DateTime;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** Aplica fereastra editabila si pastreaza starea persistenta a fiecarei luni. */
class CicluLunarCondica
{
    public const string TIP_ENTITATE = 'StareLunaCondica';
    public const string STARE_DESCHISA = 'deschisa';
    public const string STARE_INCHISA = 'inchisa';

    public function __construct(
        private EntityManager $managerEntitati,
        private ConfiguratiiCondicaAngajati $configuratii,
        private DateTime $dataOra,
        private User $utilizatorCurent,
    ) {}

    public function valideazaLuna(string $luna): void
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $luna)) {
            throw BadRequest::createWithBody(
                'lunaPrezentaInvalida',
                Body::create()->withMessageTranslation('lunaPrezentaInvalida', 'Prezenta')
            );
        }
    }

    public function obtine(string $luna, bool $pentruActualizare = false): ?Entity
    {
        $this->valideazaLuna($luna);
        $interogare = $this->managerEntitati
            ->getRDBRepository(self::TIP_ENTITATE)
            ->where(['luna' => $luna]);

        if ($pentruActualizare) {
            $interogare = $interogare->forUpdate();
        }

        return $interogare->findOne();
    }

    public function esteInchisa(string $luna): bool
    {
        return $this->obtine($luna)?->get('stare') === self::STARE_INCHISA;
    }

    /** Blocheaza operatiile CRUD obisnuite, pastrand administratorul nativ nerestrictionat. */
    public function valideazaModificareObisnuita(string $data): void
    {
        if ($this->utilizatorCurent->isAdmin()) {
            return;
        }

        $luna = substr($data, 0, 7);
        $this->valideazaLuna($luna);

        if ($this->esteInchisa($luna)) {
            throw Conflict::createWithBody(
                'lunaCondicaInchisa',
                Body::create()->withMessageTranslation('lunaCondicaInchisa', 'Prezenta')
            );
        }

        if (!$this->esteInIntervalEditabil($luna)) {
            throw BadRequest::createWithBody(
                'lunaPrezentaNeeditabila',
                Body::create()->withMessageTranslation('lunaPrezentaNeeditabila', 'Prezenta')
            );
        }
    }

    public function poateFiModificataObisnuit(string $luna): bool
    {
        if ($this->utilizatorCurent->isAdmin()) {
            return true;
        }

        return !$this->esteInchisa($luna) && $this->esteInIntervalEditabil($luna);
    }

    /** Permite managerului sa completeze orice luna deschisa, inclusiv in afara ferestrei personale. */
    public function valideazaModificareManageriala(string $data): void
    {
        $luna = substr($data, 0, 7);
        $this->valideazaLuna($luna);

        if ($this->esteInchisa($luna)) {
            throw Conflict::createWithBody(
                'lunaCondicaInchisa',
                Body::create()->withMessageTranslation('lunaCondicaInchisa', 'Prezenta')
            );
        }
    }

    public function poateFiModificataManagerial(string $luna): bool
    {
        $this->valideazaLuna($luna);

        return !$this->esteInchisa($luna);
    }

    /** Fereastra in care angajatul poate actiona si, implicit, poate primi remindere. */
    public function esteInFereastraPersonalaEditabila(string $luna): bool
    {
        return !$this->esteInchisa($luna) && $this->esteInIntervalEditabil($luna);
    }

    /** @return list<string> Lunile deschise, de la cea curenta spre cea mai veche editabila. */
    public function obtineLuniDeschiseDinFereastraPersonala(): array
    {
        $lunaCurenta = substr($this->dataOra->getToday()->toString(), 0, 7);
        $lunaMinima = $this->obtineLunaMinimaEditabila();
        $cursor = new DateTimeImmutable($lunaCurenta . '-01');
        $limita = new DateTimeImmutable($lunaMinima . '-01');
        $luni = [];

        while ($cursor >= $limita) {
            $luna = $cursor->format('Y-m');

            if (!$this->esteInchisa($luna)) {
                $luni[] = $luna;
            }

            $cursor = $cursor->modify('-1 month');
        }

        return $luni;
    }

    /**
     * @return array{
     *     stare: string,
     *     esteInchisa: bool,
     *     inchisaLa: ?string,
     *     inchisaDeId: ?string,
     *     revizie: int,
     *     lunaMinimaEditabila: string,
     *     numarLuniAnterioareEditabile: int,
     *     permiteExportCondicaIncompleta: bool
     * }
     */
    public function descrie(string $luna): array
    {
        $entitate = $this->obtine($luna);
        $inchisa = $entitate?->get('stare') === self::STARE_INCHISA;

        return [
            'stare' => $inchisa ? self::STARE_INCHISA : self::STARE_DESCHISA,
            'esteInchisa' => $inchisa,
            'inchisaLa' => $this->sirSauNull($entitate?->get('inchisaLa')),
            'inchisaDeId' => $this->sirSauNull($entitate?->get('inchisaDeId')),
            'revizie' => (int) ($entitate?->get('revizie') ?? 0),
            'lunaMinimaEditabila' => $this->obtineLunaMinimaEditabila(),
            'numarLuniAnterioareEditabile' =>
                $this->configuratii->obtineNumarLuniAnterioareEditabile(),
            'permiteExportCondicaIncompleta' =>
                $this->configuratii->permiteExportCondicaIncompleta(),
        ];
    }

    public function obtineLunaMinimaEditabila(): string
    {
        $lunaCurenta = substr($this->dataOra->getToday()->toString(), 0, 7);

        return (new DateTimeImmutable($lunaCurenta . '-01'))
            ->modify(
                '-' . $this->configuratii->obtineNumarLuniAnterioareEditabile() . ' months'
            )
            ->format('Y-m');
    }

    private function sirSauNull(mixed $valoare): ?string
    {
        return is_string($valoare) && $valoare !== '' ? $valoare : null;
    }

    private function esteInIntervalEditabil(string $luna): bool
    {
        $this->valideazaLuna($luna);
        $lunaCurenta = substr($this->dataOra->getToday()->toString(), 0, 7);
        $primaZiCurenta = new DateTimeImmutable($lunaCurenta . '-01');
        $primaZiMinima = new DateTimeImmutable($this->obtineLunaMinimaEditabila() . '-01');
        $primaZiSolicitata = new DateTimeImmutable($luna . '-01');

        return $primaZiSolicitata >= $primaZiMinima && $primaZiSolicitata <= $primaZiCurenta;
    }
}
