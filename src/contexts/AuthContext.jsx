import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import api from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [token, setToken] = useState(() => localStorage.getItem('myself_token') || '');
  const [user, setUser] = useState(() => JSON.parse(localStorage.getItem('myself_user') || 'null'));

  useEffect(() => {
    if (token) localStorage.setItem('myself_token', token);
    else localStorage.removeItem('myself_token');
  }, [token]);

  const login = async (payload) => {
    const { data } = await api.post('/auth/login.php', payload);
    if (data.success) {
      setToken(data.token);
      setUser(data.user);
      localStorage.setItem('myself_user', JSON.stringify(data.user));
    }
    return data;
  };

  const logout = async () => {
    try { await api.post('/auth/logout.php'); } catch {}
    setToken('');
    setUser(null);
    localStorage.removeItem('myself_user');
  };

  const value = useMemo(() => ({ token, user, setUser, login, logout, isLoggedIn: Boolean(token) }), [token, user]);
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export const useAuth = () => useContext(AuthContext);

