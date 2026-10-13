import { useState, useEffect, useCallback } from 'react'
import { Plus, Pencil, Power, Tag, AlertCircle } from 'lucide-react'
import type { Category, CategoryType, CreateCategoryDTO } from '../types'
import { categoriesApi } from '../api/categoriesApi'
import { Card, CardHeader } from '../components/ui/Card'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { Select } from '../components/ui/Select'
import { Badge } from '../components/ui/Badge'
import { Modal } from '../components/ui/Modal'
import { Loading } from '../components/ui/Loading'
import { EmptyState } from '../components/ui/EmptyState'
import { useToast } from '../components/ui/Toast'

const categoryTypeOptions = [
  { value: 'income', label: 'Receita' },
  { value: 'expense', label: 'Despesa' },
]

interface FormState {
  name: string
  type: CategoryType
}

interface FormErrors {
  name?: string
}

const emptyForm: FormState = { name: '', type: 'expense' }

export default function CategoriesPage() {
  const { toast } = useToast()
  const [categories, setCategories] = useState<Category[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const [modalOpen, setModalOpen] = useState(false)
  const [editingCategory, setEditingCategory] = useState<Category | null>(null)
  const [form, setForm] = useState<FormState>(emptyForm)
  const [formErrors, setFormErrors] = useState<FormErrors>({})
  const [saving, setSaving] = useState(false)
  const [togglingId, setTogglingId] = useState<number | null>(null)

  const fetchCategories = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const result = await categoriesApi.list()
      setCategories(result)
    } catch {
      setError('Não foi possível carregar as categorias.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void fetchCategories()
  }, [fetchCategories])

  function openCreate(defaultType: CategoryType = 'expense') {
    setEditingCategory(null)
    setForm({ name: '', type: defaultType })
    setFormErrors({})
    setModalOpen(true)
  }

  function openEdit(category: Category) {
    setEditingCategory(category)
    setForm({ name: category.name, type: category.type })
    setFormErrors({})
    setModalOpen(true)
  }

  function validateForm(): boolean {
    const errors: FormErrors = {}
    if (!form.name.trim()) errors.name = 'Nome é obrigatório'
    setFormErrors(errors)
    return Object.keys(errors).length === 0
  }

  async function handleSave() {
    if (!validateForm()) return
    setSaving(true)
    try {
      const dto: CreateCategoryDTO = { name: form.name.trim(), type: form.type }
      if (editingCategory) {
        const updated = await categoriesApi.update(editingCategory.id, dto)
        setCategories((prev) => prev.map((c) => (c.id === updated.id ? updated : c)))
        toast('Categoria atualizada com sucesso')
      } else {
        const created = await categoriesApi.create(dto)
        setCategories((prev) => [...prev, created])
        toast('Categoria criada com sucesso')
      }
      setModalOpen(false)
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { error?: string } } }).response?.data?.error ??
        'Erro ao salvar categoria.'
      toast(msg, 'error')
    } finally {
      setSaving(false)
    }
  }

  async function handleToggle(category: Category) {
    setTogglingId(category.id)
    try {
      const updated = await categoriesApi.toggle(category.id)
      setCategories((prev) => prev.map((c) => (c.id === updated.id ? updated : c)))
      toast(updated.isActive ? 'Categoria ativada' : 'Categoria desativada')
    } catch {
      toast('Erro ao alterar status da categoria.', 'error')
    } finally {
      setTogglingId(null)
    }
  }

  const incomeCategories = categories.filter((c) => c.type === 'income')
  const expenseCategories = categories.filter((c) => c.type === 'expense')

  function CategoryList({
    items,
    type,
  }: {
    items: Category[]
    type: CategoryType
  }) {
    if (items.length === 0) {
      return (
        <EmptyState
          icon={Tag}
          title={`Nenhuma categoria de ${type === 'income' ? 'receita' : 'despesa'}`}
          description="Clique em Nova Categoria para criar."
          className="py-8"
        />
      )
    }

    return (
      <ul className="space-y-2">
        {items.map((cat) => (
          <li
            key={cat.id}
            className="flex items-center justify-between px-4 py-3 bg-slate-50 rounded-xl"
          >
            <div className="flex items-center gap-3">
              <div
                className={`w-2 h-2 rounded-full ${type === 'income' ? 'bg-emerald-500' : 'bg-red-500'}`}
              />
              <span className="text-sm font-medium text-slate-700">{cat.name}</span>
              {!cat.isActive && (
                <Badge variant="gray" className="text-xs">
                  Inativa
                </Badge>
              )}
            </div>
            <div className="flex items-center gap-1">
              <button
                onClick={() => openEdit(cat)}
                className="p-1.5 rounded-lg hover:bg-white text-slate-400 hover:text-slate-600 transition-colors"
                title="Editar"
              >
                <Pencil className="w-3.5 h-3.5" />
              </button>
              <button
                onClick={() => void handleToggle(cat)}
                disabled={togglingId === cat.id}
                className={`p-1.5 rounded-lg transition-colors ${
                  cat.isActive
                    ? 'hover:bg-white text-slate-400 hover:text-red-500'
                    : 'hover:bg-white text-slate-400 hover:text-emerald-500'
                }`}
                title={cat.isActive ? 'Desativar' : 'Ativar'}
              >
                <Power className="w-3.5 h-3.5" />
              </button>
            </div>
          </li>
        ))}
      </ul>
    )
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Categorias</h1>
          <p className="text-sm text-slate-500 mt-0.5">
            Organize suas receitas e despesas
          </p>
        </div>
        <Button onClick={() => openCreate()}>
          <Plus className="w-4 h-4" />
          Nova Categoria
        </Button>
      </div>

      {error && (
        <div className="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
          <AlertCircle className="w-4 h-4 shrink-0" />
          {error}
          <button onClick={() => void fetchCategories()} className="ml-auto underline text-xs">
            Tentar novamente
          </button>
        </div>
      )}

      {loading && <Loading label="Carregando categorias..." />}

      {!loading && !error && (
        <div className="grid grid-cols-2 gap-6">
          {/* Income */}
          <Card>
            <CardHeader
              title="Receitas"
              subtitle={`${incomeCategories.length} categorias`}
              action={
                <button
                  onClick={() => openCreate('income')}
                  className="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-emerald-600 transition-colors"
                  title="Nova categoria de receita"
                >
                  <Plus className="w-4 h-4" />
                </button>
              }
              className="mb-4"
            />
            <CategoryList items={incomeCategories} type="income" />
          </Card>

          {/* Expense */}
          <Card>
            <CardHeader
              title="Despesas"
              subtitle={`${expenseCategories.length} categorias`}
              action={
                <button
                  onClick={() => openCreate('expense')}
                  className="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-red-500 transition-colors"
                  title="Nova categoria de despesa"
                >
                  <Plus className="w-4 h-4" />
                </button>
              }
              className="mb-4"
            />
            <CategoryList items={expenseCategories} type="expense" />
          </Card>
        </div>
      )}

      {/* Modal */}
      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingCategory ? 'Editar Categoria' : 'Nova Categoria'}
        size="sm"
      >
        <div className="space-y-4">
          <Input
            label="Nome *"
            value={form.name}
            onChange={(e) => setForm((prev) => ({ ...prev, name: e.target.value }))}
            placeholder="Ex: Alimentação"
            error={formErrors.name}
            autoFocus
          />
          <Select
            label="Tipo"
            value={form.type}
            onChange={(e) =>
              setForm((prev) => ({ ...prev, type: e.target.value as CategoryType }))
            }
            options={categoryTypeOptions}
          />

          <div className="flex gap-3 pt-2">
            <Button variant="secondary" onClick={() => setModalOpen(false)} className="flex-1">
              Cancelar
            </Button>
            <Button onClick={() => void handleSave()} loading={saving} className="flex-1">
              {editingCategory ? 'Salvar alterações' : 'Criar categoria'}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
