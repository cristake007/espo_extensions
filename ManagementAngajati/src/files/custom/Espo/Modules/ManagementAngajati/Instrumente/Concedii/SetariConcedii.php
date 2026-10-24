<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Core\AclManager;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

class SetariConcedii
{
    public const string CHEIE_AMBII = 'managementAngajatiConcediiNecesitaAmbiiManageri';
    public const string CHEIE_APROBATORI = 'managementAngajatiAprobatoriConcediiIds';
    public const string CHEIE_ACORDARE_ANUALA_AUTOMATA =
        'managementAngajatiConcediiAcordareAnualaAutomata';

    public function __construct(
        private Config $configuratie,
        private ConfigWriter $scriitor,
        private EntityManager $managerEntitati,
        private AclManager $managerAcl,
    ) {}

    /** @return array{necesitaAmbiiManageri: bool, acordareAnualaAutomata: bool, aprobatoriIds: list<string>, aprobatori: list<array{id: string, name: string}>, candidati: list<array{id: string, name: string}>} */
    public function obtine(): array
    {
        $ids = $this->obtineIdsAprobatori();
        $aprobatori = [];
        $candidati = [];

        $utilizatori = $this->managerEntitati->getRDBRepositoryByClass(User::class)
            ->where(['type' => User::TYPE_REGULAR, 'isActive' => true])
            ->order('userName', 'ASC')
            ->find();

        foreach ($utilizatori as $utilizator) {
            if ($this->esteManagerEligibil($utilizator)) {
                $candidati[] = [
                    'id' => $utilizator->getId(),
                    'name' => $utilizator->getName() ?? (string) $utilizator->get('userName'),
                ];
            }
        }

        foreach ($ids as $id) {
            $user = $this->managerEntitati->getEntityById(User::ENTITY_TYPE, $id);

            if ($user instanceof User && $user->isActive() && $user->isRegular()) {
                $aprobatori[] = ['id' => $id, 'name' => $user->getName() ?? (string) $user->get('userName')];
            }
        }

        return [
            'necesitaAmbiiManageri' => (bool) $this->configuratie->get(self::CHEIE_AMBII, false),
            'acordareAnualaAutomata' => $this->esteAcordareaAnualaAutomataActiva(),
            'aprobatoriIds' => array_column($aprobatori, 'id'),
            'aprobatori' => $aprobatori,
            'candidati' => $candidati,
        ];
    }

    /** @param list<string> $ids */
    public function salveaza(
        bool $necesitaAmbii,
        bool $acordareAnualaAutomata,
        array $ids
    ): void
    {
        $ids = array_values(array_unique(array_filter($ids, 'is_string')));

        if (count($ids) < 2) {
            throw BadRequest::createWithBody(
                'doiAprobatoriNecesari',
                Body::create()->withMessageTranslation('doiAprobatoriNecesari', 'AdministrareConcedii')
            );
        }

        foreach ($ids as $id) {
            $user = $this->managerEntitati->getEntityById(User::ENTITY_TYPE, $id);

            if (!$user instanceof User || !$this->esteManagerEligibil($user)) {
                throw BadRequest::createWithBody(
                    'aprobatorInvalid',
                    Body::create()->withMessageTranslation('aprobatorInvalid', 'AdministrareConcedii')
                );
            }
        }

        $this->scriitor->set(self::CHEIE_AMBII, $necesitaAmbii);
        $this->scriitor->set(
            self::CHEIE_ACORDARE_ANUALA_AUTOMATA,
            $acordareAnualaAutomata
        );
        $this->scriitor->set(self::CHEIE_APROBATORI, $ids);
        $this->scriitor->save();
    }

    /** @return list<string> */
    public function obtineIdsAprobatori(): array
    {
        $valoare = $this->configuratie->get(self::CHEIE_APROBATORI, []);

        return is_array($valoare) ? array_values(array_filter($valoare, 'is_string')) : [];
    }

    /** @return list<string> */
    public function aprobatoriPentru(string $idAngajat): array
    {
        return array_values(array_filter(
            $this->obtineIdsAprobatori(),
            static fn (string $id): bool => $id !== $idAngajat
        ));
    }

    public function necesitaAmbii(): bool
    {
        return (bool) $this->configuratie->get(self::CHEIE_AMBII, false);
    }

    public function esteAcordareaAnualaAutomataActiva(): bool
    {
        return (bool) $this->configuratie->get(
            self::CHEIE_ACORDARE_ANUALA_AUTOMATA,
            true
        );
    }

    private function esteManagerEligibil(User $utilizator): bool
    {
        return $utilizator->isActive() &&
            $utilizator->isRegular() &&
            $this->managerAcl->createUserAcl($utilizator)->checkScope('GestionareAngajati');
    }
}
