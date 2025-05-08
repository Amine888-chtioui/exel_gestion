<?php

namespace App\Http\Controllers;

use App\Models\ProductionStop;
use App\Imports\ProductionStopsImport;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class ProductionStopController extends Controller
{
    /**
     * Get filtered production stops data
     */
    public function index(Request $request)
    {
        $query = ProductionStop::query();

        // Apply date filtering
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->filterByDateRange($request->start_date, $request->end_date);
        } else if ($request->has('year')) {
            $year = $request->year;
            
            if ($request->has('month')) {
                $month = $request->month;
                
                if ($request->has('week')) {
                    // Filter by week within month
                    $startOfWeek = Carbon::createFromDate($year, $month, 1)
                        ->startOfMonth()
                        ->addWeeks($request->week - 1)
                        ->startOfWeek();
                    
                    $endOfWeek = clone $startOfWeek;
                    $endOfWeek->endOfWeek();
                    
                    $query->filterByDateRange($startOfWeek, $endOfWeek);
                } else if ($request->has('day')) {
                    // Filter by specific day
                    $date = Carbon::createFromDate($year, $month, $request->day);
                    $query->whereDate('date', $date);
                } else {
                    // Filter by month
                    $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                    $endOfMonth = clone $startOfMonth;
                    $endOfMonth->endOfMonth();
                    
                    $query->filterByDateRange($startOfMonth, $endOfMonth);
                }
            } else {
                // Filter by year
                $startOfYear = Carbon::createFromDate($year, 1, 1)->startOfYear();
                $endOfYear = clone $startOfYear;
                $endOfYear->endOfYear();
                
                $query->filterByDateRange($startOfYear, $endOfYear);
            }
        }

        // Apply machine filtering
        if ($request->has('machine')) {
            $query->filterByMachine($request->machine);
        }

        // Apply code filtering
        if ($request->has('code1')) {
            $query->filterByCode('code1', $request->code1);
        }
        
        if ($request->has('code2')) {
            $query->filterByCode('code2', $request->code2);
        }
        
        if ($request->has('code3')) {
            $query->filterByCode('code3', $request->code3);
        }

        // Apply pagination if requested
        $perPage = $request->per_page ?? 50;
        if ($request->has('page')) {
            $data = $query->paginate($perPage);
        } else {
            $data = $query->get();
        }

        return response()->json($data);
    }

    /**
     * Get summary statistics for dashboard
     */
    public function dashboardStats(Request $request)
    {
        $query = ProductionStop::query();

        // Apply date filtering
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->filterByDateRange($request->start_date, $request->end_date);
        } else if ($request->has('year')) {
            $year = $request->year;
            $startOfYear = Carbon::createFromDate($year, 1, 1)->startOfYear();
            $endOfYear = clone $startOfYear;
            $endOfYear->endOfYear();
            
            $query->filterByDateRange($startOfYear, $endOfYear);
        }

        // Get total stop time by machine
        $stopTimeByMachine = $query->clone()
            ->selectRaw('machine, SUM(stop_time) as total_stop_time')
            ->groupBy('machine')
            ->get();

        // Get total stop time by code1 (mechanical, electrical, etc.)
        $stopTimeByCode1 = $query->clone()
            ->selectRaw('code1, SUM(stop_time) as total_stop_time')
            ->groupBy('code1')
            ->get();

        // Get total stop time by code2 (issue type)
        $stopTimeByCode2 = $query->clone()
            ->selectRaw('code2, SUM(stop_time) as total_stop_time')
            ->groupBy('code2')
            ->get();

        // Get total stop time by code3 (component)
        $stopTimeByCode3 = $query->clone()
            ->selectRaw('code3, SUM(stop_time) as total_stop_time')
            ->groupBy('code3')
            ->get();

        // Get monthly distribution for the year
        $monthlyDistribution = [];
        if ($request->has('year')) {
            $monthlyDistribution = $this->getMonthlyDistribution($request->year);
        }

        return response()->json([
            'stop_time_by_machine' => $stopTimeByMachine,
            'stop_time_by_code1' => $stopTimeByCode1,
            'stop_time_by_code2' => $stopTimeByCode2,
            'stop_time_by_code3' => $stopTimeByCode3,
            'monthly_distribution' => $monthlyDistribution,
            'total_stop_time' => $query->sum('stop_time'),
            'total_stops_count' => $query->count(),
        ]);
    }

    /**
     * Get monthly distribution for dashboard
     * 
     * @param int $year
     * @return array
     */
    private function getMonthlyDistribution($year)
    {
        $monthlyDistribution = [];
        for ($month = 1; $month <= 12; $month++) {
            $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endOfMonth = clone $startOfMonth;
            $endOfMonth->endOfMonth();
            
            $totalStopTime = ProductionStop::query()
                ->filterByDateRange($startOfMonth, $endOfMonth)
                ->sum('stop_time');
            
            $monthlyDistribution[] = [
                'month' => $month,
                'month_name' => $startOfMonth->format('F'),
                'total_stop_time' => $totalStopTime,
            ];
        }
        return $monthlyDistribution;
    }

    /**
     * Import production stops from Excel file
     */
    public function importExcel(Request $request)
{
    $validator = Validator::make($request->all(), [
        'file' => 'required|file|mimes:xlsx,xls,csv',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    try {
        $import = new ProductionStopsImport();
        Excel::import($import, $request->file('file'));
        
        // If there were failures but some rows were imported successfully
        if (count($import->getFailures()) > 0) {
            $failedRows = array_map(function($failure) {
                return 'Row ' . $failure->row() . ': ' . implode(', ', $failure->errors());
            }, $import->getFailures());
            
            return response()->json([
                'message' => 'Data imported with some errors',
                'warnings' => $failedRows
            ], 200);
        }
        
        return response()->json([
            'message' => 'Data imported successfully'
        ]);
    } catch (\Exception $e) {
        // Log the full error for debugging
        \Log::error('Import error: ' . $e->getMessage());
        
        return response()->json([
            'message' => 'Error importing data',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Get distinct values for filters
     */
    public function getFilterOptions()
    {
        return response()->json([
            'machines' => ProductionStop::distinct()->pluck('machine'),
            'code1_values' => ProductionStop::distinct()->pluck('code1'),
            'code2_values' => ProductionStop::distinct()->pluck('code2'),
            'code3_values' => ProductionStop::distinct()->pluck('code3'),
            'years' => ProductionStop::selectRaw('YEAR(date) as year')
                ->distinct()
                ->pluck('year')
                ->sort()
                ->values(),
        ]);
    }
}