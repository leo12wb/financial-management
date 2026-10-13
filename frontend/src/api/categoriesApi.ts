import api from './client'
import type { Category, CategoryType, CreateCategoryDTO } from '../types'

export const categoriesApi = {
  list(type?: CategoryType): Promise<Category[]> {
    return api
      .get<Category[]>('/categories', { params: type ? { type } : undefined })
      .then((r) => r.data)
  },
  create(dto: CreateCategoryDTO): Promise<Category> {
    return api.post<Category>('/categories', dto).then((r) => r.data)
  },
  update(id: number, dto: Partial<CreateCategoryDTO>): Promise<Category> {
    return api.put<Category>(`/categories/${id}`, dto).then((r) => r.data)
  },
  toggle(id: number): Promise<Category> {
    return api.patch<Category>(`/categories/${id}/toggle`).then((r) => r.data)
  },
}
