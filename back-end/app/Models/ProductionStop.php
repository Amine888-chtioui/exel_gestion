<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine', // ALPHA 63, ALPHA 19, etc.
        'machine_group', // Used for grouping similar machines
        'ws_key', // Workshop key (CP 13706, CT 87088, etc.)
        'stop_time', // Time in hours (1.00, 7.17, etc.)
        'wo_key', // Work order key (683613, 683674, etc.)
        'wo_name', // Work order description
        'code1', // Main category code (1 Mechanical, 2 Electrical, etc.)
        'code2', // Issue type code (01 Breakage, 02 Wear, etc.)
        'code3', // Component code (012 Élément de Sécurité, etc.)
        'date', // Date of the stop
        'komax_model', // Model of the machine (Komax Alpha 355, etc.)
        'is_completed', // Whether the stop is resolved
    ];

    // For querying by date ranges
    protected $casts = [
        'date' => 'date',
        'stop_time' => 'float',
    ];

    // Relationships can be added here if needed
    // For example, if you want to relate to a Machine model
    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine', 'name');
    }

    // Scopes for filtering
    public function scopeFilterByDateRange($query, $startDate, $endDate)
    {
        if ($startDate && $endDate) {
            return $query->whereBetween('date', [$startDate, $endDate]);
        }
        return $query;
    }

    public function scopeFilterByMachine($query, $machine)
    {
        if ($machine) {
            return $query->where('machine', $machine);
        }
        return $query;
    }

    public function scopeFilterByCode($query, $codeType, $codeValue)
    {
        if ($codeType && $codeValue) {
            return $query->where($codeType, $codeValue);
        }
        return $query;
    }
}