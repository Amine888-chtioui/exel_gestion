<?php

namespace App\Http\Controllers;

use App\Models\ExcelFile;
use App\Services\ExcelAnalyzerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExcelFileController extends Controller
{
    protected $excelAnalyzer;

    public function __construct(ExcelAnalyzerService $excelAnalyzer)
    {
        $this->excelAnalyzer = $excelAnalyzer;
    }

    /**
     * Display a listing of uploaded excel files.
     */
    public function index()
    {
        $files = ExcelFile::orderBy('created_at', 'desc')->get();
        return response()->json($files);
    }

    /**
     * Store a newly uploaded excel file and analyze it.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
        ]);

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $filename = time() . '_' . $originalName;
            $path = $file->storeAs('excel_files', $filename, 'public');
            $fullPath = Storage::disk('public')->path($path);
            $size = $file->getSize();

            // Analyze Excel file
            $statistics = $this->excelAnalyzer->analyze($fullPath);

            // Store file information
            $excelFile = ExcelFile::create([
                'original_name' => $originalName,
                'filename' => $filename,
                'file_path' => $path,
                'size' => $size,
                'sheet_count' => $statistics['sheet_count'],
                'statistics' => $statistics,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded and analyzed successfully',
                'file' => $excelFile,
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified excel file with its statistics.
     */
    public function show($id)
    {
        $file = ExcelFile::findOrFail($id);
        return response()->json($file);
    }

    /**
     * Remove the specified excel file from storage.
     */
    public function destroy($id)
    {
        $file = ExcelFile::findOrFail($id);
        
        // Delete the file from storage
        if (Storage::disk('public')->exists($file->file_path)) {
            Storage::disk('public')->delete($file->file_path);
        }
        
        // Delete the database record
        $file->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'File deleted successfully'
        ]);
    }
}