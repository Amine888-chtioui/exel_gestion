import apiClient from './axiosConfig';

const CrossAnalysisService = {
  /**
   * Get available columns for cross-analysis in a specific sheet
   * @param {number} fileId - File ID
   * @param {string} sheetName - Sheet name
   * @returns {Promise} Promise with the available columns
   */
  getAvailableColumns: async (fileId, sheetName) => {
    try {
      const response = await apiClient.get(`/cross-analysis/${fileId}/${sheetName}`);
      return response.data;
    } catch (error) {
      console.error('Error fetching available columns:', error);
      throw error;
    }
  },

  /**
   * Analyze the relationship between two columns
   * @param {number} fileId - File ID
   * @param {string} sheetName - Sheet name
   * @param {string} targetColumn - Target column (usually numeric)
   * @param {string} sourceColumn - Source column
   * @returns {Promise} Promise with the analysis results
   */
  analyzeColumns: async (fileId, sheetName, targetColumn, sourceColumn) => {
    try {
      const response = await apiClient.get(`/cross-analysis/${fileId}/${sheetName}/${targetColumn}/${sourceColumn}`);
      return response.data;
    } catch (error) {
      console.error('Error analyzing columns:', error);
      throw error;
    }
  },

  /**
   * Generate a correlation matrix for numeric columns
   * @param {number} fileId - File ID
   * @param {string} sheetName - Sheet name
   * @param {Array} columns - Selected columns (empty for all numeric)
   * @param {number} minCorrelation - Minimum correlation threshold
   * @returns {Promise} Promise with the correlation matrix
   */
  getCorrelationMatrix: async (fileId, sheetName, columns = [], minCorrelation = 0) => {
    try {
      const response = await apiClient.post(`/cross-analysis/${fileId}/${sheetName}/correlationMatrix`, {
        columns,
        min_correlation: minCorrelation
      });
      return response.data;
    } catch (error) {
      console.error('Error generating correlation matrix:', error);
      throw error;
    }
  },

  /**
   * Generate a pivot table
   * @param {number} fileId - File ID
   * @param {string} sheetName - Sheet name
   * @param {string} rowColumn - Column to use as row labels
   * @param {string} columnColumn - Column to use as column labels
   * @param {string} valueColumn - Column to aggregate
   * @param {string} aggregation - Aggregation function (sum, avg, count, min, max, median)
   * @returns {Promise} Promise with the pivot table data
   */
  getPivotTable: async (fileId, sheetName, rowColumn, columnColumn, valueColumn, aggregation = 'sum') => {
    try {
      const response = await apiClient.post(`/cross-analysis/${fileId}/${sheetName}/pivotStats`, {
        row_column: rowColumn,
        column_column: columnColumn,
        value_column: valueColumn,
        aggregation: aggregation
      });
      return response.data;
    } catch (error) {
      console.error('Error generating pivot table:', error);
      throw error;
    }
  }
};

export default CrossAnalysisService;