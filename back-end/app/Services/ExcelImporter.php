<?php

namespace App\Services;

use App\Models\ProductionStop;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelImporter
{
    /**
     * Import production stops from an Excel file
     *
     * @param string $filePath
     * @return int Number of imported rows
     */
    public static function importProductionStops($filePath)
    {
        // Load the Excel file
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Get the highest row number
        $highestRow = $worksheet->getHighestRow();
        
        // Get the highest column letter
        $highestColumn = $worksheet->getHighestColumn();
        
        // Convert highest column letter to column index
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        
        // Get headers from the first row
        $headers = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $headers[$col] = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
        }
        
        // Find column indexes based on headers
        $columnIndexes = self::findColumnIndexes($headers);
        
        $importedRows = 0;
        
        // Start from row 2 (skipping headers)
        for ($row = 2; $row <= $highestRow; $row++) {
            // Skip empty rows
            if (empty($worksheet->getCellByColumnAndRow(1, $row)->getValue())) {
                continue;
            }
            
            // Extract data from cells
            $rowData = [];
            foreach ($columnIndexes as $fieldName => $colIndex) {
                if ($colIndex) {
                    $rowData[$fieldName] = $worksheet->getCellByColumnAndRow($colIndex, $row)->getValue();
                }
            }
            
            // Create production stop record
            self::createProductionStop($rowData);
            $importedRows++;
        }
        
        return $importedRows;
    }
    
    /**
     * Find column indexes based on headers
     *
     * @param array $headers
     * @return array
     */
    private static function findColumnIndexes($headers)
    {
        $columnIndexes = [
            'machine' => null,
            'ws_key' => null,
            'stop_time' => null,
            'wo_key' => null,
            'wo_name' => null,
            'code1' => null,
            'code2' => null,
            'code3' => null,
            'date' => null,
            'komax_model' => null,
        ];
        
        foreach ($headers as $colIndex => $header) {
            $header = strtolower(trim($header));
            
            // Match headers with field names
            if (in_array($header, ['mo_key', 'machine', 'alpha'])) {
                $columnIndexes['machine'] = $colIndex;
            } elseif (in_array($header, ['ws_key', 'workshop'])) {
                $columnIndexes['ws_key'] = $colIndex;
            } elseif (in_array($header, ['stop_time', 'stop_t', 'time'])) {
                $columnIndexes['stop_time'] = $colIndex;
            } elseif (in_array($header, ['wo_key', 'wo key', 'work order key'])) {
                $columnIndexes['wo_key'] = $colIndex;
            } elseif (in_array($header, ['wo_name', 'wo name', 'work order name'])) {
                $columnIndexes['wo_name'] = $colIndex;
            } elseif (in_array($header, ['code1', 'code1_key', 'code 1'])) {
                $columnIndexes['code1'] = $colIndex;
            } elseif (in_array($header, ['code2', 'code2_key', 'code 2'])) {
                $columnIndexes['code2'] = $colIndex;
            } elseif (in_array($header, ['code3', 'code3_key', 'code 3'])) {
                $columnIndexes['code3'] = $colIndex;
            } elseif (in_array($header, ['from_date', 'date', 'start date'])) {
                $columnIndexes['date'] = $colIndex;
            } elseif (in_array($header, ['komax_model', 'komax model', 'model'])) {
                $columnIndexes['komax_model'] = $colIndex;
            }
        }
        
        return $columnIndexes;
    }
    
    /**
     * Create a production stop record
     *
     * @param array $data
     * @return ProductionStop
     */
    private static function createProductionStop($data)
    {
        // Extract machine from data
        $machine = $data['machine'] ?? null;
        
        // Parse date
        $date = null;
        if (isset($data['date']) && $data['date']) {
            try {
                // If it's an Excel date value (numeric timestamp)
                if (is_numeric($data['date'])) {
                    $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($data['date'])->format('Y-m-d');
                } else {
                    // Try common date formats
                    $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d'];
                    foreach ($formats as $format) {
                        try {
                            $date = Carbon::createFromFormat($format, $data['date'])->format('Y-m-d');
                            break;
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Default to current date if parsing fails
                $date = now()->format('Y-m-d');
            }
        } else {
            $date = now()->format('Y-m-d');
        }
        
        // Extract komax model
        $komaxModel = null;
        if (isset($data['komax_model']) && $data['komax_model']) {
            $komaxModel = $data['komax_model'];
        } elseif (isset($data['wo_name']) && strpos($data['wo_name'], 'Komax') !== false) {
            $komaxModel = $data['wo_name'];
        }
        
        // Create the record
        return ProductionStop::create([
            'machine' => $machine,
            'machine_group' => self::getMachineGroup($machine),
            'ws_key' => $data['ws_key'] ?? null,
            'stop_time' => self::parseStopTime($data['stop_time'] ?? 0),
            'wo_key' => $data['wo_key'] ?? null,
            'wo_name' => $data['wo_name'] ?? null,
            'code1' => $data['code1'] ?? null,
            'code2' => $data['code2'] ?? null,
            'code3' => $data['code3'] ?? null,
            'date' => $date,
            'komax_model' => $komaxModel,
            'is_completed' => true, // Default to completed
        ]);
    }
    
    /**
     * Parse stop time to ensure it's a float
     *
     * @param mixed $time
     * @return float
     */
    private static function parseStopTime($time)
    {
        if (is_numeric($time)) {
            return (float) $time;
        }
        
        // Handle string format like "1,00" or "1.00"
        $normalizedTime = str_replace(',', '.', $time);
        if (is_numeric($normalizedTime)) {
            return (float) $normalizedTime;
        }
        
        return 0.0;
    }
    
    /**
     * Extract machine group from machine name
     *
     * @param string|null $machine
     * @return string|null
     */
    private static function getMachineGroup($machine)
    {
        if (!$machine) {
            return null;
        }
        
        $parts = explode(' ', trim($machine));
        if (count($parts) > 0) {
            return $parts[0];
        }
        
        return null;
    }
}