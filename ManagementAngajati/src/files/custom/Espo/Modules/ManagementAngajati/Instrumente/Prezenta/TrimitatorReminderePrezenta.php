<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\Acl\Table;
use Espo\Core\AclManager;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\ORM\Repository\Option\RemoveOption;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Language\LanguageFactory;
use Espo\Entities\Notification;
use Espo\Entities\Preferences;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;

/** Trimite o notificare nativa per angajat, agregata pentru intreaga fereastra editabila. */
class TrimitatorReminderePrezenta
{
    public function __construct(
        private EntityManager $managerEntitati,
        private CondicaPrezentaLunara $condica,
        private CicluLunarCondica $cicluLunar,
        private AclManager $managerAcl,
        private LanguageFactory $fabricaLimbi,
        private Config $configuratie,
    ) {}

    /**
     * @return array{
     *     lunaSolicitata: string,
     *     luniVerificate: list<string>,
     *     notificariCreate: int,
     *     notificariActualizate: int,
     *     angajatiFaraLipsuri: int,
     *     angajatiFaraDreptCreare: int
     * }
     */
    public function trimite(string $luna): array
    {
        $this->cicluLunar->valideazaLuna($luna);

        if (!$this->cicluLunar->esteInFereastraPersonalaEditabila($luna)) {
            throw BadRequest::createWithBody(
                'remindereInAfaraFerestreiEditabile',
                Body::create()->withMessageTranslation(
                    'remindereInAfaraFerestreiEditabile',
                    'CondicaPrezenta'
                )
            );
        }

        $luniVerificate = $this->cicluLunar->obtineLuniDeschiseDinFereastraPersonala();
        $lunaMinimaDisponibila = $this->condica->obtineLunaMinimaDisponibila();

        if ($lunaMinimaDisponibila !== null) {
            $luniVerificate = array_values(array_filter(
                $luniVerificate,
                static fn (string $lunaVerificata): bool =>
                    $lunaVerificata >= $lunaMinimaDisponibila
            ));
        }

        $lipsuriDupaAngajat = [];
        $idAngajati = [];

        foreach ($luniVerificate as $lunaVerificata) {
            $proiectie = $this->condica->obtineDinDateCurente($lunaVerificata);
            $lipsuriLuna = $this->numaraLipsuriDupaAngajat($proiectie['zile'] ?? []);

            foreach ($proiectie['angajati'] ?? [] as $dateAngajat) {
                if (!is_array($dateAngajat) || !is_string($dateAngajat['idAngajat'] ?? null)) {
                    continue;
                }

                $idAngajat = $dateAngajat['idAngajat'];
                $idAngajati[$idAngajat] = true;
                $numarLipsuri = $lipsuriLuna[$idAngajat] ?? 0;

                if ($numarLipsuri > 0) {
                    $lipsuriDupaAngajat[$idAngajat][$lunaVerificata] = $numarLipsuri;
                }
            }
        }

        $create = 0;
        $actualizate = 0;
        $faraLipsuri = 0;
        $faraDreptCreare = 0;

        foreach (array_keys($idAngajati) as $idAngajat) {
            $lipsuriPeLuni = $lipsuriDupaAngajat[$idAngajat] ?? [];

            if ($lipsuriPeLuni === []) {
                $faraLipsuri++;
                continue;
            }

            $angajat = $this->managerEntitati->getEntityById(User::ENTITY_TYPE, $idAngajat);

            if (
                !$angajat instanceof User ||
                !$angajat->isActive() ||
                !$angajat->isRegular()
            ) {
                continue;
            }

            if (!$this->managerAcl->createUserAcl($angajat)->checkScope(
                'Prezenta',
                Table::ACTION_CREATE
            )) {
                $faraDreptCreare++;
                continue;
            }

            if ($this->salveazaNotificare($angajat, $lipsuriPeLuni)) {
                $create++;
            } else {
                $actualizate++;
            }
        }

        return [
            'lunaSolicitata' => $luna,
            'luniVerificate' => $luniVerificate,
            'notificariCreate' => $create,
            'notificariActualizate' => $actualizate,
            'angajatiFaraLipsuri' => $faraLipsuri,
            'angajatiFaraDreptCreare' => $faraDreptCreare,
        ];
    }

