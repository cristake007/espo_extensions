<?php

namespace Espo\Modules\ManagementAngajati\Instrumente\Concedii;

use Espo\Core\Acl;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Field\LinkParent;
use Espo\Core\FileStorage\Manager as ManagerStocare;
use Espo\Entities\Attachment;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** Genereaza idempotent si stocheaza documentele prin Attachment/FileStorage native. */
class DocumenteConcediu
{
    private const string TIP_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    private const string VERSIUNE_SABLON = 'ManagementAngajati:cerere-concediu:v4';

    public function __construct(
        private EntityManager $managerEntitati,
        private ManagerStocare $stocare,
        private Acl $acl,
        private GeneratorDocumentConcediu $generator,
        private InstantaneuDocumenteConcediu $instantaneuDocumente,
    ) {}

    /** @return array{documente: list<array{id: string, nume: string, url: string}>} */
    public function genereaza(string $idCerere): array
    {
        return $this->managerEntitati->getTransactionManager()->run(
            fn (): array => $this->genereazaCuCerereaBlocata($idCerere)
        );
    }

    /** @return array{documente: list<array{id: string, nume: string, url: string}>} */
    private function genereazaCuCerereaBlocata(string $idCerere): array
    {
        $cerere = $this->managerEntitati->getRDBRepository('CerereConcediu')
            ->where(['id' => $idCerere])
            ->forUpdate()
            ->findOne();
        if (!$cerere) {
            throw new NotFound();
        }
        if (!$this->acl->checkEntityRead($cerere)) {
            throw new Forbidden();
        }
        if (!in_array($cerere->get('stare'), ['Aprobata', 'AnulareSolicitata'], true)) {
            throw Conflict::createWithBody(
                'documentDoarPentruCerereAprobata',
                Body::create()->withMessageTranslation(
                    'documentDoarPentruCerereAprobata',
                    'CerereConcediu'
                )
            );
        }
        $instantaneu = $cerere->get('instantaneuDocumente');
        if (!is_object($instantaneu) && !is_array($instantaneu)) {
            throw Conflict::createWithBody(
                'instantaneuDocumenteLipsa',
                Body::create()->withMessageTranslation('instantaneuDocumenteLipsa', 'CerereConcediu')
            );
        }
        $instantaneu = is_object($instantaneu) ? get_object_vars($instantaneu) : $instantaneu;
        if (!is_string($instantaneu['codVerificare'] ?? null) ||
            $instantaneu['codVerificare'] === '') {
            $instantaneu['codVerificare'] = strtoupper(bin2hex(random_bytes(16)));
            $cerere->set('instantaneuDocumente', $instantaneu);
            $this->managerEntitati->saveEntity($cerere);
        }
        $instantaneu = $this->instantaneuDocumente
            ->pregatesteInstantaneuVechiPentruDocument($instantaneu);
        $existente = $this->documenteExistente($cerere);

        foreach ((array) ($instantaneu['segmente'] ?? []) as $segmentBrut) {
            $segment = is_object($segmentBrut) ? get_object_vars($segmentBrut) : $segmentBrut;
            if (!is_array($segment) || !is_string($segment['luna'] ?? null)) {
                continue;
            }
            $luna = $segment['luna'];
            if (isset($existente[$luna])) {
                if ($existente[$luna]->get('description') === self::VERSIUNE_SABLON) {
                    continue;
                }
                $this->managerEntitati->removeEntity($existente[$luna]);
                unset($existente[$luna]);
            }
            $continut = $this->generator->genereaza($instantaneu, $segment);
            $atasament = $this->managerEntitati->getRDBRepositoryByClass(Attachment::class)->getNew();
            $numeLuna = $this->numeLuna($luna);
            $atasament
                ->setName('Cerere concediu - ' . $numeLuna . '.docx')
                ->setRole(Attachment::ROLE_ATTACHMENT)
                ->setType(self::TIP_DOCX)
                ->setSize(strlen($continut))
                ->setTargetField('documenteConcediu')
                ->setParent(LinkParent::create($cerere->getEntityType(), $cerere->getId()));
            $atasament->set([
                'contents' => $continut,
                'description' => self::VERSIUNE_SABLON,
            ]);
            $this->managerEntitati->saveEntity($atasament);
            $existente[$luna] = $atasament;
        }

        ksort($existente);
        return ['documente' => array_values(array_map(
            fn (Attachment $atasament): array => [
                'id' => $atasament->getId(),
                'nume' => (string) $atasament->getName(),
                'url' => 'api/v1/Attachment/file/' . $atasament->getId(),
            ],
            $existente
        ))];
    }

    /** Elimina documentele prin fluxul nativ Attachment, inclusiv continutul din storage. */
    public function stergePentruCerere(Entity $cerere): void
    {
        $atasamente = $this->managerEntitati->getRDBRepositoryByClass(Attachment::class)
            ->where([
                'parentType' => 'CerereConcediu',
                'parentId' => $cerere->getId(),
                'field' => 'documenteConcediu',
            ])
            ->find();

        foreach ($atasamente as $atasament) {
            if ($atasament instanceof Attachment) {
                $this->managerEntitati->removeEntity($atasament);
            }
        }
    }

    /** @return array<string, Attachment> */
    private function documenteExistente(Entity $cerere): array
    {
        $rezultat = [];
        foreach ($this->managerEntitati->getRDBRepositoryByClass(Attachment::class)->where([
            'parentType' => 'CerereConcediu',
            'parentId' => $cerere->getId(),
            'field' => 'documenteConcediu',
        ])->find() as $atasament) {
            if (!$atasament instanceof Attachment || !$this->stocare->exists($atasament)) {
                continue;
            }
            if (preg_match('/(ianuarie|februarie|martie|aprilie|mai|iunie|iulie|august|septembrie|octombrie|noiembrie|decembrie) (\d{4})\.docx$/', (string) $atasament->getName(), $potrivire)) {
                $luni = array_flip([1 => 'ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie',
                    'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie']);
                $luna = $potrivire[2] . '-' . sprintf('%02d', $luni[$potrivire[1]]);
                $rezultat[$luna] = $atasament;
            }
        }
        return $rezultat;
    }

    private function numeLuna(string $luna): string
    {
        $luni = [1 => 'ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie',
            'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
        return ($luni[(int) substr($luna, 5, 2)] ?? $luna) . ' ' . substr($luna, 0, 4);
    }
}
