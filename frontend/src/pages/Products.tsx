import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import client from '../api/client';
import type { Product } from '../types';

export default function Products() {
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchProducts = async () => {
    const res = await client.get('/products');
    setProducts(res.data.data ?? []);
    setLoading(false);
  };

  useEffect(() => { fetchProducts(); }, []);

  const handleDelete = async (id: number) => {
    if (!confirm('Excluir este produto?')) return;
    await client.delete(`/products/${id}`);
    fetchProducts();
  };

  if (loading) return <p>Carregando...</p>;

  return (
    <div>
      <div className="page-header">
        <h1>Produtos</h1>
        <Link to="/products/new" className="btn-primary">Novo Produto</Link>
      </div>
      <table className="table">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Nome</th>
            <th>Categoria</th>
            <th>Preço</th>
            <th>Estoque</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          {products.map((p) => (
            <tr key={p.id}>
              <td>{p.sku}</td>
              <td>{p.name}</td>
              <td>{p.category}</td>
              <td>R$ {p.price.toFixed(2)}</td>
              <td>{p.stock_quantity}</td>
              <td className="actions">
                <Link to={`/products/${p.id}/edit`} className="btn-sm">Editar</Link>
                <button onClick={() => handleDelete(p.id)} className="btn-sm btn-danger">Excluir</button>
              </td>
            </tr>
          ))}
          {products.length === 0 && (
            <tr><td colSpan={6}>Nenhum produto cadastrado.</td></tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
