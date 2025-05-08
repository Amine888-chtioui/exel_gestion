<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExcelAnalyzerService
{
    /**
     * Analyze an Excel file and generate statistics
     *
     * @param string $filePath Path to the Excel file
     * @return array Statistics about the Excel file
     */
    public function analyze(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $stats = [];
        
        // Basic file information
        $stats['sheets'] = [];
        $stats['total_rows'] = 0;
        $stats['total_columns'] = 0;
        $stats['sheet_count'] = $spreadsheet->getSheetCount();
        
        // Analyze each sheet
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetName = $sheet->getTitle();
            $rowCount = $sheet->getHighestDataRow();
            $columnCount = $this->columnIndexFromString($sheet->getHighestDataColumn());
            
            $sheetStats = [
                'name' => $sheetName,
                'row_count' => $rowCount,
                'column_count' => $columnCount,
                'data_types' => [],
                'columns' => []
            ];
            
            // Detect data types for each column
            if ($rowCount > 1) {
                $headers = [];
                for ($col = 1; $col <= $columnCount; $col++) {
                    $cellValue = $sheet->getCellByColumnAndRow($col, 1)->getValue();
                    $headers[$col] = $cellValue ?? "Column $col";
                }
                
                // Analyze data in each column
                for ($col = 1; $col <= $columnCount; $col++) {
                    $columnData = [];
                    $numericCount = 0;
                    $textCount = 0;
                    $dateCount = 0;
                    $emptyCount = 0;
                    $sum = 0;
                    $min = null;
                    $max = null;
                    
                    // Skip first row (headers)
                    for ($row = 2; $row <= $rowCount; $row++) {
                        $cell = $sheet->getCellByColumnAndRow($col, $row);
                        $value = $cell->getValue();
                        
                        if ($value === null || $value === '') {
                            $emptyCount++;
                            continue;
                        }
                        
                        // Ensure value is properly handled based on type
                        if (is_numeric($value)) {
                            $numericCount++;
                            $numericValue = floatval($value);
                            $sum += $numericValue;
                            $min = ($min === null) ? $numericValue : min(floatval($min), $numericValue);
                            $max = ($max === null) ? $numericValue : max(floatval($max), $numericValue);
                        } elseif ($cell->isFormula()) {
                            // Handle formulas
                            $calculatedValue = $cell->getCalculatedValue();
                            if (is_numeric($calculatedValue)) {
                                $numericCount++;
                                $numericValue = floatval($calculatedValue);
                                $sum += $numericValue;
                                $min = ($min === null) ? $numericValue : min(floatval($min), $numericValue);
                                $max = ($max === null) ? $numericValue : max(floatval($max), $numericValue);
                            } else {
                                $textCount++;
                            }
                        } else {
                            // Check if it might be a date
                            try {
                                $dateValue = $value;
                                // Make sure it's a numeric value before treating as Excel date
                                if (is_numeric($dateValue)) {
                                    $dateObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject(floatval($dateValue), null);
                                    if ($dateObj && $dateObj->format('Y') > 1970) {
                                        $dateCount++;
                                    } else {
                                        $textCount++;
                                    }
                                } else {
                                    $textCount++;
                                }
                            } catch (\Exception $e) {
                                // If any error occurs during date conversion, treat as text
                                $textCount++;
                            }
                        }
                    }
                    
                    // Determine predominant data type
                    $totalNonEmpty = $numericCount + $textCount + $dateCount;
                    $dataType = 'mixed';
                    if ($totalNonEmpty > 0) {
                        if ($numericCount / $totalNonEmpty > 0.8) {
                            $dataType = 'numeric';
                        } elseif ($textCount / $totalNonEmpty > 0.8) {
                            $dataType = 'text';
                        } elseif ($dateCount / $totalNonEmpty > 0.8) {
                            $dataType = 'date';
                        }
                    }
                    
                    // Calculate statistics for numeric columns
                    $avg = ($numericCount > 0) ? $sum / $numericCount : null;
                    
                    $columnStats = [
                        'header' => $headers[$col],
                        'data_type' => $dataType,
                        'non_empty_count' => $totalNonEmpty,
                        'empty_count' => $emptyCount,
                        'fill_rate' => ($rowCount - 1 > 0) ? round(($totalNonEmpty / ($rowCount - 1)) * 100, 2) : 0,
                    ];
                    
                    // Add numeric statistics if applicable
                    if ($dataType === 'numeric' || $numericCount > 0) {
                        $columnStats['numeric'] = [
                            'count' => $numericCount,
                            'sum' => $sum,
                            'avg' => $avg,
                            'min' => $min,
                            'max' => $max
                        ];
                    }
                    
                    $sheetStats['columns'][$headers[$col]] = $columnStats;
                }
            }
            
            $stats['total_rows'] += $rowCount;
            $stats['total_columns'] += $columnCount;
            $stats['sheets'][$sheetName] = $sheetStats;
        }
        
        return $stats;
    }
    
    /**
     * Convert column letters to index number
     *
     * @param string $columnString Column letter (A, B, AA, etc.)
     * @return int Column index
     */
    private function columnIndexFromString(string $columnString): int
    {
        $columnIndex = 0;
        $length = strlen($columnString);
        
        for ($i = 0; $i < $length; $i++) {
            $columnIndex = $columnIndex * 26 + (ord($columnString[$i]) - 64);
        }
        
        return $columnIndex;
    }
}