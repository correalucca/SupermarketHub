import { describe, it, expect, beforeEach, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import NewSale from './NewSale'

const { clientGet, clientPost } = vi.hoisted(() => ({
  clientGet: vi.fn(),
  clientPost: vi.fn(),
}))

vi.mock('../api/client', () => ({
  default: { get: clientGet, post: clientPost, put: vi.fn(), delete: vi.fn() },
}))

const products = [
  { id: 1, sku: 'SKU-1', name: 'Arroz', price: 10.5, category: 'Alimento', stock_quantity: 20 },
  { id: 2, sku: 'SKU-2', name: 'Feijão', price: 7.25, category: 'Alimento', stock_quantity: 15 },
]

async function addItem(user: ReturnType<typeof userEvent.setup>, productId: string, qty: string) {
  await screen.findByRole('option', { name: /arroz/i })
  await user.selectOptions(screen.getByRole('combobox'), productId)
  const input = screen.getByRole('spinbutton')
  await user.clear(input)
  await user.type(input, qty)
  await user.click(screen.getByRole('button', { name: 'Adicionar' }))
}

describe('NewSale page', () => {
  beforeEach(() => {
    clientGet.mockReset()
    clientPost.mockReset()
    clientGet.mockResolvedValue({ data: { data: products } })
  })

  it('carrega os produtos no seletor', async () => {
    render(
      <MemoryRouter>
        <NewSale />
      </MemoryRouter>,
    )

    await waitFor(() =>
      expect(screen.getByRole('option', { name: /arroz/i })).toBeInTheDocument(),
    )
    expect(clientGet).toHaveBeenCalledWith('/products')
  })

  it('adiciona item e calcula o total', async () => {
    const user = userEvent.setup()
    render(
      <MemoryRouter>
        <NewSale />
      </MemoryRouter>,
    )

    await addItem(user, '1', '2')

    expect(screen.getByText('Arroz')).toBeInTheDocument()
    expect(screen.getByText('R$ 21.00')).toBeInTheDocument()
    expect(screen.getByText(/Total: R\$ 21\.00/)).toBeInTheDocument()
  })

  it('acumula a quantidade ao adicionar o mesmo produto', async () => {
    const user = userEvent.setup()
    render(
      <MemoryRouter>
        <NewSale />
      </MemoryRouter>,
    )

    await addItem(user, '1', '2')
    await addItem(user, '1', '3')

    expect(screen.getByText('5')).toBeInTheDocument()
    expect(screen.getByText(/Total: R\$ 52\.50/)).toBeInTheDocument()
  })

  it('remove item da lista', async () => {
    const user = userEvent.setup()
    render(
      <MemoryRouter>
        <NewSale />
      </MemoryRouter>,
    )

    await addItem(user, '1', '2')

    expect(screen.getByText('Arroz')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Remover' }))

    expect(screen.queryByText('Arroz')).not.toBeInTheDocument()
    expect(screen.getByText(/Total: R\$ 0\.00/)).toBeInTheDocument()
  })

  it('envia a venda com os itens selecionados', async () => {
    clientPost.mockResolvedValue({ data: { success: true } })
    const user = userEvent.setup()
    render(
      <MemoryRouter>
        <NewSale />
      </MemoryRouter>,
    )

    await addItem(user, '1', '2')
    await user.click(screen.getByRole('button', { name: 'Finalizar Venda' }))

    await waitFor(() =>
      expect(clientPost).toHaveBeenCalledWith('/sales', { items: [{ product_id: 1, quantity: 2 }] }),
    )
  })

  it('desabilita o botão de finalizar sem itens', async () => {
    render(
      <MemoryRouter>
        <NewSale />
      </MemoryRouter>,
    )

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Finalizar Venda' })).toBeDisabled(),
    )
  })
})
