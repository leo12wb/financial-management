import api from './client'
import type {
  Reconciliation,
  ReconciliationSuggestion,
  PaginatedResponse,
} from '../types'

export interface ReconciliationFilters {
  page?: number
  limit?: number
  accountId?: number
}

export const reconciliationsApi = {
  suggestions(accountId?: number): Promise<ReconciliationSuggestion[]> {
    return api
      .get<ReconciliationSuggestion[]>('/reconciliations/suggestions', {
        params: accountId ? { accountId } : undefined,
      })
      .then((r) => r.data)
  },
  list(filters?: ReconciliationFilters): Promise<PaginatedResponse<Reconciliation>> {
    return api
      .get<PaginatedResponse<Reconciliation>>('/reconciliations', { params: filters })
      .then((r) => r.data)
  },
  confirm(
    transactionId: number,
    statementEntryId: number,
  ): Promise<Reconciliation> {
    return api
      .post<Reconciliation>('/reconciliations/confirm', {
        transactionId,
        statementEntryId,
      })
      .then((r) => r.data)
  },
  undo(id: number): Promise<Reconciliation> {
    return api
      .post<Reconciliation>(`/reconciliations/${id}/undo`)
      .then((r) => r.data)
  },
}
