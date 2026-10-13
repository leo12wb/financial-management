import api from './client'
import type { Account, CreateAccountDTO } from '../types'

export const accountsApi = {
  list(): Promise<Account[]> {
    return api.get<Account[]>('/accounts').then((r) => r.data)
  },
  get(id: number): Promise<Account> {
    return api.get<Account>(`/accounts/${id}`).then((r) => r.data)
  },
  create(dto: CreateAccountDTO): Promise<Account> {
    return api.post<Account>('/accounts', dto).then((r) => r.data)
  },
  update(id: number, dto: Partial<CreateAccountDTO>): Promise<Account> {
    return api.put<Account>(`/accounts/${id}`, dto).then((r) => r.data)
  },
  toggle(id: number): Promise<Account> {
    return api.patch<Account>(`/accounts/${id}/toggle`).then((r) => r.data)
  },
}
