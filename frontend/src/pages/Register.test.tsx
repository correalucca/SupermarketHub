import { describe, it, expect, beforeEach, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { AuthProvider } from '../contexts/AuthContext'
import Register from './Register'

const { clientGet, clientPost } = vi.hoisted(() => ({
  clientGet: vi.fn(),
  clientPost: vi.fn(),
}))

vi.mock('../api/client', () => ({
  default: { get: clientGet, post: clientPost, put: vi.fn(), delete: vi.fn() },
}))

describe('Register page', () => {
  beforeEach(() => {
    localStorage.clear()
    clientGet.mockReset()
    clientPost.mockReset()
    // Resposta padrão para o /me que o provider dispara após o cadastro.
    clientGet.mockResolvedValue({ data: { data: { id: 1, name: 'Ana', email: 'a@b.com' } } })
  })

  it('renderiza o formulário de cadastro', () => {
    render(
      <MemoryRouter>
        <AuthProvider>
          <Register />
        </AuthProvider>
      </MemoryRouter>,
    )

    expect(screen.getByRole('heading', { name: 'Cadastrar' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Cadastrar' })).toBeInTheDocument()
  })

  it('chama register e persiste a autenticação', async () => {
    clientPost.mockResolvedValue({
      data: { token: 't', user: { id: 1, name: 'Ana', email: 'a@b.com' } },
    })
    const user = userEvent.setup()

    render(
      <MemoryRouter>
        <AuthProvider>
          <Register />
        </AuthProvider>
      </MemoryRouter>,
    )

    await user.type(screen.getByLabelText(/nome/i), 'Ana')
    await user.type(screen.getByLabelText(/email/i), 'a@b.com')
    await user.type(screen.getByLabelText(/senha/i), 'secret123')
    await user.click(screen.getByRole('button', { name: 'Cadastrar' }))

    await waitFor(() =>
      expect(clientPost).toHaveBeenCalledWith('/register', {
        name: 'Ana',
        email: 'a@b.com',
        password: 'secret123',
      }),
    )
    expect(localStorage.getItem('token')).toBe('t')
  })

  it('exibe erro quando o cadastro falha', async () => {
    clientPost.mockRejectedValue(new Error('validation failed'))
    const user = userEvent.setup()

    render(
      <MemoryRouter>
        <AuthProvider>
          <Register />
        </AuthProvider>
      </MemoryRouter>,
    )

    await user.type(screen.getByLabelText(/nome/i), 'Ana')
    await user.type(screen.getByLabelText(/email/i), 'a@b.com')
    await user.type(screen.getByLabelText(/senha/i), 'secret123')
    await user.click(screen.getByRole('button', { name: 'Cadastrar' }))

    await waitFor(() =>
      expect(screen.getByText('Erro ao cadastrar. Verifique os dados.')).toBeInTheDocument(),
    )
  })
})
