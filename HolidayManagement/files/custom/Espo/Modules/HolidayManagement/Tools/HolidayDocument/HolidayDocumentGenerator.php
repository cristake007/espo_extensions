<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayDocument;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use RuntimeException;
use ZipArchive;

final class HolidayDocumentGenerator
{
    private const WORD_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * @param array{
     *   dateStart: string,
     *   dateEnd: string,
     *   year: string,
     *   month: string,
     *   requestedDays: string,
     *   lastName: string,
     *   firstName: string,
     *   approvalBlock1Title: string,
     *   approvalBlock1Name: string,
     *   approvalBlock2Title: string,
     *   approvalBlock2Name: string,
     *   totalDays: string,
     *   daysAlreadyUsed: string,
     *   balanceBefore: string,
     *   balanceAfter: string,
     *   documentDate: string
     * } $values
     */
    public function render(array $values): string
    {
        $templatePath = dirname(__DIR__, 2) . '/Resources/templates/holiday-request.docx';

        if (!is_file($templatePath)) {
            throw new RuntimeException('The holiday request DOCX template is missing.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'holiday-docx-');

        if ($temporaryPath === false || !copy($templatePath, $temporaryPath)) {
            throw new RuntimeException('The holiday request DOCX could not be initialized.');
        }

        try {
            $this->populate($temporaryPath, $values);
            $contents = file_get_contents($temporaryPath);

            if ($contents === false) {
                throw new RuntimeException('The generated holiday request DOCX could not be read.');
            }

            return $contents;
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /** @param array<string, string> $values */
    private function populate(string $path, array $values): void
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('The holiday request DOCX could not be opened.');
        }

        try {
            $xml = $zip->getFromName('word/document.xml');

            if ($xml === false) {
                throw new RuntimeException('The holiday request DOCX has no document body.');
            }

            $document = new DOMDocument();
            $document->preserveWhiteSpace = true;

            if (!$document->loadXML($xml)) {
                throw new RuntimeException('The holiday request DOCX body is invalid.');
            }

            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', self::WORD_NAMESPACE);
            $rows = $xpath->query('//w:tbl[1]/w:tr');

            if (!$rows instanceof DOMNodeList || $rows->length < 11) {
                throw new RuntimeException('The holiday request DOCX layout is not supported.');
            }

            $this->setParagraph($xpath, $rows, 2, 2, 0, 'De la: ' . $values['dateStart']);
            $this->setParagraph($xpath, $rows, 4, 0, 1, $values['year']);
            $this->setParagraph($xpath, $rows, 4, 1, 1, $values['month']);
            $this->setParagraph($xpath, $rows, 4, 3, 0, 'Pana la: ' . $values['dateEnd']);
            $this->setParagraph($xpath, $rows, 4, 4, 0, $values['requestedDays']);
            $this->setParagraph($xpath, $rows, 6, 0, 1, $values['lastName']);
            $this->setParagraph($xpath, $rows, 6, 1, 1, $values['firstName']);
            $this->setParagraph($xpath, $rows, 7, 5, 4, $values['approvalBlock1Title']);
            $this->setParagraph($xpath, $rows, 7, 5, 5, $values['approvalBlock1Name']);
            $this->setParagraph($xpath, $rows, 7, 5, 8, $values['approvalBlock2Title']);
            $this->setParagraph($xpath, $rows, 7, 5, 9, $values['approvalBlock2Name']);
            $this->setParagraph($xpath, $rows, 9, 0, 0, $values['totalDays'] . ' - ' . $values['year']);
            $this->setParagraph($xpath, $rows, 9, 1, 0, $values['daysAlreadyUsed']);
            $this->setParagraph($xpath, $rows, 9, 2, 0, $values['balanceBefore']);
            $this->setParagraph($xpath, $rows, 9, 3, 0, $values['requestedDays']);
            $this->setParagraph($xpath, $rows, 9, 4, 0, $values['balanceAfter']);
            $this->setParagraph($xpath, $rows, 10, 0, 1, $values['documentDate']);

            if (!$zip->addFromString('word/document.xml', $document->saveXML())) {
                throw new RuntimeException('The holiday request DOCX could not be written.');
            }
        } finally {
            $zip->close();
        }
    }

    private function setParagraph(
        DOMXPath $xpath,
        DOMNodeList $rows,
        int $rowIndex,
        int $cellIndex,
        int $paragraphIndex,
        string $value,
    ): void {
        $row = $rows->item($rowIndex);
        $cells = $row ? $xpath->query('./w:tc', $row) : false;
        $cell = $cells instanceof DOMNodeList ? $cells->item($cellIndex) : null;
        $paragraphs = $cell ? $xpath->query('./w:p', $cell) : false;
        $paragraph = $paragraphs instanceof DOMNodeList ? $paragraphs->item($paragraphIndex) : null;
        $textNodes = $paragraph ? $xpath->query('.//w:t', $paragraph) : false;

        if (!$textNodes instanceof DOMNodeList || $textNodes->length === 0) {
            throw new RuntimeException(sprintf(
                'The holiday request DOCX target %d/%d/%d is missing.',
                $rowIndex,
                $cellIndex,
                $paragraphIndex,
            ));
        }

        $first = $textNodes->item(0);

        if (!$first instanceof DOMElement) {
            throw new RuntimeException('The holiday request DOCX text target is invalid.');
        }

        $first->nodeValue = $value;

        for ($index = 1; $index < $textNodes->length; $index++) {
            $textNodes->item($index)->nodeValue = '';
        }
    }
}
