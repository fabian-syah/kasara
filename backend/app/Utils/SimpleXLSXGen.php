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
<numFmts count="1"><numFmt numFmtId="164" formatCode="&quot;Rp&quot;\ #,##0;-&quot;Rp&quot;\ #,##0;&quot;Rp&quot;\ 0"/></numFmts>
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
<fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/></patternFill></fill>
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
            $sheetName = $sheet['name'] ?? ('Sheet' . $sId);
            $sheetLower = strtolower(trim($sheetName));

            // Detect table header row (first row with multiple elements)
            $headerRow = [];
            foreach ($sheet['rows'] as $r) {
                if (is_array($r) && count($r) > 1) {
                    $headerRow = array_values($r);
                    break;
                }
            }

            $isCurrencyCol = [];
            $isNonCurrencyCol = [];

            $nonCurrencyKeywords = [
                'no', 'id', 'imei', 'barcode', 'serial', 'telepon', 'phone', 'wa', 'whatsapp', 
                'tanggal', 'date', 'waktu', 'time', 'status', 'kondisi', 'condition', 'brand', 
                'kategori', 'category', 'tipe', 'type', 'nama', 'nama barang', 'customer', 
                'pelanggan', 'cs', 'customer service', 'user', 'auditor', 'outlet', 'lokasi', 
                'catatan', 'keterangan', 'notes', 'bundling', 'sumber', 'sumber masuk', 'distributor',
                'pesanan', 'no pesanan', 'order'
            ];

            foreach ($headerRow as $cIdx => $hVal) {
                $h = strtolower(trim((string)$hVal));
                $isCurrency = false;
                $isNonCurr = false;

                if ($sheetLower === 'distributor') {
                    if (in_array($h, ['no', 'nama cabang', 'nama', 'cabang', 'distributor', 'outlet', 'lokasi', 'cs', 'customer', 'customer service', 'user'])) {
                        $isNonCurr = true;
                    } else {
                        $isCurrency = true;
                    }
                } elseif ($sheetLower === 'ranking') {
                    if (in_array($h, ['no', 'nama cabang', 'nama', 'cabang', 'tipe', 'type', 'kategori', 'category']) || str_contains($h, 'qty') || str_contains($h, '(qty)')) {
                        $isNonCurr = true;
                    } else {
                        $isCurrency = true;
                    }
                } else {
                    if (in_array($h, $nonCurrencyKeywords) || str_contains($h, '(qty)') || str_contains($h, 'qty') || str_contains($h, 'jumlah') || str_contains($h, 'imei')) {
                        $isNonCurr = true;
                    } elseif (
                        str_contains($h, '(rp)') 
                        || str_contains($h, 'rp') 
                        || str_contains($h, 'omset') 
                        || str_contains($h, 'profit') 
                        || str_contains($h, 'harga') 
                        || str_contains($h, 'modal') 
                        || str_contains($h, 'diskon') 
                        || str_contains($h, 'penjualan') 
                        || str_contains($h, 'pengeluaran') 
                        || str_contains($h, 'bayar') 
                        || str_contains($h, 'nominal') 
                        || str_contains($h, 'tarif') 
                        || str_contains($h, 'biaya') 
                        || str_contains($h, 'uang') 
                        || str_contains($h, 'dp') 
                        || str_starts_with($h, 'pisah ')
                        || str_contains($h, 'cash')
                        || str_contains($h, 'transfer')
                        || str_contains($h, 'bca')
                        || str_contains($h, 'mandiri')
                        || str_contains($h, 'bri')
                        || str_contains($h, 'bni')
                        || str_contains($h, 'edc')
                        || str_contains($h, 'qris')
                        || str_contains($h, 'kredit')
                        || str_contains($h, 'debit')
                        || str_contains($h, 'split')
                        || str_contains($h, 'avanto')
                        || str_contains($h, 'dana')
                        || str_contains($h, 'ovo')
                        || str_contains($h, 'gopay')
                        || str_contains($h, 'shopeepay')
                        || str_contains($h, 'in tukar tambah')
                    ) {
                        $isCurrency = true;
                    }
                }

                $isCurrencyCol[$cIdx] = $isCurrency;
                $isNonCurrencyCol[$cIdx] = $isNonCurr;
            }

            $colWidths = [];
            foreach ($sheet['rows'] as $row) {
                if (!is_array($row)) continue;
                $colIdx = 0;
                foreach ($row as $k => $val) {
                    if ($k === '__bg_striped') continue;
                    $cIdx = is_numeric($k) ? (int)$k : $colIdx;
                    $colIdx++;

                    $valStr = (string)$val;
                    $len = mb_strlen($valStr);
                    
                    // Predict real formatted currency length to prevent column overflow (###)
                    if (!empty($isCurrencyCol[$cIdx]) && is_numeric($val)) {
                        $fmt = "Rp " . number_format(round(abs((float)$val)), 0, '.', '.');
                        $len = max($len, mb_strlen($fmt));
                    } elseif (empty($isNonCurrencyCol[$cIdx]) && is_numeric($val) && strlen($valStr) < 12 && ($valStr === "0" || !str_starts_with($valStr, '0'))) {
                        if (abs((float)$val) > 10000) {
                            $fmt = "Rp " . number_format(abs((float)$val), 0, '.', '.');
                            $len = max($len, mb_strlen($fmt));
                        }
                    }

                    $len += 5; // Standard cell horizontal padding safety cushion
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
                    
                    // Force-text convention: values starting with \t are always rendered as text
                    $forceText = false;
                    if (is_string($val) && str_starts_with($val, "\t")) {
                        $val = substr($val, 1); // Strip the leading tab
                        $forceText = true;
                    }
                    
                    if ($rIdx === 0) {
                        $s = 2; // Header
                        $ws .= '<c r="'.$col.'" s="'.$s.'" t="inlineStr"><is><t>'.htmlspecialchars((string)$val).'</t></is></c>';
                        continue;
                    }

                    $isCurr = !empty($isCurrencyCol[$cIdx]);
                    $isNonCurr = !empty($isNonCurrencyCol[$cIdx]);

                    if ($val === '' || $val === null) {
                        $s = $isStriped ? 8 : 0;
                        $ws .= '<c r="'.$col.'" s="'.$s.'"/>';
                        continue;
                    }

                    if (!$forceText && is_numeric($val)) {
                        $valStr = (string)$val;
                        $isPhoneOrImei = (strlen($valStr) > 1 && str_starts_with($valStr, '0')) || strlen($valStr) >= 12;

                        if ($isCurr) {
                            $cleanVal = round((float)$val);
                            $s = $isStriped ? 10 : 7;
                            $ws .= '<c r="'.$col.'" s="'.$s.'"><v>'.(string)$cleanVal.'</v></c>';
                        } elseif ($isNonCurr) {
                            if ($isPhoneOrImei) {
                                $s = $isStriped ? 8 : 0;
                                $ws .= '<c r="'.$col.'" s="'.$s.'" t="inlineStr"><is><t>'.htmlspecialchars($valStr).'</t></is></c>';
                            } else {
                                $s = $isStriped ? 9 : 6;
                                $ws .= '<c r="'.$col.'" s="'.$s.'"><v>'.htmlspecialchars($valStr).'</v></c>';
                            }
                        } else {
                            if ($isPhoneOrImei) {
                                $s = $isStriped ? 8 : 0;
                                $ws .= '<c r="'.$col.'" s="'.$s.'" t="inlineStr"><is><t>'.htmlspecialchars($valStr).'</t></is></c>';
                            } else {
                                if (abs((float)$val) > 10000) {
                                    $s = $isStriped ? 10 : 7;
                                    $ws .= '<c r="'.$col.'" s="'.$s.'"><v>'.(string)round((float)$val).'</v></c>';
                                } else {
                                    $s = $isStriped ? 9 : 6;
                                    $ws .= '<c r="'.$col.'" s="'.$s.'"><v>'.htmlspecialchars($valStr).'</v></c>';
                                }
                            }
                        }
                    } else {
                        $s = $isStriped ? 8 : 0;
                        $ws .= '<c r="'.$col.'" s="'.$s.'" t="inlineStr"><is><t>'.htmlspecialchars((string)$val).'</t></is></c>';
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
