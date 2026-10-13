import { useState, useEffect, useCallback } from 'react'
import {
  Plus,
  Search,
  X,
  Pencil,
  AlertCircle,
  AlertTriangle,
  ArrowLeftRight,
} from 'lucide-react'
import type {
  Transaction,
  Account,
  Category,
  TransactionType,
  ReconciliationStatus,
  CreateTransactionDTO,
} from '../types'
import { transactionsApi } from '../api/transactionsApi'
import type { TransactionFilters } from '../api/transactionsApi'
import { accountsApi } from '../api/accountsApi'
import { categoriesApi } from '../api/categoriesApi'
import { Card } from '../components/ui/Card'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { Select } from '../components/ui/Select'
import { Badge } from '../components/ui/Badge'
import { Modal } from '../components/ui/Modal'
import { Loading } from '../components/ui/Loading'
import { EmptyState } from '../components/ui/EmptyState'
import { Pagination } from '../components/ui/Pagination'
import { DataTable } from '../components/ui/DataTable'
import type { Column } from '../components/ui/DataTable'
import { useToast } from '../components/ui/Toast'
import { formatCurrency, formatDate, toInputDate } from '../utils/format'

const LIMIT = 15

const typeOptions = [
  { value: '', label: 'Todos os tipos' },
  { value: 'income', label: 'Receita' },
  { value: 'expense', label: 'Despesa' },
]

const statusOptions = [
  { value: '', label: 'Todos os status' },
  { value: 'pending', label: 'Pendente' },
  { value: 'reconciled', label: 'Conciliada' },
  { value: 'divergent', label: 'Divergente' },
  { value: 'ignored', label: 'Ignorada' },
]

const transactionTypeOptions = [
  { value: 'income', label: 'Receita' },
  { value: 'expense', label: 'Despesa' },
]

function reconciliationBadge(status: ReconciliationStatus) {
  const map: Record<ReconciliationStatus, { label: string; variant: 'green' | 'yellow' | 'red' | 'gray' }> = {
    reconciled: { label: 'Conciliada', variant: 'green' },
    pending: { label: 'Pendente', variant: 'yellow' },
    divergent: { label: 'Divergente', variant: 'red' },
    ignored: { label: 'Ignorada', variant: 'gray' },
  }
  const { label, variant } = map[status]
  return <Badge variant={variant}>{label}</Badge>
}

interface FormState {
  accountId: string
  categoryId: string
  description: string
  amount: string
  type: TransactionType
  transactionDate: string
  externalReference: string
}

interface FormErrors {
  accountId?: string
  description?: string
  amount?: string
  transactionDate?: string
}

const emptyForm: FormState = {
  accountId: '',
  categoryId: '',
  description: '',
  amount: '',
  type: 'expense',
  transactionDate: toInputDate(new Date()),
  externalReference: '',
}

