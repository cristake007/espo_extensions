<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Angajati;

use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Angajati\OrdonatorAngajati;
use PHPUnit\Framework\TestCase;

class OrdonatorAngajatiTest extends TestCase
{
    public function testOrdoneazaNaturalDupaAcelasiNumeAfisatInToatePaginile(): void
    {
        $angajat10 = $this->creeazaAngajat('10', 'angajat10', 'Angajat 10');
        $angajat2 = $this->creeazaAngajat('2', 'angajat2', 'Angajat 2');
        $angajat1 = $this->creeazaAngajat('1', 'angajat1', 'Angajat 1');

        $rezultat = (new OrdonatorAngajati())->ordoneaza([
            $angajat10,
            $angajat2,
            $angajat1,
        ]);

        self::assertSame(['1', '2', '10'], array_map(
            static fn (User $angajat): string => $angajat->getId(),
            $rezultat
        ));
    }

    public function testFolosesteNumeleDeUtilizatorCandNumeleAfisatLipseste(): void
    {
        $angajat = $this->creeazaAngajat('2', 'angajat2', '');
        $manager = $this->creeazaAngajat('1', 'manager', 'Manager Test');

        $rezultat = (new OrdonatorAngajati())->ordoneaza([$manager, $angajat]);

        self::assertSame(['2', '1'], array_map(
            static fn (User $utilizator): string => $utilizator->getId(),
            $rezultat
        ));
    }

    private function creeazaAngajat(string $id, string $numeUtilizator, string $nume): User
    {
        $angajat = $this->createStub(User::class);
        $angajat->method('getId')->willReturn($id);
        $angajat->method('getName')->willReturn($nume);
        $angajat
            ->method('get')
            ->willReturnCallback(
                static fn (string $camp): ?string =>
                    $camp === 'userName' ? $numeUtilizator : null
            );

        return $angajat;
    }
}
