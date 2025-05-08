import React, { useState } from 'react';
import { 
  Container, 
  Box, 
  Typography, 
  AppBar, 
  Toolbar, 
  CssBaseline, 
  createTheme, 
  ThemeProvider 
} from '@mui/material';
import TableViewIcon from '@mui/icons-material/TableView';
import FileUpload from './components/FileUpload';
import FileList from './components/FileList';
import FileStatistics from './components/FileStatistics';
import './App.css';

// Création d'un thème personnalisé
const theme = createTheme({
  palette: {
    primary: {
      main: '#1976d2',
    },
    secondary: {
      main: '#dc004e',
    },
  },
  typography: {
    fontFamily: [
      '-apple-system',
      'BlinkMacSystemFont',
      '"Segoe UI"',
      'Roboto',
      '"Helvetica Neue"',
      'Arial',
      'sans-serif',
    ].join(','),
  },
});

function App() {
  const [selectedFileId, setSelectedFileId] = useState(null);
  const [refreshTrigger, setRefreshTrigger] = useState(0);

  const handleFileUploaded = (file) => {
    setSelectedFileId(file.id);
    setRefreshTrigger(prev => prev + 1);
  };

  const handleFileSelect = (fileId) => {
    setSelectedFileId(fileId);
  };

  return (
    <ThemeProvider theme={theme}>
      <CssBaseline />
      <Box sx={{ flexGrow: 1 }}>
        <AppBar position="static">
          <Toolbar>
            <TableViewIcon sx={{ mr: 2 }} />
            <Typography variant="h6" component="div" sx={{ flexGrow: 1 }}>
              Analyseur de Fichiers Excel
            </Typography>
          </Toolbar>
        </AppBar>
        
        <Container maxWidth="lg" sx={{ mt: 4, mb: 4 }}>
          <FileUpload onFileUploaded={handleFileUploaded} />
          
          <Box sx={{ display: 'flex', flexDirection: { xs: 'column', md: 'row' }, gap: 2 }}>
            <Box sx={{ width: { xs: '100%', md: '30%' } }}>
              <FileList 
                onFileSelect={handleFileSelect} 
                refresh={refreshTrigger} 
              />
            </Box>
            
            <Box sx={{ width: { xs: '100%', md: '70%' } }}>
              <FileStatistics fileId={selectedFileId} />
            </Box>
          </Box>
        </Container>
      </Box>
    </ThemeProvider>
  );
}

export default App;