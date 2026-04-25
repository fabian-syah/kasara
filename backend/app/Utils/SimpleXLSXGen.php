<?php

namespace App\Utils;

/**
 * SimpleXLSXGen (Windows PowerShell Fallback Version)
 * Generates true .xlsx files using Windows' native compression.
 */
class SimpleXLSXGen {

    protected $rows = [];
    protected $sheetName = 'Sheet1';

    public static function fromArray(array $rows, $sheetName = null) {
        $inst = new static();
        $inst->rows = $rows;
        if ($sheetName) $inst->sheetName = $sheetName;
        return $inst;
    }

    public function __toString() {
        $tempDir = storage_path('app/temp_xlsx_' . uniqid());
        if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);
        
        $xlDir = $tempDir . '/xl';
        $worksheetDir = $xlDir . '/worksheets';
        $relsDir = $tempDir . '/_rels';
        $xlRelsDir = $xlDir . '/_rels';
        $propsDir = $tempDir . '/docProps';
        
        mkdir($worksheetDir, 0777, true);
        mkdir($relsDir, 0777, true);
        mkdir($xlRelsDir, 0777, true);
        mkdir($propsDir, 0777, true);

        // Styling logic moved to styles.xml
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="3">
<font><sz val="11"/><name val="Calibri"/></font>
<font><b/><sz val="11"/><name val="Calibri"/></font>
<font><b/><sz val="14"/><name val="Calibri"/></font>
</fonts>
<fills count="6">
<fill><patternFill patternType="none"/></fill>
<fill><patternFill patternType="gray125"/></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FF2C3E50"/></patternFill></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FFD1FAE5"/></patternFill></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FFFEF2F2"/></patternFill></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FFEFF6FF"/></patternFill></fill>
</fills>
<borders count="1"><border><left/><right/><top/><bottom/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="6">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center"/></xf>
<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center"/></xf>
<xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1" applyAlignment="1"><alignment horizontal="center"/></xf>
<xf numFmtId="0" fontId="0" fillId="4" borderId="0" xfId="0" applyFill="1" applyAlignment="1"><alignment horizontal="center"/></xf>
<xf numFmtId="0" fontId="1" fillId="5" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center"/></xf>
</cellXfs>
</styleSheet>';

        file_put_contents($xlDir . '/styles.xml', $styles);
        file_put_contents($tempDir . '/[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        file_put_contents($relsDir . '/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        file_put_contents($xlDir . '/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        file_put_contents($xlRelsDir . '/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');

        // Sheet Data
        $sheetData = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="40" customWidth="1"/></cols><sheetData>';
        foreach ($this->rows as $rIdx => $row) {
            $sheetData .= '<row r="'.($rIdx+1).'">';
            foreach ($row as $cIdx => $val) {
                $col = chr(65 + $cIdx) . ($rIdx + 1);
                $s = 0; // Style
                if ($rIdx === 0) $s = 1; // Title
                elseif ($rIdx === 1) $s = 2; // Header
                elseif ($rIdx > 1) {
                    if ($cIdx >= 3 && $cIdx <= 8) $s = 3; // In
                    elseif ($cIdx >= 9 && $cIdx <= 15) $s = 4; // Out
                    elseif ($cIdx === 16) $s = 5; // Total
                }
                
                $t = is_numeric($val) ? 'n' : 'inlineStr';
                if ($t === 'n') {
                    $sheetData .= '<c r="'.$col.'" s="'.$s.'"><v>'.htmlspecialchars($val).'</v></c>';
                } else {
                    $sheetData .= '<c r="'.$col.'" s="'.$s.'" t="inlineStr"><is><t>'.htmlspecialchars($val).'</t></is></c>';
                }
            }
            $sheetData .= '</row>';
        }
        $sheetData .= '</sheetData></worksheet>';
        file_put_contents($worksheetDir . '/sheet1.xml', $sheetData);

        // ZIP using PowerShell
        $zipFile = storage_path('app/output_' . uniqid() . '.xlsx');
        
        // Correct powershell command to zip contents without parent folder
        $cmd = "powershell -command \"Get-ChildItem -Path '$tempDir' | Compress-Archive -DestinationPath '$zipFile'\"";
        exec($cmd);

        if (file_exists($zipFile)) {
            $content = file_get_contents($zipFile);
            unlink($zipFile);
            $this->rrmdir($tempDir);
            return $content;
        }

        return "FAILED TO GENERATE XLSX";
    }

    protected function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object))
                        $this->rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                    else
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                }
            }
            rmdir($dir);
        }
    }
}
