<?php

declare(strict_types=1);

$fixtureDirectory = __DIR__ . '/fixtures/schedules';
$xlsxPath = $fixtureDirectory . '/typed-date.xlsx';
$bomCsvPath = $fixtureDirectory . '/semicolon-bom.csv';

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "The PHP zip extension is required to generate the XLSX fixture.\n");
    exit(1);
}

file_put_contents(
    $bomCsvPath,
    "\xEF\xBB\xBFTitle;Permalink;Ianuarie;Februarie\n" .
    "CSV cu BOM;https://wp.example.test/cursuri/csv-bom/;10.01.2026;11-12.02.2026\n"
);

$entries = [
    '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>
XML,
    '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>
XML,
    'xl/workbook.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets><sheet name="Schedule" sheetId="1" r:id="rId1"/></sheets></workbook>
XML,
    'xl/_rels/workbook.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>
XML,
    'xl/styles.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="dd.mm.yyyy"/></numFmts><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs></styleSheet>
XML,
    'xl/worksheets/sheet1.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:C2"/><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Nume curs</t></is></c><c r="B1" t="inlineStr"><is><t>Permalink</t></is></c><c r="C1" t="inlineStr"><is><t>Ianuarie</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>XLSX typed date</t></is></c><c r="B2" t="inlineStr"><is><t>https://wp.example.test/cursuri/xlsx-typed-date/</t></is></c><c r="C2" s="1" t="n"><v>46032</v></c></row></sheetData></worksheet>
XML,
];

$zip = new ZipArchive();
$result = $zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

if ($result !== true) {
    fwrite(STDERR, "Unable to create XLSX fixture: {$result}\n");
    exit(1);
}

foreach ($entries as $name => $contents) {
    $zip->addFromString($name, $contents);

    if (method_exists($zip, 'setMtimeName')) {
        $zip->setMtimeName($name, 0);
    }
}

$zip->close();

fwrite(STDOUT, "Generated semicolon-bom.csv and typed-date.xlsx\n");
