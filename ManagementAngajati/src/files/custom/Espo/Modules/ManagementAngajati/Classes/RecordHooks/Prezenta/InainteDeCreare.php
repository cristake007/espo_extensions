<?php

namespace Espo\Modules\ManagementAngajati\Classes\RecordHooks\Prezenta;

use Espo\Core\Record\CreateParams;
use Espo\Core\Record\Hook\CreateHook;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ValidatorPrezenta;
use Espo\ORM\Entity;

/** @implements CreateHook<Entity> */
class InainteDeCreare implements CreateHook
{
    public function __construct(private ValidatorPrezenta $validatorPrezenta)
    {}

    public function process(Entity $inregistrare, CreateParams $parametri): void
    {
        $this->validatorPrezenta->valideazaLaCreare($inregistrare);
    }
}
