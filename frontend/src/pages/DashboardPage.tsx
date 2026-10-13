import { useState, useEffect, useCallback } from 'react'
import {
  Wallet,
  TrendingUp,
  TrendingDown,
  Clock,
  CheckCircle,
  AlertCircle,
} from 'lucide-react'
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
  Legend,
} from 'recharts'
import type { DashboardData, Transaction } from '../types'

// The dashboard endpoint returns a flat shape for recent transactions
// (accountName / categoryName instead of nested objects)
interface DashboardTransaction extends Omit<Transaction, 'account' | 'category'> {
  accountName: string
  categoryName: string | null
}
import { dashboardApi } from '../api/dashboardApi'
import { Card, CardHeader } from '../components/ui/Card'
import { Badge } from '../components/ui/Badge'
import { Button } from '../components/ui/Button'
import { Loading } from '../components/ui/Loading'
import { EmptyState } from '../components/ui/EmptyState'
import { DataTable } from '../components/ui/DataTable'
import type { Column } from '../components/ui/DataTable'
import { formatCurrency, formatDate, toInputDate } from '../utils/format'

const PIE_COLORS = [
  '#10b981',
  '#3b82f6',
  '#f59e0b',
  '#ef4444',
  '#8b5cf6',
  '#ec4899',
  '#14b8a6',
  '#f97316',
]

function getFirstDayOfMonth(): string {
  const d = new Date()
  d.setDate(1)
  return toInputDate(d)
}

function reconciliationBadge(status: Transaction['reconciliationStatus']) {
  const map: Record<Transaction['reconciliationStatus'], { label: string; variant: 'green' | 'yellow' | 'red' | 'gray' }> = {
    reconciled: { label: 'Conciliada', variant: 'green' },
    pending: { label: 'Pendente', variant: 'yellow' },
    divergent: { label: 'Divergente', variant: 'red' },
    ignored: { label: 'Ignorada', variant: 'gray' },
  }
  const { label, variant } = map[status]
  return <Badge variant={variant}>{label}</Badge>
}

const transactionColumns: Column<DashboardTransaction>[] = [
  {
    key: 'date',
    header: 'Data',
    render: (row) => <span className="text-slate-500">{formatDate(row.transactionDate)}</span>,
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
    render: (row) => <span className="text-slate-600">{row.accountName}</span>,
  },
  {
    key: 'category',
    header: 'Categoria',
    render: (row) =>
      row.categoryName ? (
        <span className="text-slate-600">{row.categoryName}</span>
      ) : (
        <span className="text-slate-400">—</span>
      ),
  },
  {
    key: 'amount',
    header: 'Valor',
    align: 'right',
    render: (row) => (
      <span
        className={
          row.type === 'income' ? 'font-medium text-emerald-600' : 'font-medium text-red-500'
        }
      >
        {row.type === 'expense' ? '- ' : '+ '}
        {formatCurrency(row.amount)}
      </span>
    ),
  },
  {
    key: 'status',
    header: 'Conciliação',
    align: 'center',
    render: (row) => reconciliationBadge(row.reconciliationStatus),
  },
]

