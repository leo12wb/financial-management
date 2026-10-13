import { useState, useEffect, useCallback } from 'react'
import {
  GitMerge,
  Check,
  X,
  Undo2,
  AlertCircle,
  RefreshCw,
  ArrowLeftRight,
} from 'lucide-react'
import type {
  Account,
  ReconciliationSuggestion,
  Reconciliation,
} from '../types'
import { reconciliationsApi } from '../api/reconciliationsApi'
import { accountsApi } from '../api/accountsApi'
import { Card, CardHeader } from '../components/ui/Card'
import { Button } from '../components/ui/Button'
import { Select } from '../components/ui/Select'
import { Badge } from '../components/ui/Badge'
import { Loading } from '../components/ui/Loading'
import { EmptyState } from '../components/ui/EmptyState'
import { Pagination } from '../components/ui/Pagination'
import { DataTable } from '../components/ui/DataTable'
import type { Column } from '../components/ui/DataTable'
import { useToast } from '../components/ui/Toast'
import { formatCurrency, formatDate, formatDateTime } from '../utils/format'

const LIMIT = 10

export default function ReconciliationPage() {
  const { toast } = useToast()

  const [accounts, setAccounts] = useState<Account[]>([])
  const [accountFilter, setAccountFilter] = useState('')

  // Suggestions
  const [suggestions, setSuggestions] = useState<ReconciliationSuggestion[]>([])
  const [suggestionsLoading, setSuggestionsLoading] = useState(false)
  const [suggestionsError, setSuggestionsError] = useState('')
  const [confirmingId, setConfirmingId] = useState<string | null>(null)
  const [ignoringId, setIgnoringId] = useState<string | null>(null)

  // History
  const [history, setHistory] = useState<Reconciliation[]>([])
  const [historyTotal, setHistoryTotal] = useState(0)
  const [historyTotalPages, setHistoryTotalPages] = useState(1)
  const [historyPage, setHistoryPage] = useState(1)
  const [historyLoading, setHistoryLoading] = useState(true)
  const [historyError, setHistoryError] = useState('')
  const [undoingId, setUndoingId] = useState<number | null>(null)

  useEffect(() => {
    void accountsApi.list().then(setAccounts)
  }, [])

  const fetchHistory = useCallback(async (page: number) => {
    setHistoryLoading(true)
    setHistoryError('')
    try {
      const result = await reconciliationsApi.list({ page, limit: LIMIT })
      setHistory(result.data)
      setHistoryTotal(result.total)
      setHistoryTotalPages(result.totalPages)
    } catch {
      setHistoryError('Não foi possível carregar o histórico de conciliações.')
    } finally {
      setHistoryLoading(false)
    }
  }, [])

  useEffect(() => {
    void fetchHistory(historyPage)
  }, [fetchHistory, historyPage])

  async function fetchSuggestions() {
    setSuggestionsLoading(true)
    setSuggestionsError('')
    setSuggestions([])
    try {
      const accountId = accountFilter ? parseInt(accountFilter, 10) : undefined
      const data = await reconciliationsApi.suggestions(accountId)
      setSuggestions(data)
    } catch {
      setSuggestionsError('Não foi possível buscar sugestões de conciliação.')
    } finally {
      setSuggestionsLoading(false)
    }
  }

  async function handleConfirm(suggestion: ReconciliationSuggestion) {
    const key = `${suggestion.transaction.id}-${suggestion.statementEntry.id}`
    setConfirmingId(key)
    try {
      await reconciliationsApi.confirm(
        suggestion.transaction.id,
        suggestion.statementEntry.id,
      )
      setSuggestions((prev) =>
        prev.filter(
          (s) =>
            !(
              s.transaction.id === suggestion.transaction.id &&
              s.statementEntry.id === suggestion.statementEntry.id
            ),
        ),
      )
      toast('Conciliação confirmada com sucesso')
      void fetchHistory(1)
      setHistoryPage(1)
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { error?: string } } }).response?.data?.error ??
        'Erro ao confirmar conciliação.'
      toast(msg, 'error')
    } finally {
      setConfirmingId(null)
    }
  }

  async function handleIgnore(suggestion: ReconciliationSuggestion) {
    const key = `${suggestion.transaction.id}-${suggestion.statementEntry.id}`
    setIgnoringId(key)
    // Optimistically remove from list
    setSuggestions((prev) =>
      prev.filter(
        (s) =>
          !(
            s.transaction.id === suggestion.transaction.id &&
            s.statementEntry.id === suggestion.statementEntry.id
          ),
      ),
    )
    setIgnoringId(null)
    toast('Sugestão ignorada')
  }

  async function handleUndo(reconciliation: Reconciliation) {
    setUndoingId(reconciliation.id)
    try {
      await reconciliationsApi.undo(reconciliation.id)
      toast('Conciliação desfeita com sucesso')
      void fetchHistory(historyPage)
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { error?: string } } }).response?.data?.error ??
        'Erro ao desfazer conciliação.'
      toast(msg, 'error')
    } finally {
      setUndoingId(null)
    }
  }

  const accountOptions = [
    { value: '', label: 'Todas as contas' },
    ...accounts.map((a) => ({ value: String(a.id), label: a.name })),
  ]

  const historyColumns: Column<Reconciliation>[] = [
    {
      key: 'transaction',
      header: 'Transação Interna',
      render: (row) => (
        <div>
          <p className="text-sm font-medium text-slate-700 line-clamp-1">
            {row.transaction.description}
          </p>
          <p className="text-xs text-slate-500 mt-0.5">
            {formatDate(row.transaction.transactionDate)} ·{' '}
            <span className="font-medium text-slate-600">
              {formatCurrency(row.transaction.amount)}
            </span>
          </p>
        </div>
      ),
    },
    {
      key: 'statement',
      header: 'Registro do Extrato',
      render: (row) => (
        <div>
          <p className="text-sm font-medium text-slate-700 line-clamp-1">
            {row.statementEntry.description}
          </p>
          <p className="text-xs text-slate-500 mt-0.5">
            {formatDate(row.statementEntry.transactionDate)} ·{' '}
            <span className="font-medium text-slate-600">
              {formatCurrency(row.statementEntry.amount)}
            </span>
          </p>
        </div>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      align: 'center',
      render: (row) => {
        const map: Record<Reconciliation['status'], { label: string; variant: 'green' | 'gray' | 'red' | 'yellow' }> = {
          reconciled: { label: 'Conciliada', variant: 'green' },
          pending: { label: 'Pendente', variant: 'yellow' },
          rejected: { label: 'Rejeitada', variant: 'red' },
          undone: { label: 'Desfeita', variant: 'gray' },
        }
        const { label, variant } = map[row.status]
        return <Badge variant={variant}>{label}</Badge>
      },
      width: '120px',
    },
    {
      key: 'reconciledAt',
      header: 'Data de Conciliação',
      render: (row) => (
        <span className="text-sm text-slate-500">{formatDateTime(row.reconciledAt)}</span>
      ),
      width: '160px',
    },
    {
      key: 'actions',
      header: '',
      align: 'center',
      width: '80px',
      render: (row) =>
        row.status === 'reconciled' ? (
          <Button
            variant="ghost"
            size="sm"
            loading={undoingId === row.id}
            onClick={() => void handleUndo(row)}
            title="Desfazer conciliação"
          >
            <Undo2 className="w-3.5 h-3.5" />
            Desfazer
          </Button>
        ) : null,
    },
  ]

  return (
    <div className="space-y-8">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-slate-800">Conciliação Bancária</h1>
        <p className="text-sm text-slate-500 mt-0.5">
          Associe transações internas com registros do extrato bancário
        </p>
      </div>

      {/* Suggestions section */}
      <section>
        <Card>
          <CardHeader
            title="Sugestões de Conciliação"
            subtitle="Pares sugeridos automaticamente com base em data, valor e descrição"
            className="mb-5"
          />

          <div className="flex items-end gap-3 mb-5">
            <div className="w-64">
              <Select
                label="Filtrar por conta"
                value={accountFilter}
                onChange={(e) => setAccountFilter(e.target.value)}
                options={accountOptions}
              />
            </div>
            <Button
              onClick={() => void fetchSuggestions()}
              loading={suggestionsLoading}
              variant="secondary"
            >
              <RefreshCw className="w-4 h-4" />
              Buscar Sugestões
            </Button>
          </div>

          {suggestionsError && (
            <div className="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm mb-4">
              <AlertCircle className="w-4 h-4 shrink-0" />
              {suggestionsError}
            </div>
          )}

          {suggestionsLoading && <Loading label="Buscando sugestões..." />}

          {!suggestionsLoading && suggestions.length === 0 && !suggestionsError && (
            <EmptyState
              icon={GitMerge}
              title="Nenhuma sugestão encontrada"
              description="Clique em Buscar Sugestões para procurar pares de conciliação."
              className="py-10"
            />
          )}

          {!suggestionsLoading && suggestions.length > 0 && (
            <div className="space-y-4">
              {suggestions.map((suggestion) => {
                const key = `${suggestion.transaction.id}-${suggestion.statementEntry.id}`
                const isConfirming = confirmingId === key
                const isIgnoring = ignoringId === key
                const scorePercent = Math.round(suggestion.score * 100)

                return (
                  <div
                    key={key}
                    className="border border-slate-200 rounded-xl overflow-hidden"
                  >
                    {/* Match header */}
                    <div className="flex items-center justify-between px-4 py-2 bg-slate-50 border-b border-slate-100">
                      <div className="flex items-center gap-3">
                        <span className="text-xs font-medium text-slate-600">
                          Correspondência
                        </span>
                        {/* Score bar */}
                        <div className="flex items-center gap-2">
                          <div className="w-24 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                            <div
                              className={`h-full rounded-full transition-all ${
                                scorePercent >= 80
                                  ? 'bg-emerald-500'
                                  : scorePercent >= 60
                                    ? 'bg-yellow-400'
                                    : 'bg-red-400'
                              }`}
                              style={{ width: `${scorePercent}%` }}
                            />
                          </div>
                          <span
                            className={`text-xs font-semibold ${
                              scorePercent >= 80
                                ? 'text-emerald-600'
                                : scorePercent >= 60
                                  ? 'text-yellow-600'
                                  : 'text-red-500'
                            }`}
                          >
                            {scorePercent}%
                          </span>
                        </div>
                        {/* Reasons */}
                        <div className="flex gap-1">
                          {suggestion.matchReasons.map((reason) => (
                            <span
                              key={reason}
                              className="px-2 py-0.5 bg-blue-50 text-blue-600 text-xs rounded-full"
                            >
                              {reason}
                            </span>
                          ))}
                        </div>
                      </div>
                      <div className="flex gap-2">
                        <Button
                          size="sm"
                          variant="secondary"
                          loading={isIgnoring}
                          disabled={isConfirming}
                          onClick={() => void handleIgnore(suggestion)}
                        >
                          <X className="w-3.5 h-3.5" />
                          Ignorar
                        </Button>
                        <Button
                          size="sm"
                          loading={isConfirming}
                          disabled={isIgnoring}
                          onClick={() => void handleConfirm(suggestion)}
                        >
                          <Check className="w-3.5 h-3.5" />
                          Confirmar
                        </Button>
                      </div>
                    </div>

                    {/* Side-by-side */}
                    <div className="grid grid-cols-2 divide-x divide-slate-100">
                      {/* Internal transaction */}
                      <div className="p-4">
                        <p className="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">
                          Transação interna
                        </p>
                        <p className="font-medium text-slate-800">
                          {suggestion.transaction.description}
                        </p>
                        <div className="flex items-center gap-3 mt-1">
                          <span className="text-xs text-slate-500">
                            {formatDate(suggestion.transaction.transactionDate)}
                          </span>
                          <span
                            className={`text-sm font-bold ${
                              suggestion.transaction.type === 'income'
                                ? 'text-emerald-600'
                                : 'text-red-500'
                            }`}
                          >
                            {formatCurrency(suggestion.transaction.amount)}
                          </span>
                          <Badge
                            variant={
                              suggestion.transaction.type === 'income' ? 'green' : 'red'
                            }
                          >
                            {suggestion.transaction.type === 'income' ? 'Receita' : 'Despesa'}
                          </Badge>
                        </div>
                        {suggestion.transaction.account && (
                          <p className="text-xs text-slate-500 mt-1">
                            {suggestion.transaction.account.name}
                          </p>
                        )}
                      </div>

                      {/* Statement entry */}
                      <div className="p-4">
                        <p className="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">
                          Registro do extrato
                        </p>
                        <p className="font-medium text-slate-800">
                          {suggestion.statementEntry.description}
                        </p>
                        <div className="flex items-center gap-3 mt-1">
                          <span className="text-xs text-slate-500">
                            {formatDate(suggestion.statementEntry.transactionDate)}
                          </span>
                          <span className="text-sm font-bold text-slate-700">
                            {formatCurrency(suggestion.statementEntry.amount)}
                          </span>
                        </div>
                        {suggestion.statementEntry.externalReference && (
                          <p className="text-xs text-slate-500 mt-1">
                            Ref: {suggestion.statementEntry.externalReference}
                          </p>
                        )}
                      </div>
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </Card>
      </section>

      {/* History section */}
      <section>
        <h2 className="text-lg font-semibold text-slate-800 mb-4">
          Histórico de Conciliações
        </h2>
        <Card padding={false}>
          {historyError && (
            <div className="m-4 flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
              <AlertCircle className="w-4 h-4 shrink-0" />
              {historyError}
            </div>
          )}

          {historyLoading && <Loading label="Carregando histórico..." />}

          {!historyLoading && history.length === 0 && !historyError && (
            <EmptyState
              icon={ArrowLeftRight}
              title="Nenhuma conciliação registrada"
              description="Confirme sugestões para que elas apareçam aqui."
              className="py-10"
            />
          )}

          {!historyLoading && history.length > 0 && (
            <>
              <DataTable
                columns={historyColumns}
                data={history}
                rowKey={(row) => row.id}
              />
              <div className="px-4 py-4 border-t border-slate-100">
                <Pagination
                  page={historyPage}
                  totalPages={historyTotalPages}
                  total={historyTotal}
                  limit={LIMIT}
                  onPageChange={setHistoryPage}
                />
              </div>
            </>
          )}
        </Card>
      </section>
    </div>
  )
}
