<?php
namespace Espo\Modules\ManagementAngajati\Classes\RecordHooks\CerereConcediu;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Hook\UpdateHook;
use Espo\Core\Record\UpdateParams;
use Espo\ORM\Entity;
/** @implements UpdateHook<Entity> */
class BlocheazaActualizareaDirecta implements UpdateHook
{
    public function process(Entity $inregistrare, UpdateParams $parametri): void
    {
        throw Forbidden::createWithBody('operatieDoarPrinFluxConcedii', Body::create()->withMessageTranslation('operatieDoarPrinFluxConcedii', 'CerereConcediu'));
    }
}
