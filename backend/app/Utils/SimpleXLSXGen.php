<?php

namespace App\Utils;

/**
 * SimpleXLSGen
 * Generates Microsoft Excel 2003 XML (SpreadsheetML) files.
 * No ZipArchive required.
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
        $data = $this->rows;
        $title = $this->sheetName ?? 'Laporan';
        
        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        $html .= '<head><meta charset="utf-8"/><style>
            .title { font-size: 16pt; font-weight: bold; text-align: center; height: 40px; }
            .header { background-color: #2C3E50; color: #FFFFFF; font-weight: bold; text-align: center; border: 0.5pt solid #000000; }
            .cell { border: 0.5pt solid #cccccc; text-align: center; }
            .pname { text-align: left; border: 0.5pt solid #cccccc; }
            .in-cell { background-color: #D1FAE5; border: 0.5pt solid #cccccc; text-align: center; }
            .out-cell { background-color: #FEF2F2; border: 0.5pt solid #cccccc; text-align: center; }
            .total-cell { background-color: #EFF6FF; border: 0.5pt solid #cccccc; font-weight: bold; text-align: center; color: #1D4ED8; }
        </style></head><body>';
        
        $html .= '<table>';
        foreach ($data as $rIdx => $row) {
            $html .= '<tr>';
            foreach ($row as $cIdx => $val) {
                $class = 'cell';
                $colspan = 1;
                
                if ($rIdx === 0) {
                    $class = 'title';
                    $colspan = 17;
                } elseif ($rIdx === 1) {
                    $class = 'header';
                } else {
                    if ($cIdx === 0) $class = 'pname';
                    elseif ($cIdx >= 3 && $cIdx <= 8) $class = 'in-cell';
                    elseif ($cIdx >= 9 && $cIdx <= 15) $class = 'out-cell';
                    elseif ($cIdx === 16) $class = 'total-cell';
                }
                
                $html .= '<td class="' . $class . '"' . ($colspan > 1 ? ' colspan="' . $colspan . '"' : '') . '>';
                $html .= htmlspecialchars($val ?? '');
                $html .= '</td>';
                
                if ($colspan > 1) break;
            }
            $html .= '</tr>';
        }
        $html .= '</table></body></html>';

        $boundary = '------=_NextPart_01C9EB77.632D2830';
        $mhtml = "MIME-Version: 1.0\n";
        $mhtml .= "Content-Type: multipart/related; boundary=\"$boundary\"\n\n";
        $mhtml .= "--$boundary\n";
        $mhtml .= "Content-Location: file:///C:/excel.htm\n";
        $html .= "Content-Transfer-Encoding: base64\n";
        $html .= "Content-Type: text/html; charset=\"utf-8\"\n\n";
        $mhtml .= base64_encode($html) . "\n\n";
        $mhtml .= "--$boundary--\n";
        
        return $mhtml;
    }

    public function downloadAs($filename) {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        echo $this->__toString();
    }
}
