import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import MockAdapter from 'axios-mock-adapter'
import client from './client'

describe('api client interceptors', () => {
  let mock: MockAdapter

  beforeEach(() => {
    localStorage.clear()
    mock = new MockAdapter(client)

    // jsdom não implementa navegação; expõe um objeto simples para o interceptor
    // gravar o redirecionamento de 401.
    Object.defineProperty(window, 'location', {
      configurable: true,
      writable: true,
      value: { href: '' },
    })
  })

  afterEach(() => {
    mock.restore()
  })

  it('adiciona o token Bearer quando existente no localStorage', async () => {
    localStorage.setItem('token', 'tok-123')

    mock.onGet('/me').reply((config) => {
      expect(config.headers?.Authorization).toBe('Bearer tok-123')
      return [200, { success: true, data: { id: 1 } }]
    })

    await client.get('/me')
  })

  it('não envia header Authorization sem token', async () => {
    mock.onGet('/me').reply((config) => {
      expect(config.headers?.Authorization).toBeUndefined()
      return [200, { success: true, data: { id: 1 } }]
    })

    await client.get('/me')
  })

  it('limpa o token e redireciona para /login em resposta 401', async () => {
    localStorage.setItem('token', 'tok-123')
    mock.onGet('/me').reply(401, { message: 'Não autenticado.' })

    await expect(client.get('/me')).rejects.toBeTruthy()

    expect(localStorage.getItem('token')).toBeNull()
    expect(window.location.href).toBe('/login')
  })

  it('não redireciona para 401 em respostas de erro com outro status', async () => {
    localStorage.setItem('token', 'tok-123')
    mock.onGet('/me').reply(500, { message: 'Erro interno.' })

    await expect(client.get('/me')).rejects.toBeTruthy()

    expect(localStorage.getItem('token')).toBe('tok-123')
    expect(window.location.href).toBe('')
  })
})
