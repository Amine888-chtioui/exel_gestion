import React, { useState, useEffect } from 'react';
import { 
  Box, 
  Typography, 
  Paper, 
  CircularProgress, 
  Alert,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Divider,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Card,
  CardContent
} from '@mui/material';
import {
  ScatterChart,
  Scatter,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer
} from 'recharts';
import CrossAnalysisService from '../services/CrossAnalysisService';

const CorrelationMatrix = ({ fileId, sheetName }) => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [availableColumns, setAvailableColumns] = useState([]);
  const [sourceColumn, setSourceColumn] = useState('');
  const [targetColumn, setTargetColumn] = useState('');
  const [analysisResult, setAnalysisResult] = useState(null);

  // Récupérer les colonnes disponibles pour l'analyse
  useEffect(() => {
    if (!fileId || !sheetName) return;

    const fetchColumns = async () => {
      try {
        setLoading(true);
        console.log("Fetching columns for fileId:", fileId, "sheetName:", sheetName);
        const response = await CrossAnalysisService.getAvailableColumns(fileId, sheetName);
        
        if (response.success) {
          const numericCols = response.columns.numeric.map(col => col.name);
          setAvailableColumns(numericCols);
          
          // Par défaut, sélectionner les deux premières colonnes si disponibles
          if (numericCols.length >= 2) {
            setSourceColumn(numericCols[0]);
            setTargetColumn(numericCols[1]);
          }
        } else {
          console.error("API returned failure:", response);
          setError("L'API a retourné une erreur: " + (response.message || "Erreur inconnue"));
        }
      } catch (err) {
        console.error("Error fetching columns:", err);
        setError('Erreur lors de la récupération des colonnes: ' + (err.message || err));
      } finally {
        setLoading(false);
      }
    };

    fetchColumns();
  }, [fileId, sheetName]);

  // Analyser la relation entre les deux colonnes sélectionnées
  useEffect(() => {
    if (!fileId || !sheetName || !sourceColumn || !targetColumn) {
      setAnalysisResult(null);
      return;
    }

    const analyzeColumns = async () => {
      try {
        setLoading(true);
        setError(null);
        
        console.log("Analyzing relationship between", sourceColumn, "and", targetColumn);
        const response = await CrossAnalysisService.analyzeColumns(
          fileId,
          sheetName,
          targetColumn, // Colonne cible (généralement numérique)
          sourceColumn  // Colonne source
        );
        
        if (response.success) {
          console.log("Analysis result:", response.analysis);
          setAnalysisResult(response.analysis);
        } else {
          setError("L'analyse n'a pas pu être effectuée: " + (response.message || "Erreur inconnue"));
        }
      } catch (err) {
        setError('Erreur lors de l\'analyse: ' + (err.message || err));
      } finally {
        setLoading(false);
      }
    };

    if (sourceColumn !== targetColumn) {
      analyzeColumns();
    }
  }, [fileId, sheetName, sourceColumn, targetColumn]);

  // Gérer le changement de colonne source
  const handleSourceChange = (event) => {
    const value = event.target.value;
    setSourceColumn(value);
    
    // Si la même colonne est sélectionnée pour la cible, changer la cible
    if (value === targetColumn && availableColumns.length > 1) {
      const otherColumn = availableColumns.find(col => col !== value);
      if (otherColumn) {
        setTargetColumn(otherColumn);
      }
    }
  };

  // Gérer le changement de colonne cible
  const handleTargetChange = (event) => {
    const value = event.target.value;
    setTargetColumn(value);
    
    // Si la même colonne est sélectionnée pour la source, changer la source
    if (value === sourceColumn && availableColumns.length > 1) {
      const otherColumn = availableColumns.find(col => col !== value);
      if (otherColumn) {
        setSourceColumn(otherColumn);
      }
    }
  };

  if (!fileId || !sheetName) {
    return (
      <Paper elevation={3} sx={{ p: 3, textAlign: 'center' }}>
        <Typography variant="body1" color="textSecondary">
          Sélectionnez un fichier et une feuille pour voir l'analyse de corrélation
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
        Analyse de la relation entre deux colonnes
      </Typography>
      <Divider sx={{ mb: 3 }} />

      {/* Sélection des colonnes */}
      <Box sx={{ display: 'flex', gap: 2, mb: 3 }}>
        <FormControl fullWidth>
          <InputLabel id="source-column-label">Colonne de référence</InputLabel>
          <Select
            labelId="source-column-label"
            id="source-column"
            value={sourceColumn}
            label="Colonne de référence"
            onChange={handleSourceChange}
          >
            {availableColumns.map(column => (
              <MenuItem key={column} value={column}>{column}</MenuItem>
            ))}
          </Select>
        </FormControl>

        <FormControl fullWidth>
          <InputLabel id="target-column-label">Colonne à analyser</InputLabel>
          <Select
            labelId="target-column-label"
            id="target-column"
            value={targetColumn}
            label="Colonne à analyser"
            onChange={handleTargetChange}
          >
            {availableColumns.map(column => (
              <MenuItem key={column} value={column}>{column}</MenuItem>
            ))}
          </Select>
        </FormControl>
      </Box>

      {/* Résultats de l'analyse */}
      {analysisResult ? (
        <Box>
          {/* Graphique de dispersion pour visualiser la relation */}
          {analysisResult.correlation && (
            <Box sx={{ height: 400, mb: 3 }}>
              <Typography variant="subtitle1" gutterBottom align="center">
                Relation entre {sourceColumn} et {targetColumn}
              </Typography>
              <ResponsiveContainer width="100%" height="90%">
                <ScatterChart
                  margin={{ top: 20, right: 20, bottom: 20, left: 20 }}
                >
                  <CartesianGrid />
                  <XAxis 
                    type="number" 
                    dataKey="x" 
                    name={sourceColumn} 
                    label={{ value: sourceColumn, position: 'insideBottomRight', offset: -5 }}
                  />
                  <YAxis 
                    type="number" 
                    dataKey="y" 
                    name={targetColumn} 
                    label={{ value: targetColumn, angle: -90, position: 'insideLeft' }}
                  />
                  <Tooltip 
                    cursor={{ strokeDasharray: '3 3' }}
                    formatter={(value, name) => [value.toFixed(2), name === 'x' ? sourceColumn : targetColumn]}
                  />
                  <Scatter 
                    name="Valeurs" 
                    data={analysisResult.correlation.sample_pairs} 
                    fill="#8884d8"
                  />
                </ScatterChart>
              </ResponsiveContainer>
            </Box>
          )}

          {/* Tableau des statistiques */}
          <Card sx={{ mb: 3 }}>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Statistiques de corrélation
              </Typography>
              
              {analysisResult.correlation && (
                <TableContainer component={Paper} variant="outlined">
                  <Table>
                    <TableHead>
                      <TableRow>
                        <TableCell>Métrique</TableCell>
                        <TableCell>Valeur</TableCell>
                        <TableCell>Description</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      <TableRow>
                        <TableCell>Coefficient de corrélation</TableCell>
                        <TableCell>
                          <Typography
                            sx={{
                              fontWeight: 'bold',
                              color: Math.abs(analysisResult.correlation.coefficient) > 0.5 ? 
                                analysisResult.correlation.coefficient > 0 ? 'success.main' : 'error.main' 
                                : 'text.primary'
                            }}
                          >
                            {analysisResult.correlation.coefficient.toFixed(4)}
                          </Typography>
                        </TableCell>
                        <TableCell>
                          Le coefficient varie de -1 à 1. Une valeur proche de 1 indique une forte corrélation positive, 
                          proche de -1 une forte corrélation négative, et proche de 0 une absence de corrélation.
                        </TableCell>
                      </TableRow>
                      <TableRow>
                        <TableCell>Force de la relation</TableCell>
                        <TableCell>{analysisResult.correlation.strength}</TableCell>
                        <TableCell>
                          Interprétation de l'intensité de la corrélation.
                        </TableCell>
                      </TableRow>
                      <TableRow>
                        <TableCell>Nombre d'échantillons</TableCell>
                        <TableCell>{analysisResult.correlation.sample_count}</TableCell>
                        <TableCell>
                          Nombre de paires de valeurs utilisées pour calculer la corrélation.
                        </TableCell>
                      </TableRow>
                    </TableBody>
                  </Table>
                </TableContainer>
              )}
            </CardContent>
          </Card>

          {/* Interprétation */}
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Interprétation
              </Typography>
              
              {analysisResult.correlation && (
                <Typography variant="body1">
                  {analysisResult.correlation.coefficient > 0.7 ? (
                    `Il existe une forte corrélation positive entre ${sourceColumn} et ${targetColumn}. Quand ${sourceColumn} augmente, ${targetColumn} augmente également de manière significative.`
                  ) : analysisResult.correlation.coefficient > 0.3 ? (
                    `Il existe une corrélation positive modérée entre ${sourceColumn} et ${targetColumn}. Quand ${sourceColumn} augmente, ${targetColumn} tend à augmenter également.`
                  ) : analysisResult.correlation.coefficient > 0 ? (
                    `Il existe une faible corrélation positive entre ${sourceColumn} et ${targetColumn}. La relation entre ces deux variables est limitée.`
                  ) : analysisResult.correlation.coefficient > -0.3 ? (
                    `Il existe une faible corrélation négative entre ${sourceColumn} et ${targetColumn}. La relation entre ces deux variables est limitée.`
                  ) : analysisResult.correlation.coefficient > -0.7 ? (
                    `Il existe une corrélation négative modérée entre ${sourceColumn} et ${targetColumn}. Quand ${sourceColumn} augmente, ${targetColumn} tend à diminuer.`
                  ) : (
                    `Il existe une forte corrélation négative entre ${sourceColumn} et ${targetColumn}. Quand ${sourceColumn} augmente, ${targetColumn} diminue de manière significative.`
                  )}
                </Typography>
              )}
              
              <Typography variant="body2" sx={{ mt: 2, fontStyle: 'italic' }}>
                Remarque : Une corrélation indique une relation, mais ne prouve pas nécessairement une causalité.
              </Typography>
            </CardContent>
          </Card>
        </Box>
      ) : (
        <Alert severity="info">
          Sélectionnez deux colonnes différentes pour analyser leur relation.
        </Alert>
      )}
    </Paper>
  );
};

export default CorrelationMatrix;