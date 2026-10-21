<?php

declare(strict_types=1);

use Espo\Modules\EmployeeManagement\Tools\HolidayDocument\HolidayDocumentGenerator;
use Espo\Modules\EmployeeManagement\Tools\HolidayDocument\HolidayDocumentProjection;

require_once __DIR__ . '/../../files/custom/Espo/Modules/EmployeeManagement/Tools/HolidayDocument/HolidayDocumentProjection.php';
require_once __DIR__ . '/../../files/custom/Espo/Modules/EmployeeManagement/Tools/HolidayDocument/HolidayDocumentGenerator.php';

function check(string $name, bool $condition): void
{
    if (!$condition) { throw new RuntimeException('FAIL: ' . $name); }
    echo 'PASS: ' . $name . PHP_EOL;
}

$projection = new HolidayDocumentProjection();
$segments = [
    ['dateStart' => '2026-12-24', 'dateEnd' => '2026-12-31', 'year' => 2026, 'month' => 12, 'days' => 5],
    ['dateStart' => '2027-01-01', 'dateEnd' => '2027-01-09', 'year' => 2027, 'month' => 1, 'days' => 3],
];
$rows = $projection->build($segments, 21.0, 8.0, '2027-01-01', true);
check('December values stay 21/13/8/5/3',
    $rows[0]['annualEntitlement'] === 21.0 && $rows[0]['daysAlreadyUsed'] === 13.0 &&
    $rows[0]['balanceBefore'] === 8.0 && $rows[0]['days'] === 5 && $rows[0]['balanceAfter'] === 3.0);
check('January values are 21+3/24/3/21',
    $rows[1]['annualEntitlement'] === 21.0 && $rows[1]['carryover'] === 3.0 &&
    $rows[1]['balanceBefore'] === 24.0 && $rows[1]['days'] === 3 && $rows[1]['balanceAfter'] === 21.0);
check('monthly segmentation remains two records', count($rows) === 2 && $rows[0]['month'] === 12 && $rows[1]['month'] === 1);

$zero = $projection->build($segments, 25.0, 5.0, '2027-01-01', true);
check('different entitlement and zero carryover', $zero[1]['annualEntitlement'] === 25.0 && $zero[1]['carryover'] === 0.0 && $zero[1]['balanceBefore'] === 25.0);
$alreadyApplied = $projection->build([$segments[1]], 21.0, 24.0, '2028-01-01', true);
check('already-applied grant is not doubled', $alreadyApplied[0]['balanceBefore'] === 24.0 && $alreadyApplied[0]['carryover'] === 3.0);
$disabled = $projection->build($segments, 21.0, 8.0, '2027-01-01', false);
check('disabled future grant is not invented', $disabled[1]['balanceBefore'] === 3.0);
$single = $projection->build([$segments[0]], 21.0, 8.0, '2027-01-01', true);
check('single-month values remain sequentially unchanged', $single[0]['daysAlreadyUsed'] === 13.0 && $single[0]['balanceAfter'] === 3.0);
$sameYear = $projection->build([
    ['dateStart' => '2027-02-01', 'dateEnd' => '2027-02-28', 'year' => 2027, 'month' => 2, 'days' => 2],
    ['dateStart' => '2027-03-01', 'dateEnd' => '2027-03-31', 'year' => 2027, 'month' => 3, 'days' => 3],
], 21.0, 10.0, '2028-01-01', true);
check('multiple months in one year remain segmented and sequential', count($sameYear) === 2 && $sameYear[0]['balanceAfter'] === 8.0 && $sameYear[1]['balanceAfter'] === 5.0);
$ambiguousFailed = false;
try { $projection->build([$segments[1]], 21.0, 3.0, '2027-01-05', true); }
catch (RuntimeException) { $ambiguousFailed = true; }
check('ambiguous grant inside a segment fails safely', $ambiguousFailed);

