import React, { useState, useEffect } from 'react';
import { 
  Box, 
  Typography, 
  Paper, 
  FormControl, 
  InputLabel, 
  Select, 
  MenuItem, 
  Grid, 
  Card, 
  CardContent, 
  Divider,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tab,
  Tabs,
  Alert,
} from '@mui/material';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
  ScatterChart,
  Scatter,
  ZAxis,
  Cell,
} from 'recharts';

const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884d8', '#82ca9d'];

const CrossColumnAnalysis = ({ sheetData }) => {
  const [targetColumn, setTargetColumn] = useState('');
  const [sourceColumn, setSourceColumn] = useState('');
  const [availableTargets, setAvailableTargets] = useState([]);
  const [availableSources, setAvailableSources] = useState([]);
  const [crossStats, setCrossStats] = useState(null);
  const [activeTab, setActiveTab] = useState(0);

  // Fonction pour déterminer la couleur du coefficient de corrélation
  const getCorrelationColor = (coef) => {
    const absCoef = Math.abs(coef);
    if (absCoef < 0.1) return '#9e9e9e'; // Négligeable - gris
    if (absCoef < 0.3) return '#2196f3'; // Faible - bleu
    if (absCoef < 0.5) return '#4caf50'; // Modéré - vert
    if (absCoef < 0.7) return '#ff9800'; // Fort - orange
    return '#f44336'; // Très fort - rouge
  };

  // Filtrer les colonnes disponibles quand les données changent
  useEffect(() => {
    if (!sheetData || !sheetData.columns) {
      setAvailableTargets([]);
      setAvailableSources([]);
      return;
    }

    // Trouver toutes les colonnes numériques pour les cibles
    const numericColumns = Object.entries(sheetData.columns)
      .filter(([_, data]) => data.data_type === 'numeric')
      .map(([header]) => header);

    // Toutes les colonnes peuvent être des sources
    const allColumns = Object.keys(sheetData.columns);

    setAvailableTargets(numericColumns);
    setAvailableSources(allColumns);

    // Par défaut, sélectionner la première colonne numérique comme cible
    if (numericColumns.length > 0 && !targetColumn) {
      setTargetColumn(numericColumns[0]);
    }

    // Par défaut, sélectionner la première colonne différente comme source
    if (allColumns.length > 1 && !sourceColumn) {
      const defaultSource = allColumns.find(col => col !== numericColumns[0]);
      if (defaultSource) {
        setSourceColumn(defaultSource);
      }
    }
  }, [sheetData]);

  // Trouver les statistiques croisées lorsque les sélections changent
  useEffect(() => {
    if (!sheetData || !targetColumn || !sourceColumn || !sheetData.cross_column_stats) {
      setCrossStats(null);
      return;
    }

    const stats = sheetData.cross_column_stats.find(
      stat => stat.target_column === targetColumn && stat.source_column === sourceColumn
    );

    setCrossStats(stats || null);
  }, [sheetData, targetColumn, sourceColumn]);

  const handleTargetChange = (event) => {
    setTargetColumn(event.target.value);
  };

  const handleSourceChange = (event) => {
    setSourceColumn(event.target.value);
  };

  const handleChangeTab = (event, newValue) => {
    setActiveTab(newValue);
  };

  if (!sheetData) {
    return (
      <Paper elevation={3} sx={{ p: 3, textAlign: 'center' }}>
        <Typography variant="body1" color="textSecondary">
          Sélectionnez une feuille pour voir les analyses croisées
        </Typography>
      </Paper>
    );
  }

  if (!sheetData.cross_column_stats || sheetData.cross_column_stats.length === 0) {
    return (
      <Paper elevation={3} sx={{ p: 3 }}>
        <Alert severity="info">
          Pas d'analyses croisées disponibles pour cette feuille
        </Alert>
      </Paper>
    );
  }

  const targetColumnData = targetColumn ? sheetData.columns[targetColumn] : null;
  const sourceColumnData = sourceColumn ? sheetData.columns[sourceColumn] : null;

  return (
    <Paper elevation={3} sx={{ p: 3 }}>
      <Typography variant="h6" gutterBottom>
        Analyse croisée des colonnes
      </Typography>
      <Divider sx={{ mb: 3 }} />

      {/* Sélection des colonnes */}
      <Grid container spacing={3} sx={{ mb: 3 }}>
        <Grid item xs={12} md={6}>
          <FormControl fullWidth>
            <InputLabel id="target-column-label">Colonne cible (numérique)</InputLabel>
            <Select
              labelId="target-column-label"
              id="target-column-select"
              value={targetColumn}
              label="Colonne cible (numérique)"
              onChange={handleTargetChange}
            >
              {availableTargets.map((column) => (
                <MenuItem key={column} value={column}>
                  {column}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
        </Grid>
        <Grid item xs={12} md={6}>
          <FormControl fullWidth>
            <InputLabel id="source-column-label">Colonne source (regroupement)</InputLabel>
            <Select
              labelId="source-column-label"
              id="source-column-select"
              value={sourceColumn}
              label="Colonne source (regroupement)"
              onChange={handleSourceChange}
              disabled={!targetColumn}
            >
              {availableSources
                .filter(col => col !== targetColumn)
                .map((column) => (
                  <MenuItem key={column} value={column}>
                    {column}
                  </MenuItem>
                ))}
            </Select>
          </FormControl>
        </Grid>
      </Grid>

      {/* Affichage des informations sur les colonnes sélectionnées */}
      {targetColumnData && sourceColumnData && (
        <Grid container spacing={3} sx={{ mb: 3 }}>
          <Grid item xs={12} md={6}>
            <Card>
              <CardContent>
                <Typography variant="subtitle1" gutterBottom>
                  Colonne cible: <strong>{targetColumn}</strong>
                </Typography>
                <Typography variant="body2">
                  Type: {targetColumnData.data_type}
                </Typography>
                {targetColumnData.numeric && (
                  <>
                    <Typography variant="body2">
                      Min: {targetColumnData.numeric.min} | Max: {targetColumnData.numeric.max}
                    </Typography>
                    <Typography variant="body2">
                      Moyenne: {targetColumnData.numeric.avg?.toFixed(2)}
                    </Typography>
                  </>
                )}
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} md={6}>
            <Card>
              <CardContent>
                <Typography variant="subtitle1" gutterBottom>
                  Colonne source: <strong>{sourceColumn}</strong>
                </Typography>
                <Typography variant="body2">
                  Type: {sourceColumnData.data_type}
                </Typography>
                <Typography variant="body2">
                  Taux de remplissage: {sourceColumnData.fill_rate}%
                </Typography>
              </CardContent>
            </Card>
          </Grid>
        </Grid>
      )}

      {/* Affichage des statistiques croisées */}
      {crossStats ? (
        <Box>
          <Box sx={{ borderBottom: 1, borderColor: 'divider', mb: 2 }}>
            <Tabs value={activeTab} onChange={handleChangeTab}>
              <Tab label="Résumé" />
              {crossStats.category_stats && <Tab label="Par Catégorie" />}
              {crossStats.correlation && <Tab label="Corrélation" />}
            </Tabs>
          </Box>

          {/* Onglet Résumé */}
          {activeTab === 0 && (
            <Box>
              <Typography variant="h6" gutterBottom>
                Résumé de l'analyse
              </Typography>
              <Typography variant="body1" paragraph>
                Cette analyse montre la relation entre la colonne numérique <strong>{targetColumn}</strong> et 
                la colonne <strong>{sourceColumn}</strong> de type {sourceColumnData?.data_type}.
              </Typography>

              {crossStats.correlation && (
                <Card sx={{ mb: 3 }}>
                  <CardContent>
                    <Typography variant="subtitle1" gutterBottom>
                      Corrélation
                    </Typography>
                    <Typography variant="h4" sx={{ 
                      color: getCorrelationColor(crossStats.correlation.coefficient),
                      textAlign: 'center',
                      my: 2
                    }}>
                      {crossStats.correlation.coefficient.toFixed(4)}
                    </Typography>
                    <Typography variant="body1" textAlign="center">
                      Intensité: <strong>{crossStats.correlation.strength}</strong> 
                      ({crossStats.correlation.sample_count} paires analysées)
                    </Typography>
                  </CardContent>
                </Card>
              )}

              {crossStats.category_stats && (
                <Box>
                  <Typography variant="subtitle1" gutterBottom>
                    Principales catégories
                  </Typography>
                  <TableContainer component={Paper} variant="outlined">
                    <Table size="small">
                      <TableHead>
                        <TableRow>
                          <TableCell>Catégorie</TableCell>
                          <TableCell align="right">Nombre</TableCell>
                          <TableCell align="right">%</TableCell>
                          <TableCell align="right">Moyenne</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {crossStats.category_stats.slice(0, 5).map((category, index) => (
                          <TableRow key={index}>
                            <TableCell>{category.category}</TableCell>
                            <TableCell align="right">{category.count}</TableCell>
                            <TableCell align="right">{category.percent}%</TableCell>
                            <TableCell align="right">{category.avg.toFixed(2)}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </TableContainer>
                </Box>
              )}
            </Box>
          )}

          {/* Onglet Par Catégorie */}
          {activeTab === 1 && crossStats.category_stats && (
            <Box>
              <Typography variant="h6" gutterBottom>
                Analyse par catégorie
              </Typography>
              
              <Box sx={{ height: 400, mb: 3 }}>
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart
                    data={crossStats.category_stats}
                    margin={{ top: 20, right: 30, left: 20, bottom: 100 }}
                  >
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis 
                      dataKey="category" 
                      tick={{ angle: -45, textAnchor: 'end' }}
                      height={100}
                    />
                    <YAxis />
                    <Tooltip 
                      formatter={(value, name) => {
                        if (name === 'avg') return [value.toFixed(2), 'Moyenne'];
                        if (name === 'count') return [value, 'Nombre'];
                        return [value, name];
                      }}
                    />
                    <Legend />
                    <Bar dataKey="avg" name="Moyenne" fill="#8884d8">
                      {crossStats.category_stats.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                      ))}
                    </Bar>
                  </BarChart>
                </ResponsiveContainer>
              </Box>

              <TableContainer component={Paper} variant="outlined">
                <Table>
                  <TableHead>
                    <TableRow>
                      <TableCell>Catégorie</TableCell>
                      <TableCell align="right">Nombre</TableCell>
                      <TableCell align="right">%</TableCell>
                      <TableCell align="right">Moyenne</TableCell>
                      <TableCell align="right">Min</TableCell>
                      <TableCell align="right">Max</TableCell>
                      <TableCell align="right">Écart type</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {crossStats.category_stats.map((category, index) => (
                      <TableRow key={index}>
                        <TableCell>{category.category}</TableCell>
                        <TableCell align="right">{category.count}</TableCell>
                        <TableCell align="right">{category.percent}%</TableCell>
                        <TableCell align="right">{category.avg.toFixed(2)}</TableCell>
                        <TableCell align="right">{category.min}</TableCell>
                        <TableCell align="right">{category.max}</TableCell>
                        <TableCell align="right">{category.std_dev.toFixed(2)}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            </Box>
          )}

          {/* Onglet Corrélation */}
          {activeTab === 2 && crossStats.correlation && (
            <Box>
              <Typography variant="h6" gutterBottom>
                Analyse de corrélation
              </Typography>
              
              <Card sx={{ mb: 3 }}>
                <CardContent>
                  <Grid container spacing={2}>
                    <Grid item xs={12} md={4}>
                      <Typography variant="subtitle1" gutterBottom>
                        Coefficient de corrélation (Pearson)
                      </Typography>
                      <Typography variant="h4" sx={{ 
                        color: getCorrelationColor(crossStats.correlation.coefficient),
                        my: 1
                      }}>
                        {crossStats.correlation.coefficient.toFixed(4)}
                      </Typography>
                    </Grid>
                    <Grid item xs={12} md={4}>
                      <Typography variant="subtitle1" gutterBottom>
                        Interprétation
                      </Typography>
                      <Typography variant="body1">
                        <strong>{crossStats.correlation.strength.charAt(0).toUpperCase() + crossStats.correlation.strength.slice(1)}</strong>
                        {crossStats.correlation.coefficient > 0 
                          ? ' corrélation positive' 
                          : crossStats.correlation.coefficient < 0 
                            ? ' corrélation négative' 
                            : ' absence de corrélation'}
                      </Typography>
                    </Grid>
                    <Grid item xs={12} md={4}>
                      <Typography variant="subtitle1" gutterBottom>
                        Échantillon
                      </Typography>
                      <Typography variant="body1">
                        {crossStats.correlation.sample_count} paires de valeurs analysées
                      </Typography>
                    </Grid>
                  </Grid>
                  
                  <Box sx={{ mt: 2 }}>
                    <Typography variant="body2" color="textSecondary">
                      Un coefficient proche de -1 ou +1 indique une forte corrélation, tandis qu'un coefficient proche de 0 indique une absence de corrélation linéaire.
                    </Typography>
                  </Box>
                </CardContent>
              </Card>
              
              {/* Nuage de points pour visualiser la corrélation */}
              <Box sx={{ height: 400, mb: 2 }}>
                <ResponsiveContainer width="100%" height="100%">
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
                    <ZAxis range={[60]} />
                    <Tooltip 
                      cursor={{ strokeDasharray: '3 3' }}
                      formatter={(value, name) => {
                        return [value.toFixed(2), name === 'x' ? sourceColumn : targetColumn];
                      }}
                    />
                    <Scatter 
                      name="Valeurs" 
                      data={crossStats.correlation.sample_pairs} 
                      fill={getCorrelationColor(crossStats.correlation.coefficient)}
                    />
                  </ScatterChart>
                </ResponsiveContainer>
              </Box>
              
              <Typography variant="subtitle2" color="textSecondary" align="center">
                Ce graphique montre la relation entre {sourceColumn} (axe X) et {targetColumn} (axe Y).
                {crossStats.correlation.coefficient > 0.3 
                  ? ' La tendance positive indique que lorsque ' + sourceColumn + ' augmente, ' + targetColumn + ' tend aussi à augmenter.'
                  : crossStats.correlation.coefficient < -0.3
                    ? ' La tendance négative indique que lorsque ' + sourceColumn + ' augmente, ' + targetColumn + ' tend à diminuer.'
                    : ' Il n\'y a pas de tendance claire entre ces deux variables.'}
              </Typography>
            </Box>
          )}
        </Box>
      ) : (
        <Alert severity="info" sx={{ mt: 2 }}>
          Sélectionnez des colonnes pour voir l'analyse croisée
        </Alert>
      )}
    </Paper>
  );
};

export default CrossColumnAnalysis;