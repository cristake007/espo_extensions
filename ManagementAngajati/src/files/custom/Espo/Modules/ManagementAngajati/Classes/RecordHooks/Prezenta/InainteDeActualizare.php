<?php

namespace Espo\Modules\ManagementAngajati\Classes\RecordHooks\Prezenta;

use Espo\Core\Record\Hook\UpdateHook;
use Espo\Core\Record\UpdateParams;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ValidatorPrezenta;
use Espo\ORM\Entity;

/** @implements UpdateHook<Entity> */
class InainteDeActualizare implements UpdateHook
{
    public function __construct(private ValidatorPrezenta $validatorPrezenta)
    {}

    public function process(Entity $inregistrare, UpdateParams $parametri): void
    {
        $this->validatorPrezenta->valideazaLaActualizare($inregistrare);
    }
}
