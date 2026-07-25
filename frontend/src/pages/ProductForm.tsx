import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import client from '../api/client';
import type { Product, ProductFormData } from '../types';

export default function ProductForm() {
  const { id } = useParams();
  const isEditing = Boolean(id);
  const navigate = useNavigate();
  const [form, setForm] = useState<ProductFormData>({
    sku: '', name: '', price: 0, category: '', stock_quantity: 0,
  });
  const [error, setError] = useState('');

  useEffect(() => {
    if (id) {
      client.get(`/products/${id}`).then((res) => {
        const p: Product = res.data.data;
        setForm({ sku: p.sku, name: p.name, price: p.price, category: p.category, stock_quantity: p.stock_quantity });
      });
    }
  }, [id]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      if (isEditing) {
        await client.put(`/products/${id}`, form);
      } else {
        await client.post('/products', form);
      }
      navigate('/products');
    } catch {
      setError('Erro ao salvar produto. Verifique os dados.');
    }
  };

  return (
    <div>
      <h1>{isEditing ? 'Editar Produto' : 'Novo Produto'}</h1>
      {error && <div className="alert error">{error}</div>}
      <form onSubmit={handleSubmit} className="product-form">
        <label>SKU <input value={form.sku} onChange={(e) => setForm({ ...form, sku: e.target.value })} required /></label>
        <label>Nome <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required /></label>
        <label>Categoria <input value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} required /></label>
        <label>Preço <input type="number" step="0.01" value={form.price} onChange={(e) => setForm({ ...form, price: +e.target.value })} required /></label>
        <label>Estoque <input type="number" value={form.stock_quantity} onChange={(e) => setForm({ ...form, stock_quantity: +e.target.value })} required /></label>
        <div className="form-actions">
          <button type="submit" className="btn-primary">Salvar</button>
          <button type="button" onClick={() => navigate('/products')} className="btn-secondary">Cancelar</button>
        </div>
      </form>
    </div>
  );
}
