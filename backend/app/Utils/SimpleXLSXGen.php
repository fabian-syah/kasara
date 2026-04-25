<?php

namespace App\Utils;

/**
 * SimpleXLSXGen v0.4
 * Simple and light PHP class for generating Microsoft Excel XLSX files
 * 
 * @author sergey.shuchkin@gmail.com
 */
class SimpleXLSXGen {

	public $curSheet = 0;
	protected $sheets = [['name' => 'Sheet1', 'data' => []]];
	protected $template = [
		'_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>',
		'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
<Application>SimpleXLSXGen</Application>
</Properties>',
		'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<dc:creator>SimpleXLSXGen</dc:creator>
<cp:lastModifiedBy>SimpleXLSXGen</cp:lastModifiedBy>
<dcterms:created xsi:type="dcterms:W3CDTF">{DATE}</dcterms:created>
<dcterms:modified xsi:type="dcterms:W3CDTF">{DATE}</dcterms:modified>
</cp:coreProperties>',
		'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="4">
<font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>
<font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>
<font><sz val="14"/><b/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>
<font><b/><sz val="11"/><color rgb="FF0000FF"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>
</fonts>
<fills count="5">
<fill><patternFill patternType="none"/></fill>
<fill><patternFill patternType="gray125"/></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FF2C3E50"/><bgColor indexed="64"/></patternFill></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/><bgColor indexed="64"/></patternFill></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FFD1FAE5"/><bgColor indexed="64"/></patternFill></fill>
</fills>
<borders count="1">
<border><left/><right/><top/><bottom/></border>
</borders>
<cellStyleXfs count="1">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
</cellStyleXfs>
<cellXfs count="7">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
<xf numFmtId="0" fontId="1" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center"/></xf>
<xf numFmtId="0" fontId="1" fillId="4" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center"/></xf>
<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>
<cellStyles count="1">
<cellStyle name="Normal" builtinId="0" xfId="0"/>
</cellStyles>
<dxfs count="0"/>
<tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>
</styleSheet>',
		'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>
{SHEETS}
</sheets>
</workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
{RELS}
</Relationships>',
		'[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
{TYPES}
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>'
	];

	public static function fromArray(array $rows, $sheetName = null) {
		return (new static())->addSheet($rows, $sheetName);
	}

	public function addSheet(array $rows, $name = null) {
		$this->curSheet = count($this->sheets);
		$this->sheets[$this->curSheet] = ['name' => $name ?: 'Sheet' . ($this->curSheet + 1), 'data' => $rows];
		return $this;
	}

	public function __toString() {
		$fh = fopen('php://memory', 'wb');
		if (!$fh) return '';
		
		$zip = new \ZipArchive();
		$tmpFilename = tempnam(sys_get_temp_dir(), 'xlsx');
		$zip->open($tmpFilename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

		$date = date('Y-m-d\TH:i:s\Z');
		$this->template['docProps/core.xml'] = str_replace('{DATE}', $date, $this->template['docProps/core.xml']);

		$sheets = '';
		$rels = '';
		$types = '';
		foreach ($this->sheets as $idx => $s) {
			$n = $idx + 1;
			$sheets .= '<sheet name="' . $this->esc($s['name']) . '" sheetId="' . $n . '" r:id="rId' . ($n + 1) . '"/>';
			$rels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
			$types .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
			$zip->addFromString('xl/worksheets/sheet' . $n . '.xml', $this->makeSheet($s['data']));
		}

		$this->template['xl/workbook.xml'] = str_replace('{SHEETS}', $sheets, $this->template['xl/workbook.xml']);
		$this->template['xl/_rels/workbook.xml.rels'] = str_replace('{RELS}', $rels, $this->template['xl/_rels/workbook.xml.rels']);
		$this->template['[Content_Types].xml'] = str_replace('{TYPES}', $types, $this->template['[Content_Types].xml']);

		foreach ($this->template as $path => $content) {
			$zip->addFromString($path, $content);
		}

		$zip->close();
		$res = file_get_contents($tmpFilename);
		unlink($tmpFilename);
		return $res;
	}

	public function downloadAs($filename) {
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $filename . '"');
		header('Cache-Control: max-age=0');
		echo $this;
	}

	protected function makeSheet(array $rows) {
		$res = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheetData>';
		foreach ($rows as $rIdx => $row) {
			$res .= '<row r="' . ($rIdx + 1) . '">';
			foreach ($row as $cIdx => $val) {
				$cName = $this->num2alpha($cIdx) . ($rIdx + 1);
				$s = 0;
				// Styling logic
				if ($rIdx === 0) $s = 2; // Title line
				elseif ($rIdx === 1) $s = 3; // Header line
				
				if (is_numeric($val) && (strlen($val) < 12)) {
					$res .= '<c r="' . $cName . '" s="' . $s . '"><v>' . $val . '</v></c>';
				} else {
					$res .= '<c r="' . $cName . '" s="' . $s . '" t="inlineStr"><is><t>' . $this->esc($val) . '</t></is></c>';
				}
			}
			$res .= '</row>';
		}
		$res .= '</sheetData></worksheet>';
		return $res;
	}

	protected function num2alpha($n) {
		for ($r = ''; $n >= 0; $n = intval($n / 26) - 1) $r = chr($n % 26 + 0x41) . $r;
		return $r;
	}

	protected function esc($str) {
		return htmlspecialchars($str, ENT_QUOTES | ENT_XML1);
	}
}
