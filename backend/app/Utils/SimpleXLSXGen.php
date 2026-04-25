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
        ob_start();
        echo '<?xml version="1.0"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
        echo 'xmlns:o="urn:schemas-microsoft-com:office:office" ';
        echo 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
        echo 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" ';
        echo 'xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        
        echo '<Styles>
            <Style ss:ID="Default" ss:Name="Normal">
                <Alignment ss:Vertical="Bottom"/>
                <Borders/>
                <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000"/>
                <Interior/>
                <NumberFormat/>
                <Protection/>
            </Style>
            <Style ss:ID="sHeader">
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
                <Borders>
                    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
                </Borders>
                <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#FFFFFF" ss:Bold="1"/>
                <Interior ss:Color="#2C3E50" ss:Pattern="Solid"/>
            </Style>
            <Style ss:ID="sTitle">
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
                <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="14" ss:Color="#000000" ss:Bold="1"/>
            </Style>
            <Style ss:ID="sIn">
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
                <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders>
                <Interior ss:Color="#D1FAE5" ss:Pattern="Solid"/>
            </Style>
            <Style ss:ID="sOut">
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
                <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders>
                <Interior ss:Color="#FEF2F2" ss:Pattern="Solid"/>
            </Style>
            <Style ss:ID="sTotal">
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
                <Font ss:Bold="1" ss:Color="#1D4ED8"/>
                <Interior ss:Color="#EFF6FF" ss:Pattern="Solid"/>
            </Style>
        </Styles>' . "\n";

        echo '<Worksheet ss:Name="' . htmlspecialchars($this->sheetName ?? 'Sheet1') . '">' . "\n";
        echo '<Table>' . "\n";
        
        echo '<Column ss:Width="250"/>' . "\n";
        for ($i=0; $i<16; $i++) echo '<Column ss:Width="80"/>' . "\n";

        foreach ($this->rows as $rIdx => $row) {
            echo '<Row ';
            if ($rIdx === 0) echo 'ss:Height="25"';
            echo '>' . "\n";
            
            foreach ($row as $cIdx => $val) {
                $style = 'Default';
                if ($rIdx === 0) $style = 'sTitle';
                elseif ($rIdx === 1) $style = 'sHeader';
                elseif ($rIdx > 1) {
                    if ($cIdx >= 3 && $cIdx <= 8) $style = 'sIn';
                    elseif ($cIdx >= 9 && $cIdx <= 15) $style = 'sOut';
                    elseif ($cIdx === 16) $style = 'sTotal';
                }

                echo '<Cell ss:StyleID="' . $style . '">';
                $type = (is_numeric($val) && strlen($val) < 12) ? 'Number' : 'String';
                echo '<Data ss:Type="' . $type . '">' . htmlspecialchars($val ?? '') . '</Data>';
                echo '</Cell>' . "\n";
            }
            echo '</Row>' . "\n";
        }
        
        echo '</Table>' . "\n";
        echo '</Worksheet>' . "\n";
        echo '</Workbook>' . "\n";
        
        return ob_get_clean();
    }

    public function downloadAs($filename) {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        echo $this->__toString();
    }
}
