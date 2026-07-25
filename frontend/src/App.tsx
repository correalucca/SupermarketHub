import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import Layout from './components/Layout';
import Login from './pages/Login';
import Register from './pages/Register';
import Products from './pages/Products';
import ProductForm from './pages/ProductForm';
import NewSale from './pages/NewSale';
import type { ReactNode } from 'react';

function PrivateRoute({ children }: { children: ReactNode }) {
  const { token, loading } = useAuth();
  if (loading) return <p className="container">Carregando...</p>;
  return token ? children : <Navigate to="/login" />;
}

function PublicRoute({ children }: { children: ReactNode }) {
  const { token, loading } = useAuth();
  if (loading) return <p className="container">Carregando...</p>;
  return token ? <Navigate to="/products" /> : children;
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<PublicRoute><Login /></PublicRoute>} />
          <Route path="/register" element={<PublicRoute><Register /></PublicRoute>} />
          <Route element={<PrivateRoute><Layout /></PrivateRoute>}>
            <Route path="/products" element={<Products />} />
            <Route path="/products/new" element={<ProductForm />} />
            <Route path="/products/:id/edit" element={<ProductForm />} />
            <Route path="/sales/new" element={<NewSale />} />
            <Route path="/" element={<Navigate to="/products" />} />
          </Route>
          <Route path="*" element={<Navigate to="/products" />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
