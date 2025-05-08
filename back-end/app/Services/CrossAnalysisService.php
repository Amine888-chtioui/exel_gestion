<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class CrossAnalysisService
{
    /**
     * Analyze the relationship between two columns
     *
     * @param string $filePath Path to the Excel file
     * @param string $sheetName Sheet name
     * @param string $targetColumn Target column header (typically numeric)
     * @param string $sourceColumn Source column header
     * @return array Analysis results
     */
    public function analyzeColumns(string $filePath, string $sheetName, string $targetColumn, string $sourceColumn): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName($sheetName);
        
        if (!$sheet) {
            throw new \Exception("Sheet '$sheetName' not found in the file");
        }
        
        // Find column indices
        $headers = [];
        $targetColumnIndex = null;
        $sourceColumnIndex = null;
        
        $rowIterator = $sheet->getRowIterator(1, 1); // Just the header row
        foreach ($rowIterator as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            
            foreach ($cellIterator as $cell) {
                $column = $cell->getColumn();
                $columnIndex = Coordinate::columnIndexFromString($column);
                $value = $cell->getValue();
                
                $headers[$columnIndex] = $value;
                
                if ($value === $targetColumn) {
                    $targetColumnIndex = $columnIndex;
                }
                
                if ($value === $sourceColumn) {
                    $sourceColumnIndex = $columnIndex;
                }
            }
        }
        
        if ($targetColumnIndex === null || $sourceColumnIndex === null) {
            throw new \Exception("One or both columns not found in the sheet");
        }
        
        // Read target and source data
        $targetData = [];
        $sourceData = [];
        $rowCount = $sheet->getHighestDataRow();
        
        for ($rowIndex = 2; $rowIndex <= $rowCount; $rowIndex++) {
            $targetCell = $sheet->getCellByColumnAndRow($targetColumnIndex, $rowIndex);
            $sourceCell = $sheet->getCellByColumnAndRow($sourceColumnIndex, $rowIndex);
            
            $targetValue = $targetCell->getValue();
            $sourceValue = $sourceCell->getValue();
            
            // Skip if any is empty
            if ($targetValue === null || $sourceValue === null || $targetValue === '' || $sourceValue === '') {
                continue;
            }
            
            // Handle calculated values for formulas
            if ($targetCell->isFormula()) {
                $targetValue = $targetCell->getCalculatedValue();
            }
            
            if ($sourceCell->isFormula()) {
                $sourceValue = $sourceCell->getCalculatedValue();
            }
            
            $targetData[] = $targetValue;
            $sourceData[] = $sourceValue;
        }
        
        // Determine data types
        $targetType = $this->determineDataType($targetData);
        $sourceType = $this->determineDataType($sourceData);
        
        $result = [
            'target_column' => $targetColumn,
            'source_column' => $sourceColumn,
            'target_type' => $targetType,
            'source_type' => $sourceType,
            'sample_count' => count($targetData)
        ];
        
        // Perform appropriate analysis based on data types
        if ($targetType === 'numeric') {
            if ($sourceType === 'numeric') {
                // Correlation analysis for two numeric columns
                $result['correlation'] = $this->calculateCorrelation($targetData, $sourceData);
            } else {
                // Category-based analysis for numeric target and categorical source
                $result['category_stats'] = $this->calculateCategoryStats($targetData, $sourceData);
            }
        }
        
        return $result;
    }
    
    /**
     * Generate a correlation matrix for numeric columns
     *
     * @param string $filePath Path to the Excel file
     * @param string $sheetName Sheet name
     * @param array $selectedColumns Specific columns to include (empty for all numeric)
     * @param float $minCorrelation Minimum correlation threshold to include
     * @return array Correlation matrix
     */
    public function generateCorrelationMatrix(
        string $filePath, 
        string $sheetName, 
        array $selectedColumns = [],
        float $minCorrelation = 0
    ): array {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName($sheetName);
        
        if (!$sheet) {
            throw new \Exception("Sheet '$sheetName' not found in the file");
        }
        
        // Get headers and find numeric columns
        $headers = [];
        $numericColumns = [];
        $columnData = [];
        
        // Read header row
        $rowIterator = $sheet->getRowIterator(1, 1);
        foreach ($rowIterator as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            
            foreach ($cellIterator as $cell) {
                $column = $cell->getColumn();
                $columnIndex = Coordinate::columnIndexFromString($column);
                $value = $cell->getValue();
                
                $headers[$columnIndex] = $value;
                
                // Initialize column data array
                if (empty($selectedColumns) || in_array($value, $selectedColumns)) {
                    $columnData[$columnIndex] = [];
                }
            }
        }
        
        // Read data
        $rowCount = $sheet->getHighestDataRow();
        foreach ($columnData as $columnIndex => $data) {
            for ($rowIndex = 2; $rowIndex <= $rowCount; $rowIndex++) {
                $cell = $sheet->getCellByColumnAndRow($columnIndex, $rowIndex);
                $value = $cell->getValue();
                
                if ($cell->isFormula()) {
                    $value = $cell->getCalculatedValue();
                }
                
                $columnData[$columnIndex][$rowIndex] = $value;
            }
            
            // Check if column is numeric
            $dataType = $this->determineDataType(array_values($columnData[$columnIndex]));
            if ($dataType === 'numeric') {
                $numericColumns[$columnIndex] = $headers[$columnIndex];
            } else {
                unset($columnData[$columnIndex]);
            }
        }
        
        // Generate correlation matrix
        $matrix = [
            'columns' => array_values($numericColumns),
            'correlations' => []
        ];
        
        foreach ($numericColumns as $col1Index => $col1Name) {
            $matrixRow = [];
            
            foreach ($numericColumns as $col2Index => $col2Name) {
                if ($col1Index === $col2Index) {
                    // Perfect correlation with self
                    $matrixRow[] = 1;
                } else {
                    // Filter out non-numeric and paired empty values
                    $validPairs = [];
                    foreach ($columnData[$col1Index] as $rowIndex => $val1) {
                        if (isset($columnData[$col2Index][$rowIndex]) && 
                            is_numeric($val1) && 
                            is_numeric($columnData[$col2Index][$rowIndex])) {
                            $validPairs[] = [
                                (float) $val1,
                                (float) $columnData[$col2Index][$rowIndex]
                            ];
                        }
                    }
                    
                    $col1Values = array_column($validPairs, 0);
                    $col2Values = array_column($validPairs, 1);
                    
                    $correlation = $this->calculateCorrelationCoefficient($col1Values, $col2Values);
                    
                    // Only include correlations above threshold
                    if (abs($correlation) >= $minCorrelation) {
                        $matrixRow[] = $correlation;
                    } else {
                        $matrixRow[] = 0; // Set to zero correlations below threshold
                    }
                }
            }
            
            $matrix['correlations'][] = $matrixRow;
        }
        
        return $matrix;
    }
    
    /**
     * Generate a pivot table
     *
     * @param string $filePath Path to the Excel file
     * @param string $sheetName Sheet name
     * @param string $rowColumn Column to use for row labels
     * @param string $columnColumn Column to use for column labels
     * @param string $valueColumn Column to use for values
     * @param string $aggregation Aggregation function (sum, avg, count, min, max, median)
     * @return array Pivot table data
     */
    public function generatePivotTable(
        string $filePath,
        string $sheetName,
        string $rowColumn,
        string $columnColumn,
        string $valueColumn,
        string $aggregation = 'sum'
    ): array {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName($sheetName);
        
        if (!$sheet) {
            throw new \Exception("Sheet '$sheetName' not found in the file");
        }
        
        // Find column indices
        $headers = [];
        $rowColIndex = null;
        $colColIndex = null;
        $valueColIndex = null;
        
        $rowIterator = $sheet->getRowIterator(1, 1);
        foreach ($rowIterator as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            
            foreach ($cellIterator as $cell) {
                $column = $cell->getColumn();
                $columnIndex = Coordinate::columnIndexFromString($column);
                $value = $cell->getValue();
                
                $headers[$columnIndex] = $value;
                
                if ($value === $rowColumn) {
                    $rowColIndex = $columnIndex;
                }
                
                if ($value === $columnColumn) {
                    $colColIndex = $columnIndex;
                }
                
                if ($value === $valueColumn) {
                    $valueColIndex = $columnIndex;
                }
            }
        }
        
        if ($rowColIndex === null || $colColIndex === null || $valueColIndex === null) {
            throw new \Exception("One or more required columns not found in the sheet");
        }
        
        // Read data
        $rowCount = $sheet->getHighestDataRow();
        $pivotData = [];
        $rowLabels = [];
        $colLabels = [];
        
        for ($rowIndex = 2; $rowIndex <= $rowCount; $rowIndex++) {
            $rowLabel = (string) $sheet->getCellByColumnAndRow($rowColIndex, $rowIndex)->getValue();
            $colLabel = (string) $sheet->getCellByColumnAndRow($colColIndex, $rowIndex)->getValue();
            $value = $sheet->getCellByColumnAndRow($valueColIndex, $rowIndex)->getValue();
            
            // Skip if any is empty
            if ($rowLabel === '' || $colLabel === '' || $value === null || $value === '') {
                continue;
            }
            
            // Handle calculated values for formulas
            if ($sheet->getCellByColumnAndRow($valueColIndex, $rowIndex)->isFormula()) {
                $value = $sheet->getCellByColumnAndRow($valueColIndex, $rowIndex)->getCalculatedValue();
            }
            
            // Skip if value is not numeric
            if (!is_numeric($value)) {
                continue;
            }
            
            // Add to row and column labels
            if (!in_array($rowLabel, $rowLabels)) {
                $rowLabels[] = $rowLabel;
            }
            
            if (!in_array($colLabel, $colLabels)) {
                $colLabels[] = $colLabel;
            }
            
            // Initialize pivot cell if needed
            if (!isset($pivotData[$rowLabel][$colLabel])) {
                $pivotData[$rowLabel][$colLabel] = [
                    'values' => [],
                    'result' => null
                ];
            }
            
            $pivotData[$rowLabel][$colLabel]['values'][] = (float) $value;
        }
        
        // Sort labels
        sort($rowLabels);
        sort($colLabels);
        
        // Apply aggregation
        foreach ($pivotData as $rowLabel => &$rowData) {
            foreach ($rowData as $colLabel => &$cell) {
                $values = $cell['values'];
                
                switch ($aggregation) {
                    case 'sum':
                        $cell['result'] = array_sum($values);
                        break;
                    case 'avg':
                        $cell['result'] = array_sum($values) / count($values);
                        break;
                    case 'count':
                        $cell['result'] = count($values);
                        break;
                    case 'min':
                        $cell['result'] = min($values);
                        break;
                    case 'max':
                        $cell['result'] = max($values);
                        break;
                    case 'median':
                        sort($values);
                        $count = count($values);
                        $middle = floor($count / 2);
                        $cell['result'] = ($count % 2 === 0) 
                            ? ($values[$middle - 1] + $values[$middle]) / 2 
                            : $values[$middle];
                        break;
                }
            }
        }
        
        // Format results for frontend
        $result = [
            'row_labels' => $rowLabels,
            'col_labels' => $colLabels,
            'data' => []
        ];
        
        foreach ($rowLabels as $rowLabel) {
            $row = ['row_label' => $rowLabel];
            
            foreach ($colLabels as $colLabel) {
                if (isset($pivotData[$rowLabel][$colLabel])) {
                    $row[$colLabel] = $pivotData[$rowLabel][$colLabel]['result'];
                } else {
                    $row[$colLabel] = null;
                }
            }
            
            $result['data'][] = $row;
        }
        
        // Add summary statistics
        $result['summary'] = [
            'aggregation' => $aggregation,
            'row_column' => $rowColumn,
            'column_column' => $columnColumn,
            'value_column' => $valueColumn
        ];
        
        return $result;
    }
    
    /**
     * Determine the predominant data type in an array
     *
     * @param array $data Array of values
     * @return string Data type (numeric, text, date, mixed)
     */
    private function determineDataType(array $data): string
    {
        $numericCount = 0;
        $textCount = 0;
        $dateCount = 0;
        $totalValues = count($data);
        
        if ($totalValues === 0) {
            return 'mixed';
        }
        
        foreach ($data as $value) {
            if (is_numeric($value)) {
                $numericCount++;
            } else {
                // Check if it might be a date
                if (strtotime($value) !== false) {
                    $dateCount++;
                } else {
                    $textCount++;
                }
            }
        }
        
        // Determine predominant type (>80%)
        if ($numericCount / $totalValues > 0.8) {
            return 'numeric';
        } elseif ($textCount / $totalValues > 0.8) {
            return 'text';
        } elseif ($dateCount / $totalValues > 0.8) {
            return 'date';
        } else {
            return 'mixed';
        }
    }
    
    /**
     * Calculate correlation between two columns
     *
     * @param array $targetData Target column data
     * @param array $sourceData Source column data
     * @return array Correlation statistics
     */
    private function calculateCorrelation(array $targetData, array $sourceData): array
    {
        $pairs = [];
        
        // Convert to numeric and create pairs
        for ($i = 0; $i < count($targetData); $i++) {
            if (is_numeric($targetData[$i]) && is_numeric($sourceData[$i])) {
                $pairs[] = [
                    'x' => (float) $sourceData[$i],
                    'y' => (float) $targetData[$i]
                ];
            }
        }
        
        $numericTargetData = array_map('floatval', array_filter($targetData, 'is_numeric'));
        $numericSourceData = array_map('floatval', array_filter($sourceData, 'is_numeric'));
        
        $coefficient = $this->calculateCorrelationCoefficient($numericTargetData, $numericSourceData);
        
        return [
            'coefficient' => $coefficient,
            'strength' => $this->getCorrelationStrength($coefficient),
            'sample_count' => count($pairs),
            'sample_pairs' => array_slice($pairs, 0, min(50, count($pairs))) // Limit to 50 data points
        ];
    }
    
    /**
     * Calculate statistics grouped by categories
     *
     * @param array $targetData Target column data (numeric)
     * @param array $sourceData Source column data (categorical)
     * @return array Statistics by category
     */
    private function calculateCategoryStats(array $targetData, array $sourceData): array
    {
        $categories = [];
        
        // Group by categories
        for ($i = 0; $i < count($targetData); $i++) {
            if (!is_numeric($targetData[$i])) {
                continue;
            }
            
            $category = (string) $sourceData[$i];
            $value = (float) $targetData[$i];
            
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
        $categoryStats = [];
        $totalCount = array_sum(array_column($categories, 'count'));
        
        foreach ($categories as $category => $stats) {
            if ($stats['count'] > 0) {
                $avg = $stats['sum'] / $stats['count'];
                $variance = 0;
                
                // Calculate variance
                foreach ($stats['values'] as $value) {
                    $variance += pow($value - $avg, 2);
                }
                $variance = ($stats['count'] > 1) ? $variance / $stats['count'] : 0;
                
                $categoryStats[] = [
                    'category' => $category,
                    'count' => $stats['count'],
                    'percent' => round(($stats['count'] / $totalCount) * 100, 2),
                    'sum' => $stats['sum'],
                    'avg' => $avg,
                    'min' => $stats['min'],
                    'max' => $stats['max'],
                    'variance' => $variance,
                    'std_dev' => sqrt($variance)
                ];
            }
        }
        
        // Sort by count (descending)
        usort($categoryStats, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        // Limit to top 10 categories for frontend performance
        return array_slice($categoryStats, 0, 10);
    }
    
    /**
     * Calculate the Pearson correlation coefficient between two arrays
     *
     * @param array $x First array of numeric values
     * @param array $y Second array of numeric values
     * @return float Correlation coefficient
     */
    private function calculateCorrelationCoefficient(array $x, array $y): float
    {
        $n = count($x);
        
        // Need at least two points for correlation
        if ($n < 2 || $n !== count($y)) {
            return 0;
        }
        
        // Calculate means
        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;
        
        // Calculate sums
        $sumXY = 0;
        $sumX2 = 0;
        $sumY2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $xDiff = $x[$i] - $meanX;
            $yDiff = $y[$i] - $meanY;
            
            $sumXY += $xDiff * $yDiff;
            $sumX2 += $xDiff * $xDiff;
            $sumY2 += $yDiff * $yDiff;
        }
        
        // Avoid division by zero
        if ($sumX2 === 0 || $sumY2 === 0) {
            return 0;
        }
        
        return $sumXY / sqrt($sumX2 * $sumY2);
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
}