<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\HolidayDocument;

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
     *   carryover?: string,
     *   carryoverYear?: string,
     *   balanceBefore: string,
     *   balanceAfter: string,
     *   documentDate: string,
     *   verificationCode?: string
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
            $this->setCellText($xpath, $rows, 8, 0, 'Total zile');
            $this->setCellText($xpath, $rows, 8, 1, 'Deja primite');
            $this->setCellText($xpath, $rows, 8, 2, 'Au rămas');
            $this->setCellText($xpath, $rows, 8, 3, 'Zile cerute');
            $this->setCellText($xpath, $rows, 8, 4, 'Rest zile');
            $carryover = (float) ($values['carryover'] ?? '0');
            $carryoverYear = $values['carryoverYear'] ?? (string) ((int) $values['year'] - 1);
            $this->setTotalDays(
                $document,
                $xpath,
                $rows,
                $values['totalDays'] . ' - ' . $values['year'],
                $carryover > 0.0 ? ($values['carryover'] . ' - report ' . $carryoverYear) : null,
            );
            $this->setParagraph($xpath, $rows, 9, 1, 0, $values['daysAlreadyUsed']);
            $this->setParagraph($xpath, $rows, 9, 2, 0, $values['balanceBefore']);
            $this->setParagraph($xpath, $rows, 9, 3, 0, $values['requestedDays']);
            $this->setParagraph($xpath, $rows, 9, 4, 0, $values['balanceAfter']);
            $this->setParagraph($xpath, $rows, 10, 0, 1, $values['documentDate']);

            if (isset($values['verificationCode']) && $values['verificationCode'] !== '') {
                $this->setVerificationLine($document, $xpath, $values['verificationCode']);
            }

            if (!$zip->addFromString('word/document.xml', $document->saveXML())) {
                throw new RuntimeException('The holiday request DOCX could not be written.');
            }
        } finally {
            $zip->close();
        }
    }

    private function setCellText(
        DOMXPath $xpath,
        DOMNodeList $rows,
        int $rowIndex,
        int $cellIndex,
        string $value,
    ): void {
        $row = $rows->item($rowIndex);
        $cells = $row ? $xpath->query('./w:tc', $row) : false;
        $cell = $cells instanceof DOMNodeList ? $cells->item($cellIndex) : null;
        $textNodes = $cell ? $xpath->query('.//w:t', $cell) : false;

        if (!$textNodes instanceof DOMNodeList || $textNodes->length === 0) {
            throw new RuntimeException(sprintf(
                'The holiday request DOCX cell target %d/%d is missing.',
                $rowIndex,
                $cellIndex,
            ));
        }

        $textNodes->item(0)->nodeValue = $value;

        for ($index = 1; $index < $textNodes->length; $index++) {
            $textNodes->item($index)->nodeValue = '';
        }
    }

    private function setTotalDays(
        DOMDocument $document,
        DOMXPath $xpath,
        DOMNodeList $rows,
        string $annualLine,
        ?string $carryoverLine,
    ): void {
        $this->setParagraph($xpath, $rows, 9, 0, 0, $annualLine);

        if ($carryoverLine === null) {
            return;
        }

        $row = $rows->item(9);
        $cells = $row ? $xpath->query('./w:tc', $row) : false;
        $cell = $cells instanceof DOMNodeList ? $cells->item(0) : null;
        $paragraphs = $cell ? $xpath->query('./w:p', $cell) : false;
        $paragraph = $paragraphs instanceof DOMNodeList ? $paragraphs->item(0) : null;

        if (!$paragraph instanceof DOMElement) {
            throw new RuntimeException('The holiday request DOCX total-days target is missing.');
        }

        $run = $document->createElementNS(self::WORD_NAMESPACE, 'w:r');
        $firstRun = $xpath->query('./w:r', $paragraph)?->item(0);
        $runProperties = $firstRun ? $xpath->query('./w:rPr', $firstRun)?->item(0) : null;

        if ($runProperties) {
            $run->appendChild($runProperties->cloneNode(true));
        }

        $run->appendChild($document->createElementNS(self::WORD_NAMESPACE, 'w:br'));
        $text = $document->createElementNS(self::WORD_NAMESPACE, 'w:t');
        $text->nodeValue = $carryoverLine;
        $run->appendChild($text);
        $paragraph->appendChild($run);
    }

    private function setVerificationLine(
        DOMDocument $document,
        DOMXPath $xpath,
        string $code,
    ): void
    {
        if (!preg_match('/^[A-F0-9]{32}$/D', $code)) {
            throw new RuntimeException('The holiday document verification code is invalid.');
        }

        $table = $xpath->query('//w:body/w:tbl[1]')?->item(0);

        if (!$table instanceof DOMElement || !$table->parentNode instanceof DOMElement) {
            throw new RuntimeException('The holiday request DOCX main table is missing.');
        }

        $next = $table->nextSibling;

        while ($next && $next->nodeType !== XML_ELEMENT_NODE) {
            $next = $next->nextSibling;
        }

        $paragraph = $next instanceof DOMElement &&
            $next->namespaceURI === self::WORD_NAMESPACE &&
            $next->localName === 'p' &&
            $xpath->query('./*[not(self::w:pPr)]', $next)?->length === 0
                ? $next
                : $document->createElementNS(self::WORD_NAMESPACE, 'w:p');

        if (!$paragraph->parentNode) {
            $table->parentNode->insertBefore($paragraph, $next);
        }

        $paragraphProperties = $xpath->query('./w:pPr', $paragraph)?->item(0);

        if (!$paragraphProperties instanceof DOMElement) {
            $paragraphProperties = $document->createElementNS(self::WORD_NAMESPACE, 'w:pPr');
            $paragraph->insertBefore($paragraphProperties, $paragraph->firstChild);
        }

        $alignment = $xpath->query('./w:jc', $paragraphProperties)?->item(0);

        if (!$alignment instanceof DOMElement) {
            $alignment = $document->createElementNS(self::WORD_NAMESPACE, 'w:jc');
            $paragraphProperties->appendChild($alignment);
        }

        $alignment->setAttributeNS(self::WORD_NAMESPACE, 'w:val', 'right');

        $spacing = $xpath->query('./w:spacing', $paragraphProperties)?->item(0);

        if (!$spacing instanceof DOMElement) {
            $spacing = $document->createElementNS(self::WORD_NAMESPACE, 'w:spacing');
            $paragraphProperties->appendChild($spacing);
        }

        $spacing->setAttributeNS(self::WORD_NAMESPACE, 'w:before', '40');
        $spacing->setAttributeNS(self::WORD_NAMESPACE, 'w:after', '0');

        $run = $document->createElementNS(self::WORD_NAMESPACE, 'w:r');
        $runProperties = $document->createElementNS(self::WORD_NAMESPACE, 'w:rPr');
        $color = $document->createElementNS(self::WORD_NAMESPACE, 'w:color');
        $color->setAttributeNS(self::WORD_NAMESPACE, 'w:val', '000000');
        $runProperties->appendChild($color);
        $size = $document->createElementNS(self::WORD_NAMESPACE, 'w:sz');
        $size->setAttributeNS(self::WORD_NAMESPACE, 'w:val', '16');
        $runProperties->appendChild($size);
        $complexSize = $document->createElementNS(self::WORD_NAMESPACE, 'w:szCs');
        $complexSize->setAttributeNS(self::WORD_NAMESPACE, 'w:val', '16');
        $runProperties->appendChild($complexSize);
        $run->appendChild($runProperties);
        $text = $document->createElementNS(self::WORD_NAMESPACE, 'w:t');
        $text->nodeValue = 'Cod de verificare: ' . $code;
        $run->appendChild($text);
        $paragraph->appendChild($run);
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
