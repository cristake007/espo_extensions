<?php

namespace Espo\Modules\ManagementAngajati\Classes\RecordHooks\Prezenta;

use Espo\Core\Record\CreateParams;
use Espo\Core\Record\Hook\CreateHook;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\DenumirePrezenta;
use Espo\ORM\Entity;

/** @implements CreateHook<Entity> */
class StabilesteNumele implements CreateHook
{
    public function __construct(private DenumirePrezenta $denumirePrezenta) {}

    public function process(Entity $inregistrare, CreateParams $parametri): void
    {
        $this->denumirePrezenta->aplica($inregistrare);
    }
}
