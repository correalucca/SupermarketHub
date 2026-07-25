import { Link, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

export default function Layout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <div className="layout">
      <nav className="navbar">
        <Link to="/" className="brand">SupermarketHub</Link>
        <div className="nav-links">
          <Link to="/products">Produtos</Link>
          <Link to="/sales/new">Nova Venda</Link>
        </div>
        <div className="nav-user">
          <span>{user?.name}</span>
          <button onClick={handleLogout} className="btn-link">Sair</button>
        </div>
      </nav>
      <main className="container">
        <Outlet />
      </main>
    </div>
  );
}
