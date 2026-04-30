<?php

namespace App\Utils;

/**
 * NanoXLSX - A minimal pure-PHP XLSX generator with zero dependencies.
 * Includes a built-in minimal ZIP writer.
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
        $files = [];
        
        // 1. [Content_Types].xml
        $files['[Content_Types].xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
        
        // 2. _rels/.rels
        $files['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        
        // 3. xl/_rels/workbook.xml.rels
        $files['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        
        // 4. xl/workbook.xml
        $files['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>';
        
        // 5. xl/styles.xml
        $files['xl/styles.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font>
<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
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
<xf numFmtId="0" fontId="2" fillId="0" borderId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
<xf numFmtId="0" fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
<xf numFmtId="0" fontId="0" fillId="3" borderId="0" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
<xf numFmtId="0" fontId="0" fillId="4" borderId="0" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
<xf numFmtId="0" fontId="1" fillId="5" borderId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
</cellXfs>
</styleSheet>';
        
        // 6. xl/worksheets/sheet1.xml
        $ws = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="20"/><col min="2" max="2" width="20"/><col min="3" max="100" width="15"/></cols><sheetData>';
        foreach ($this->rows as $rIdx => $row) {
            $ws .= '<row r="'.($rIdx+1).'">';
            foreach ($row as $cIdx => $val) {
                $col = $this->num2alpha($cIdx) . ($rIdx + 1);
                $s = 0; 
                if ($rIdx === 0) $s = 1; // Title style
                elseif ($rIdx === 1) $s = 2; // Header style
                
                if (is_numeric($val) && strlen($val) < 12 && !str_starts_with($val, '0')) {
                    $ws .= '<c r="'.$col.'" s="'.$s.'"><v>'.htmlspecialchars($val).'</v></c>';
                } else {
                    $ws .= '<c r="'.$col.'" s="'.$s.'" t="inlineStr"><is><t>'.htmlspecialchars($val).'</t></is></c>';
                }
            }
            $ws .= '</row>';
        }
        $ws .= '</sheetData></worksheet>';
        $files['xl/worksheets/sheet1.xml'] = $ws;

        return $this->zipFiles($files);
    }

    protected function num2alpha($n) {
        for ($r = ""; $n >= 0; $n = intval($n / 26) - 1) $r = chr($n % 26 + 0x41) . $r;
        return $r;
    }

    protected function zipFiles($files) {
        $zipData = "";
        $centralDir = "";
        $offset = 0;

        foreach ($files as $name => $content) {
            $crc = crc32($content);
            $size = strlen($content);
            $nameLen = strlen($name);

            // Local file header
            $header = pack("VvvvvvVVVvv", 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLen, 0);
            $zipData .= $header . $name . $content;

            // Central directory entry
            $cdEntry = pack("VvvvvvvVVVvvvvvVV", 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLen, 0, 0, 0, 0, 128, $offset);
            $centralDir .= $cdEntry . $name;
            
            $offset += strlen($header) + $nameLen + $size;
        }

        $dirSize = strlen($centralDir);
        $endRecord = pack("VvvvvVVv", 0x06054b50, 0, 0, count($files), count($files), $dirSize, $offset, 0);
        
        return $zipData . $centralDir . $endRecord;
    }
}
