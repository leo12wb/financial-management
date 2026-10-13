import api from './client'
import type { DashboardData } from '../types'

export interface DashboardParams {
  dateFrom: string
  dateTo: string
}

export const dashboardApi = {
  get(params: DashboardParams): Promise<DashboardData> {
    return api
      .get<DashboardData>('/dashboard', { params })
      .then((r) => r.data)
  },
}
