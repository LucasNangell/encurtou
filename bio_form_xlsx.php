<?php

/**
 * Gera XLSX mínimo (Office Open XML) sem dependências externas.
 * Requer ext-zip.
 */
function bio_form_xlsx_xml_escape(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function bio_form_xlsx_col_letter(int $zeroBasedIndex): string
{
    $n = $zeroBasedIndex + 1;
    $s = '';
    while ($n > 0) {
        $n--;
        $s = chr($n % 26 + 65) . $s;
        $n = intdiv($n, 26);
    }
    return $s;
}

function bio_form_stream_xlsx_download(string $downloadName, array $headers, array $rows): void
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive não disponível no PHP.');
    }

    $sheetRows = [];
    $sheetRows[] = $headers;
    foreach ($rows as $r) {
        $sheetRows[] = $r;
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $sheetXml .= '<sheetData>';

    foreach ($sheetRows as $ri => $cells) {
        $rowNum = $ri + 1;
        $sheetXml .= '<row r="' . $rowNum . '">';
        foreach ($cells as $ci => $cellVal) {
            $col = bio_form_xlsx_col_letter($ci);
            $ref = $col . $rowNum;
            $v = (string) $cellVal;
            $sheetXml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . bio_form_xlsx_xml_escape($v) . '</t></is></c>';
        }
        $sheetXml .= '</row>';
    }

    $sheetXml .= '</sheetData></worksheet>';

    $tmp = tempnam(sys_get_temp_dir(), 'bioxlsx');
    if ($tmp === false) {
        throw new RuntimeException('Não foi possível criar arquivo temporário.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        throw new RuntimeException('Falha ao criar XLSX.');
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>');

    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');

    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>');

    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Respostas" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>');

    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
    header('Cache-Control: max-age=0');
    readfile($tmp);
    @unlink($tmp);
}
