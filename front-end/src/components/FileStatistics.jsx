import React, { useState, useEffect } from 'react';
import { 
  Box, 
  Typography, 
  Paper, 
  CircularProgress, 
  Alert,
  Tabs,
  Tab,
  Grid,
  Card,
  CardContent,
  Divider,
  LinearProgress,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
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
  PieChart,
  Pie,
  Cell,
} from 'recharts';
import CrossColumnAnalysis from './CrossColumnAnalysis';
import ExcelService from '../services/ExcelService';

// Constantes pour les couleurs des graphiques
const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884d8', '#82ca9d'];
const DATA_TYPE_COLORS = {
  numeric: '#0088FE',
  text: '#00C49F',
  date: '#FFBB28',
  mixed: '#FF8042',
};

const FileStatistics = ({ fileId }) => {
  const [file, setFile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState(0);
  const [activeSheet, setActiveSheet] = useState('');

  useEffect(() => {
    const fetchFileData = async () => {
      if (!fileId) {
        setFile(null);
        setLoading(false);
        return;
      }

      setLoading(true);
      setError(null);
      
      try {
        const data = await ExcelService.getFileById(fileId);
        setFile(data);
        // Définir la première feuille comme active par défaut
        if (data.statistics && data.statistics.sheets) {
          setActiveSheet(Object.keys(data.statistics.sheets)[0]);
        }
      } catch (err) {
        setError('Erreur lors de la récupération des statistiques du fichier');
        console.error(err);
      } finally {
        setLoading(false);
      }
    };

    fetchFileData();
  }, [fileId]);

  const handleChangeTab = (event, newValue) => {
    setActiveTab(newValue);
  };

  const handleChangeSheet = (sheetName) => {
    setActiveSheet(sheetName);
  };

  if (!fileId) {
    return (
      <Paper elevation={3} sx={{ p: 3, textAlign: 'center' }}>
        <Typography variant="body1" color="textSecondary">
          Sélectionnez un fichier pour voir ses statistiques
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

  if (!file) {
    return (
      <Alert severity="warning" sx={{ mb: 3 }}>
        Fichier non trouvé
      </Alert>
    );
  }

  const { statistics } = file;
  
  if (!statistics || !statistics.sheets || Object.keys(statistics.sheets).length === 0) {
    return (
      <Paper elevation={3} sx={{ p: 3 }}>
        <Typography variant="h5" component="h2" gutterBottom>
          {file.original_name}
        </Typography>
        <Alert severity="info">
          Aucune statistique disponible pour ce fichier
        </Alert>
      </Paper>
    );
  }

  // Préparer les données pour les graphiques
  const prepareDataTypeDistribution = (sheet) => {
    const counts = {
      numeric: 0,
      text: 0,
      date: 0,
      mixed: 0
    };

    const currentSheet = statistics.sheets[sheet];
    if (!currentSheet || !currentSheet.columns) return [];

    Object.values(currentSheet.columns).forEach(column => {
      counts[column.data_type] = (counts[column.data_type] || 0) + 1;
    });

    return Object.keys(counts).map(type => ({
      name: type.charAt(0).toUpperCase() + type.slice(1),
      value: counts[type]
    })).filter(item => item.value > 0);
  };

  const prepareFillRateData = (sheet) => {
    const currentSheet = statistics.sheets[sheet];
    if (!currentSheet || !currentSheet.columns) return [];

    return Object.entries(currentSheet.columns).map(([header, data]) => ({
      name: header,
      fillRate: data.fill_rate
    }));
  };

  const prepareNumericStatsData = (sheet) => {
    const currentSheet = statistics.sheets[sheet];
    if (!currentSheet || !currentSheet.columns) return [];

    return Object.entries(currentSheet.columns)
      .filter(([_, data]) => data.data_type === 'numeric' && data.numeric)
      .map(([header, data]) => ({
        name: header,
        min: data.numeric.min,
        max: data.numeric.max,
        avg: data.numeric.avg
      }));
  };

  return (
    <Paper elevation={3} sx={{ mb: 3 }}>
      <Box sx={{ p: 2, borderBottom: '1px solid #eee' }}>
        <Typography variant="h5" component="h2">
          {file.original_name}
        </Typography>
        <Typography variant="body2" color="textSecondary">
          {file.sheet_count} feuille(s) | {Object.keys(statistics.sheets).length} avec des données
        </Typography>
      </Box>

      {/* Sélecteur de feuilles */}
      <Box sx={{ borderBottom: 1, borderColor: 'divider' }}>
        <Tabs
          value={activeSheet}
          onChange={(e, value) => handleChangeSheet(value)}
          variant="scrollable"
          scrollButtons="auto"
        >
          {Object.keys(statistics.sheets).map((sheetName) => (
            <Tab 
              key={sheetName} 
              label={sheetName} 
              value={sheetName}
            />
          ))}
        </Tabs>
      </Box>

      {/* Contenu pour la feuille sélectionnée */}
      {activeSheet && (
        <Box sx={{ p: 2 }}>
          <Box sx={{ borderBottom: 1, borderColor: 'divider', mb: 2 }}>
            <Tabs value={activeTab} onChange={handleChangeTab}>
              <Tab label="Aperçu" />
              <Tab label="Colonnes" />
              <Tab label="Graphiques" />
              <Tab label="Analyse croisée" />
            </Tabs>
          </Box>

          {/* Onglet Aperçu */}
          {activeTab === 0 && (
            <Box>
              <Grid container spacing={3}>
                <Grid item xs={12} md={6}>
                  <Card>
                    <CardContent>
                      <Typography variant="h6" gutterBottom>
                        Informations générales
                      </Typography>
                      <Divider sx={{ mb: 2 }} />
                      <Typography variant="body1">
                        Nom de la feuille: <strong>{activeSheet}</strong>
                      </Typography>
                      <Typography variant="body1">
                        Nombre de lignes: <strong>{statistics.sheets[activeSheet].row_count}</strong>
                      </Typography>
                      <Typography variant="body1">
                        Nombre de colonnes: <strong>{statistics.sheets[activeSheet].column_count}</strong>
                      </Typography>
                    </CardContent>
                  </Card>
                </Grid>
                <Grid item xs={12} md={6}>
                  <Card>
                    <CardContent>
                      <Typography variant="h6" gutterBottom>
                        Distribution des types de données
                      </Typography>
                      <Divider sx={{ mb: 2 }} />
                      <Box sx={{ height: 200 }}>
                        <ResponsiveContainer width="100%" height="100%">
                          <PieChart>
                            <Pie
                              data={prepareDataTypeDistribution(activeSheet)}
                              cx="50%"
                              cy="50%"
                              labelLine={false}
                              label={({ name, percent }) => `${name}: ${(percent * 100).toFixed(0)}%`}
                              outerRadius={80}
                              fill="#8884d8"
                              dataKey="value"
                            >
                              {prepareDataTypeDistribution(activeSheet).map((entry, index) => (
                                <Cell key={`cell-${index}`} fill={DATA_TYPE_COLORS[entry.name.toLowerCase()] || COLORS[index % COLORS.length]} />
                              ))}
                            </Pie>
                            <Tooltip />
                          </PieChart>
                        </ResponsiveContainer>
                      </Box>
                    </CardContent>
                  </Card>
                </Grid>
              </Grid>
            </Box>
          )}

          {/* Onglet Colonnes */}
          {activeTab === 1 && (
            <TableContainer>
              <Table>
                <TableHead>
                  <TableRow>
                    <TableCell>Nom de la colonne</TableCell>
                    <TableCell>Type de données</TableCell>
                    <TableCell>Taux de remplissage</TableCell>
                    <TableCell>Valeurs non vides</TableCell>
                    <TableCell>Statistiques (si numérique)</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {Object.entries(statistics.sheets[activeSheet].columns).map(([header, data]) => (
                    <TableRow key={header}>
                      <TableCell>{header}</TableCell>
                      <TableCell>
                        <Box sx={{ display: 'flex', alignItems: 'center' }}>
                          <Box 
                            sx={{ 
                              width: 12, 
                              height: 12, 
                              borderRadius: '50%', 
                              bgcolor: DATA_TYPE_COLORS[data.data_type] || '#ccc',
                              mr: 1 
                            }} 
                          />
                          {data.data_type.charAt(0).toUpperCase() + data.data_type.slice(1)}
                        </Box>
                      </TableCell>
                      <TableCell>
                        <Box sx={{ display: 'flex', alignItems: 'center', width: '100%' }}>
                          <Box sx={{ width: '100%', mr: 1 }}>
                            <LinearProgress 
                              variant="determinate" 
                              value={data.fill_rate} 
                              sx={{ 
                                height: 10, 
                                borderRadius: 5,
                                backgroundColor: '#f0f0f0',
                                '& .MuiLinearProgress-bar': {
                                  backgroundColor: data.fill_rate > 70 ? '#4caf50' : data.fill_rate > 30 ? '#ff9800' : '#f44336'
                                }
                              }}
                            />
                          </Box>
                          <Box sx={{ minWidth: 35 }}>
                            <Typography variant="body2" color="textSecondary">{`${data.fill_rate}%`}</Typography>
                          </Box>
                        </Box>
                      </TableCell>
                      <TableCell>{data.non_empty_count} / {data.non_empty_count + data.empty_count}</TableCell>
                      <TableCell>
                        {data.numeric ? (
                          <>
                            <Typography variant="body2">Min: {data.numeric.min}</Typography>
                            <Typography variant="body2">Max: {data.numeric.max}</Typography>
                            <Typography variant="body2">Moy: {data.numeric.avg?.toFixed(2)}</Typography>
                            <Typography variant="body2">Somme: {data.numeric.sum}</Typography>
                          </>
                        ) : (
                          '-'
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}

          {/* Onglet Graphiques */}
          {activeTab === 2 && (
            <Box>
              <Typography variant="h6" gutterBottom>
                Taux de remplissage par colonne
              </Typography>
              <Box sx={{ height: 300, mb: 4 }}>
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart
                    data={prepareFillRateData(activeSheet)}
                    margin={{ top: 5, right: 30, left: 20, bottom: 100 }}
                  >
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis 
                      dataKey="name" 
                      tick={{ angle: -45, textAnchor: 'end' }}
                      height={70}
                    />
                    <YAxis 
                      tickFormatter={(value) => `${value}%`}
                      domain={[0, 100]}
                    />
                    <Tooltip formatter={(value) => [`${value}%`, 'Taux de remplissage']} />
                    <Legend />
                    <Bar dataKey="fillRate" name="Taux de remplissage (%)" fill="#8884d8" />
                  </BarChart>
                </ResponsiveContainer>
              </Box>

              {/* Graphique pour les colonnes numériques */}
              {prepareNumericStatsData(activeSheet).length > 0 && (
                <>
                  <Typography variant="h6" gutterBottom>
                    Statistiques des colonnes numériques
                  </Typography>
                  <Box sx={{ height: 300 }}>
                    <ResponsiveContainer width="100%" height="100%">
                      <BarChart
                        data={prepareNumericStatsData(activeSheet)}
                        margin={{ top: 5, right: 30, left: 20, bottom: 100 }}
                      >
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis 
                          dataKey="name" 
                          tick={{ angle: -45, textAnchor: 'end' }}
                          height={70}
                        />
                        <YAxis />
                        <Tooltip />
                        <Legend />
                        <Bar dataKey="min" name="Minimum" fill="#0088FE" />
                        <Bar dataKey="avg" name="Moyenne" fill="#00C49F" />
                        <Bar dataKey="max" name="Maximum" fill="#FFBB28" />
                      </BarChart>
                    </ResponsiveContainer>
                  </Box>
                </>
              )}
            </Box>
          )}

          {/* Onglet Analyse croisée */}
          {activeTab === 3 && (
            <CrossColumnAnalysis 
              sheetData={statistics.sheets[activeSheet]} 
            />
          )}
        </Box>
      )}
    </Paper>
  );
};

export default FileStatistics;