<?php
// Mulai sesi untuk menghindari error
session_start();
$_SESSION['mikhmon'] = true;

// Include file yang diperlukan
require_once __DIR__ . '/include/db_config.php';
require_once __DIR__ . '/lib/BillingService.class.php';
require_once __DIR__ . '/lib/excel/SimpleXLSX.php';

// Buat versi debug dari SimpleXLSX
class DebugSimpleXLSX
{
    private $zip = null;
    private $sheets = [];
    private $sharedStrings = [];

    public static function parse(string $filename): ?self
    {
        echo "Membuka file: $filename\n";
        $xlsx = new self();
        if (!$xlsx->open($filename)) {
            echo "Gagal membuka file.\n";
            return null;
        }
        echo "File berhasil dibuka.\n";
        return $xlsx;
    }

    private function open(string $filename): bool
    {
        echo "Memulai proses open...\n";
        $zip = new ZipArchive();
        if ($zip->open($filename) !== true) {
            echo "Gagal membuka file ZIP.\n";
            return false;
        }
        echo "File ZIP berhasil dibuka.\n";
        $this->zip = $zip;

        $strings = $this->zip->getFromName('xl/sharedStrings.xml');
        echo "Shared strings: " . ($strings !== false ? "ditemukan" : "tidak ditemukan") . "\n";
        if ($strings !== false) {
            $xml = simplexml_load_string($strings);
            echo "Shared strings XML: " . ($xml ? "valid" : "tidak valid") . "\n";
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
        echo "Jumlah shared strings: " . count($this->sharedStrings) . "\n";

        $workbook = $this->zip->getFromName('xl/workbook.xml');
        echo "Workbook: " . ($workbook !== false ? "ditemukan" : "tidak ditemukan") . "\n";
        if ($workbook === false) {
            return false;
        }
        $workbookXml = simplexml_load_string($workbook);
        echo "Workbook XML: " . ($workbookXml ? "valid" : "tidak valid") . "\n";
        if (!$workbookXml) {
            return false;
        }

        echo "Jumlah sheet dalam workbook: " . count($workbookXml->sheets->sheet) . "\n";
        foreach ($workbookXml->sheets->sheet as $sheet) {
            echo "Sheet ditemukan: " . $sheet['name'] . " dengan r:id=" . $sheet['r:id'] . "\n";
            
            // Coba cara yang berbeda untuk menemukan path worksheet
            $sheetId = (string)$sheet['r:id'];
            echo "Mencari path untuk sheetId: $sheetId\n";
            
            // Coba path langsung
            $directPath = 'xl/worksheets/sheet1.xml';
            $sheetContent = $this->zip->getFromName($directPath);
            echo "Direct path $directPath: " . ($sheetContent !== false ? "ditemukan" : "tidak ditemukan") . "\n";
            
            if ($sheetContent === false) {
                // Coba dengan rels
                $rels = $this->zip->getFromName('xl/_rels/workbook.xml.rels');
                if ($rels !== false) {
                    echo "Rels file ditemukan\n";
                    $relsXml = simplexml_load_string($rels);
                    if ($relsXml) {
                        echo "Rels XML valid\n";
                        foreach ($relsXml->Relationship as $rel) {
                            $relId = (string)$rel['Id'];
                            $relTarget = (string)$rel['Target'];
                            echo "  Relationship: Id=$relId, Target=$relTarget\n";
                            if ($relId === $sheetId) {
                                $path = 'xl/' . $relTarget;
                                echo "  Menggunakan path dari rels: $path\n";
                                $sheetContent = $this->zip->getFromName($path);
                                echo "  Sheet content dari rels path: " . ($sheetContent !== false ? "ditemukan" : "tidak ditemukan") . "\n";
                                break;
                            }
                        }
                    }
                }
            } else {
                echo "Menggunakan direct path\n";
            }
            
            if ($sheetContent !== false) {
                $sheetXml = simplexml_load_string($sheetContent); 
                echo "Sheet XML: " . ($sheetXml ? "valid" : "tidak valid") . "\n";
                if ($sheetXml) {
                    $this->sheets[] = $sheetXml;
                }
            }
        }
        echo "Jumlah sheet yang berhasil dimuat: " . count($this->sheets) . "\n";
        return !empty($this->sheets);
    }

    public function rows(int $sheetIndex = 0): array
    {
        echo "Mengambil rows dari sheet index: $sheetIndex\n";
        echo "Jumlah sheet tersedia: " . count($this->sheets) . "\n";
        if (!isset($this->sheets[$sheetIndex])) {
            echo "Sheet index tidak ditemukan\n";
            return [];
        }
        $sheetXml = $this->sheets[$sheetIndex];
        $rows = [];

        echo "Memproses sheet data...\n";
        if (!isset($sheetXml->sheetData)) {
            echo "sheetData tidak ditemukan\n";
            return [];
        }
        
        if (!isset($sheetXml->sheetData->row)) {
            echo "row tidak ditemukan\n";
            return [];
        }
        
        echo "Jumlah baris: " . count($sheetXml->sheetData->row) . "\n";
        foreach ($sheetXml->sheetData->row as $row) {
            $cells = [];
            $currentColumn = 0;
            if (isset($row->c)) {
                foreach ($row->c as $c) {
                    $cellRef = (string)$c['r'];
                    echo "  Memproses cell: $cellRef\n";
                    $cellIndex = $this->columnIndex($cellRef);
                    while ($currentColumn < $cellIndex) {
                        $cells[] = null;
                        $currentColumn++;
                    }
                    $cells[] = $this->value($c);
                    $currentColumn++;
                }
            }
            $rows[] = $cells;
        }

        return $rows;
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

    private function value($cell)
    {
        $type = (string)$cell['t'];
        echo "    Tipe cell: $type\n";
        if ($type === 's') {
            $idx = (int)$cell->v;
            echo "    Shared string index: $idx\n";
            echo "    Shared strings count: " . count($this->sharedStrings) . "\n";
            if (isset($this->sharedStrings[$idx])) {
                echo "    Shared string value: " . $this->sharedStrings[$idx] . "\n";
                return $this->sharedStrings[$idx];
            } else {
                echo "    Shared string tidak ditemukan untuk index: $idx\n";
                return '';
            }
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

// Uji dengan file debug_template.xlsx
$filename = 'debug_template.xlsx';
echo "=== Menguji DebugSimpleXLSX ===\n";
$xlsxRead = DebugSimpleXLSX::parse($filename);
if (!$xlsxRead) {
    echo "Gagal membaca file.\n";
} else {
    echo "File berhasil dibaca.\n";
    $rowsRead = $xlsxRead->rows();
    echo "Jumlah baris: " . count($rowsRead) . "\n";
    
    foreach ($rowsRead as $index => $row) {
        echo "Baris $index: " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
}