export default function DashboardPage() {
  const today = toInputDate(new Date())
  const [dateFrom, setDateFrom] = useState(getFirstDayOfMonth())
  const [dateTo, setDateTo] = useState(today)
  const [appliedFrom, setAppliedFrom] = useState(getFirstDayOfMonth())
  const [appliedTo, setAppliedTo] = useState(today)

  const [data, setData] = useState<DashboardData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const fetchData = useCallback(async (from: string, to: string) => {
    setLoading(true)
    setError('')
    try {
      const result = await dashboardApi.get({ dateFrom: from, dateTo: to })
      setData(result)
    } catch {
      setError('Não foi possível carregar os dados do dashboard.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void fetchData(appliedFrom, appliedTo)
  }, [fetchData, appliedFrom, appliedTo])

  function applyFilter() {
    setAppliedFrom(dateFrom)
    setAppliedTo(dateTo)
  }

  const kpiCards = data
    ? [
        {
          title: 'Saldo Total',
          value: formatCurrency(data.summary.totalBalance),
          sub: 'Todas as contas',
          icon: Wallet,
          iconBg: 'bg-blue-100',
          iconColor: 'text-blue-600',
          valueColor: 'text-slate-800',
        },
        {
          title: 'Receitas',
          value: formatCurrency(data.summary.totalIncome),
          sub: `${formatDate(appliedFrom)} – ${formatDate(appliedTo)}`,
          icon: TrendingUp,
          iconBg: 'bg-emerald-100',
          iconColor: 'text-emerald-600',
          valueColor: 'text-emerald-600',
        },
        {
          title: 'Despesas',
          value: formatCurrency(data.summary.totalExpenses),
          sub: `${formatDate(appliedFrom)} – ${formatDate(appliedTo)}`,
          icon: TrendingDown,
          iconBg: 'bg-red-100',
          iconColor: 'text-red-600',
          valueColor: 'text-red-500',
        },
        {
          title: 'Pendentes de Conciliação',
          value: String(data.summary.pendingReconciliationCount),
          sub: <Badge variant="yellow">Pendente</Badge>,
          icon: Clock,
          iconBg: 'bg-yellow-100',
          iconColor: 'text-yellow-600',
          valueColor: 'text-slate-800',
        },
        {
          title: 'Conciliadas',
          value: String(data.summary.reconciledCount),
          sub: <Badge variant="green">Conciliada</Badge>,
          icon: CheckCircle,
          iconBg: 'bg-emerald-100',
          iconColor: 'text-emerald-600',
          valueColor: 'text-slate-800',
        },
      ]
    : []

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Dashboard</h1>
          <p className="text-sm text-slate-500 mt-0.5">Visão geral das suas finanças</p>
        </div>

        {/* Period filter */}
        <div className="flex items-center gap-3 bg-white border border-slate-200 rounded-xl px-4 py-2.5 shadow-sm">
          <span className="text-sm text-slate-500">Período:</span>
          <input
            type="date"
            value={dateFrom}
            onChange={(e) => setDateFrom(e.target.value)}
            className="text-sm text-slate-700 border-0 outline-none bg-transparent"
          />
          <span className="text-slate-400">—</span>
          <input
            type="date"
            value={dateTo}
            onChange={(e) => setDateTo(e.target.value)}
            className="text-sm text-slate-700 border-0 outline-none bg-transparent"
          />
          <Button size="sm" onClick={applyFilter} loading={loading}>
            Aplicar
          </Button>
        </div>
      </div>

      {error && (
        <div className="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
          <AlertCircle className="w-4 h-4 shrink-0" />
          {error}
        </div>
      )}

      {loading && !data && <Loading label="Carregando dashboard..." />}

      {data && (
        <>
          {/* KPI cards */}
          <div className="grid grid-cols-5 gap-4">
            {kpiCards.map((card) => {
              const Icon = card.icon
              return (
                <Card key={card.title}>
                  <div className="flex items-start justify-between">
                    <div className="flex-1 min-w-0">
                      <p className="text-xs font-medium text-slate-500 uppercase tracking-wide">
                        {card.title}
                      </p>
                      <p className={`text-2xl font-bold mt-1 ${card.valueColor}`}>
                        {card.value}
                      </p>
                      <div className="mt-1 text-xs text-slate-500">{card.sub}</div>
                    </div>
                    <div className={`p-2.5 rounded-xl ${card.iconBg} shrink-0 ml-3`}>
                      <Icon className={`w-5 h-5 ${card.iconColor}`} />
                    </div>
                  </div>
                </Card>
              )
            })}
          </div>

          {/* Charts */}
          <div className="grid grid-cols-3 gap-6">
            {/* Bar chart */}
            <Card className="col-span-2" padding={false}>
              <div className="p-6 pb-2">
                <CardHeader title="Receitas vs Despesas" subtitle="Por mês" />
              </div>
              {data.chartData.length === 0 ? (
                <EmptyState
                  title="Sem dados no período"
                  description="Ajuste os filtros para visualizar o gráfico"
                  className="py-12"
                />
              ) : (
                <ResponsiveContainer width="100%" height={280}>
                  <BarChart
                    data={data.chartData}
                    margin={{ top: 8, right: 24, left: 8, bottom: 16 }}
                  >
                    <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
                    <XAxis
                      dataKey="month"
                      tick={{ fontSize: 12, fill: '#94a3b8' }}
                      axisLine={false}
                      tickLine={false}
                    />
                    <YAxis
                      tickFormatter={(v: number) =>
                        new Intl.NumberFormat('pt-BR', {
                          notation: 'compact',
                          style: 'currency',
                          currency: 'BRL',
                        }).format(v)
                      }
                      tick={{ fontSize: 11, fill: '#94a3b8' }}
                      axisLine={false}
                      tickLine={false}
                    />
                    <Tooltip
                      formatter={(value) => [formatCurrency(Number(value ?? 0)), '']}
                      contentStyle={{
                        borderRadius: '12px',
                        border: '1px solid #e2e8f0',
                        fontSize: '13px',
                      }}
                    />
                    <Legend
                      formatter={(value) => (value === 'income' ? 'Receita' : 'Despesa')}
                      wrapperStyle={{ fontSize: '12px', paddingTop: '8px' }}
                    />
                    <Bar dataKey="income" fill="#10b981" radius={[4, 4, 0, 0]} name="income" />
                    <Bar dataKey="expense" fill="#ef4444" radius={[4, 4, 0, 0]} name="expense" />
                  </BarChart>
                </ResponsiveContainer>
              )}
            </Card>

            {/* Pie chart */}
            <Card padding={false}>
              <div className="p-6 pb-2">
                <CardHeader title="Despesas por Categoria" />
              </div>
              {data.categoryExpenses.length === 0 ? (
                <EmptyState
                  title="Sem despesas"
                  description="Nenhuma despesa encontrada no período"
                  className="py-12"
                />
              ) : (
                <div className="px-4 pb-4">
                  <ResponsiveContainer width="100%" height={200}>
                    <PieChart>
                      <Pie
                        data={data.categoryExpenses}
                        dataKey="amount"
                        nameKey="categoryName"
                        cx="50%"
                        cy="50%"
                        innerRadius={50}
                        outerRadius={80}
                        paddingAngle={2}
                      >
                        {data.categoryExpenses.map((_, i) => (
                          <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />
                        ))}
                      </Pie>
                      <Tooltip
                        formatter={(value) => [formatCurrency(Number(value ?? 0)), '']}
                        contentStyle={{
                          borderRadius: '12px',
                          border: '1px solid #e2e8f0',
                          fontSize: '12px',
                        }}
                      />
                    </PieChart>
                  </ResponsiveContainer>
                  <ul className="space-y-1.5 mt-2">
                    {data.categoryExpenses.map((cat, i) => (
                      <li key={cat.categoryName} className="flex items-center gap-2 text-xs">
                        <span
                          className="w-2.5 h-2.5 rounded-full shrink-0"
                          style={{ background: PIE_COLORS[i % PIE_COLORS.length] }}
                        />
                        <span className="flex-1 truncate text-slate-600">
                          {cat.categoryName}
                        </span>
                        <span className="font-medium text-slate-700">
                          {cat.percentage.toFixed(1)}%
                        </span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </Card>
          </div>

          {/* Recent transactions */}
          <Card padding={false}>
            <div className="p-6 pb-0">
              <CardHeader
                title="Transações Recentes"
                subtitle="Últimas 10 movimentações"
              />
            </div>
            {data.recentTransactions.length === 0 ? (
              <EmptyState
                title="Sem transações"
                description="Nenhuma transação encontrada no período selecionado"
                className="py-12"
              />
            ) : (
              <DataTable
                columns={transactionColumns}
                data={data.recentTransactions.slice(0, 10) as unknown as DashboardTransaction[]}
                rowKey={(row) => row.id}
                className="mt-3"
              />
            )}
          </Card>
        </>
      )}
    </div>
  )
}
