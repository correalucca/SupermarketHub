import { describe, it, expect, beforeEach, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { AuthProvider, useAuth } from './AuthContext'

const { clientGet, clientPost } = vi.hoisted(() => ({
  clientGet: vi.fn(),
  clientPost: vi.fn(),
}))

vi.mock('../api/client', () => ({
  default: { get: clientGet, post: clientPost, put: vi.fn(), delete: vi.fn() },
}))

function Harness() {
  const { user, token, loading, login, register, logout } = useAuth()
  return (
    <div>
      <span data-testid="loading">{String(loading)}</span>
      <span data-testid="user">{user ? user.name : 'none'}</span>
      <span data-testid="token">{token ?? 'none'}</span>
      <button onClick={() => login('a@b.com', 'secret123')}>login</button>
      <button onClick={() => register('Ana', 'a@b.com', 'secret123')}>register</button>
      <button onClick={logout}>logout</button>
    </div>
  )
}

function renderProvider() {
  return render(
    <AuthProvider>
      <Harness />
    </AuthProvider>,
  )
}

describe('AuthContext', () => {
  beforeEach(() => {
    localStorage.clear()
    clientGet.mockReset()
    clientPost.mockReset()
    // Resposta padrão para o /me que o provider dispara após login/register.
    clientGet.mockResolvedValue({ data: { data: { id: 1, name: 'Ana', email: 'a@b.com' } } })
  })

  it('busca /me quando já existe token no localStorage', async () => {
    localStorage.setItem('token', 'tok-1')
    clientGet.mockResolvedValue({ data: { data: { id: 1, name: 'Ana', email: 'a@b.com' } } })

    renderProvider()

    await waitFor(() => expect(screen.getByTestId('user').textContent).toBe('Ana'))
    expect(clientGet).toHaveBeenCalledWith('/me')
    expect(screen.getByTestId('loading').textContent).toBe('false')
  })

  it('descarta token inválido quando /me falha', async () => {
    localStorage.setItem('token', 'tok-invalido')
    clientGet.mockRejectedValue(new Error('401'))

    renderProvider()

    await waitFor(() => expect(screen.getByTestId('token').textContent).toBe('none'))
    expect(localStorage.getItem('token')).toBeNull()
  })

  it('login persiste o token e define o usuário', async () => {
    clientPost.mockResolvedValue({
      data: { token: 'tok-2', user: { id: 1, name: 'Ana', email: 'a@b.com' } },
    })

    renderProvider()
    await userEvent.click(screen.getByText('login'))

    await waitFor(() => expect(screen.getByTestId('user').textContent).toBe('Ana'))
    expect(localStorage.getItem('token')).toBe('tok-2')
    expect(clientPost).toHaveBeenCalledWith('/login', { email: 'a@b.com', password: 'secret123' })
  })

  it('register persiste o token e define o usuário', async () => {
    clientPost.mockResolvedValue({
      data: { token: 'tok-3', user: { id: 2, name: 'Ana', email: 'a@b.com' } },
    })

    renderProvider()
    await userEvent.click(screen.getByText('register'))

    await waitFor(() => expect(screen.getByTestId('user').textContent).toBe('Ana'))
    expect(localStorage.getItem('token')).toBe('tok-3')
    expect(clientPost).toHaveBeenCalledWith('/register', { name: 'Ana', email: 'a@b.com', password: 'secret123' })
  })

  it('logout limpa o token e o usuário', async () => {
    localStorage.setItem('token', 'tok-1')
    clientGet.mockResolvedValue({ data: { data: { id: 1, name: 'Ana', email: 'a@b.com' } } })
    clientPost.mockResolvedValue({ data: {} })

    renderProvider()
    await waitFor(() => expect(screen.getByTestId('user').textContent).toBe('Ana'))

    await userEvent.click(screen.getByText('logout'))

    await waitFor(() => expect(screen.getByTestId('user').textContent).toBe('none'))
    expect(screen.getByTestId('token').textContent).toBe('none')
    expect(localStorage.getItem('token')).toBeNull()
  })
})
