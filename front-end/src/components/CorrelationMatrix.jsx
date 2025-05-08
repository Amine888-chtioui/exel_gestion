import React, { useState, useEffect } from 'react';
import { 
  Box, 
  Typography, 
  Paper, 
  CircularProgress, 
  Alert,
  Slider,
  FormControlLabel,
  Switch,
  Card,
  CardContent,
  Divider,
  FormGroup,
  Chip,
  Stack,
  TextField,
  InputAdornment,
} from '@mui/material';
import { DataGrid } from '@mui/x-data-grid';
import {
  Heatmap,
  Treemap,
  ResponsiveContainer,
  Tooltip,
} from 'recharts';
import ExcelService from '../services/ExcelService';
import CrossAnalysisService from '../services/CrossAnalysisService';

// Palette de couleurs pour la matrice de corrélation
const CORRELATION_COLORS = {
  'very_strong_positive': '#d32f2f', // Rouge
  'strong_positive': '#f44336',
  'moderate_positive': '#ff9800', // Orange
  'weak_positive': '#ffeb3b', // Jaune
  'negligible': '#e0e0e0', // Gris
  'weak_negative': '#bbdefb', // Bleu clair
  'moderate_negative': '#64b5f6',
  'strong_negative': '#2196f3',
  'very_strong_negative': '#0d47a1', // Bleu foncé
};

