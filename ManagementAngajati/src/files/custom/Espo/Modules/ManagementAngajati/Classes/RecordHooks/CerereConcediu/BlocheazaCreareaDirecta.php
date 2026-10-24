<?php
namespace Espo\Modules\ManagementAngajati\Classes\RecordHooks\CerereConcediu;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\CreateParams;
use Espo\Core\Record\Hook\CreateHook;
use Espo\ORM\Entity;
/** @implements CreateHook<Entity> */
class BlocheazaCreareaDirecta implements CreateHook
{
    public function process(Entity $inregistrare, CreateParams $parametri): void
    {
        throw Forbidden::createWithBody('operatieDoarPrinFluxConcedii', Body::create()->withMessageTranslation('operatieDoarPrinFluxConcedii', 'CerereConcediu'));
    }
}
