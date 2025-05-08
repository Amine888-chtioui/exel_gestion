import React, { useState, useEffect } from 'react';
import { 
  Box, 
  Typography, 
  Paper, 
  List, 
  ListItem, 
  ListItemText, 
  ListItemSecondaryAction, 
  IconButton,
  CircularProgress,
  Alert,
  Divider,
} from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import VisibilityIcon from '@mui/icons-material/Visibility';
import ExcelService from '../services/ExcelService';
import { formatBytes, formatDate } from '../utils/formatters';

const FileList = ({ onFileSelect, refresh }) => {
  const [files, setFiles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchFiles = async () => {
      setLoading(true);
      setError(null);
      try {
        const data = await ExcelService.getAllFiles();
        setFiles(data);
      } catch (err) {
        setError('Erreur lors de la récupération des fichiers');
        console.error(err);
      } finally {
        setLoading(false);
      }
    };

    fetchFiles();
  }, [refresh]);

  const handleDelete = async (id, e) => {
    e.stopPropagation();
    if (window.confirm('Êtes-vous sûr de vouloir supprimer ce fichier?')) {
      try {
        await ExcelService.deleteFile(id);
        setFiles(files.filter(file => file.id !== id));
      } catch (error) {
        console.error('Error deleting file:', error);
        alert('Erreur lors de la suppression du fichier');
      }
    }
  };

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
    <Paper elevation={3} sx={{ mb: 3 }}>
      <Box sx={{ p: 2, borderBottom: '1px solid #eee' }}>
        <Typography variant="h5" component="h2">
          Fichiers importés
        </Typography>
      </Box>
      
      {files.length === 0 ? (
        <Box sx={{ p: 3, textAlign: 'center' }}>
          <Typography variant="body1" color="textSecondary">
            Aucun fichier importé pour le moment
          </Typography>
        </Box>
      ) : (
        <List>
          {files.map((file, index) => (
            <React.Fragment key={file.id}>
              {index > 0 && <Divider />}
              <ListItem 
                button 
                onClick={() => onFileSelect(file.id)}
                sx={{ 
                  cursor: 'pointer',
                  '&:hover': { backgroundColor: '#f5f5f5' } 
                }}
              >
                <ListItemText
                  primary={file.original_name}
                  secondary={
                    <>
                      <Typography component="span" variant="body2" color="textSecondary">
                        Importé le: {formatDate(file.created_at)}
                      </Typography>
                      <br />
                      <Typography component="span" variant="body2" color="textSecondary">
                        Taille: {formatBytes(file.size)} | {file.sheet_count} feuille(s)
                      </Typography>
                    </>
                  }
                />
                <ListItemSecondaryAction>
                  <IconButton 
                    edge="end" 
                    aria-label="view" 
                    onClick={() => onFileSelect(file.id)}
                  >
                    <VisibilityIcon />
                  </IconButton>
                  <IconButton 
                    edge="end" 
                    aria-label="delete" 
                    onClick={(e) => handleDelete(file.id, e)}
                  >
                    <DeleteIcon />
                  </IconButton>
                </ListItemSecondaryAction>
              </ListItem>
            </React.Fragment>
          ))}
        </List>
      )}
    </Paper>
  );
};

export default FileList;