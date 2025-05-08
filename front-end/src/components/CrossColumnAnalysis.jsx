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
  Alert,
  Tabs,
  Tab
} from '@mui/material';
import CorrelationMatrix from './CorrelationMatrix';
import CrossAnalysisService from '../services/CrossAnalysisService';

const CrossColumnAnalysis = ({ sheetData }) => {
  const [activeTab, setActiveTab] = useState(0);
  const [fileId, setFileId] = useState(null);
  const [sheetName, setSheetName] = useState(null);

  // Utilisez useEffect pour extraire fileId et sheetName des données
  useEffect(() => {
    if (sheetData) {
      // Vérifiez si les statistiques contiennent un ID de fichier
      // Sinon, essayez de l'extraire de l'URL
      const urlParams = new URLSearchParams(window.location.search);
      const idFromUrl = urlParams.get('fileId');
      
      // Définir l'ID du fichier (peut être dans sheetData ou dans l'URL)
      setFileId(sheetData.file_id || idFromUrl);
      
      // Définir le nom de la feuille
      setSheetName(sheetData.name);
      
      console.log("SheetData:", sheetData);
      console.log("FileID:", sheetData.file_id || idFromUrl);
      console.log("SheetName:", sheetData.name);
    }
  }, [sheetData]);

  const handleTabChange = (event, newValue) => {
    setActiveTab(newValue);
  };

  if (!sheetData) {
    return (
      <Alert severity="info">
        Sélectionnez une feuille pour voir l'analyse croisée des colonnes
      </Alert>
    );
  }

  if (!sheetData.columns || Object.keys(sheetData.columns).length === 0) {
    return (
      <Alert severity="warning">
        Cette feuille ne contient pas de données pour l'analyse croisée
      </Alert>
    );
  }

  // Trouver les colonnes numériques
  const numericColumns = Object.entries(sheetData.columns)
    .filter(([_, data]) => data.data_type === 'numeric')
    .map(([header]) => header);

  if (numericColumns.length < 2) {
    return (
      <Alert severity="info">
        L'analyse croisée nécessite au moins deux colonnes numériques. Cette feuille n'en contient que {numericColumns.length}.
      </Alert>
    );
  }

  return (
    <Box>
      <Tabs value={activeTab} onChange={handleTabChange} sx={{ mb: 2 }}>
        <Tab label="Matrice de corrélation" />
        <Tab label="Analyse par catégorie" disabled={numericColumns.length < 2} />
      </Tabs>

      {activeTab === 0 && (
        <Box>
          <Typography variant="body1" paragraph>
            Cette feuille contient {numericColumns.length} colonnes numériques qui peuvent être analysées pour trouver des corrélations et des relations.
          </Typography>
          
          {fileId && sheetName ? (
            <CorrelationMatrix 
              fileId={fileId} 
              sheetName={sheetName} 
            />
          ) : (
            <Alert severity="warning">
              Impossible de déterminer l'ID du fichier ou le nom de la feuille. Veuillez recharger la page.
            </Alert>
          )}
        </Box>
      )}

      {activeTab === 1 && (
        <Box>
          <Typography variant="body1">
            Fonctionnalité en développement
          </Typography>
        </Box>
      )}
    </Box>
  );
};

export default CrossColumnAnalysis;