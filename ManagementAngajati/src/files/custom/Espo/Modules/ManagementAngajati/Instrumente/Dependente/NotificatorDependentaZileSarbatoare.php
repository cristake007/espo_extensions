<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Dependente;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Language\LanguageFactory;
use Espo\Entities\Notification;
use Espo\Entities\Preferences;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

/** Creeaza notificarea nativa destinata administratorilor cand dependenta lipseste. */
class NotificatorDependentaZileSarbatoare
{
    private const string ID_ACTIUNE = 'management-angajati:dependenta-zile-sarbatoare';

    public function __construct(
        private EntityManager $managerEntitati,
        private VerificatorZileSarbatoare $verificator,
        private LanguageFactory $fabricaLimbi,
        private Config $configuratie,
    ) {}

    public function notificaDacaEsteNecesara(): int
    {
        if ($this->verificator->esteInstalataCompatibil()) {
            return 0;
        }

        $numarNotificari = 0;
        $administratori = $this->managerEntitati
            ->getRDBRepositoryByClass(User::class)
            ->where([
                User::ATTR_IS_ACTIVE => true,
                User::ATTR_TYPE => [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN],
            ])
            ->find();

        foreach ($administratori as $administrator) {
            if (!$administrator instanceof User || $this->existaNotificare($administrator)) {
                continue;
            }

            $limba = $this->obtineLimba($administrator);
            $notificare = $this->managerEntitati
                ->getRDBRepositoryByClass(Notification::class)
                ->getNew();

            $notificare
                ->setType(Notification::TYPE_MESSAGE)
                ->setMessage($limba->translateLabel(
                    'dependentaZileSarbatoareIndisponibila',
                    'messages',
                    'Prezenta'
                ))
                ->setUserId($administrator->getId())
                ->setActionId($this->obtineIdActiune($administrator))
                ->setRead(false)
                ->setData([
                    'managementAngajatiDependentaZileSarbatoare' => true,
                    'style' => 'text-danger',
                    'versiuneMinima' => VerificatorZileSarbatoare::VERSIUNE_MINIMA,
                ]);
            $this->managerEntitati->saveEntity($notificare);
            $numarNotificari++;
        }

        return $numarNotificari;
    }

    private function existaNotificare(User $administrator): bool
    {
        return $this->managerEntitati
            ->getRDBRepositoryByClass(Notification::class)
            ->where([
                Notification::ATTR_USER_ID => $administrator->getId(),
                Notification::ATTR_ACTION_ID => $this->obtineIdActiune($administrator),
            ])
            ->findOne() instanceof Notification;
    }

    private function obtineIdActiune(User $administrator): string
    {
        return md5(self::ID_ACTIUNE . ':' . $administrator->getId());
    }

    private function obtineLimba(User $administrator): Language
    {
        $preferinte = $this->managerEntitati->getEntityById(
            Preferences::ENTITY_TYPE,
            $administrator->getId()
        );
        $preferinte = $preferinte instanceof Preferences ? $preferinte : null;
        $codLimba = Language::detectLanguage($this->configuratie, $preferinte) ?? 'en_US';

        return $this->fabricaLimbi->create($codLimba);
    }
}