const CorrelationMatrix = ({ fileId, sheetName }) => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [matrix, setMatrix] = useState(null);
  const [columns, setColumns] = useState([]);
  const [minCorrelation, setMinCorrelation] = useState(0.3);
  const [showHeatmap, setShowHeatmap] = useState(true);
  const [selectedColumns, setSelectedColumns] = useState([]);
  const [availableColumns, setAvailableColumns] = useState([]);
  const [displayMode, setDisplayMode] = useState('heatmap'); // heatmap, table, treemap

  // Fonction pour obtenir la couleur en fonction de la corrélation
  const getCorrelationColor = (correlation) => {
    const absCorrelation = Math.abs(correlation);
    
    if (absCorrelation < 0.1) return CORRELATION_COLORS.negligible;
    
    if (correlation > 0) {
      if (absCorrelation < 0.3) return CORRELATION_COLORS.weak_positive;
      if (absCorrelation < 0.5) return CORRELATION_COLORS.moderate_positive;
      if (absCorrelation < 0.7) return CORRELATION_COLORS.strong_positive;
      return CORRELATION_COLORS.very_strong_positive;
    } else {
      if (absCorrelation < 0.3) return CORRELATION_COLORS.weak_negative;
      if (absCorrelation < 0.5) return CORRELATION_COLORS.moderate_negative;
      if (absCorrelation < 0.7) return CORRELATION_COLORS.strong_negative;
      return CORRELATION_COLORS.very_strong_negative;
    }
  };

  // Récupérer les colonnes disponibles pour l'analyse
  useEffect(() => {
    if (!fileId || !sheetName) return;

    const fetchColumns = async () => {
      try {
        setLoading(true);
        const response = await CrossAnalysisService.getAvailableColumns(fileId, sheetName);
        
        if (response.success) {
          const numericCols = response.columns.numeric.map(col => col.name);
          setAvailableColumns(numericCols);
          
          // Par défaut, sélectionner toutes les colonnes numériques
          setSelectedColumns(numericCols);
        }
      } catch (err) {
        setError('Erreur lors de la récupération des colonnes');
        console.error(err);
      } finally {
        setLoading(false);
      }
    };

    fetchColumns();
  }, [fileId, sheetName]);

  // Récupérer la matrice de corrélation
  useEffect(() => {
    if (!fileId || !sheetName || selectedColumns.length < 2) {
      setMatrix(null);
      return;
    }

    const fetchMatrix = async () => {
      try {
        setLoading(true);
        setError(null);
        
        const response = await CrossAnalysisService.getCorrelationMatrix(
          fileId,
          sheetName,
          selectedColumns,
          minCorrelation
        );
        
        if (response.success) {
          setMatrix(response.matrix);
          
          // Préparer les colonnes pour le tableau
          const gridColumns = response.matrix.columns.map((col, index) => ({
            field: col,
            headerName: col,
            flex: 1,
            minWidth: 100,
            renderCell: (params) => {
              const value = params.value;
              return (
                <Box sx={{ 
                  width: '100%', 
                  height: '100%', 
                  display: 'flex', 
                  alignItems: 'center', 
                  justifyContent: 'center',
                  backgroundColor: getCorrelationColor(value),
                  color: Math.abs(value) > 0.5 ? 'white' : 'black',
                  fontWeight: Math.abs(value) > 0.7 ? 'bold' : 'normal'
                }}>
                  {Math.abs(value) > 0.01 ? value.toFixed(2) : '0'}
                </Box>
              );
            }
          }));
          
          setColumns([
            { field: 'column', headerName: '', width: 150 },
            ...gridColumns
          ]);
        }
      } catch (err) {
        setError('Erreur lors de la récupération de la matrice de corrélation');
        console.error(err);
      } finally {
        setLoading(false);
      }
    };

    fetchMatrix();
  }, [fileId, sheetName, selectedColumns, minCorrelation]);

  // Préparer les données pour le heatmap
  const prepareHeatmapData = () => {
    if (!matrix) return [];
    
    const data = [];
    
    for (let i = 0; i < matrix.columns.length; i++) {
      const row = {
        name: matrix.columns[i],
      };
      
      for (let j = 0; j < matrix.columns.length; j++) {
        row[matrix.columns[j]] = matrix.correlations[i][j];
      }
      
      data.push(row);
    }
    
    return data;
  };

  // Préparer les données pour le tableau
  const prepareTableRows = () => {
    if (!matrix) return [];
    
    return matrix.columns.map((col, rowIndex) => {
      const row = { id: rowIndex, column: col };
      
      matrix.columns.forEach((colName, colIndex) => {
        row[colName] = matrix.correlations[rowIndex][colIndex];
      });
      
      return row;
    });
  };

  // Préparer les données pour le treemap
  const prepareTreemapData = () => {
    if (!matrix) return [];
    
    const data = [];
    
    for (let i = 0; i < matrix.columns.length; i++) {
      for (let j = i + 1; j < matrix.columns.length; j++) {
        const correlation = matrix.correlations[i][j];
        
        if (Math.abs(correlation) >= minCorrelation) {
          data.push({
            name: `${matrix.columns[i]} - ${matrix.columns[j]}`,
            size: Math.abs(correlation) * 100,
            value: correlation,
            color: getCorrelationColor(correlation)
          });
        }
      }
    }
    
    // Trier par force de corrélation (descendant)
    data.sort((a, b) => Math.abs(b.value) - Math.abs(a.value));
    
    return data;
  };

  // Gérer le changement de seuil de corrélation
  const handleMinCorrelationChange = (event, newValue) => {
    setMinCorrelation(newValue);
  };

  // Gérer le changement du mode d'affichage
  const handleDisplayModeChange = (mode) => {
    setDisplayMode(mode);
  };

  // Gérer la sélection/désélection de colonnes
  const handleColumnToggle = (column) => {
    if (selectedColumns.includes(column)) {
      setSelectedColumns(selectedColumns.filter(col => col !== column));
    } else {
      setSelectedColumns([...selectedColumns, column]);
    }
  };

  if (!fileId || !sheetName) {
    return (
      <Paper elevation={3} sx={{ p: 3, textAlign: 'center' }}>
        <Typography variant="body1" color="textSecondary">
          Sélectionnez un fichier et une feuille pour voir la matrice de corrélation
        </Typography>
      </Paper>
    );
  }

  if (loading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', p: 3 }}>
        <CircularProgress />
      </Box>
    );
  }

  if (error) {
    return (
      <Alert severity="error" sx={{ mb: 3 }}>
        {error}
      </Alert>
    );
  }

  return (
    <Paper elevation={3} sx={{ p: 3 }}>
      <Typography variant="h6" gutterBottom>
        Matrice de corrélation
      </Typography>
      <Divider sx={{ mb: 3 }} />

      {/* Options de configuration */}
      <Card variant="outlined" sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="subtitle1" gutterBottom>
            Options d'affichage
          </Typography>
          
          <Box sx={{ mb: 2 }}>
            <Typography variant="body2" gutterBottom>
              Seuil de corrélation minimum: {minCorrelation.toFixed(2)}
            </Typography>
            <Slider
              value={minCorrelation}
              onChange={handleMinCorrelationChange}
              aria-labelledby="correlation-threshold-slider"
              valueLabelDisplay="auto"
              step={0.05}
              marks
              min={0}
              max={1}
            />
          </Box>
          
          <Typography variant="body2" gutterBottom>
            Mode d'affichage:
          </Typography>
          <Stack direction="row" spacing={1} sx={{ mb: 2 }}>
            <Chip 
              label="Heatmap" 
              color={displayMode === 'heatmap' ? 'primary' : 'default'} 
              onClick={() => handleDisplayModeChange('heatmap')}
            />
            <Chip 
              label="Tableau" 
              color={displayMode === 'table' ? 'primary' : 'default'} 
              onClick={() => handleDisplayModeChange('table')}
            />
            <Chip 
              label="Treemap" 
              color={displayMode === 'treemap' ? 'primary' : 'default'} 
              onClick={() => handleDisplayModeChange('treemap')}
            />
          </Stack>
          
          <Typography variant="body2" gutterBottom>
            Colonnes sélectionnées ({selectedColumns.length}/{availableColumns.length}):
          </Typography>
          <Box sx={{ maxHeight: '150px', overflowY: 'auto', mb: 2 }}>
            <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
              {availableColumns.map((column) => (
                <Chip 
                  key={column}
                  label={column}
                  color={selectedColumns.includes(column) ? 'primary' : 'default'}
                  onClick={() => handleColumnToggle(column)}
                  sx={{ mb: 1 }}
                />
              ))}
            </Stack>
          </Box>
        </CardContent>
      </Card>

      {/* Légende */}
      <Box sx={{ mb: 3 }}>
        <Typography variant="subtitle2" gutterBottom>
          Légende des corrélations
        </Typography>
        <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
          <Chip 
            label="Très forte +"
            size="small"
            sx={{ bgcolor: CORRELATION_COLORS.very_strong_positive, color: 'white' }}
          />
          <Chip 
            label="Forte +"
            size="small"
            sx={{ bgcolor: CORRELATION_COLORS.strong_positive, color: 'white' }}
          />
          <Chip 
            label="Modérée +"
            size="small"
            sx={{ bgcolor: CORRELATION_COLORS.moderate_positive }}
          />
          <Chip 
            label="Faible +"
            size="small"
            sx={{ bgcolor: CORRELATION_COLORS.weak_positive }}
          />
          <Chip 
            label="Négligeable"
            size="small"
            sx={{ bgcolor: CORRELATION_COLORS.negligible }}
          />
          <Chip 
            label="Faible -"
            size="small"
            sx={{ bgcolor: CORRELATION_COLORS.weak_negative }}
          />
          <Chip 
            label="Modérée -"
            size="small"
            sx={{ bgcolor: CORRELATION_COLORS.moderate_negative }}
          />
          <Chip 
            label="Forte -"
            size="small"
            sx={{ bgcolor: CORRELATION_COLORS.strong_negative, color: 'white' }}
          />
          <Chip 
            label="Très forte -"
            size="small"
            sx={{ bgcolor: CORRELATION_COLORS.very_strong_negative, color: 'white' }}
          />
        </Stack>
      </Box>

      {/* Affichage de la matrice */}
      {matrix ? (
        <Box>
          {/* Heatmap */}
          {displayMode === 'heatmap' && (
            <Box sx={{ height: 500, mb: 3 }}>
              <ResponsiveContainer width="100%" height="100%">
                <Heatmap
                  data={prepareHeatmapData()}
                  nameKey="name"
                  dataKey={(column) => column.name}
                  getCellColor={(value) => getCorrelationColor(value)}
                  cellStyle={{ stroke: '#fff', strokeWidth: 1 }}
                  renderTooltip={({ active, payload }) => {
                    if (active && payload && payload.length > 0) {
                      const data = payload[0];
                      return (
                        <Box sx={{ 
                          bgcolor: 'background.paper', 
                          p: 1, 
                          border: '1px solid #ccc',
                          boxShadow: 2
                        }}>
                          <Typography variant="body2">
                            {data.column} - {data.row}
                          </Typography>
                          <Typography variant="body1" fontWeight="bold">
                            {data.value && typeof data.value === 'number' 
                              ? data.value.toFixed(4) 
                              : 'N/A'}
                          </Typography>
                        </Box>
                      );
                    }
                    return null;
                  }}
                />
              </ResponsiveContainer>
            </Box>
          )}

          {/* Tableau */}
          {displayMode === 'table' && (
            <Box sx={{ height: 500, mb: 3 }}>
              <DataGrid
                rows={prepareTableRows()}
                columns={columns}
                pageSize={10}
                rowsPerPageOptions={[10, 25, 50]}
                disableSelectionOnClick
                density="compact"
              />
            </Box>
          )}

          {/* Treemap */}
          {displayMode === 'treemap' && (
            <Box sx={{ height: 500, mb: 3 }}>
              <ResponsiveContainer width="100%" height="100%">
                <Treemap
                  data={prepareTreemapData()}
                  dataKey="size"
                  aspectRatio={1}
                  stroke="#fff"
                  fill="#8884d8"
                  content={({ root, depth, x, y, width, height, index, payload, colors, rank, name }) => {
                    const item = root.children[index];
                    if (!item) return null;
                    
                    return (
                      <g>
                        <rect
                          x={x}
                          y={y}
                          width={width}
                          height={height}
                          style={{
                            fill: item.color,
                            stroke: '#fff',
                            strokeWidth: 2 / (depth + 1e-10),
                            strokeOpacity: 1 / (depth + 1e-10),
                          }}
                        />
                        {width > 70 && height > 30 ? (
                          <text
                            x={x + width / 2}
                            y={y + height / 2}
                            textAnchor="middle"
                            dominantBaseline="middle"
                            style={{
                              fontFamily: 'sans-serif',
                              fontSize: 12,
                              fill: Math.abs(item.value) > 0.5 ? '#fff' : '#000',
                              pointerEvents: 'none',
                            }}
                          >
                            {item.name}
                            <tspan x={x + width / 2} y={y + height / 2 + 15} fontSize={11} fontWeight="bold">
                              {item.value.toFixed(2)}
                            </tspan>
                          </text>
                        ) : null}
                      </g>
                    );
                  }}
                  renderTooltip={({ payload }) => {
                    if (payload && payload.name) {
                      return (
                        <Box sx={{ 
                          bgcolor: 'background.paper', 
                          p: 1, 
                          border: '1px solid #ccc',
                          boxShadow: 2
                        }}>
                          <Typography variant="body2">
                            {payload.name}
                          </Typography>
                          <Typography variant="body1" fontWeight="bold">
                            Corrélation: {payload.value.toFixed(4)}
                          </Typography>
                        </Box>
                      );
                    }
                    return null;
                  }}
                />
              </ResponsiveContainer>
            </Box>
          )}
        </Box>
      ) : (
        <Alert severity="info" sx={{ mt: 2 }}>
          {availableColumns.length < 2 
            ? "Il faut au moins deux colonnes numériques pour générer une matrice de corrélation." 
            : "Sélectionnez au moins deux colonnes pour générer la matrice de corrélation."}
        </Alert>
      )}

      {/* Interprétation */}
      {matrix && (
        <Card variant="outlined">
          <CardContent>
            <Typography variant="subtitle1" gutterBottom>
              Interprétation des résultats
            </Typography>
            <Typography variant="body2" paragraph>
              Une corrélation positive forte (proche de 1) indique que lorsqu'une variable augmente, l'autre tend également à augmenter.
              Une corrélation négative forte (proche de -1) indique que lorsqu'une variable augmente, l'autre tend à diminuer.
              Une corrélation proche de 0 indique qu'il n'y a pas de relation linéaire entre les variables.
            </Typography>
            <Typography variant="body2">
              <strong>Note:</strong> La corrélation indique une relation, mais ne prouve pas nécessairement une causalité.
            </Typography>
          </CardContent>
        </Card>
      )}
    </Paper>
  );
};

export default CorrelationMatrix;