<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Hooks\ConfiguratieCondicaAngajat;

use Espo\Core\FieldValidation\FieldValidationManager;
use Espo\Modules\ManagementAngajati\Hooks\ConfiguratieCondicaAngajat\Valideaza;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ValidatorConfiguratieCondicaAngajat;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use PHPUnit\Framework\TestCase;

class ValideazaTest extends TestCase
{
    public function testAplicaValidareaNativaInainteaRegulilorDeBusiness(): void
    {
        $configuratie = $this->createStub(Entity::class);
        $ordine = [];
        $managerValidare = $this->createMock(FieldValidationManager::class);
        $validator = $this->createMock(ValidatorConfiguratieCondicaAngajat::class);
        $managerValidare
            ->expects(self::once())
            ->method('process')
            ->with($configuratie)
            ->willReturnCallback(static function () use (&$ordine): void {
                $ordine[] = 'nativ';
            });
        $validator
            ->expects(self::once())
            ->method('valideaza')
            ->with($configuratie)
            ->willReturnCallback(static function () use (&$ordine): void {
                $ordine[] = 'business';
            });

        (new Valideaza($managerValidare, $validator))->beforeSave(
            $configuratie,
            SaveOptions::fromAssoc([])
        );

        self::assertSame(['nativ', 'business'], $ordine);
    }
}
