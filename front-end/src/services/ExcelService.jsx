import apiClient from './axiosConfig';

const ExcelService = {
  // Récupérer tous les fichiers
  getAllFiles: async () => {
    try {
      const response = await apiClient.get('/excel-files');
      return response.data;
    } catch (error) {
      console.error('Error fetching files:', error);
      throw error;
    }
  },

  // Récupérer un fichier par son ID
  getFileById: async (id) => {
    try {
      const response = await apiClient.get(`/excel-files/${id}`);
      return response.data;
    } catch (error) {
      console.error(`Error fetching file with ID ${id}:`, error);
      throw error;
    }
  },

  // Uploader un nouveau fichier
  uploadFile: async (file) => {
    try {
      const formData = new FormData();
      formData.append('file', file);

      const response = await apiClient.post('/excel-files', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      return response.data;
    } catch (error) {
      console.error('Error uploading file:', error);
      throw error;
    }
  },

  // Supprimer un fichier
  deleteFile: async (id) => {
    try {
      const response = await apiClient.delete(`/excel-files/${id}`);
      return response.data;
    } catch (error) {
      console.error(`Error deleting file with ID ${id}:`, error);
      throw error;
    }
  },
};

export default ExcelService;