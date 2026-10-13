import api from './client'
import type {
  Transaction,
  CreateTransactionDTO,
  PaginatedResponse,
  TransactionType,
  ReconciliationStatus,
} from '../types'

export interface TransactionFilters {
  page?: number
  limit?: number
  accountId?: number
  type?: TransactionType
  reconciliationStatus?: ReconciliationStatus
  description?: string
  dateFrom?: string
  dateTo?: string
}

export const transactionsApi = {
  list(filters?: TransactionFilters): Promise<PaginatedResponse<Transaction>> {
    return api
      .get<PaginatedResponse<Transaction>>('/transactions', { params: filters })
      .then((r) => r.data)
  },
  get(id: number): Promise<Transaction> {
    return api.get<Transaction>(`/transactions/${id}`).then((r) => r.data)
  },
  create(dto: CreateTransactionDTO): Promise<Transaction> {
    return api.post<Transaction>('/transactions', dto).then((r) => r.data)
  },
  update(id: number, dto: Partial<CreateTransactionDTO>): Promise<Transaction> {
    return api.put<Transaction>(`/transactions/${id}`, dto).then((r) => r.data)
  },
  delete(id: number): Promise<void> {
    return api.delete(`/transactions/${id}`).then(() => undefined)
  },
}
