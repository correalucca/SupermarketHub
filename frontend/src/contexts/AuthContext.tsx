import { createContext, useContext, useState, useEffect, type ReactNode } from 'react';
import client from '../api/client';
import type { User } from '../types';

interface AuthContextType {
  user: User | null;
  token: string | null;
  login: (email: string, password: string) => Promise<void>;
  register: (name: string, email: string, password: string) => Promise<void>;
  logout: () => void;
  loading: boolean;
}

const AuthContext = createContext<AuthContextType | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(localStorage.getItem('token'));
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (token) {
      client.get('/me')
        .then((res) => setUser(res.data.data))
        .catch(() => { localStorage.removeItem('token'); setToken(null); })
        .finally(() => setLoading(false));
    } else {
      setLoading(false);
    }
  }, [token]);

  const login = async (email: string, password: string) => {
    const res = await client.post('/login', { email, password });
    const t = res.data.token;
    localStorage.setItem('token', t);
    setToken(t);
    setUser(res.data.user);
  };

  const register = async (name: string, email: string, password: string) => {
    const res = await client.post('/register', { name, email, password });
    const t = res.data.token;
    localStorage.setItem('token', t);
    setToken(t);
    setUser(res.data.user);
  };

  const logout = () => {
    client.post('/logout').catch(() => {});
    localStorage.removeItem('token');
    setToken(null);
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{ user, token, login, register, logout, loading }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth deve ser usado dentro de AuthProvider');
  return ctx;
}
