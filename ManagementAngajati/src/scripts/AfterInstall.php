<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Modules\ManagementAngajati\Instrumente\Dependente\NotificatorDependentaZileSarbatoare;

/**
 * Apelat la instalarea extensiei. Clasa si metoda sunt impuse de mecanismul EspoCRM.
 */
class AfterInstall
{
    private const string SCOPE_PREZENTA = 'Prezenta';
    private const string SCOPE_CONCEDII = 'CerereConcediu';
    private const string SCOPE_CONDICA = 'CondicaPrezenta';
    private const string SCOPE_ADMINISTRARE_CONCEDII = 'AdministrareConcedii';
    private const string SCOPE_GESTIONARE = 'GestionareAngajati';
    private const array SCOPE_EXTENSIE = [
        self::SCOPE_PREZENTA,
        self::SCOPE_CONCEDII,
        self::SCOPE_CONDICA,
        self::SCOPE_ADMINISTRARE_CONCEDII,
        self::SCOPE_GESTIONARE,
    ];
    private const array SCOPE_RETRASE = [
        'InregistrarePrezenta',
    ];
    private const array RUTE_URL_RETRASE = [
        '#Prezenta',
        '#CerereConcediu',
        '#CondicaPrezenta',
        '#AdministrareConcedii',
        '#GestionareAngajati',
    ];

    public function run(Container $containerServicii)
    {
        $containerServicii
            ->getByClass(InjectableFactory::class)
            ->create(NotificatorDependentaZileSarbatoare::class)
            ->notificaDacaEsteNecesara();

        $configuratie = $containerServicii->getByClass(Config::class);
        $listaTaburi = $configuratie->get('tabList', []);
        $listaEntitatiCalendar = $configuratie->get('calendarEntityList', []);

        if (!is_array($listaTaburi)) {
            $listaTaburi = [];
        }

        if (!is_array($listaEntitatiCalendar)) {
            $listaEntitatiCalendar = [];
        }

        $listaNoua = $this->curataLista($listaTaburi);
        $listaNoua[] = self::SCOPE_PREZENTA;
        $listaNoua[] = self::SCOPE_CONCEDII;
        $listaNoua[] = [
            'type' => 'group',
            'text' => '$condicaPrezenta',
            'iconClass' => 'fas fa-users',
            'itemList' => [
                self::SCOPE_CONDICA,
                self::SCOPE_ADMINISTRARE_CONCEDII,
            ],
        ];
        $listaNoua[] = [
            'type' => 'group',
            'text' => '$managementAngajati',
            'iconClass' => 'fas fa-user-cog',
            'itemList' => [
                self::SCOPE_GESTIONARE,
            ],
        ];

        $scriitorConfiguratie = $containerServicii
            ->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        $listaCalendarNoua = array_values(array_unique([
            ...$listaEntitatiCalendar,
            self::SCOPE_CONCEDII,
        ]));

        if ($listaNoua === $listaTaburi && $listaCalendarNoua === $listaEntitatiCalendar) {
            return;
        }

        if ($listaNoua !== $listaTaburi) {
            $scriitorConfiguratie->set('tabList', $listaNoua);
        }

        if ($listaCalendarNoua !== $listaEntitatiCalendar) {
            $scriitorConfiguratie->set('calendarEntityList', $listaCalendarNoua);
        }

        $scriitorConfiguratie->save();
    }

    /**
     * @param array<mixed> $lista
     * @return array<mixed>
     */
    private function curataLista(array $lista): array
    {
        $rezultat = [];

        foreach ($lista as $element) {
            if (
                is_string($element) &&
                (
                    in_array($element, self::SCOPE_EXTENSIE, true) ||
                    in_array($element, self::SCOPE_RETRASE, true)
                )
            ) {
                continue;
            }

            $date = is_object($element) ? get_object_vars($element) : $element;

            if (!is_array($date)) {
                $rezultat[] = $element;

                continue;
            }

            if (
                ($date['type'] ?? null) === 'url' &&
                in_array($date['url'] ?? null, self::RUTE_URL_RETRASE, true)
            ) {
                continue;
            }

            if (($date['type'] ?? null) === 'group' && is_array($date['itemList'] ?? null)) {
                $date['itemList'] = $this->curataLista($date['itemList']);

                if ($date['itemList'] === []) {
                    continue;
                }
            }

            $rezultat[] = $date;
        }

        return array_values($rezultat);
    }
}

