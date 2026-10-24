<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Prezenta;

use Espo\Core\FileStorage\Manager as ManagerStocareFisiere;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\File\Manager as ManagerFisiere;
use Espo\Core\Utils\ThemeManager;
use Espo\Entities\Attachment;
use Espo\ORM\EntityManager;

/** Citeste logo-ul companiei configurat nativ in EspoCRM. */
class FurnizorLogoCondica
{
    public function __construct(
        private Config $configuratie,
        private EntityManager $managerEntitati,
        private ManagerStocareFisiere $managerStocareFisiere,
        private ThemeManager $managerTema,
        private ManagerFisiere $managerFisiere,
    ) {}

    public function obtineDataUri(): ?string
    {
        $id = $this->configuratie->get('companyLogoId');

        if (!is_string($id) || $id === '') {
            return $this->obtineLogoTema();
        }

        $logo = $this->managerEntitati->getEntityById(Attachment::ENTITY_TYPE, $id);

        if (!$logo instanceof Attachment || !$this->managerStocareFisiere->exists($logo)) {
            return $this->obtineLogoTema();
        }

        $tip = $logo->getType();

        if (!is_string($tip) || !str_starts_with($tip, 'image/')) {
            return $this->obtineLogoTema();
        }

        $continut = $this->managerStocareFisiere->getContents($logo);

        return $continut !== '' ? 'data:' . $tip . ';base64,' . base64_encode($continut) : null;
    }

    private function obtineLogoTema(): ?string
    {
        $cale = $this->managerTema->getLogoSrc();

        if (
            str_contains($cale, '://') ||
            !$this->managerFisiere->exists($cale)
        ) {
            return null;
        }

        $continut = $this->managerFisiere->getContents($cale);

        if ($continut === '') {
            return null;
        }

        $extensie = strtolower(pathinfo($cale, PATHINFO_EXTENSION));
        $tip = match ($extensie) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => null,
        };

        return $tip ? 'data:' . $tip . ';base64,' . base64_encode($continut) : null;
    }
}
