<?php

namespace App\Http\Controllers;

use App\Models\ExcelFile;
use App\Services\ExcelAnalyzerService;
use App\Services\CrossAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CrossAnalysisController extends Controller
{
    protected $crossAnalysisService;
    protected $excelAnalyzer;

    public function __construct(CrossAnalysisService $crossAnalysisService, ExcelAnalyzerService $excelAnalyzer)
    {
        $this->crossAnalysisService = $crossAnalysisService;
        $this->excelAnalyzer = $excelAnalyzer;
    }

    /**
     * Get available columns for cross-analysis in a specific sheet
     */
    public function getAvailableColumns($fileId, $sheetName)
    {
        try {
            $file = ExcelFile::findOrFail($fileId);
            
            if (!$file->statistics || !isset($file->statistics['sheets'][$sheetName])) {
                return response()->json([
                    'success' => false,
                    'message' => "Feuille '$sheetName' non trouvée dans le fichier"
                ], 404);
            }
            
            $columns = $file->statistics['sheets'][$sheetName]['columns'];
            
            // Séparer les colonnes par type de données
            $numericColumns = [];
            $categoricalColumns = [];
            $dateColumns = [];
            $otherColumns = [];
            
            foreach ($columns as $header => $data) {
                switch ($data['data_type']) {
                    case 'numeric':
                        $numericColumns[] = [
                            'name' => $header,
                            'type' => 'numeric',
                            'stats' => isset($data['numeric']) ? [
                                'min' => $data['numeric']['min'],
                                'max' => $data['numeric']['max'],
                                'avg' => $data['numeric']['avg'],
                            ] : null
                        ];
                        break;
                    case 'text':
                        $categoricalColumns[] = [
                            'name' => $header,
                            'type' => 'text',
                            'fill_rate' => $data['fill_rate']
                        ];
                        break;
                    case 'date':
                        $dateColumns[] = [
                            'name' => $header,
                            'type' => 'date',
                            'fill_rate' => $data['fill_rate']
                        ];
                        break;
                    default:
                        $otherColumns[] = [
                            'name' => $header,
                            'type' => $data['data_type'],
                            'fill_rate' => $data['fill_rate']
                        ];
                }
            }
            
            return response()->json([
                'success' => true,
                'columns' => [
                    'numeric' => $numericColumns,
                    'categorical' => $categoricalColumns,
                    'date' => $dateColumns,
                    'other' => $otherColumns
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des colonnes : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Analyze the relationship between two columns
     */
    public function analyze($fileId, $sheetName, $targetColumn, $sourceColumn)
    {
        try {
            $file = ExcelFile::findOrFail($fileId);
            
            if (!$file->statistics || !isset($file->statistics['sheets'][$sheetName])) {
                return response()->json([
                    'success' => false,
                    'message' => "Feuille '$sheetName' non trouvée dans le fichier"
                ], 404);
            }
            
            $sheetStats = $file->statistics['sheets'][$sheetName];
            
            // Vérifier que les deux colonnes existent
            if (!isset($sheetStats['columns'][$targetColumn]) || !isset($sheetStats['columns'][$sourceColumn])) {
                return response()->json([
                    'success' => false,
                    'message' => "Une ou plusieurs colonnes n'existent pas dans cette feuille"
                ], 404);
            }
            
            // Chercher l'analyse croisée dans les statistiques existantes
            if (isset($sheetStats['cross_column_stats'])) {
                foreach ($sheetStats['cross_column_stats'] as $crossStat) {
                    if ($crossStat['target_column'] === $targetColumn && $crossStat['source_column'] === $sourceColumn) {
                        return response()->json([
                            'success' => true,
                            'analysis' => $crossStat
                        ]);
                    }
                }
            }
            
            // Si l'analyse n'existe pas déjà, effectuer une analyse dynamique
            $filePath = Storage::disk('public')->path($file->file_path);
            $analysis = $this->crossAnalysisService->analyzeColumns($filePath, $sheetName, $targetColumn, $sourceColumn);
            
            return response()->json([
                'success' => true,
                'analysis' => $analysis
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'analyse des colonnes : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a correlation matrix for all numeric columns
     */
    public function getCorrelationMatrix(Request $request, $fileId, $sheetName)
    {
        try {
            $file = ExcelFile::findOrFail($fileId);
            
            if (!$file->statistics || !isset($file->statistics['sheets'][$sheetName])) {
                return response()->json([
                    'success' => false,
                    'message' => "Feuille '$sheetName' non trouvée dans le fichier"
                ], 404);
            }
            
            $filePath = Storage::disk('public')->path($file->file_path);
            
            // Paramètres optionnels
            $columns = $request->input('columns', []); // Colonnes spécifiques ou toutes si vide
            $minCorrelation = $request->input('min_correlation', 0); // Seuil minimum pour inclure la corrélation
            
            $matrix = $this->crossAnalysisService->generateCorrelationMatrix(
                $filePath, 
                $sheetName, 
                $columns,
                $minCorrelation
            );
            
            return response()->json([
                'success' => true,
                'matrix' => $matrix
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération de la matrice de corrélation : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pivot table statistics
     */
    public function getPivotStatistics(Request $request, $fileId, $sheetName)
    {
        try {
            $request->validate([
                'row_column' => 'required|string',
                'column_column' => 'required|string',
                'value_column' => 'required|string',
                'aggregation' => 'required|string|in:sum,avg,count,min,max,median'
            ]);
            
            $file = ExcelFile::findOrFail($fileId);
            
            if (!$file->statistics || !isset($file->statistics['sheets'][$sheetName])) {
                return response()->json([
                    'success' => false,
                    'message' => "Feuille '$sheetName' non trouvée dans le fichier"
                ], 404);
            }
            
            $filePath = Storage::disk('public')->path($file->file_path);
            $rowColumn = $request->input('row_column');
            $columnColumn = $request->input('column_column');
            $valueColumn = $request->input('value_column');
            $aggregation = $request->input('aggregation');
            
            $pivotResults = $this->crossAnalysisService->generatePivotTable(
                $filePath,
                $sheetName,
                $rowColumn,
                $columnColumn,
                $valueColumn,
                $aggregation
            );
            
            return response()->json([
                'success' => true,
                'pivot_data' => $pivotResults
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération des statistiques pivot : ' . $e->getMessage()
            ], 500);
        }
    }
}