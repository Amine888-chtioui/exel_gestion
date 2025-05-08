<?php

namespace App\Imports;

use App\Models\ProductionStop;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Validators\ValidationException;
use Carbon\Carbon;
use Throwable;

class ProductionStopsImport implements ToModel, WithHeadingRow, SkipsEmptyRows, SkipsOnError, SkipsOnFailure
{
    /**
     * @var array
     */
    protected $failures = [];
    
    /**
     * @param array $row
     * @return ProductionStop|null
     */
    public function model(array $row)
    {
        // Try to find machine in various possible column names
        $machine = $this->findValueInRow($row, ['machine', 'mo_key', 'alpha']);
        
        // Skip if machine is not found
        if (empty($machine)) {
            return null;
        }
        
        // Map other fields with possible variations
        $wsKey = $this->findValueInRow($row, ['ws_key', 'workshop']);
        $stopTime = $this->parseStopTime($row);
        $woKey = $this->findValueInRow($row, ['wo_key', 'wo key', 'work order key']);
        $woName = $this->findValueInRow($row, ['wo_name', 'wo name', 'work order name']);
        $code1 = $this->findValueInRow($row, ['code1', 'code1_key', 'code 1']);
        $code2 = $this->findValueInRow($row, ['code2', 'code2_key', 'code 2']);
        $code3 = $this->findValueInRow($row, ['code3', 'code3_key', 'code 3']);
        $date = $this->parseDate($row);
        $komaxModel = $this->findValueInRow($row, ['komax_model', 'komax model', 'model']);
        
        // If komax_model not found but wo_name contains 'Komax', use that
        if (empty($komaxModel) && !empty($woName) && strpos($woName, 'Komax') !== false) {
            $komaxModel = $woName;
        }
        
        // Get machine group
        $machineGroup = $this->getMachineGroup($machine);
        
        // Create the record
        return new ProductionStop([
            'machine' => $machine,
            'machine_group' => $machineGroup,
            'ws_key' => $wsKey,
            'stop_time' => $stopTime,
            'wo_key' => $woKey,
            'wo_name' => $woName,
            'code1' => $code1,
            'code2' => $code2,
            'code3' => $code3,
            'date' => $date,
            'komax_model' => $komaxModel,
            'is_completed' => true,
        ]);
    }
    
    /**
     * Find a value in a row by checking multiple possible column names
     */
    private function findValueInRow(array $row, array $possibleColumns)
    {
        foreach ($possibleColumns as $column) {
            // Check for exact match
            if (isset($row[$column]) && !empty($row[$column])) {
                return $row[$column];
            }
            
            // Try case-insensitive match
            foreach ($row as $key => $value) {
                if (strtolower($key) === strtolower($column) && !empty($value)) {
                    return $value;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Parse stop time from multiple possible columns
     */
    private function parseStopTime(array $row)
    {
        $value = $this->findValueInRow($row, ['stop_time', 'stop_t', 'time']);
        
        if ($value !== null) {
            if (is_numeric($value)) {
                return (float) $value;
            } else {
                // Handle string format like "1,00" or "1.00"
                $normalizedTime = str_replace(',', '.', $value);
                if (is_numeric($normalizedTime)) {
                    return (float) $normalizedTime;
                }
            }
        }
        
        return 0.0;
    }
    
    /**
     * Parse date from various formats
     */
    private function parseDate(array $row)
    {
        $value = $this->findValueInRow($row, ['date', 'from_date', 'start date']);
        
        if ($value !== null) {
            try {
                // If it's an Excel date value (numeric timestamp)
                if (is_numeric($value)) {
                    return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
                } else {
                    // Try common date formats
                    $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d'];
                    foreach ($formats as $format) {
                        try {
                            return Carbon::createFromFormat($format, $value)->format('Y-m-d');
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Default to current date if parsing fails
            }
        }
        
        return now()->format('Y-m-d');
    }
    
    /**
     * Extract machine group from machine name
     */
    private function getMachineGroup($machine)
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
    
    /**
     * @param Throwable $e
     */
    public function onError(Throwable $e)
    {
        // Log the error but continue importing
        \Log::error('Row import error: ' . $e->getMessage());
    }
    
    /**
     * @param Failure[] $failures
     */
    public function onFailure(Failure ...$failures)
    {
        // Store all validation failures but continue importing
        foreach ($failures as $failure) {
            \Log::warning('Row ' . $failure->row() . ' failed validation. ' . implode(', ', $failure->errors()));
            $this->failures[] = $failure;
        }
    }
    
    /**
     * Get all failures encountered during import
     */
    public function getFailures()
    {
        return $this->failures;
    }
}