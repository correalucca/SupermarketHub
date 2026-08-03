import { describe, it, expect, beforeEach, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Routes, Route } from 'react-router-dom'
import { AuthProvider } from '../contexts/AuthContext'
import Login from './Login'

const { clientGet, clientPost } = vi.hoisted(() => ({
  clientGet: vi.fn(),
  clientPost: vi.fn(),
}))

vi.mock('../api/client', () => ({
  default: { get: clientGet, post: clientPost, put: vi.fn(), delete: vi.fn() },
}))

describe('Login page', () => {
  beforeEach(() => {
    localStorage.clear()
    clientGet.mockReset()
    clientPost.mockReset()
    // Resposta padrão para o /me que o provider dispara após o login.
    clientGet.mockResolvedValue({ data: { data: { id: 1, name: 'Ana', email: 'a@b.com' } } })
  })

  it('renderiza o formulário de login', () => {
    render(
      <MemoryRouter>
        <AuthProvider>
          <Login />
        </AuthProvider>
      </MemoryRouter>,
    )

    expect(screen.getByRole('heading', { name: 'Entrar' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Entrar' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Cadastre-se' })).toHaveAttribute('href', '/register')
  })

  it('chama login com as credenciais e navega para /products', async () => {
    clientPost.mockResolvedValue({
      data: { token: 't', user: { id: 1, name: 'Ana', email: 'a@b.com' } },
    })
    const user = userEvent.setup()

    render(
      <MemoryRouter initialEntries={['/login']}>
        <AuthProvider>
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/products" element={<div>PRODUCTS</div>} />
          </Routes>
        </AuthProvider>
      </MemoryRouter>,
    )

    await user.type(screen.getByLabelText(/email/i), 'a@b.com')
    await user.type(screen.getByLabelText(/senha/i), 'secret123')
    await user.click(screen.getByRole('button', { name: 'Entrar' }))

    await waitFor(() => expect(screen.getByText('PRODUCTS')).toBeInTheDocument())
    expect(clientPost).toHaveBeenCalledWith('/login', { email: 'a@b.com', password: 'secret123' })
  })

  it('exibe erro quando as credenciais são inválidas', async () => {
    clientPost.mockRejectedValue(new Error('invalid credentials'))
    const user = userEvent.setup()

    render(
      <MemoryRouter>
        <AuthProvider>
          <Login />
        </AuthProvider>
      </MemoryRouter>,
    )

    await user.type(screen.getByLabelText(/email/i), 'a@b.com')
    await user.type(screen.getByLabelText(/senha/i), 'errada')
    await user.click(screen.getByRole('button', { name: 'Entrar' }))

    await waitFor(() => expect(screen.getByText('Email ou senha inválidos')).toBeInTheDocument())
  })
})
