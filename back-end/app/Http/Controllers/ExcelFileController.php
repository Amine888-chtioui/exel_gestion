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
                'columns' => [],
                'cross_column_stats' => []
            ];
            
            // Detect data types for each column
            if ($rowCount > 1) {
                $headers = [];
                $columnData = []; // Pour stocker les données de chaque colonne
                
                for ($col = 1; $col <= $columnCount; $col++) {
                    $cellValue = $sheet->getCellByColumnAndRow($col, 1)->getValue();
                    $headers[$col] = $cellValue ?? "Column $col";
                    $columnData[$headers[$col]] = []; // Initialiser le tableau pour stocker les valeurs
                }
                
                // Collecter toutes les données pour chaque colonne
                for ($col = 1; $col <= $columnCount; $col++) {
                    $currentHeader = $headers[$col];
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
                        $formattedValue = $value; // Valeur à stocker pour l'analyse croisée
                        
                        // Stocker la valeur pour l'analyse croisée
                        $columnData[$currentHeader][$row] = $value;
                        
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
                                // Mise à jour de la valeur formatée pour l'analyse croisée
                                $columnData[$currentHeader][$row] = $calculatedValue;
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
                                        // Mise à jour de la valeur formatée pour l'analyse croisée
                                        $columnData[$currentHeader][$row] = $dateObj->format('Y-m-d');
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
                        'header' => $currentHeader,
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
                    
                    $sheetStats['columns'][$currentHeader] = $columnStats;
                }
                
                // Maintenant que nous avons collecté toutes les données, calculons les statistiques croisées
                $this->calculateCrossColumnStats($sheetStats, $columnData, $rowCount);
            }
            
            $stats['total_rows'] += $rowCount;
            $stats['total_columns'] += $columnCount;
            $stats['sheets'][$sheetName] = $sheetStats;
        }
        
        return $stats;
    }
    
    /**
     * Calculate cross-column statistics - this is the new function for analyzing relationships
     * 
     * @param array &$sheetStats Sheet statistics to update
     * @param array $columnData All column data collected
     * @param int $rowCount Total number of rows
     * @return void
     */
    private function calculateCrossColumnStats(array &$sheetStats, array $columnData, int $rowCount): void
    {
        $crossStats = [];
        $headers = array_keys($columnData);
        
        // Pour chaque paire de colonnes (où au moins l'une est numérique)
        foreach ($headers as $targetHeader) {
            $targetType = $sheetStats['columns'][$targetHeader]['data_type'];
            
            // On ne fait l'analyse croisée que pour les colonnes numériques en cible
            if ($targetType !== 'numeric') {
                continue;
            }
            
            foreach ($headers as $sourceHeader) {
                // Éviter l'auto-comparaison
                if ($sourceHeader === $targetHeader) {
                    continue;
                }
                
                $sourceType = $sheetStats['columns'][$sourceHeader]['data_type'];
                
                $crossStat = [
                    'target_column' => $targetHeader,
                    'source_column' => $sourceHeader,
                    'source_type' => $sourceType,
                    'target_type' => $targetType,
                ];
                
                // Analyse par catégorie pour les colonnes texte
                if ($sourceType === 'text' || $sourceType === 'date' || $sourceType === 'mixed') {
                    $categoryStats = $this->calculateCategoricalStats(
                        $columnData[$targetHeader], 
                        $columnData[$sourceHeader], 
                        $rowCount
                    );
                    $crossStat['category_stats'] = $categoryStats;
                }
                
                // Analyse de corrélation pour les colonnes numériques
                if ($sourceType === 'numeric' && $targetType === 'numeric') {
                    $correlationStats = $this->calculateCorrelationStats(
                        $columnData[$targetHeader], 
                        $columnData[$sourceHeader]
                    );
                    $crossStat['correlation'] = $correlationStats;
                }
                
                $crossStats[] = $crossStat;
            }
        }
        
        $sheetStats['cross_column_stats'] = $crossStats;
    }
    
    /**
     * Calculate statistics for numeric target grouped by categorical source
     * 
     * @param array $targetData Target column data (numeric)
     * @param array $sourceData Source column data (categorical)
     * @param int $rowCount Total number of rows
     * @return array Statistics by category
     */
    private function calculateCategoricalStats(array $targetData, array $sourceData, int $rowCount): array
    {
        $categoryStats = [];
        $categories = [];
        
        // Group target values by source categories
        for ($row = 2; $row <= $rowCount; $row++) {
            if (!isset($sourceData[$row]) || !isset($targetData[$row])) {
                continue;
            }
            
            $category = (string)$sourceData[$row];
            $value = $targetData[$row];
            
            // Skip if category is empty or value is not numeric
            if (empty($category) || !is_numeric($value)) {
                continue;
            }
            
            if (!isset($categories[$category])) {
                $categories[$category] = [
                    'count' => 0,
                    'sum' => 0,
                    'min' => null,
                    'max' => null,
                    'values' => []
                ];
            }
            
            $categories[$category]['count']++;
            $categories[$category]['sum'] += $value;
            $categories[$category]['min'] = ($categories[$category]['min'] === null) 
                ? $value 
                : min($categories[$category]['min'], $value);
                
            $categories[$category]['max'] = ($categories[$category]['max'] === null) 
                ? $value 
                : max($categories[$category]['max'], $value);
                
            $categories[$category]['values'][] = $value;
        }
        
        // Calculate final statistics for each category
        foreach ($categories as $category => $stats) {
            if ($stats['count'] > 0) {
                $avg = $stats['sum'] / $stats['count'];
                $variance = 0;
                
                // Calculate variance if we have at least 2 values
                if ($stats['count'] > 1) {
                    foreach ($stats['values'] as $value) {
                        $variance += pow($value - $avg, 2);
                    }
                    $variance = $variance / $stats['count'];
                }
                
                $categoryStats[] = [
                    'category' => $category,
                    'count' => $stats['count'],
                    'percent' => ($rowCount > 1) ? round(($stats['count'] / ($rowCount - 1)) * 100, 2) : 0,
                    'sum' => $stats['sum'],
                    'avg' => $avg,
                    'min' => $stats['min'],
                    'max' => $stats['max'],
                    'variance' => $variance,
                    'std_dev' => sqrt($variance)
                ];
            }
        }
        
        // Sort by category count (descending)
        usort($categoryStats, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        // Limit to top 10 categories
        return array_slice($categoryStats, 0, 10);
    }
    
    /**
     * Calculate correlation between two numeric columns
     * 
     * @param array $targetData Target column data
     * @param array $sourceData Source column data
     * @return array Correlation statistics
     */
    private function calculateCorrelationStats(array $targetData, array $sourceData): array
    {
        $pairs = [];
        $targetValues = [];
        $sourceValues = [];
        
        // Collect paired values
        foreach ($targetData as $row => $targetValue) {
            if (isset($sourceData[$row]) && is_numeric($targetValue) && is_numeric($sourceData[$row])) {
                $targetValues[] = (float)$targetValue;
                $sourceValues[] = (float)$sourceData[$row];
                $pairs[] = [
                    'x' => (float)$sourceData[$row],
                    'y' => (float)$targetValue
                ];
            }
        }
        
        $n = count($targetValues);
        
        // Calculate correlation coefficient (Pearson)
        $correlation = 0;
        
        if ($n > 1) {
            $targetMean = array_sum($targetValues) / $n;
            $sourceMean = array_sum($sourceValues) / $n;
            
            $targetVariance = 0;
            $sourceVariance = 0;
            $covariance = 0;
            
            for ($i = 0; $i < $n; $i++) {
                $targetDiff = $targetValues[$i] - $targetMean;
                $sourceDiff = $sourceValues[$i] - $sourceMean;
                
                $targetVariance += pow($targetDiff, 2);
                $sourceVariance += pow($sourceDiff, 2);
                $covariance += $targetDiff * $sourceDiff;
            }
            
            if ($targetVariance > 0 && $sourceVariance > 0) {
                $correlation = $covariance / (sqrt($targetVariance) * sqrt($sourceVariance));
            }
        }
        
        return [
            'coefficient' => $correlation,
            'strength' => $this->getCorrelationStrength($correlation),
            'sample_count' => $n,
            'sample_pairs' => array_slice($pairs, 0, min(50, $n)) // Limit to 50 data points
        ];
    }
    
    /**
     * Get descriptive correlation strength
     * 
     * @param float $correlation Correlation coefficient
     * @return string Description of correlation strength
     */
    private function getCorrelationStrength(float $correlation): string
    {
        $absCorrelation = abs($correlation);
        
        if ($absCorrelation < 0.1) {
            return 'negligible';
        } elseif ($absCorrelation < 0.3) {
            return 'weak';
        } elseif ($absCorrelation < 0.5) {
            return 'moderate';
        } elseif ($absCorrelation < 0.7) {
            return 'strong';
        } else {
            return 'very strong';
        }
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