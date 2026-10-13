import api from './client'
import type { AuthResponse, LoginCredentials } from '../types'

export const authApi = {
  login(credentials: LoginCredentials): Promise<AuthResponse> {
    return api.post<AuthResponse>('/auth/login', credentials).then((r) => r.data)
  },
  logout(): Promise<void> {
    return api.post('/auth/logout').then(() => undefined)
  },
}
