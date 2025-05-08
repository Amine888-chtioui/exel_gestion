<?php

namespace App\Imports;

use App\Models\ProductionStop;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Carbon\Carbon;

class ProductionStopsImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     * @return ProductionStop
     */
    public function model(array $row)
    {
        // Parse date
        $date = null;
        if (isset($row['date']) && $row['date']) {
            try {
                // If it's an Excel date value (numeric timestamp)
                if (is_numeric($row['date'])) {
                    $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['date'])->format('Y-m-d');
                } else {
                    // Try common date formats
                    $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d'];
                    foreach ($formats as $format) {
                        try {
                            $date = Carbon::createFromFormat($format, $row['date'])->format('Y-m-d');
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
        
        // Extract machine
        $machine = $row['machine'] ?? null;
        
        // Get machine group
        $machineGroup = null;
        if ($machine) {
            $parts = explode(' ', trim($machine));
            if (count($parts) > 0) {
                $machineGroup = $parts[0];
            }
        }
        
        // Extract komax model
        $komaxModel = null;
        if (isset($row['komax_model']) && $row['komax_model']) {
            $komaxModel = $row['komax_model'];
        } elseif (isset($row['wo_name']) && strpos($row['wo_name'], 'Komax') !== false) {
            $komaxModel = $row['wo_name'];
        }
        
        // Parse stop time
        $stopTime = 0.0;
        if (isset($row['stop_time'])) {
            if (is_numeric($row['stop_time'])) {
                $stopTime = (float) $row['stop_time'];
            } else {
                // Handle string format like "1,00" or "1.00"
                $normalizedTime = str_replace(',', '.', $row['stop_time']);
                if (is_numeric($normalizedTime)) {
                    $stopTime = (float) $normalizedTime;
                }
            }
        }
        
        return new ProductionStop([
            'machine' => $machine,
            'machine_group' => $machineGroup,
            'ws_key' => $row['ws_key'] ?? null,
            'stop_time' => $stopTime,
            'wo_key' => $row['wo_key'] ?? null,
            'wo_name' => $row['wo_name'] ?? null,
            'code1' => $row['code1'] ?? null,
            'code2' => $row['code2'] ?? null,
            'code3' => $row['code3'] ?? null,
            'date' => $date,
            'komax_model' => $komaxModel,
            'is_completed' => true, // Default to completed
        ]);
    }
}