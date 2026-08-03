import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import Products from './Products'

const { clientGet, clientDelete } = vi.hoisted(() => ({
  clientGet: vi.fn(),
  clientDelete: vi.fn(),
}))

vi.mock('../api/client', () => ({
  default: { get: clientGet, post: vi.fn(), put: vi.fn(), delete: clientDelete },
}))

const products = [
  { id: 1, sku: 'SKU-1', name: 'Arroz', price: 10.5, category: 'Alimento', stock_quantity: 20 },
  { id: 2, sku: 'SKU-2', name: 'Feijão', price: 7.25, category: 'Alimento', stock_quantity: 15 },
]

describe('Products page', () => {
  beforeEach(() => {
    clientGet.mockReset()
    clientDelete.mockReset()
    clientGet.mockResolvedValue({ data: { data: products } })
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('lista os produtos retornados pela API', async () => {
    render(
      <MemoryRouter>
        <Products />
      </MemoryRouter>,
    )

    await waitFor(() => expect(screen.getByText('Arroz')).toBeInTheDocument())
    expect(screen.getByText('SKU-1')).toBeInTheDocument()
    expect(screen.getByText('Feijão')).toBeInTheDocument()
    expect(clientGet).toHaveBeenCalledWith('/products')
  })

  it('mostra mensagem de lista vazia quando não há produtos', async () => {
    clientGet.mockResolvedValue({ data: { data: [] } })

    render(
      <MemoryRouter>
        <Products />
      </MemoryRouter>,
    )

    await waitFor(() => expect(screen.getByText('Nenhum produto cadastrado.')).toBeInTheDocument())
  })

  it('exclui produto após confirmar', async () => {
    clientDelete.mockResolvedValue({ data: { success: true } })
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const user = userEvent.setup()

    render(
      <MemoryRouter>
        <Products />
      </MemoryRouter>,
    )

    await waitFor(() => expect(screen.getByText('Arroz')).toBeInTheDocument())
    await user.click(screen.getAllByRole('button', { name: 'Excluir' })[0])

    await waitFor(() => expect(clientDelete).toHaveBeenCalledWith('/products/1'))
  })

  it('não exclui quando a confirmação é cancelada', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false)
    const user = userEvent.setup()

    render(
      <MemoryRouter>
        <Products />
      </MemoryRouter>,
    )

    await waitFor(() => expect(screen.getByText('Arroz')).toBeInTheDocument())
    await user.click(screen.getAllByRole('button', { name: 'Excluir' })[0])

    expect(clientDelete).not.toHaveBeenCalled()
  })
})
