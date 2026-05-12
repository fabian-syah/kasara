<?php

namespace App\Utils;

/**
 * NanoXLSX - A minimal pure-PHP XLSX generator with zero dependencies.
 * Now supports multiple sheets.
 */
class SimpleXLSXGen {
    protected $sheets = []; // [ ['name' => 'Sheet1', 'rows' => [...]] ]

    public static function fromArray(array $rows, $sheetName = 'Sheet1') {
        $inst = new static();
        $inst->sheets[] = ['name' => $sheetName, 'rows' => $rows];
        return $inst;
    }

    public static function fromSheets(array $sheets) {
        $inst = new static();
        foreach ($sheets as $name => $rows) {
            $inst->sheets[] = ['name' => $name, 'rows' => $rows];
        }
        return $inst;
    }

    public function __toString() {
        $files = [];
        
        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $contentTypes .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $contentTypes .= '<Default Extension="xml" ContentType="application/xml"/>';
        $contentTypes .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        foreach ($this->sheets as $idx => $sheet) {
            $sId = $idx + 1;
            $contentTypes .= '<Override PartName="/xl/worksheets/sheet'.$sId.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $contentTypes .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
        $files['[Content_Types].xml'] = $contentTypes;
        
        // 2. _rels/.rels
        $files['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        
        // 3. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->sheets as $idx => $sheet) {
            $sId = $idx + 1;
            $wbRels .= '<Relationship Id="rId'.$sId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$sId.'.xml"/>';
        }
        $wbRels .= '<Relationship Id="rId'.(count($this->sheets) + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $files['xl/_rels/workbook.xml.rels'] = $wbRels;
        
        // 4. xl/workbook.xml
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        foreach ($this->sheets as $idx => $sheet) {
            $sId = $idx + 1;
            $wb .= '<sheet name="'.htmlspecialchars($sheet['name']).'" sheetId="'.$sId.'" r:id="rId'.$sId.'"/>';
        }
        $wb .= '</sheets></workbook>';
        $files['xl/workbook.xml'] = $wb;
        
        // 5. xl/styles.xml
        $files['xl/styles.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<numFmts count="1"><numFmt numFmtId="164" formatCode="&quot;Rp&quot;\ #,##0"/></numFmts>
<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font>
<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
<font><b/><sz val="14"/><name val="Calibri"/></font>
</fonts>
<fills count="7">
<fill><patternFill patternType="none"/></fill>
<fill><patternFill patternType="gray125"/></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FF2C3E50"/></patternFill></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FFD1FAE5"/></patternFill></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FFFEF2F2"/></patternFill></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FFEFF6FF"/></patternFill></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/></patternFill></fill>
</fills>
<borders count="1"><border><left/><right/><top/><bottom/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="11">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
<xf numFmtId="0" fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
<xf numFmtId="0" fontId="0" fillId="3" borderId="0" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
<xf numFmtId="0" fontId="0" fillId="4" borderId="0" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
<xf numFmtId="0" fontId="1" fillId="5" borderId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>
<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1" applyNumberFormat="1"><alignment horizontal="right" vertical="center"/></xf>
<xf numFmtId="0" fontId="0" fillId="6" borderId="0" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
<xf numFmtId="0" fontId="0" fillId="6" borderId="0" applyFill="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>
<xf numFmtId="164" fontId="0" fillId="6" borderId="0" applyFill="1" applyNumberFormat="1"><alignment horizontal="right" vertical="center"/></xf>
</cellXfs>
</styleSheet>';
        
        // 6. xl/worksheets/sheetN.xml
        foreach ($this->sheets as $idx => $sheet) {
            $sId = $idx + 1;
            $colWidths = [];
            foreach ($sheet['rows'] as $row) {
                foreach ($row as $cIdx => $val) {
                    if ($cIdx === '__bg_striped') continue;
                    $len = mb_strlen((string)$val) + 4;
                    if (!isset($colWidths[$cIdx]) || $len > $colWidths[$cIdx]) {
                        $colWidths[$cIdx] = $len;
                    }
                }
            }
            foreach ($colWidths as $k => $v) {
                if ($v > 50) $colWidths[$k] = 50;
            }

            $colsXml = '<cols>';
            foreach ($colWidths as $cIdx => $width) {
                $cNum = $cIdx + 1;
                $colsXml .= '<col min="'.$cNum.'" max="'.$cNum.'" width="'.$width.'"/>';
            }
            $colsXml .= '</cols>';

            $ws = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $colsXml . '<sheetData>';
            foreach ($sheet['rows'] as $rIdx => $row) {
                $isStriped = false;
                if (is_array($row)) {
                    if (isset($row['__bg_striped'])) {
                        $isStriped = (bool) $row['__bg_striped'];
                        unset($row['__bg_striped']); // DO NOT render meta as a column
                    }
                    // Re-index purely to numeric just in case it was associative
                    $row = array_values($row);
                }

                $ws .= '<row r="'.($rIdx+1).'">';
                foreach ($row as $cIdx => $val) {
                    $col = $this->num2alpha($cIdx) . ($rIdx + 1);
                    $s = 0; 
                    if ($rIdx === 0) {
                        $s = 2; // Header
                    } elseif (is_numeric($val) && strlen((string)$val) < 12 && ((string)$val === "0" || !str_starts_with((string)$val, '0'))) {
                        // Right align numbers. Format as rupiah if value looks like a price
                        if (abs((float)$val) > 10000) {
                            $s = 7; 
                        } else {
                            $s = 6;
                        }
                    }
                    
                    // Apply striping mapping
                    if ($isStriped) {
                        if ($s === 0) $s = 8;
                        elseif ($s === 6) $s = 9;
                        elseif ($s === 7) $s = 10;
                    }

                    if (is_numeric($val) && strlen((string)$val) < 12 && ((string)$val === "0" || !str_starts_with((string)$val, '0'))) {
                        $ws .= '<c r="'.$col.'" s="'.$s.'"><v>'.htmlspecialchars($val).'</v></c>';
                    } else {
                        $ws .= '<c r="'.$col.'" s="'.$s.'" t="inlineStr"><is><t>'.htmlspecialchars($val).'</t></is></c>';
                    }
                }
                $ws .= '</row>';
            }
            $ws .= '</sheetData></worksheet>';
            $files['xl/worksheets/sheet'.$sId.'.xml'] = $ws;
        }

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

            $header = pack("VvvvvvVVVvv", 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLen, 0);
            $zipData .= $header . $name . $content;

            $cdEntry = pack("VvvvvvvVVVvvvvvVV", 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLen, 0, 0, 0, 0, 128, $offset);
            $centralDir .= $cdEntry . $name;
            
            $offset += strlen($header) + $nameLen + $size;
        }

        $dirSize = strlen($centralDir);
        $endRecord = pack("VvvvvVVv", 0x06054b50, 0, 0, count($files), count($files), $dirSize, $offset, 0);
        
        return $zipData . $centralDir . $endRecord;
    }
}