    /**
     * @param mixed $zile
     * @return array<string, int>
     */
    private function numaraLipsuriDupaAngajat(mixed $zile): array
    {
        if (!is_array($zile)) {
            return [];
        }

        $rezultat = [];

        foreach ($zile as $zi) {
            if (!is_array($zi) || !is_array($zi['celule'] ?? null)) {
                continue;
            }

            foreach ($zi['celule'] as $celula) {
                if (
                    !is_array($celula) ||
                    ($celula['esteLipsa'] ?? false) !== true ||
                    !is_string($celula['idAngajat'] ?? null)
                ) {
                    continue;
                }

                $idAngajat = $celula['idAngajat'];
                $rezultat[$idAngajat] = ($rezultat[$idAngajat] ?? 0) + 1;
            }
        }

        return $rezultat;
    }

    /**
     * Returneaza true daca notificarea a fost creata, false daca a fost actualizata.
     *
     * @param array<string, int> $lipsuriPeLuni
     */
    private function salveazaNotificare(
        User $angajat,
        array $lipsuriPeLuni,
    ): bool {
        $idActiune = md5(
            'management-angajati:reminder-fereastra:' . $angajat->getId()
        );
        $depozit = $this->managerEntitati->getRDBRepositoryByClass(Notification::class);
        $notificare = $depozit
            ->where([
                Notification::ATTR_USER_ID => $angajat->getId(),
                Notification::ATTR_ACTION_ID => $idActiune,
            ])
            ->findOne();

        if (!$notificare instanceof Notification) {
            $notificare = $this->preiaNotificareAnterioara($depozit, $angajat);
        }

        $esteNoua = !$notificare instanceof Notification;
        $notificare = $notificare instanceof Notification ? $notificare : $depozit->getNew();
        $limba = $this->obtineLimba($angajat);
        $randuri = [];

        foreach ($lipsuriPeLuni as $luna => $numarLipsuri) {
            $lunaAfisata = $limba->translateLabel(substr($luna, 5, 2), 'luni', 'Prezenta') .
                ' ' . substr($luna, 0, 4);
            $randuri[] = strtr(
                $limba->translateLabel(
                    'reminderPrezentaLuna',
                    'messages',
                    'CondicaPrezenta'
                ),
                [
                    '{numar}' => (string) $numarLipsuri,
                    '{luna}' => $lunaAfisata,
                    '{ruta}' => '#Prezenta/list?luna=' . $luna,
                ]
            );
        }

        $mesaj = $limba->translateLabel(
            'reminderPrezentaFereastra',
            'messages',
            'CondicaPrezenta'
        ) . "\n\n" . implode("\n", $randuri);
        $numarTotalLipsuri = array_sum($lipsuriPeLuni);

        $notificare
            ->setType(Notification::TYPE_MESSAGE)
            ->setMessage($mesaj)
            ->setUserId($angajat->getId())
            ->setActionId($idActiune)
            ->setRead(false)
            ->setData([
                'managementAngajatiReminderPrezenta' => true,
                'style' => 'text-warning',
                'luniLipsa' => $lipsuriPeLuni,
                'numarZileLipsa' => $numarTotalLipsuri,
            ]);
        $this->managerEntitati->saveEntity($notificare);

        return $esteNoua;
    }

    /**
     * Consolideaza notificarile lunare create de versiunea anterioara.
     *
     * @param RDBRepository<Notification> $depozit
     */
    private function preiaNotificareAnterioara(
        RDBRepository $depozit,
        User $angajat,
    ): ?Notification {
        $notificarePastrata = null;
        $notificari = $depozit
            ->where([
                Notification::ATTR_USER_ID => $angajat->getId(),
                Notification::FIELD_TYPE => Notification::TYPE_MESSAGE,
            ])
            ->find();

        foreach ($notificari as $notificare) {
            if (!$notificare instanceof Notification) {
                continue;
            }

            $date = $notificare->getData() ?? (object) [];
            $esteReminder = ($date->managementAngajatiReminderPrezenta ?? false) === true ||
                isset($date->luna, $date->numarZileLipsa);

            if (!$esteReminder) {
                continue;
            }

            if (!$notificarePastrata) {
                $notificarePastrata = $notificare;
                continue;
            }

            $this->managerEntitati->removeEntity(
                $notificare,
                [RemoveOption::SILENT => true]
            );
        }

        return $notificarePastrata;
    }

    private function obtineLimba(User $angajat): Language
    {
        $preferinte = $this->managerEntitati->getEntityById(
            Preferences::ENTITY_TYPE,
            $angajat->getId()
        );
        $preferinte = $preferinte instanceof Preferences ? $preferinte : null;
        $codLimba = Language::detectLanguage($this->configuratie, $preferinte) ?? 'en_US';

        return $this->fabricaLimbi->create($codLimba);
    }
}
