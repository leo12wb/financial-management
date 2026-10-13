import api from './client'
import type { AuthResponse, LoginCredentials, User } from '../types'

export const authApi = {
  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    // POST /api/login returns only { token }
    const { data } = await api.post<{ token: string }>('/login', credentials)
    localStorage.setItem('token', data.token)

    // Fetch user profile with the new token (interceptor unwraps { data: User } → User)
    const { data: user } = await api.get<User>('/me')
    return { token: data.token, user }
  },
  logout(): Promise<void> {
    // JWT is stateless — just clear client-side storage
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    return Promise.resolve()
  },
}
