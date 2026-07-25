import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import client from '../api/client';
import type { Product, SaleItem } from '../types';

export default function NewSale() {
  const navigate = useNavigate();
  const [products, setProducts] = useState<Product[]>([]);
  const [items, setItems] = useState<SaleItem[]>([]);
  const [selectedId, setSelectedId] = useState('');
  const [quantity, setQuantity] = useState(1);
  const [error, setError] = useState('');

  useEffect(() => {
    client.get('/products').then((res) => setProducts(res.data.data ?? []));
  }, []);

  const addItem = () => {
    if (!selectedId) return;
    const productId = +selectedId;
    const existing = items.find((i) => i.product_id === productId);
    if (existing) {
      setItems(items.map((i) => i.product_id === productId ? { ...i, quantity: i.quantity + quantity } : i));
    } else {
      setItems([...items, { product_id: productId, quantity }]);
    }
    setSelectedId('');
    setQuantity(1);
  };

  const removeItem = (productId: number) => {
    setItems(items.filter((i) => i.product_id !== productId));
  };

  const total = items.reduce((sum, item) => {
    const p = products.find((pr) => pr.id === item.product_id);
    return sum + (p ? p.price * item.quantity : 0);
  }, 0);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (items.length === 0) { setError('Adicione pelo menos um item.'); return; }
    try {
      await client.post('/sales', { items });
      navigate('/products');
    } catch {
      setError('Erro ao finalizar venda.');
    }
  };

  return (
    <div>
      <h1>Nova Venda</h1>
      {error && <div className="alert error">{error}</div>}
      <form onSubmit={handleSubmit}>
        <div className="sale-row">
          <select value={selectedId} onChange={(e) => setSelectedId(e.target.value)}>
            <option value="">Selecione um produto</option>
            {products.map((p) => (
              <option key={p.id} value={p.id}>{p.name} (R$ {p.price.toFixed(2)}) - Est: {p.stock_quantity}</option>
            ))}
          </select>
          <input type="number" min={1} value={quantity} onChange={(e) => setQuantity(+e.target.value)} style={{ width: 80 }} />
          <button type="button" onClick={addItem} className="btn-primary">Adicionar</button>
        </div>

        <table className="table">
          <thead>
            <tr><th>Produto</th><th>Preço Unit.</th><th>Qtd</th><th>Subtotal</th><th></th></tr>
          </thead>
          <tbody>
            {items.map((item) => {
              const p = products.find((pr) => pr.id === item.product_id);
              return (
                <tr key={item.product_id}>
                  <td>{p?.name}</td>
                  <td>R$ {p?.price.toFixed(2)}</td>
                  <td>{item.quantity}</td>
                  <td>R$ {(p ? p.price * item.quantity : 0).toFixed(2)}</td>
                  <td><button type="button" onClick={() => removeItem(item.product_id)} className="btn-sm btn-danger">Remover</button></td>
                </tr>
              );
            })}
          </tbody>
        </table>

        <div className="sale-total">
          <strong>Total: R$ {total.toFixed(2)}</strong>
        </div>

        <div className="form-actions">
          <button type="submit" className="btn-primary" disabled={items.length === 0}>Finalizar Venda</button>
          <button type="button" onClick={() => navigate('/products')} className="btn-secondary">Cancelar</button>
        </div>
      </form>
    </div>
  );
}
