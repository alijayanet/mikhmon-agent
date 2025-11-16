<?php
/**
 * SimpleXLSX reader (no Composer dependency).
 *
 * Source: https://github.com/shuchkin/simplexlsx (MIT License)
 * Trimmed for basic parsing (values only).
 */

if (!class_exists('SimpleXLSX')) {
    class SimpleXLSX
    {
        private ?ZipArchive $zip = null;
        private array $sheets = [];
        private array $sharedStrings = [];

        public static function parse(string $filename): ?self
        {
            $xlsx = new self();
            if (!$xlsx->open($filename)) {
                return null;
            }
            return $xlsx;
        }

        public function rows(int $sheetIndex = 0): array
        {
            if (!isset($this->sheets[$sheetIndex])) {
                return [];
            }
            $sheetXml = $this->sheets[$sheetIndex];
            $rows = [];

            foreach ($sheetXml->sheetData->row as $row) {
                $cells = [];
                $currentColumn = 0;
                foreach ($row->c as $c) {
                    $cellIndex = $this->columnIndex($c['r']);
                    while ($currentColumn < $cellIndex) {
                        $cells[] = null;
                        $currentColumn++;
                    }
                    $cells[] = $this->value($c);
                    $currentColumn++;
                }
                $rows[] = $cells;
            }

            return $rows;
        }

        public function dimension(int $sheetIndex = 0): array
        {
            $rows = $this->rows($sheetIndex);
            $rowCount = count($rows);
            $colCount = 0;
            foreach ($rows as $row) {
                $colCount = max($colCount, count($row));
            }
            return [$rowCount, $colCount];
        }

        private function open(string $filename): bool
        {
            $zip = new ZipArchive();
            if ($zip->open($filename) !== true) {
                return false;
            }
            $this->zip = $zip;

            $strings = $this->zip->getFromName('xl/sharedStrings.xml');
            if ($strings !== false) {
                $xml = simplexml_load_string($strings);
                if ($xml && isset($xml->si)) {
                    foreach ($xml->si as $si) {
                        if (isset($si->t)) {
                            $this->sharedStrings[] = (string)$si->t;
                        } elseif (isset($si->r)) {
                            $text = '';
                            foreach ($si->r as $run) {
                                $text .= (string)$run->t;
                            }
                            $this->sharedStrings[] = $text;
                        } else {
                            $this->sharedStrings[] = '';
                        }
                    }
                }
            }

            $workbook = $this->zip->getFromName('xl/workbook.xml');
            if ($workbook === false) {
                return false;
            }
            $workbookXml = simplexml_load_string($workbook);
            if (!$workbookXml) {
                return false;
            }

            foreach ($workbookXml->sheets->sheet as $sheet) {
                // Perbaikan: Gunakan path default yang lebih aman
                $relationId = (string)$sheet['r:id'];
                $sheetContent = false;
                
                if (!empty($relationId)) {
                    // Jika ada relationId, gunakan metode pencarian yang ada
                    $target = $this->findWorksheetPath($relationId);
                    if ($target !== null) {
                        $sheetContent = $this->zip->getFromName($target);
                    }
                }
                
                // Jika belum berhasil, coba path default
                if ($sheetContent === false) {
                    $sheetContent = $this->zip->getFromName('xl/worksheets/sheet1.xml');
                }
                
                // Jika masih belum berhasil, coba metode alternatif
                if ($sheetContent === false) {
                    $sheetContent = $this->zip->getFromName('xl/worksheets/sheet.xml');
                }
                
                if ($sheetContent !== false) {
                    $sheetXml = simplexml_load_string($sheetContent); 
                    if ($sheetXml) {
                        $this->sheets[] = $sheetXml;
                    }
                }
            }
            return !empty($this->sheets);
        }

        private function findWorksheetPath(string $relationId): ?string
        {
            $rels = $this->zip->getFromName('xl/_rels/workbook.xml.rels');
            if ($rels === false) {
                return null;
            }
            $xml = simplexml_load_string($rels);
            if (!$xml) {
                return null;
            }
            foreach ($xml->Relationship as $rel) {
                if ((string)$rel['Id'] === $relationId) {
                    return 'xl/' . (string)$rel['Target'];
                }
            }
            return null;
        }

        private function columnIndex($cellRef): int
        {
            $letters = preg_replace('/[0-9]/', '', (string)$cellRef);
            $letters = strtoupper($letters);
            $len = strlen($letters);
            $index = 0;
            for ($i = 0; $i < $len; $i++) {
                $index = $index * 26 + (ord($letters[$i]) - 64);
            }
            return $index - 1;
        }

        private function value(SimpleXMLElement $cell)
        {
            $type = (string)$cell['t'];
            if ($type === 's') {
                $idx = (int)$cell->v;
                return $this->sharedStrings[$idx] ?? '';
            }
            if ($type === 'b') {
                return ((string)$cell->v) === '1';
            }
            if (isset($cell->f) && !isset($cell->v)) {
                return (string)$cell->f;
            }
            if (!isset($cell->v)) {
                return null;
            }
            return (string)$cell->v;
        }

        public function __destruct()
        {
            if ($this->zip instanceof ZipArchive) {
                $this->zip->close();
            }
        }
    }
}