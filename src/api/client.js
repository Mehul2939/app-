import axios from 'axios';

export const API_BASE = '/app/api';

const api = axios.create({
  baseURL: API_BASE,
  headers: { 'Content-Type': 'application/json' }
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('myself_token');
  if (token) config.headers['X-User-Token'] = token;
  if (config.url?.startsWith('/admin/')) {
    const adminToken = localStorage.getItem('myself_admin_token');
    if (adminToken) config.headers['X-Admin-Token'] = adminToken;
  }
  return config;
});

export default api;
