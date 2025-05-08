<?php

namespace App\Http\Controllers;

use App\Models\ExcelFile;
use App\Services\ExcelAnalyzerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelFileController extends Controller
{
    protected $excelAnalyzer;

    public function __construct(ExcelAnalyzerService $excelAnalyzer)
    {
        $this->excelAnalyzer = $excelAnalyzer;
    }

    /**
     * Liste tous les fichiers Excel importés
     */
    public function index()
    {
        $files = ExcelFile::orderBy('created_at', 'desc')->get();
        return response()->json($files);
    }

    /**
     * Stocke un nouveau fichier Excel et l'analyse
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
        ]);

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $size = $file->getSize();
            
            // Générer un nom de fichier unique
            $filename = Str::random(40) . '.' . $extension;
            $path = $file->storeAs('excel_files', $filename, 'public');
            
            // Analyser le fichier Excel
            $filePath = Storage::disk('public')->path($path);
            $spreadsheet = IOFactory::load($filePath);
            $sheetCount = $spreadsheet->getSheetCount();
            
            // Exécuter l'analyse complète
            $statistics = $this->excelAnalyzer->analyze($filePath);
            
            // Enregistrer les données du fichier
            $excelFile = ExcelFile::create([
                'original_name' => $originalName,
                'filename' => $filename,
                'file_path' => $path,
                'size' => $size,
                'sheet_count' => $sheetCount,
                'statistics' => $statistics,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Fichier importé avec succès',
                'file' => $excelFile
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'importation du fichier : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère les détails d'un fichier Excel spécifique
     */
    public function show($id)
    {
        try {
            $file = ExcelFile::findOrFail($id);
            return response()->json($file);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fichier non trouvé'
            ], 404);
        }
    }

    /**
     * Supprime un fichier Excel
     */
    public function destroy($id)
    {
        try {
            $file = ExcelFile::findOrFail($id);
            
            // Supprimer le fichier physique
            if (Storage::disk('public')->exists($file->file_path)) {
                Storage::disk('public')->delete($file->file_path);
            }
            
            // Supprimer l'enregistrement
            $file->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Fichier supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du fichier : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Filtre les données d'un fichier Excel
     */
    public function filterData(Request $request)
    {
        $request->validate([
            'file_id' => 'required|integer',
            'sheet_name' => 'required|string',
            'filters' => 'required|array'
        ]);

        try {
            $fileId = $request->input('file_id');
            $sheetName = $request->input('sheet_name');
            $filters = $request->input('filters');
            
            $file = ExcelFile::findOrFail($fileId);
            $filePath = Storage::disk('public')->path($file->file_path);
            
            // Logique de filtrage à implémenter ici
            // Par exemple, lire les données avec PhpSpreadsheet et appliquer les filtres
            
            return response()->json([
                'success' => true,
                'message' => 'Données filtrées avec succès',
                'data' => [] // Renvoyer les données filtrées
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du filtrage des données : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Groupe les données d'un fichier Excel
     */
    public function groupData(Request $request)
    {
        $request->validate([
            'file_id' => 'required|integer',
            'sheet_name' => 'required|string',
            'group_by' => 'required|string',
            'aggregation' => 'required|string|in:sum,avg,count,min,max'
        ]);

        try {
            // Logique de groupement à implémenter ici
            
            return response()->json([
                'success' => true,
                'message' => 'Données groupées avec succès',
                'data' => [] // Renvoyer les données groupées
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du groupement des données : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exporte les données en CSV
     */
    public function exportCsv($fileId, $sheetName)
    {
        try {
            $file = ExcelFile::findOrFail($fileId);
            $filePath = Storage::disk('public')->path($file->file_path);
            
            // Logique d'export CSV à implémenter ici
            
            // Exemple de création d'un fichier CSV
            $outputPath = storage_path('app/public/exports/' . Str::random(10) . '.csv');
            // Code pour générer le CSV...
            
            return response()->download($outputPath)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export CSV : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exporte les données en Excel
     */
    public function exportExcel($fileId, $sheetName)
    {
        try {
            // Logique d'export Excel similaire à exportCsv
            return response()->json([
                'success' => false,
                'message' => 'Fonctionnalité non implémentée'
            ], 501);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export Excel : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcule des métriques personnalisées
     */
    public function calculateCustomMetrics(Request $request)
    {
        // Implémentation à fournir
        return response()->json([
            'success' => false,
            'message' => 'Fonctionnalité non implémentée'
        ], 501);
    }

    /**
     * Exporte des graphiques
     */
    public function exportCharts(Request $request)
    {
        // Implémentation à fournir
        return response()->json([
            'success' => false,
            'message' => 'Fonctionnalité non implémentée'
        ], 501);
    }

    /**
     * Récupère la configuration
     */
    public function getConfig()
    {
        // Implémentation à fournir
        return response()->json([
            'success' => true,
            'config' => [
                'max_file_size' => 10240, // 10MB
                'allowed_extensions' => ['xlsx', 'xls'],
                'features_enabled' => [
                    'export_csv' => true,
                    'export_excel' => true,
                    'correlation_analysis' => true,
                    'pivot_tables' => true
                ]
            ]
        ]);
    }

    /**
     * Met à jour la configuration
     */
    public function updateConfig(Request $request)
    {
        // Implémentation à fournir
        return response()->json([
            'success' => false,
            'message' => 'Fonctionnalité non implémentée'
        ], 501);
    }
}