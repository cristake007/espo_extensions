<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Modules\ManagementAngajati\Instrumente\Concedii\GeneratorDocumentConcediu;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class GeneratorDocumentConcediuTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 7) .
            '/src/files/custom/Espo/Modules/ManagementAngajati/vendor/autoload.php';
    }

    public function testGenereazaUnDocxA4CuDateleInstantaneului(): void
    {
        $continut = (new GeneratorDocumentConcediu())->genereaza([
            'emisLa' => '2026-10-23',
            'codVerificare' => 'ABCDEF0123456789ABCDEF0123456789',
            'angajat' => ['nume' => 'Popescu', 'prenume' => 'Ana'],
            'aprobatori' => ['Manager Unu', 'Manager Doi'],
        ], [
            'luna' => '2027-01',
            'dataInceput' => '2027-01-04',
            'dataSfarsit' => '2027-01-06',
            'an' => 2027,
            'numarLuna' => 1,
            'zileSolicitate' => 3,
            'zileAnuale' => 21,
            'zileConsumate' => 0,
            'zileReportate' => 4,
            'soldInainte' => 25,
            'soldDupa' => 22,
        ]);

        self::assertStringStartsWith('PK', $continut);

        $cale = tempnam(sys_get_temp_dir(), 'test-docx-');
        self::assertNotFalse($cale);
        file_put_contents($cale, $continut);
        $arhiva = new ZipArchive();

        try {
            self::assertTrue($arhiva->open($cale));
            $xml = $arhiva->getFromName('word/document.xml');
            self::assertIsString($xml);
            self::assertStringContainsString('<w:t>Cerere</w:t>', $xml);
            self::assertStringContainsString('<w:t>concediu</w:t>', $xml);
            self::assertStringContainsString('Popescu', $xml);
            self::assertStringContainsString('Ana', $xml);
            self::assertStringContainsString('Manager Unu', $xml);
            self::assertStringContainsString('21 - 2027', $xml);
            self::assertStringContainsString('4 - report 2026', $xml);
            self::assertStringContainsString('04.01.2027', $xml);
            self::assertStringContainsString('06.01.2027', $xml);
            self::assertStringContainsString(
                'Cod de verificare: ABCDEF0123456789ABCDEF0123456789',
                $xml
            );
            self::assertStringContainsString('<w:pgSz w:w="11906" w:h="16838"/>', $xml);
            self::assertStringContainsString('w:w="9067"', $xml);
            self::assertStringNotContainsString('${', $xml);
        } finally {
            $arhiva->close();
            unlink($cale);
        }
    }

    public function testNuAfiseazaDreptulAnualInainteDeDataAcordarii(): void
    {
        $continut = (new GeneratorDocumentConcediu())->genereaza([
            'emisLa' => '2026-10-23',
            'codVerificare' => 'ABCDEF0123456789ABCDEF0123456789',
            'angajat' => ['nume' => 'Popescu', 'prenume' => 'Ana'],
            'aprobatori' => ['Manager Unu', 'Manager Doi'],
        ], [
            'dataInceput' => '2027-01-04',
            'dataSfarsit' => '2027-01-08',
            'an' => 2027,
            'numarLuna' => 1,
            'zileSolicitate' => 3,
            'zileAnuale' => 0,
            'zileConsumate' => 0,
            'zileReportate' => 16,
            'soldInainte' => 16,
            'soldDupa' => 13,
        ]);

        $cale = tempnam(sys_get_temp_dir(), 'test-docx-');
        self::assertNotFalse($cale);
        file_put_contents($cale, $continut);
        $arhiva = new ZipArchive();

        try {
            self::assertTrue($arhiva->open($cale));
            $xml = $arhiva->getFromName('word/document.xml');
            self::assertIsString($xml);
            self::assertStringContainsString('16 - report 2026', $xml);
            self::assertStringNotContainsString('21 - 2027', $xml);
            self::assertStringNotContainsString('0 - 2027', $xml);
            self::assertStringNotContainsString('${', $xml);
        } finally {
            $arhiva->close();
            unlink($cale);
        }
    }
}
