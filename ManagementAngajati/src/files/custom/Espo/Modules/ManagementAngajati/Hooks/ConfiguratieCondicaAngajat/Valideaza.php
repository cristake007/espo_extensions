<?php

namespace Espo\Modules\ManagementAngajati\Hooks\ConfiguratieCondicaAngajat;

use Espo\Core\FieldValidation\FieldValidationManager;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ValidatorConfiguratieCondicaAngajat;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/** @implements BeforeSave<Entity> */
class Valideaza implements BeforeSave
{
    public function __construct(
        private FieldValidationManager $managerValidareCampuri,
        private ValidatorConfiguratieCondicaAngajat $validatorConfiguratie,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $this->managerValidareCampuri->process($entity);
        $this->validatorConfiguratie->valideaza($entity);
    }
}
