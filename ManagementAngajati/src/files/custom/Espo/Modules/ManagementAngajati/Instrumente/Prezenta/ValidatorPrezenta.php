<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Field\DateTime as CampDataOra;
use Espo\Core\Utils\DateTime;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\EligibilitateAngajat;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\ConcediiAprobate;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\WorkingTime\CalendarFactory;
use Espo\Tools\WorkingTime\CalendarUtilityFactory;

class ValidatorPrezenta
{
    private const string TIP_ENTITATE = 'Prezenta';
    private const string SCOPE_TRADUCERI = 'Prezenta';

    public function __construct(
        private EntityManager $managerEntitati,
        private CalendarFactory $fabricaCalendare,
        private CalendarUtilityFactory $fabricaUtilitareCalendar,
        private DateTime $dataOra,
        private ReguliPrezenta $reguliPrezenta,
        private ConfiguratiiCondicaAngajati $configuratiiCondica,
        private CicluLunarCondica $cicluLunar,
        private SarbatoriLegale $sarbatoriLegale,
        private EligibilitateAngajat $eligibilitateAngajat,
        private ConcediiAprobate $concediiAprobate,
    ) {}

    /**
     * @throws BadRequest
     * @throws Conflict
     */
    public function valideazaLaCreare(Entity $inregistrare): void
    {
        $this->valideazaCreare($inregistrare, false, null, true);
    }

    /** @throws BadRequest|Conflict */
    public function valideazaLaCreareManageriala(
        Entity $inregistrare,
        ?User $angajat = null,
        bool $verificaDuplicat = true,
    ): void {
        $this->valideazaCreare(
            $inregistrare,
            true,
            $angajat,
            $verificaDuplicat
        );
    }

    /**
     * @throws BadRequest
     * @throws Conflict
     */
    private function valideazaCreare(
        Entity $inregistrare,
        bool $esteManageriala,
        ?User $angajat,
        bool $verificaDuplicat,
    ): void {
        $idAngajat = (string) $inregistrare->get('assignedUserId');
        $dataPrezenta = (string) $inregistrare->get('dataPrezenta');

        $this->reguliPrezenta->valideazaDataPrezenta(
            dataPrezenta: $dataPrezenta,
            dataCurenta: $this->dataOra->getToday()->toString(),
        );
        if ($esteManageriala) {
            $this->cicluLunar->valideazaModificareManageriala($dataPrezenta);
        } else {
            $this->cicluLunar->valideazaModificareObisnuita($dataPrezenta);
        }

        $angajat ??= $this->obtineAngajat($idAngajat);
        $this->reguliPrezenta->valideazaDataAngajare(
            $this->eligibilitateAngajat->obtineDataAngajare($angajat),
            $dataPrezenta
        );

        $this->valideazaAbsentaCic($inregistrare);
        $this->valideazaAbsentaConcediu($inregistrare);

        $calendar = $this->fabricaCalendare->createForUser($angajat);

        if (!$calendar->isAvailable()) {
            throw BadRequest::createWithBody(
                'calendarLucruIndisponibil',
                Body::create()->withMessageTranslation('calendarLucruIndisponibil', self::SCOPE_TRADUCERI)
            );
        }

        $dataCalendar = CampDataOra::fromDateTime(
            new DateTimeImmutable($dataPrezenta . ' 12:00:00', $calendar->getTimezone())
        );

        $esteZiLucratoare = $this->fabricaUtilitareCalendar
            ->create($calendar)
            ->isWorkingDay($dataCalendar);

        $this->reguliPrezenta->valideazaEligibilitatea(
            esteZiLucratoare: $esteZiLucratoare,
            esteSarbatoareLegala: $this->sarbatoriLegale->esteSarbatoare($dataPrezenta),
            esteDuplicat: $verificaDuplicat && $this->esteDuplicat($idAngajat, $dataPrezenta),
        );
    }

    /** @throws BadRequest|Conflict */
    public function valideazaLaActualizare(Entity $inregistrare): void
    {
        $this->cicluLunar->valideazaModificareObisnuita(
            (string) $inregistrare->get('dataPrezenta')
        );
        $this->valideazaAbsentaCic($inregistrare);
        $this->valideazaAbsentaConcediu($inregistrare);
    }

    /** @throws Conflict */
    public function valideazaLaActualizareManageriala(Entity $inregistrare): void
    {
        $this->cicluLunar->valideazaModificareManageriala(
            (string) $inregistrare->get('dataPrezenta')
        );
        $this->valideazaAbsentaCic($inregistrare);
        $this->valideazaAbsentaConcediu($inregistrare);
    }

    /** @throws Conflict */
    private function valideazaAbsentaCic(Entity $inregistrare): void
    {
        if (!$this->configuratiiCondica->esteAngajatInCic(
            (string) $inregistrare->get('assignedUserId'),
            (string) $inregistrare->get('dataPrezenta')
        )) {
            return;
        }

        throw Conflict::createWithBody(
            'ziAcoperitaDeCic',
            Body::create()->withMessageTranslation(
                'ziAcoperitaDeCic',
                self::SCOPE_TRADUCERI
            )
        );
    }

    /** @throws Conflict */
    private function valideazaAbsentaConcediu(Entity $inregistrare): void
    {
        if (!$this->concediiAprobate->esteInConcediu(
            (string) $inregistrare->get('assignedUserId'),
            (string) $inregistrare->get('dataPrezenta')
        )) {
            return;
        }

        throw Conflict::createWithBody(
            'ziAcoperitaDeConcediu',
            Body::create()->withMessageTranslation(
                'ziAcoperitaDeConcediu',
                self::SCOPE_TRADUCERI
            )
        );
    }

    /** @throws BadRequest */
    private function obtineAngajat(string $idAngajat): User
    {
        $angajat = $this->managerEntitati
            ->getRDBRepositoryByClass(User::class)
            ->where(['id' => $idAngajat])
            ->forUpdate()
            ->findOne();

        if (!$angajat instanceof User || !$angajat->isActive() || !$angajat->isRegular()) {
            throw BadRequest::createWithBody(
                'angajatInvalid',
                Body::create()->withMessageTranslation('angajatInvalid', self::SCOPE_TRADUCERI)
            );
        }

        return $angajat;
    }

    private function esteDuplicat(string $idAngajat, string $dataPrezenta): bool
    {
        return $this->managerEntitati
            ->getRDBRepository(self::TIP_ENTITATE)
            ->where([
                'assignedUserId' => $idAngajat,
                'dataPrezenta' => $dataPrezenta,
            ])
            ->findOne() !== null;
    }
}
