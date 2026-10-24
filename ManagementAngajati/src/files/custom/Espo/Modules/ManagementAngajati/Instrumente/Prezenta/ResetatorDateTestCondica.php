<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\ORM\Repository\Option\RemoveOption;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Throwable;

/** Readuce datele condicii la starea unei instalari curate, pastrand datele native EspoCRM. */
class ResetatorDateTestCondica
{
    public const string CONFIRMARE = 'RESET DATE CONDICA';

    private const array OPTIUNI_STERGERE = [RemoveOption::SILENT => true];
    private const array OPTIUNI_SALVARE = [
        SaveOption::SILENT => true,
        SaveOption::SKIP_HOOKS => true,
    ];

    public function __construct(
        private EntityManager $managerEntitati,
        private Config $configuratie,
        private ConfigWriter $scriitorConfiguratie,
    ) {}

    /**
     * @return array{
     *     prezente: int,
     *     stariLuna: int,
     *     luniInchise: int,
     *     remindere: int,
     *     configuratiiCicRemote: int,
     *     dateAngajare: int,
     *     calendareLucruPastrate: int,
     *     modAplicareRemote: string,
     *     numarLuniAnterioareEditabile: int
     * }
     */
    public function previzualizeaza(): array
    {
        return [
            'prezente' => $this->numara($this->obtineEntitati('Prezenta')),
            'stariLuna' => $this->numara($this->obtineEntitati(CicluLunarCondica::TIP_ENTITATE)),
            'luniInchise' => $this->numara(
                $this->managerEntitati
                    ->getRDBRepository(CicluLunarCondica::TIP_ENTITATE)
                    ->where(['stare' => CicluLunarCondica::STARE_INCHISA])
                    ->find()
            ),
            'remindere' => $this->numara($this->obtineRemindere()),
            'configuratiiCicRemote' => $this->numara(
                $this->obtineEntitati(ConfiguratiiCondicaAngajati::TIP_ENTITATE)
            ),
            'dateAngajare' => $this->numara(
                $this->managerEntitati
                    ->getRDBRepositoryByClass(User::class)
                    ->where([
                        'type' => User::TYPE_REGULAR,
                        'dataAngajare!=' => null,
                    ])
                    ->find()
            ),
            'calendareLucruPastrate' => $this->managerEntitati
                ->getRDBRepository('WorkingTimeCalendar')
                ->count(),
            'modAplicareRemote' => (string) $this->configuratie->get(
                ConfiguratiiCondicaAngajati::CHEIE_MOD_REMOTE,
                ConfiguratiiCondicaAngajati::MOD_REMOTE_CURENT
            ),
            'numarLuniAnterioareEditabile' => (int) $this->configuratie->get(
                ConfiguratiiCondicaAngajati::CHEIE_LUNI_ANTERIOARE_EDITABILE,
                ConfiguratiiCondicaAngajati::LUNI_ANTERIOARE_EDITABILE_IMPLICIT
            ),
        ];
    }

    /** @return array<string, mixed> */
    public function reseteaza(): array
    {
        $inainte = $this->previzualizeaza();
        $configuratieAnterioara = $this->obtineConfiguratieAnterioara();

        $this->eliminaSetariGenerale();

        try {
            $this->managerEntitati->getTransactionManager()->run(function (): void {
                $utilizatori = $this->managerEntitati
                    ->getRDBRepositoryByClass(User::class)
                    ->where(['type' => User::TYPE_REGULAR])
                    ->order('id')
                    ->forUpdate()
                    ->find();

                $this->stergeEntitatiDefinitiv($this->obtineEntitati('Prezenta'));
                $this->stergeEntitatiDefinitiv($this->obtineRemindere());
                $this->stergeEntitatiDefinitiv(
                    $this->obtineEntitati(CicluLunarCondica::TIP_ENTITATE)
                );
                $this->stergeEntitatiDefinitiv(
                    $this->obtineEntitati(ConfiguratiiCondicaAngajati::TIP_ENTITATE)
                );

                foreach ($utilizatori as $utilizator) {
                    if ($utilizator->get('dataAngajare') === null) {
                        continue;
                    }

                    $utilizator->set('dataAngajare', null);
                    $this->managerEntitati->saveEntity($utilizator, self::OPTIUNI_SALVARE);
                }
            });
        } catch (Throwable $exceptie) {
            $this->restaureazaConfiguratia($configuratieAnterioara);

            throw $exceptie;
        }

        return [
            'sterse' => $inainte,
            'dupaResetare' => $this->previzualizeaza(),
        ];
    }

    /** @return iterable<Entity> */
    private function obtineEntitati(string $tipEntitate): iterable
    {
        return $this->managerEntitati->getRDBRepository($tipEntitate)->find();
    }

    /** @return iterable<Notification> */
    private function obtineRemindere(): iterable
    {
        $notificari = $this->managerEntitati
            ->getRDBRepositoryByClass(Notification::class)
            ->where(['type' => Notification::TYPE_MESSAGE])
            ->find();

        foreach ($notificari as $notificare) {
            $date = $notificare->getData() ?? (object) [];
            $esteMarcata = ($date->managementAngajatiReminderPrezenta ?? false) === true;
            $esteDinVersiuneAnterioara = isset($date->luna, $date->numarZileLipsa);

            if ($esteMarcata || $esteDinVersiuneAnterioara) {
                yield $notificare;
            }
        }
    }

    /** @param iterable<Entity> $entitati */
    private function stergeEntitatiDefinitiv(iterable $entitati): void
    {
        foreach ($entitati as $entitate) {
            $tipEntitate = $entitate->getEntityType();
            $id = $entitate->getId();
            $this->managerEntitati->removeEntity($entitate, self::OPTIUNI_STERGERE);
            $this->managerEntitati
                ->getRDBRepository($tipEntitate)
                ->deleteFromDb($id, true);
        }
    }

    /** @param iterable<mixed> $elemente */
    private function numara(iterable $elemente): int
    {
        $numar = 0;

        foreach ($elemente as $_element) {
            $numar++;
        }

        return $numar;
    }

    /** @return array<string, array{exista: bool, valoare: mixed}> */
    private function obtineConfiguratieAnterioara(): array
    {
        $rezultat = [];

        foreach ($this->cheiConfiguratie() as $cheie) {
            $rezultat[$cheie] = [
                'exista' => $this->configuratie->has($cheie),
                'valoare' => $this->configuratie->get($cheie),
            ];
        }

        return $rezultat;
    }

    private function eliminaSetariGenerale(): void
    {
        foreach ($this->cheiConfiguratie() as $cheie) {
            $this->scriitorConfiguratie->remove($cheie);
        }

        $this->scriitorConfiguratie->save();
    }

    /** @param array<string, array{exista: bool, valoare: mixed}> $anterioara */
    private function restaureazaConfiguratia(array $anterioara): void
    {
        foreach ($anterioara as $cheie => $date) {
            if ($date['exista']) {
                $this->scriitorConfiguratie->set($cheie, $date['valoare']);
            } else {
                $this->scriitorConfiguratie->remove($cheie);
            }
        }

        $this->scriitorConfiguratie->save();
    }

    /** @return list<string> */
    private function cheiConfiguratie(): array
    {
        return [
            ConfiguratiiCondicaAngajati::CHEIE_MOD_REMOTE,
            ConfiguratiiCondicaAngajati::CHEIE_LUNI_ANTERIOARE_EDITABILE,
        ];
    }
}
