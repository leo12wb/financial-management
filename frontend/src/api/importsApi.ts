import api from './client'
import type { ImportedStatement, ImportPreviewResponse } from '../types'

export const importsApi = {
  list(): Promise<ImportedStatement[]> {
    return api.get<ImportedStatement[]>('/imports').then((r) => r.data)
  },
  upload(accountId: number, file: File): Promise<ImportPreviewResponse> {
    const fd = new FormData()
    fd.append('accountId', String(accountId))
    fd.append('file', file)
    return api
      .post<ImportPreviewResponse>('/imports/upload', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      .then((r) => r.data)
  },
  confirm(importId: number): Promise<ImportedStatement> {
    return api
      .post<ImportedStatement>(`/imports/${importId}/confirm`)
      .then((r) => r.data)
  },
}
