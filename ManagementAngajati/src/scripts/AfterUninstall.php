<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

/**
 * Apelat la dezinstalarea extensiei. Clasa si metoda sunt impuse de mecanismul EspoCRM.
 */
class AfterUninstall
{
    private const array SCOPE_EXTENSIE = [
        'Prezenta',
        'CerereConcediu',
        'CondicaPrezenta',
        'AdministrareConcedii',
        'GestionareAngajati',
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
        $configuratie = $containerServicii->getByClass(Config::class);
        $listaTaburi = $configuratie->get('tabList', []);
        $listaEntitatiCalendar = $configuratie->get('calendarEntityList', []);
        $listaTaburi = is_array($listaTaburi) ? $listaTaburi : [];
        $listaEntitatiCalendar = is_array($listaEntitatiCalendar) ?
            $listaEntitatiCalendar : [];

        $listaNoua = $this->curataLista($listaTaburi);
        $listaCalendarNoua = array_values(array_filter(
            $listaEntitatiCalendar,
            fn (mixed $scope): bool => $scope !== 'CerereConcediu'
        ));

        if ($listaNoua === $listaTaburi && $listaCalendarNoua === $listaEntitatiCalendar) {
            return;
        }

        $scriitorConfiguratie = $containerServicii
            ->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

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

