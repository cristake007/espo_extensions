<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Dependente;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Extension;
use Espo\ORM\EntityManager;

/** Verifica instalarea si contractul de integrare oferit de extensia ZileSarbatoare. */
class VerificatorZileSarbatoare
{
    public const string NUME_EXTENSIE = 'Zile Sărbătoare';
    public const string VERSIUNE_MINIMA = '0.9.5';
    public const string TIP_ENTITATE = 'ZileLibere';

    public function __construct(
        private EntityManager $managerEntitati,
        private Metadata $metadate,
    ) {}

    public function esteStructuraDisponibila(): bool
    {
        return (bool) $this->metadate->get([
            'entityDefs',
            self::TIP_ENTITATE,
            'fields',
            'dateStart',
        ]) && (bool) $this->metadate->get([
            'entityDefs',
            self::TIP_ENTITATE,
            'fields',
            'countryCode',
        ]);
    }

    public function obtineVersiuneInstalata(): ?string
    {
        $extensie = $this->managerEntitati
            ->getRDBRepositoryByClass(Extension::class)
            ->where([
                'name' => self::NUME_EXTENSIE,
                'isInstalled' => true,
            ])
            ->findOne();

        if (!$extensie instanceof Extension) {
            return null;
        }

        $versiune = $extensie->getVersion();

        return $versiune !== '' ? $versiune : null;
    }

    public function esteInstalataCompatibil(): bool
    {
        $versiune = $this->obtineVersiuneInstalata();

        return $versiune !== null &&
            version_compare($versiune, self::VERSIUNE_MINIMA, '>=') &&
            $this->esteStructuraDisponibila();
    }

    /** @throws BadRequest */
    public function verificaDisponibilitateSauArunca(): void
    {
        $versiune = $this->obtineVersiuneInstalata();

        if (
            $this->esteStructuraDisponibila() &&
            ($versiune === null || version_compare($versiune, self::VERSIUNE_MINIMA, '>='))
        ) {
            return;
        }

        throw BadRequest::createWithBody(
            'extensieZileSarbatoareIndisponibila',
            Body::create()->withMessageTranslation(
                'extensieZileSarbatoareIndisponibila',
                'Prezenta'
            )
        );
    }
}
