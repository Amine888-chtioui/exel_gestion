import React, { useState, useCallback } from 'react';
import { useDropzone } from 'react-dropzone';
import { 
  Box, 
  Button, 
  CircularProgress, 
  Typography, 
  Paper, 
  Alert,
} from '@mui/material';
import CloudUploadIcon from '@mui/icons-material/CloudUpload';
import ExcelService from '../services/ExcelService';

const FileUpload = ({ onFileUploaded }) => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);

  const onDrop = useCallback(async (acceptedFiles) => {
    const file = acceptedFiles[0];
    if (!file) return;

    // Vérifier si le fichier est un fichier Excel
    const isExcel = 
      file.type === 'application/vnd.ms-excel' || 
      file.type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ||
      file.name.endsWith('.xls') || 
      file.name.endsWith('.xlsx');

    if (!isExcel) {
      setError('Veuillez importer un fichier Excel (.xls ou .xlsx)');
      return;
    }

    setLoading(true);
    setError(null);
    setSuccess(false);

    try {
      const response = await ExcelService.uploadFile(file);
      setSuccess(true);
      if (onFileUploaded) {
        onFileUploaded(response.file);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Une erreur est survenue lors de l\'importation du fichier');
    } finally {
      setLoading(false);
    }
  }, [onFileUploaded]);

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: {
      'application/vnd.ms-excel': ['.xls'],
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['.xlsx']
    },
    multiple: false
  });

  return (
    <Paper elevation={3} sx={{ padding: 3, mb: 3 }}>
      <Typography variant="h5" component="h2" gutterBottom>
        Importer un fichier Excel
      </Typography>

      <Box 
        {...getRootProps()} 
        sx={{
          border: '2px dashed #cccccc',
          borderRadius: 2,
          padding: 3,
          textAlign: 'center',
          backgroundColor: isDragActive ? '#f0f8ff' : 'white',
          cursor: 'pointer',
          mb: 2
        }}
      >
        <input {...getInputProps()} />
        <CloudUploadIcon sx={{ fontSize: 48, color: 'primary.main', mb: 2 }} />
        <Typography variant="body1" gutterBottom>
          {isDragActive
            ? 'Déposez le fichier ici...'
            : 'Glissez-déposez un fichier Excel ici, ou cliquez pour sélectionner un fichier'}
        </Typography>
        <Typography variant="body2" color="textSecondary">
          Formats acceptés: .xls, .xlsx
        </Typography>
      </Box>

      {loading && (
        <Box sx={{ display: 'flex', justifyContent: 'center', mt: 2 }}>
          <CircularProgress />
        </Box>
      )}

      {error && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {error}
        </Alert>
      )}

      {success && (
        <Alert severity="success" sx={{ mt: 2 }}>
          Fichier importé avec succès!
        </Alert>
      )}

      <Button 
        variant="contained"
        color="primary"
        startIcon={<CloudUploadIcon />}
        onClick={() => document.querySelector('input[type="file"]').click()}
        sx={{ mt: 2 }}
        disabled={loading}
      >
        Sélectionner un fichier
      </Button>
    </Paper>
  );
};

export default FileUpload;