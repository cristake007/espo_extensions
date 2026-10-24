<?php
namespace Espo\Modules\ManagementAngajati\Classes\RecordHooks\CerereConcediu;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\DeleteParams;
use Espo\Core\Record\Hook\DeleteHook;
use Espo\ORM\Entity;
/** @implements DeleteHook<Entity> */
class BlocheazaStergereaDirecta implements DeleteHook
{
    public function process(Entity $inregistrare, DeleteParams $parametri): void
    {
        throw Forbidden::createWithBody('operatieDoarPrinFluxConcedii', Body::create()->withMessageTranslation('operatieDoarPrinFluxConcedii', 'CerereConcediu'));
    }
}