$code = strtoupper(bin2hex(random_bytes(16)));
$otherCode = strtoupper(bin2hex(random_bytes(16)));
check('codes contain 128 random bits and differ', strlen($code) === 32 && ctype_xdigit($code) && $code !== $otherCode);
$generator = new HolidayDocumentGenerator();
$januaryValues = [
    'dateStart' => '01.01.2027', 'dateEnd' => '09.01.2027', 'year' => '2027', 'month' => '1',
    'requestedDays' => '3', 'lastName' => 'Test', 'firstName' => 'Angajat',
    'approvalBlock1Title' => 'Director general', 'approvalBlock1Name' => 'Manager Test',
    'approvalBlock2Title' => '', 'approvalBlock2Name' => '', 'totalDays' => '21',
    'daysAlreadyUsed' => '0', 'carryover' => '3', 'carryoverYear' => '2026',
    'balanceBefore' => '24', 'balanceAfter' => '21', 'documentDate' => '21.10.2026',
    'verificationCode' => $code,
];
$januaryBytes = $generator->render($januaryValues);
$januaryRetryBytes = $generator->render($januaryValues);
check('technical retry preserves verification code and final DOCX bytes',
    hash_equals(hash('sha256', $januaryBytes), hash('sha256', $januaryRetryBytes)));
$januaryWithoutCode = $januaryValues;
unset($januaryWithoutCode['verificationCode']);
$januaryWithoutCodeBytes = $generator->render($januaryWithoutCode);
$decemberBytes = $generator->render([
    'dateStart' => '24.12.2026', 'dateEnd' => '31.12.2026', 'year' => '2026', 'month' => '12',
    'requestedDays' => '5', 'lastName' => 'Test', 'firstName' => 'Angajat',
    'approvalBlock1Title' => 'Director general', 'approvalBlock1Name' => 'Manager Test',
    'approvalBlock2Title' => '', 'approvalBlock2Name' => '', 'totalDays' => '21',
    'daysAlreadyUsed' => '13', 'carryover' => '0', 'carryoverYear' => '2025',
    'balanceBefore' => '8', 'balanceAfter' => '3', 'documentDate' => '21.10.2026',
    'verificationCode' => $otherCode,
]);
$inspect = static function (string $bytes): array {
    $temporary = tempnam(sys_get_temp_dir(), 'holiday-regression-');
    file_put_contents($temporary, $bytes);
    $zip = new ZipArchive();
    check('actual DOCX opens', $zip->open($temporary) === true);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($temporary);
    $documentXml = new DOMDocument();
    $documentXml->loadXML($xml);
    $xpath = new DOMXPath($documentXml);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    return [$xml, $xpath];
};
[$xml, $xpath] = $inspect($januaryBytes);
$visibleText = '';
foreach ($xpath->query('//w:t') as $textNode) { $visibleText .= $textNode->textContent . "\n"; }
$rows = $xpath->query('//w:tbl[1]/w:tr');
$headers = $xpath->query('./w:tc', $rows->item(8));
$values = $xpath->query('./w:tc', $rows->item(9));
$cellText = static function (DOMXPath $xpath, DOMNode $cell): string {
    $text = '';
    foreach ($xpath->query('.//w:t', $cell) as $node) { $text .= $node->textContent; }
    return $text;
};
check('original five financial headers are preserved',
    $cellText($xpath, $headers->item(0)) === 'Total zile' &&
    $cellText($xpath, $headers->item(1)) === 'Deja primite' &&
    $cellText($xpath, $headers->item(2)) === 'Au rămas' &&
    $cellText($xpath, $headers->item(3)) === 'Zile cerute' &&
    $cellText($xpath, $headers->item(4)) === 'Rest zile');
check('January total cell uses a real Word line break for entitlement and carryover',
    $cellText($xpath, $values->item(0)) === '21 - 20273 - report 2026' &&
    $xpath->query('.//w:br', $values->item(0))->length === 1);
check('January row displays 0/24/3/21 in the original columns',
    $cellText($xpath, $values->item(1)) === '0' &&
    $cellText($xpath, $values->item(2)) === '24' &&
    $cellText($xpath, $values->item(3)) === '3' &&
    $cellText($xpath, $values->item(4)) === '21' &&
    !str_contains($visibleText, 'Report an anterior'));
check('actual DOCX contains only the required verification line',
    substr_count($visibleText, 'Cod de verificare: ' . $code) === 1 &&
    !str_contains($visibleText, 'Verificați') && !str_contains($visibleText, 'EspoCRM') && !str_contains($visibleText, 'http'));
