<?php

namespace Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\Attachment;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;
use ZipArchive;

class WordComparisonService
{
    private const MAX_WORD_BYTES = 20971520;
    private const MAX_SCHEDULE_ROWS = 5000;
    private const MAX_TEXT_LENGTH = 1000;

    /**
     * Below this score, two titles are treated as unrelated courses rather than a
     * renamed/retyped version of the same course. Titles that share most of their
     * wording but differ in a standard/code number (see
     * {@see self::applyCodeMismatchPenalty()}) are pushed well below this floor, so
     * it can sit close to typical "same course, retyped title" scores without
     * pairing two genuinely different courses that happen to share a template
     * sentence.
     */
    private const MATCH_FLOOR = 82.0;

    /** Below this score a candidate is not worth offering as a manual quick-pick. */
    private const CANDIDATE_FLOOR = 20.0;
    private const MAX_CANDIDATES = 5;

    public function __construct(
        private EntityManager $entityManager,
        private FileStorageManager $fileStorageManager
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function compare(string $id, string $entityType = 'GeneratorPerioadeCursuriWordComparator'): array
    {
        $record = $this->entityManager->getEntityById($entityType, $id);

        if (!$record) {
            throw new BadRequest('Inregistrarea nu a fost gasita.');
        }

        $wordTemplateId = $record->get('wordTemplateFileId');
        $scheduleFileId = $record->get('wordScheduleFileId');

        if (!is_string($wordTemplateId) || $wordTemplateId === '') {
            throw new BadRequest('Incarca documentul Word inainte de comparare.');
        }

        if (!is_string($scheduleFileId) || $scheduleFileId === '') {
            throw new BadRequest('Incarca fisierul Excel cu cursurile de pe site inainte de comparare.');
        }

        /** @var ?Attachment $wordAttachment */
        $wordAttachment = $this->entityManager->getEntityById(Attachment::ENTITY_TYPE, $wordTemplateId);
        /** @var ?Attachment $scheduleAttachment */
        $scheduleAttachment = $this->entityManager->getEntityById(Attachment::ENTITY_TYPE, $scheduleFileId);

        if (!$wordAttachment) {
            throw new BadRequest('Documentul Word nu a fost gasit.');
        }

        if (!$scheduleAttachment) {
            throw new BadRequest('Fisierul Excel nu a fost gasit.');
        }

        $wordRows = $this->readWordRows($this->fileStorageManager->getContents($wordAttachment));
        $excelRows = $this->readExcelRows($this->fileStorageManager->getContents($scheduleAttachment));

        return $this->buildReview($wordRows, $excelRows);
    }

    /**
     * The algorithm still proposes its single best-scoring Excel row per Word row
     * (via the same global greedy assignment as before, gated by
     * {@see self::MATCH_FLOOR}), but the pairing is no longer final: every Word
     * row is sent with its suggested selection, a short list of alternate
     * candidates, and the full Excel course list, so the browser can render an
     * editable dropdown per row (mirroring the Word Matcher's review workflow).
     * The actual duration/price/title diff is computed client-side from whichever
     * Excel row is currently selected, so correcting a wrong guess never needs a
     * second request.
     *
     * @param array<int, array{title: string, normalizedTitle: string, duration: string, price: string}> $wordRows
     * @param array<int, array{rowIndex: int, title: string, normalizedTitle: string, duration: string, price: string}> $excelRows
     * @return array<string, mixed>
     */
    private function buildReview(array $wordRows, array $excelRows): array
    {
        $pairs = [];

        foreach ($wordRows as $wordIndex => $wordRow) {
            if ($wordRow['normalizedTitle'] === '') {
                continue;
            }

            foreach ($excelRows as $excelIndex => $excelRow) {
                $score = $this->pairScore($wordRow['normalizedTitle'], $excelRow['normalizedTitle']);

                if ($score <= 0.0) {
                    continue;
                }

                $pairs[] = ['wordIndex' => $wordIndex, 'excelIndex' => $excelIndex, 'score' => $score];
            }
        }

        usort($pairs, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $matches = [];
        $matchedExcelIndexes = [];

        foreach ($pairs as $pair) {
            if ($pair['score'] < self::MATCH_FLOOR) {
                break;
            }

            if (isset($matches[$pair['wordIndex']]) || isset($matchedExcelIndexes[$pair['excelIndex']])) {
                continue;
            }

            $matches[$pair['wordIndex']] = $pair['excelIndex'];
            $matchedExcelIndexes[$pair['excelIndex']] = true;
        }

        $candidatesByWord = [];

        foreach ($pairs as $pair) {
            if ($pair['score'] < self::CANDIDATE_FLOOR) {
                continue;
            }

            $candidatesByWord[$pair['wordIndex']][] = $pair;
        }

        $wordRowsPayload = [];

        foreach ($wordRows as $wordIndex => $wordRow) {
            $candidates = array_slice($candidatesByWord[$wordIndex] ?? [], 0, self::MAX_CANDIDATES);

            $wordRowsPayload[] = [
                'wordIndex' => $wordIndex,
                'title' => $wordRow['title'],
                'duration' => $wordRow['duration'],
                'price' => $wordRow['price'],
                'selectedExcelIndex' => $matches[$wordIndex] ?? null,
                'candidates' => array_map(
                    fn (array $pair): array => [
                        'excelIndex' => $pair['excelIndex'],
                        'title' => $excelRows[$pair['excelIndex']]['title'],
                        'score' => round($pair['score'], 1),
                    ],
                    $candidates
                ),
            ];
        }

        $excelOptionsPayload = [];

        foreach ($excelRows as $excelIndex => $excelRow) {
            $excelOptionsPayload[] = [
                'excelIndex' => $excelIndex,
                'title' => $excelRow['title'],
                'duration' => $excelRow['duration'],
                'price' => $excelRow['price'],
            ];
        }

        return [
            'success' => true,
            'wordRows' => $wordRowsPayload,
            'excelOptions' => $excelOptionsPayload,
        ];
    }

    private function pairScore(string $wordNormalized, string $excelNormalized): float
    {
        if ($wordNormalized === $excelNormalized) {
            return 100.0;
        }

        $score = $this->combinedTitleScore($wordNormalized, $excelNormalized);

        if ($this->isSignificantContainment($wordNormalized, $excelNormalized)) {
            $score = max($score, 90.0);
        }

        return $this->applyCodeMismatchPenalty($score, $wordNormalized, $excelNormalized);
    }

    /**
     * One title sometimes carries extra detail the other doesn't - a Word row
     * spells out "(ISO 14064-1)" while the website keeps the short form, or the
     * other way around. The raw blended score penalizes that length difference
     * even though it is clearly the same course, so when the shorter title's
     * tokens are (almost) entirely contained in the longer one - and there are
     * enough of them to not be a coincidental generic phrase - the pair is scored
     * as a strong match instead.
     */
    private function isSignificantContainment(string $wordNormalized, string $excelNormalized): bool
    {
        $wordTokens = array_values(array_filter(explode(' ', $wordNormalized)));
        $excelTokens = array_values(array_filter(explode(' ', $excelNormalized)));

        if (min(count($wordTokens), count($excelTokens)) < 3) {
            return false;
        }

        $wordCoverage = $this->tokenCoverage($wordNormalized, $excelNormalized);
        $scheduleCoverage = $this->tokenCoverage($excelNormalized, $wordNormalized);

        return max($wordCoverage, $scheduleCoverage) >= 95.0;
    }

    /**
     * Course titles in this catalog often embed a standard/code number (e.g. "ISO
     * 9001"). Two titles can otherwise look very similar ("Auditor intern ISO
     * 9001" vs "Auditor intern ISO 14001") while actually naming two different
     * courses. When both titles contain a code-like number and none of them are
     * shared, the similarity score is halved so an unrelated course with a
     * similarly worded title does not outrank (or wrongly clear the floor for) the
     * genuine match.
     */
    private function applyCodeMismatchPenalty(float $score, string $wordNormalized, string $excelNormalized): float
    {
        $wordCodes = $this->standardCodeTokens($wordNormalized);
        $excelCodes = $this->standardCodeTokens($excelNormalized);

        if ($wordCodes === [] || $excelCodes === [] || array_intersect($wordCodes, $excelCodes) !== []) {
            return $score;
        }

        return $score * 0.5;
    }

    /**
     * @return string[]
     */
    private function standardCodeTokens(string $value): array
    {
        preg_match_all('/\b\d{4,5}\b/', $value, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    /**
     * @return array<int, array{title: string, normalizedTitle: string, duration: string, price: string}>
     */
    private function readWordRows(string $wordBytes): array
    {
        if ($wordBytes === '' || strlen($wordBytes) > self::MAX_WORD_BYTES || !str_starts_with($wordBytes, 'PK')) {
            throw new BadRequest('Documentul Word nu are o structura valida.');
        }

        $path = tempnam(sys_get_temp_dir(), 'generator-perioade-cursuri-word-compare-');

        if ($path === false) {
            throw new BadRequest('Documentul Word nu a putut fi citit.');
        }

        file_put_contents($path, $wordBytes);

        $zip = new ZipArchive();
        $zipOpen = false;

        try {
            if ($zip->open($path) !== true) {
                throw new BadRequest('Documentul Word nu a putut fi citit.');
            }

            $zipOpen = true;
            $documentXml = $zip->getFromName('word/document.xml');

            if (!is_string($documentXml) || $documentXml === '') {
                throw new BadRequest('Documentul Word nu contine continut editabil.');
            }

            $document = new DOMDocument();
            $document->preserveWhiteSpace = false;

            if (!$document->loadXML($documentXml)) {
                throw new BadRequest('Documentul Word nu a putut fi citit.');
            }

            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            $rows = $this->getWordRows($xpath);

            if ($rows === []) {
                throw new BadRequest('Documentul Word nu contine randuri de curs compatibile.');
            }

            return $rows;
        } finally {
            if ($zipOpen) {
                $zip->close();
            }

            @unlink($path);
        }
    }

    /**
     * @return array<int, array{title: string, normalizedTitle: string, duration: string, price: string}>
     */
    private function getWordRows(DOMXPath $xpath): array
    {
        $rows = [];
        $tableRows = $xpath->query('//w:tbl//w:tr');

        if (!$tableRows) {
            return [];
        }

        foreach ($tableRows as $tableRow) {
            if (!$tableRow instanceof DOMElement) {
                continue;
            }

            $cells = [];

            foreach ($tableRow->childNodes as $childNode) {
                if ($childNode instanceof DOMElement && $childNode->localName === 'tc') {
                    $cells[] = $childNode;
                }
            }

            if (count($cells) < 6 || $this->cellHasGridSpan($xpath, $cells[0])) {
                continue;
            }

            $title = trim($this->wordCellText($xpath, $cells[0]));

            if ($title === '' || mb_strlen($title) > self::MAX_TEXT_LENGTH) {
                continue;
            }

            $filledCells = 0;

            foreach ($cells as $cell) {
                if (trim($this->wordCellText($xpath, $cell)) !== '') {
                    $filledCells++;
                }
            }

            if ($filledCells <= 1) {
                continue;
            }

            $rows[] = [
                'title' => $title,
                'normalizedTitle' => $this->normalizeTitle($title),
                'duration' => trim($this->wordCellText($xpath, $cells[1])),
                'price' => trim($this->wordCellText($xpath, $cells[2])),
            ];
        }

        return $rows;
    }

    private function cellHasGridSpan(DOMXPath $xpath, DOMElement $cell): bool
    {
        $gridSpan = $xpath->query('./w:tcPr/w:gridSpan', $cell);

        return $gridSpan && $gridSpan->length > 0;
    }

    private function wordCellText(DOMXPath $xpath, DOMElement $cell): string
    {
        $texts = [];
        $nodes = $xpath->query('.//w:t', $cell);

        if (!$nodes) {
            return '';
        }

        foreach ($nodes as $node) {
            $texts[] = $node->textContent;
        }

        return implode('', $texts);
    }

    /**
     * @return array<int, array{rowIndex: int, title: string, normalizedTitle: string, duration: string, price: string}>
     */
    private function readExcelRows(string $contents): array
    {
        if ($contents === '' || !str_starts_with($contents, 'PK')) {
            throw new BadRequest('Fisierul Excel nu are o structura valida.');
        }

        $path = tempnam(sys_get_temp_dir(), 'generator-perioade-cursuri-word-compare-excel-');

        if ($path === false) {
            throw new BadRequest('Fisierul Excel nu a putut fi citit.');
        }

        try {
            file_put_contents($path, $contents);

            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getSheetByName('Program') ?? $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
        } catch (Throwable $e) {
            throw new BadRequest('Fisierul Excel nu a putut fi citit.');
        } finally {
            @unlink($path);
        }

        if ($rows === []) {
            throw new BadRequest('Fisierul Excel nu contine un antet.');
        }

        $header = array_map(fn ($value): string => $this->cellText($value), $rows[0]);
        $normalized = [];

        foreach ($header as $index => $name) {
            if ($name !== '') {
                $normalized[CourseTitleHeaderResolver::normalizeHeader($name)] = $index;
            }
        }

        try {
            $titleResolver = new CourseTitleHeaderResolver($header);
        } catch (CourseTitleHeaderException $e) {
            throw $this->titleHeaderBadRequest($e);
        }

        if (!$titleResolver->hasTitleHeader()) {
            throw new BadRequest('Fisierul Excel trebuie sa contina coloana cu numele cursului.');
        }

        if (!isset($normalized['durata curs'])) {
            throw new BadRequest('Fisierul Excel trebuie sa contina coloana "Durata Curs".');
        }

        if (!isset($normalized['investitie'])) {
            throw new BadRequest('Fisierul Excel trebuie sa contina coloana "Investitie".');
        }

        $durationIndex = $normalized['durata curs'];
        $priceIndex = $normalized['investitie'];
        $excelRows = [];

        foreach (array_slice($rows, 1) as $offset => $row) {
            if (count($excelRows) >= self::MAX_SCHEDULE_ROWS) {
                throw new BadRequest('Fisierul Excel poate contine cel mult 5000 cursuri.');
            }

            try {
                $title = $titleResolver->resolveTitle($row, $offset + 2);
            } catch (CourseTitleHeaderException $e) {
                throw $this->titleHeaderBadRequest($e);
            }

            $title = $this->decodeHtmlEntities($title);

            if ($title === '') {
                continue;
            }

            if (mb_strlen($title) > self::MAX_TEXT_LENGTH) {
                throw new BadRequest('Fisierul Excel contine un titlu prea lung.');
            }

            $excelRows[] = [
                'rowIndex' => $offset,
                'title' => $title,
                'normalizedTitle' => $this->normalizeTitle($title),
                'duration' => $this->cellText($row[$durationIndex] ?? ''),
                'price' => $this->cellText($row[$priceIndex] ?? ''),
            ];
        }

        if ($excelRows === []) {
            throw new BadRequest('Fisierul Excel nu contine cursuri valide.');
        }

        return $excelRows;
    }

    private function combinedTitleScore(string $wordTitle, string $scheduleTitle): float
    {
        return 0.45 * $this->similarity($wordTitle, $scheduleTitle) +
            0.25 * $this->tokenSortSimilarity($wordTitle, $scheduleTitle) +
            0.15 * $this->tokenCoverage($wordTitle, $scheduleTitle) +
            0.15 * $this->tokenCoverage($scheduleTitle, $wordTitle);
    }

    private function similarity(string $a, string $b): float
    {
        similar_text($a, $b, $percent);

        return $percent;
    }

    private function tokenSortSimilarity(string $a, string $b): float
    {
        $aTokens = explode(' ', $a);
        $bTokens = explode(' ', $b);
        sort($aTokens);
        sort($bTokens);

        return $this->similarity(implode(' ', $aTokens), implode(' ', $bTokens));
    }

    private function tokenCoverage(string $source, string $target): float
    {
        $sourceTokens = array_values(array_filter(explode(' ', $source)));
        $targetTokens = array_flip(array_values(array_filter(explode(' ', $target))));

        if ($sourceTokens === []) {
            return 0.0;
        }

        $matched = 0;

        foreach (array_unique($sourceTokens) as $token) {
            if (isset($targetTokens[$token])) {
                $matched++;
            }
        }

        return $matched / count(array_unique($sourceTokens)) * 100;
    }

    private function titleHeaderBadRequest(CourseTitleHeaderException $exception): BadRequest
    {
        if ($exception->getReason() === CourseTitleHeaderException::DUPLICATE_HEADER) {
            return new BadRequest(sprintf(
                'Fisierul Excel contine antetul duplicat "%s".',
                (string) $exception->getHeader()
            ));
        }

        return new BadRequest(sprintf(
            'Randul %d din fisierul Excel contine valori diferite pentru coloanele "title" si "nume curs".',
            (int) $exception->getSourceRow()
        ));
    }

    /**
     * The website export sometimes double-escapes titles (a literal "&" ends up
     * stored as "&amp;amp;"), which XML parsing only unwraps by one level,
     * leaving a literal "&amp;" in the value read back from the sheet. A single
     * html_entity_decode() call only strips one layer, leaving stray "amp" text
     * behind that pollutes matching and stays visible in the UI. Decoding
     * repeatedly until the value stops changing resolves any depth of
     * double-encoding while staying a no-op for already-clean titles.
     */
    private function decodeHtmlEntities(string $value): string
    {
        for ($i = 0; $i < 5; $i++) {
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($decoded === $value) {
                break;
            }

            $value = $decoded;
        }

        return $value;
    }

    private function normalizeTitle(string $value): string
    {
        $text = $this->decodeHtmlEntities(str_replace("\xc2\xa0", ' ', $value));
        $text = mb_strtolower(trim($text));
        $text = strtr($text, [
            'ă' => 'a',
            'â' => 'a',
            'î' => 'i',
            'ș' => 's',
            'ş' => 's',
            'ț' => 't',
            'ţ' => 't',
        ]);
        $text = preg_replace('/[^\p{L}\p{N}_\s]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function cellText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}