export default function TransactionsPage() {
  const { toast } = useToast()

  // Transactions
  const [transactions, setTransactions] = useState<Transaction[]>([])
  const [total, setTotal] = useState(0)
  const [totalPages, setTotalPages] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  // Filters
  const [page, setPage] = useState(1)
  const [accountFilter, setAccountFilter] = useState('')
  const [typeFilter, setTypeFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [descriptionFilter, setDescriptionFilter] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')

  // Reference data
  const [accounts, setAccounts] = useState<Account[]>([])
  const [categories, setCategories] = useState<Category[]>([])

  // Modal
  const [modalOpen, setModalOpen] = useState(false)
  const [editingTransaction, setEditingTransaction] = useState<Transaction | null>(null)
  const [form, setForm] = useState<FormState>(emptyForm)
  const [formErrors, setFormErrors] = useState<FormErrors>({})
  const [saving, setSaving] = useState(false)

  // Load reference data
  useEffect(() => {
    void accountsApi.list().then(setAccounts).catch(() => undefined)
    void categoriesApi.list().then(setCategories).catch(() => undefined)
  }, [])

  const fetchTransactions = useCallback(async (filters: TransactionFilters) => {
    setLoading(true)
    setError('')
    try {
      const result = await transactionsApi.list(filters)
      setTransactions(result.data)
      setTotal(result.total)
      setTotalPages(result.totalPages)
    } catch {
      setError('Não foi possível carregar as transações.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    const filters: TransactionFilters = {
      page,
      limit: LIMIT,
      ...(accountFilter && { accountId: parseInt(accountFilter, 10) }),
      ...(typeFilter && { type: typeFilter as TransactionType }),
      ...(statusFilter && { reconciliationStatus: statusFilter as ReconciliationStatus }),
      ...(descriptionFilter && { description: descriptionFilter }),
      ...(dateFrom && { dateFrom }),
      ...(dateTo && { dateTo }),
    }
    void fetchTransactions(filters)
  }, [fetchTransactions, page, accountFilter, typeFilter, statusFilter, descriptionFilter, dateFrom, dateTo])

  function clearFilters() {
    setAccountFilter('')
    setTypeFilter('')
    setStatusFilter('')
    setDescriptionFilter('')
    setDateFrom('')
    setDateTo('')
    setPage(1)
  }

  function openCreate() {
    setEditingTransaction(null)
    setForm(emptyForm)
    setFormErrors({})
    setModalOpen(true)
  }

  function openEdit(transaction: Transaction) {
    setEditingTransaction(transaction)
    setForm({
      accountId: String(transaction.account.id),
      categoryId: transaction.category ? String(transaction.category.id) : '',
      description: transaction.description,
      amount: transaction.amount,
      type: transaction.type,
      transactionDate: transaction.transactionDate.split('T')[0],
      externalReference: transaction.externalReference ?? '',
    })
    setFormErrors({})
    setModalOpen(true)
  }

  function validateForm(): boolean {
    const errors: FormErrors = {}
    if (!form.accountId) errors.accountId = 'Conta é obrigatória'
    if (!form.description.trim()) errors.description = 'Descrição é obrigatória'
    if (!form.amount || isNaN(parseFloat(form.amount)) || parseFloat(form.amount) <= 0) {
      errors.amount = 'Informe um valor válido maior que zero'
    }
    if (!form.transactionDate) errors.transactionDate = 'Data é obrigatória'
    setFormErrors(errors)
    return Object.keys(errors).length === 0
  }

  async function handleSave() {
    if (!validateForm()) return
    setSaving(true)
    try {
      const dto: CreateTransactionDTO = {
        accountId: parseInt(form.accountId, 10),
        description: form.description.trim(),
        amount: form.amount,
        type: form.type,
        transactionDate: form.transactionDate,
        ...(form.categoryId && { categoryId: parseInt(form.categoryId, 10) }),
        ...(form.externalReference && { externalReference: form.externalReference }),
      }
      if (editingTransaction) {
        const updated = await transactionsApi.update(editingTransaction.id, dto)
        setTransactions((prev) => prev.map((t) => (t.id === updated.id ? updated : t)))
        toast('Transação atualizada com sucesso')
      } else {
        await transactionsApi.create(dto)
        toast('Transação criada com sucesso')
        // Re-fetch to keep pagination consistent
        void fetchTransactions({ page, limit: LIMIT })
      }
      setModalOpen(false)
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { error?: string } } }).response?.data?.error ??
        'Erro ao salvar transação.'
      toast(msg, 'error')
    } finally {
      setSaving(false)
    }
  }

  function setField<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((prev) => ({ ...prev, [key]: value }))
    setFormErrors((prev) => ({ ...prev, [key]: undefined }))
  }

  const isReconciled = editingTransaction?.reconciliationStatus === 'reconciled'

  const accountOptions = [
    { value: '', label: 'Todas as contas' },
    ...accounts.map((a) => ({ value: String(a.id), label: a.name })),
  ]

  const categoryOptions = [
    { value: '', label: 'Sem categoria' },
    ...categories
      .filter((c) => c.isActive)
      .map((c) => ({ value: String(c.id), label: c.name })),
  ]

  const columns: Column<Transaction>[] = [
    {
      key: 'date',
      header: 'Data',
      render: (row) => (
        <span className="text-slate-500 text-sm">{formatDate(row.transactionDate)}</span>
      ),
      width: '110px',
    },
    {
      key: 'description',
      header: 'Descrição',
      render: (row) => (
        <span className="font-medium text-slate-800 line-clamp-1">{row.description}</span>
      ),
    },
    {
      key: 'account',
      header: 'Conta',
      render: (row) => <span className="text-slate-600 text-sm">{row.accountName}</span>,
    },
    {
      key: 'category',
      header: 'Categoria',
      render: (row) =>
        row.categoryName ? (
          <span className="text-slate-600 text-sm">{row.categoryName}</span>
        ) : (
          <span className="text-slate-400">—</span>
        ),
    },
    {
      key: 'type',
      header: 'Tipo',
      align: 'center',
      render: (row) => (
        <Badge variant={row.type === 'income' ? 'green' : 'red'}>
          {row.type === 'income' ? 'Receita' : 'Despesa'}
        </Badge>
      ),
    },
    {
      key: 'amount',
      header: 'Valor',
      align: 'right',
      render: (row) => (
        <span
          className={`font-semibold text-sm ${
            row.type === 'income' ? 'text-emerald-600' : 'text-red-500'
          }`}
        >
          {row.type === 'expense' ? '- ' : '+ '}
          {formatCurrency(row.amount)}
        </span>
      ),
    },
    {
      key: 'source',
      header: 'Origem',
      align: 'center',
      render: (row) => (
        <Badge variant={row.source === 'imported' ? 'blue' : 'gray'}>
          {row.source === 'imported' ? 'Importada' : 'Manual'}
        </Badge>
      ),
    },
    {
      key: 'reconciliation',
      header: 'Conciliação',
      align: 'center',
      render: (row) => reconciliationBadge(row.reconciliationStatus),
    },
    {
      key: 'actions',
      header: '',
      align: 'center',
      width: '50px',
      render: (row) => (
        <button
          onClick={(e) => {
            e.stopPropagation()
            openEdit(row)
          }}
          className="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition-colors"
          title="Editar"
        >
          <Pencil className="w-3.5 h-3.5" />
        </button>
      ),
    },
  ]

  const hasFilters =
    accountFilter || typeFilter || statusFilter || descriptionFilter || dateFrom || dateTo

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Transações</h1>
          <p className="text-sm text-slate-500 mt-0.5">
            {total > 0 ? `${total} transações encontradas` : 'Nenhuma transação encontrada'}
          </p>
        </div>
        <Button onClick={openCreate}>
          <Plus className="w-4 h-4" />
          Nova Transação
        </Button>
      </div>

      {/* Filters */}
      <Card>
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
          <Select
            options={accountOptions}
            value={accountFilter}
            onChange={(e) => { setAccountFilter(e.target.value); setPage(1) }}
            placeholder="Conta"
          />
          <Select
            options={typeOptions}
            value={typeFilter}
            onChange={(e) => { setTypeFilter(e.target.value); setPage(1) }}
          />
          <Select
            options={statusOptions}
            value={statusFilter}
            onChange={(e) => { setStatusFilter(e.target.value); setPage(1) }}
          />
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" />
            <input
              value={descriptionFilter}
              onChange={(e) => { setDescriptionFilter(e.target.value); setPage(1) }}
              placeholder="Buscar descrição..."
              className="w-full rounded-lg border border-slate-200 pl-9 pr-3 py-2 text-sm text-slate-900 bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none"
            />
          </div>
          <input
            type="date"
            value={dateFrom}
            onChange={(e) => { setDateFrom(e.target.value); setPage(1) }}
            className="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none"
            title="Data inicial"
          />
          <input
            type="date"
            value={dateTo}
            onChange={(e) => { setDateTo(e.target.value); setPage(1) }}
            className="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none"
            title="Data final"
          />
        </div>
        {hasFilters && (
          <button
            onClick={clearFilters}
            className="mt-3 flex items-center gap-1 text-xs text-slate-500 hover:text-slate-700 transition-colors"
          >
            <X className="w-3 h-3" />
            Limpar filtros
          </button>
        )}
      </Card>

      {error && (
        <div className="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
          <AlertCircle className="w-4 h-4 shrink-0" />
          {error}
        </div>
      )}

      <Card padding={false}>
        {loading && <Loading label="Carregando transações..." />}

        {!loading && transactions.length === 0 && !error && (
          <EmptyState
            icon={ArrowLeftRight}
            title="Nenhuma transação encontrada"
            description="Ajuste os filtros ou crie uma nova transação."
            action={
              <Button onClick={openCreate}>
                <Plus className="w-4 h-4" />
                Nova Transação
              </Button>
            }
          />
        )}

        {!loading && transactions.length > 0 && (
          <>
            <DataTable
              columns={columns}
              data={transactions}
              rowKey={(row) => row.id}
            />
            <div className="px-4 py-4 border-t border-slate-100">
              <Pagination
                page={page}
                totalPages={totalPages}
                total={total}
                limit={LIMIT}
                onPageChange={setPage}
              />
            </div>
          </>
        )}
      </Card>

      {/* Modal */}
      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingTransaction ? 'Editar Transação' : 'Nova Transação'}
        size="md"
      >
        {isReconciled && (
          <div className="mb-4 flex items-start gap-2 px-4 py-3 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl text-sm">
            <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" />
            <span>
              Esta transação está conciliada. Os campos principais estão desabilitados para
              proteger a integridade da conciliação.
            </span>
          </div>
        )}

        <div className="space-y-4">
          <Select
            label="Conta *"
            value={form.accountId}
            onChange={(e) => setField('accountId', e.target.value)}
            options={accounts.map((a) => ({ value: String(a.id), label: a.name }))}
            placeholder="Selecione uma conta"
            error={formErrors.accountId}
            disabled={isReconciled}
          />
          <Input
            label="Descrição *"
            value={form.description}
            onChange={(e) => setField('description', e.target.value)}
            placeholder="Ex: Pagamento de fatura"
            error={formErrors.description}
            disabled={isReconciled}
          />
          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Valor *"
              type="number"
              step="0.01"
              min="0.01"
              value={form.amount}
              onChange={(e) => setField('amount', e.target.value)}
              placeholder="0,00"
              error={formErrors.amount}
              disabled={isReconciled}
            />
            <Select
              label="Tipo"
              value={form.type}
              onChange={(e) => setField('type', e.target.value as TransactionType)}
              options={transactionTypeOptions}
              disabled={isReconciled}
            />
          </div>
          <Input
            label="Data *"
            type="date"
            value={form.transactionDate}
            onChange={(e) => setField('transactionDate', e.target.value)}
            error={formErrors.transactionDate}
            disabled={isReconciled}
          />
          <Select
            label="Categoria"
            value={form.categoryId}
            onChange={(e) => setField('categoryId', e.target.value)}
            options={categoryOptions}
          />
          <Input
            label="Referência externa"
            value={form.externalReference}
            onChange={(e) => setField('externalReference', e.target.value)}
            placeholder="Opcional"
          />

          <div className="flex gap-3 pt-2">
            <Button variant="secondary" onClick={() => setModalOpen(false)} className="flex-1">
              Cancelar
            </Button>
            <Button onClick={() => void handleSave()} loading={saving} className="flex-1">
              {editingTransaction ? 'Salvar alterações' : 'Criar transação'}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
