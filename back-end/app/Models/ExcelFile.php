<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExcelFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_name',
        'filename',
        'file_path',
        'size',
        'sheet_count',
        'statistics',
    ];

    protected $casts = [
        'statistics' => 'array',
    ];
}