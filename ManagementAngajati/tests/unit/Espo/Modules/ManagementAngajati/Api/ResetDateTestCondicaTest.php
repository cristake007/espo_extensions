<?php

namespace tests\unit\Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\ManagementAngajati\Api\ObtinePrevizualizareResetCondica;
use Espo\Modules\ManagementAngajati\Api\ReseteazaDateTestCondica;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\ResetatorDateTestCondica;
use PHPUnit\Framework\TestCase;

class ResetDateTestCondicaTest extends TestCase
{
    public function testPrevizualizareaNecesitaGestionareAngajati(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('checkScope')->willReturn(false);
        $resetator = $this->createMock(ResetatorDateTestCondica::class);
        $resetator->expects(self::never())->method('previzualizeaza');

        $this->expectException(Forbidden::class);
        (new ObtinePrevizualizareResetCondica($acl, $resetator))->process(
            $this->createStub(Request::class)
        );
    }

    public function testResetareaRespingeExpresiaInvalida(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('checkScope')->willReturn(true);
        $resetator = $this->createMock(ResetatorDateTestCondica::class);
        $resetator->expects(self::never())->method('reseteaza');
        $cerere = $this->createStub(Request::class);
        $cerere->method('getParsedBody')->willReturn((object) ['confirmare' => 'reset']);

        $this->expectException(BadRequest::class);
        (new ReseteazaDateTestCondica($acl, $resetator))->process($cerere);
    }

    public function testResetareaAcceptaDoarExpresiaExacta(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('checkScope')->willReturn(true);
        $resetator = $this->createMock(ResetatorDateTestCondica::class);
        $resetator
            ->expects(self::once())
            ->method('reseteaza')
            ->willReturn([
                'sterse' => ['prezente' => 10],
                'dupaResetare' => ['prezente' => 0],
            ]);
        $cerere = $this->createStub(Request::class);
        $cerere->method('getParsedBody')->willReturn((object) [
            'confirmare' => ResetatorDateTestCondica::CONFIRMARE,
        ]);

        $raspuns = (new ReseteazaDateTestCondica($acl, $resetator))->process($cerere);
        $date = json_decode((string) $raspuns->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(0, $date['dupaResetare']['prezente']);
    }
}