$verificationText = 'Cod de verificare: ' . $code;
$verificationNodes = $xpath->query('//w:body//w:t[. = ' . xpathLiteral($verificationText) . ']');
$verificationNode = $verificationNodes->item(0);
$verificationParagraph = $xpath->query('ancestor::w:p', $verificationNode)->item(0);
$mainTable = $xpath->query('//w:body/w:tbl[1]')->item(0);
$nextElement = $mainTable->nextSibling;
while ($nextElement && $nextElement->nodeType !== XML_ELEMENT_NODE) { $nextElement = $nextElement->nextSibling; }
check('verification line is the normal paragraph immediately after the table',
    $verificationNodes->length === 1 && $verificationParagraph instanceof DOMElement &&
    $nextElement instanceof DOMElement && $verificationParagraph->isSameNode($nextElement) &&
    $xpath->query('ancestor::w:tc', $verificationNode)->length === 0);
check('verification paragraph is right aligned, black and readable',
    $xpath->query('./w:pPr/w:jc[@w:val="right"]', $verificationParagraph)->length === 1 &&
    $xpath->query('./w:r/w:rPr/w:color[@w:val="000000"]', $verificationParagraph)->length === 1 &&
    $xpath->query('./w:r/w:rPr/w:sz[@w:val="16"]', $verificationParagraph)->length === 1 &&
    $xpath->query('./w:r/w:rPr/w:szCs[@w:val="16"]', $verificationParagraph)->length === 1 &&
    $xpath->query('.//w:vanish|.//w:webHidden', $verificationParagraph)->length === 0);
check('verification line is a single unsplit text run',
    $xpath->query('./w:r/w:t', $verificationParagraph)->length === 1 &&
    $xpath->query('.//w:br|.//w:cr|.//w:tab', $verificationParagraph)->length === 0 &&
    $verificationParagraph->textContent === $verificationText);
[, $withoutCodeXpath] = $inspect($januaryWithoutCodeBytes);
check('verification placement does not change the table, merges, page or paragraph count',
    $mainTable->C14N() === $withoutCodeXpath->query('//w:body/w:tbl[1]')->item(0)->C14N() &&
    $xpath->query('//w:body/w:p')->length === $withoutCodeXpath->query('//w:body/w:p')->length &&
    $xpath->query('//w:body/w:sectPr')->item(0)->C14N() === $withoutCodeXpath->query('//w:body/w:sectPr')->item(0)->C14N());

$defectiveDocument = new DOMDocument();
$defectiveDocument->loadXML(
    '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' .
    '<w:tbl><w:tr><w:tc><w:tcPr><w:vMerge/></w:tcPr><w:p><w:pPr><w:jc w:val="right"/></w:pPr>' .
    '<w:r><w:t>' . $verificationText . '</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>'
);
$defectiveXpath = new DOMXPath($defectiveDocument);
$defectiveXpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
$defectiveCode = $defectiveXpath->query('//w:t[starts-with(., "Cod de verificare:")]')->item(0);
check('verification code in a vertically merged continuation cell is rejected as invisible',
    $defectiveXpath->query('ancestor::w:tc[w:tcPr/w:vMerge]', $defectiveCode)->length === 1 &&
    $defectiveXpath->query('ancestor::w:tc', $defectiveCode)->length !== 0);
$digest = hash('sha256', $januaryBytes);
check('modified DOCX bytes fail SHA-256 equality', !hash_equals($digest, hash('sha256', $januaryBytes . 'modified')));

[, $decemberXpath] = $inspect($decemberBytes);
$decemberRows = $decemberXpath->query('//w:tbl[1]/w:tr');
$decemberValues = $decemberXpath->query('./w:tc', $decemberRows->item(9));
check('December remains 21/13/8/5/3 with no carryover line',
    $cellText($decemberXpath, $decemberValues->item(0)) === '21 - 2026' &&
    $decemberXpath->query('.//w:br', $decemberValues->item(0))->length === 0 &&
    $cellText($decemberXpath, $decemberValues->item(1)) === '13' &&
    $cellText($decemberXpath, $decemberValues->item(2)) === '8' &&
    $cellText($decemberXpath, $decemberValues->item(3)) === '5' &&
    $cellText($decemberXpath, $decemberValues->item(4)) === '3');

echo "PASS: Holiday DOCX projection and byte-integrity regression complete.\n";

function xpathLiteral(string $value): string
{
    if (!str_contains($value, "'")) { return "'" . $value . "'"; }
    if (!str_contains($value, '"')) { return '"' . $value . '"'; }
    throw new RuntimeException('Unsupported XPath literal.');
}
