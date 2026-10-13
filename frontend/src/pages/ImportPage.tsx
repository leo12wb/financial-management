import { useState, useEffect, useRef, useCallback } from 'react'
import {
  Upload,
  FileText,
  CheckCircle,
  ChevronRight,
  Plus,
  ExternalLink,
  AlertCircle,
} from 'lucide-react'
import type { Account, ImportedStatement, ImportPreviewResponse, StatementEntry } from '../types'
import { importsApi } from '../api/importsApi'
import { accountsApi } from '../api/accountsApi'
import { Card } from '../components/ui/Card'
import { Button } from '../components/ui/Button'
import { Select } from '../components/ui/Select'
import { Badge } from '../components/ui/Badge'
import { Loading } from '../components/ui/Loading'
import { EmptyState } from '../components/ui/EmptyState'
import { DataTable } from '../components/ui/DataTable'
import type { Column } from '../components/ui/DataTable'
import { useToast } from '../components/ui/Toast'
import { formatCurrency, formatDate, formatDateTime } from '../utils/format'
import { cn } from '../utils/cn'
import { useNavigate } from 'react-router-dom'

type Step = 1 | 2 | 3

interface StepResult {
  imported: number
  rejected: number
  total: number
  filename: string
}

function entryBadge(entry: StatementEntry) {
  if (entry.isDuplicate) return <Badge variant="yellow">Duplicata</Badge>
  if (entry.error) return <Badge variant="red">Inválido</Badge>
  return <Badge variant="green">Válido</Badge>
}

const entryColumns: Column<StatementEntry>[] = [
  {
    key: 'date',
    header: 'Data',
    render: (row) => <span className="text-slate-500 text-sm">{formatDate(row.transactionDate)}</span>,
    width: '110px',
  },
  {
    key: 'description',
    header: 'Descrição',
    render: (row) => (
      <span className="text-slate-700 text-sm line-clamp-1">{row.description}</span>
    ),
  },
  {
    key: 'amount',
    header: 'Valor',
    align: 'right',
    render: (row) => (
      <span className="font-medium text-slate-800 text-sm">{formatCurrency(row.amount)}</span>
    ),
    width: '130px',
  },
  {
    key: 'ref',
    header: 'Referência',
    render: (row) => (
      <span className="text-slate-500 text-xs">{row.externalReference ?? '—'}</span>
    ),
  },
  {
    key: 'status',
    header: 'Status',
    align: 'center',
    render: (row) => entryBadge(row),
    width: '100px',
  },
  {
    key: 'error',
    header: 'Observação',
    render: (row) =>
      row.error ? (
        <span className="text-xs text-red-600">{row.error}</span>
      ) : (
        <span className="text-slate-400">—</span>
      ),
  },
]

const historyColumns: Column<ImportedStatement>[] = [
  {
    key: 'file',
    header: 'Arquivo',
    render: (row) => (
      <div className="flex items-center gap-2">
        <FileText className="w-4 h-4 text-slate-400 shrink-0" />
        <span className="text-sm font-medium text-slate-700 line-clamp-1">
          {row.originalFilename}
        </span>
      </div>
    ),
  },
  {
    key: 'account',
    header: 'Conta',
    render: (row) => <span className="text-sm text-slate-600">{row.account.name}</span>,
  },
  {
    key: 'createdAt',
    header: 'Data',
    render: (row) => (
      <span className="text-sm text-slate-500">{formatDateTime(row.createdAt)}</span>
    ),
    width: '150px',
  },
  {
    key: 'status',
    header: 'Status',
    align: 'center',
    render: (row) => {
      const map: Record<ImportedStatement['status'], { label: string; variant: 'green' | 'yellow' | 'red' | 'blue' | 'gray' }> = {
        confirmed: { label: 'Confirmada', variant: 'green' },
        pending: { label: 'Pendente', variant: 'yellow' },
        previewed: { label: 'Em análise', variant: 'blue' },
        failed: { label: 'Falhou', variant: 'red' },
      }
      const { label, variant } = map[row.status]
      return <Badge variant={variant}>{label}</Badge>
    },
    width: '120px',
  },
  {
    key: 'rows',
    header: 'Linhas',
    align: 'right',
    render: (row) => (
      <div className="text-sm text-right">
        <span className="text-emerald-600 font-medium">{row.importedRows}</span>
        <span className="text-slate-400"> / </span>
        <span className="text-red-500">{row.rejectedRows}</span>
        <span className="text-slate-400"> / </span>
        <span className="text-slate-600">{row.totalRows}</span>
      </div>
    ),
    width: '120px',
  },
]

export default function ImportPage() {
  const { toast } = useToast()
  const navigate = useNavigate()
  const fileInputRef = useRef<HTMLInputElement>(null)

  const [step, setStep] = useState<Step>(1)
  const [accounts, setAccounts] = useState<Account[]>([])
  const [selectedAccountId, setSelectedAccountId] = useState('')
  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [isDragging, setIsDragging] = useState(false)
  const [uploading, setUploading] = useState(false)

  const [preview, setPreview] = useState<ImportPreviewResponse | null>(null)
  const [confirming, setConfirming] = useState(false)

  const [result, setResult] = useState<StepResult | null>(null)

  const [history, setHistory] = useState<ImportedStatement[]>([])
  const [historyLoading, setHistoryLoading] = useState(true)
  const [accountError, setAccountError] = useState('')
  const [fileError, setFileError] = useState('')

  const fetchHistory = useCallback(async () => {
    setHistoryLoading(true)
    try {
      const data = await importsApi.list()
      setHistory(data)
    } catch {
      // history is optional
    } finally {
      setHistoryLoading(false)
    }
  }, [])

  useEffect(() => {
    void accountsApi.list().then((data) => {
      setAccounts(data.filter((a) => a.isActive))
    })
    void fetchHistory()
  }, [fetchHistory])

  function handleFileSelect(file: File) {
    if (!file.name.endsWith('.csv')) {
      setFileError('Apenas arquivos CSV são aceitos.')
      return
    }
    setFileError('')
    setSelectedFile(file)
  }

  function handleDrop(e: React.DragEvent) {
    e.preventDefault()
    setIsDragging(false)
    const file = e.dataTransfer.files[0]
    if (file) handleFileSelect(file)
  }

  async function handleAnalyze() {
    if (!selectedAccountId) { setAccountError('Selecione uma conta'); return }
    setAccountError('')
    if (!selectedFile) { setFileError('Selecione um arquivo CSV'); return }
    setFileError('')
    setUploading(true)
    try {
      const data = await importsApi.upload(parseInt(selectedAccountId, 10), selectedFile)
      setPreview(data)
      setStep(2)
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { error?: string } } }).response?.data?.error ??
        'Erro ao analisar o arquivo CSV.'
      toast(msg, 'error')
    } finally {
      setUploading(false)
    }
  }

  async function handleConfirm() {
    if (!preview) return
    setConfirming(true)
    try {
      const confirmed = await importsApi.confirm(preview.importId)
      setResult({
        imported: confirmed.importedRows,
        rejected: confirmed.rejectedRows,
        total: confirmed.totalRows,
        filename: confirmed.originalFilename,
      })
      setStep(3)
      void fetchHistory()
      toast('Importação concluída com sucesso')
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { error?: string } } }).response?.data?.error ??
        'Erro ao confirmar importação.'
      toast(msg, 'error')
    } finally {
      setConfirming(false)
    }
  }

  function resetWizard() {
    setStep(1)
    setSelectedFile(null)
    setSelectedAccountId('')
    setPreview(null)
    setResult(null)
    setAccountError('')
    setFileError('')
  }

  const accountOptions = accounts.map((a) => ({ value: String(a.id), label: a.name }))

  const stepLabels: Record<Step, string> = {
    1: 'Selecionar arquivo',
    2: 'Pré-visualizar',
    3: 'Resultado',
  }

  return (
    <div className="space-y-8">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-slate-800">Importar Extrato CSV</h1>
        <p className="text-sm text-slate-500 mt-0.5">
          Importe transações bancárias via arquivo CSV
        </p>
      </div>

      {/* Stepper */}
      <Card>
        <div className="flex items-center gap-2 mb-6">
          {([1, 2, 3] as Step[]).map((s) => (
            <div key={s} className="flex items-center gap-2">
              <div
                className={cn(
                  'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 transition-colors',
                  step === s
                    ? 'bg-emerald-500 text-white'
                    : step > s
                      ? 'bg-emerald-100 text-emerald-700'
                      : 'bg-slate-100 text-slate-400',
                )}
              >
                {step > s ? <CheckCircle className="w-3.5 h-3.5" /> : s}
              </div>
              <span
                className={cn(
                  'text-sm font-medium',
                  step === s ? 'text-emerald-600' : 'text-slate-400',
                )}
              >
                {stepLabels[s]}
              </span>
              {s < 3 && <ChevronRight className="w-4 h-4 text-slate-300 mx-1" />}
            </div>
          ))}
        </div>

        {/* Step 1 */}
        {step === 1 && (
          <div className="space-y-5 max-w-lg">
            <Select
              label="Conta *"
              value={selectedAccountId}
              onChange={(e) => { setSelectedAccountId(e.target.value); setAccountError('') }}
              options={accountOptions}
              placeholder="Selecione a conta"
              error={accountError}
            />

            {/* Drop zone */}
            <div>
              <label className="text-sm font-medium text-slate-700 block mb-1">
                Arquivo CSV *
              </label>
              <div
                onDragOver={(e) => { e.preventDefault(); setIsDragging(true) }}
                onDragLeave={() => setIsDragging(false)}
                onDrop={handleDrop}
                onClick={() => fileInputRef.current?.click()}
                className={cn(
                  'border-2 border-dashed rounded-xl p-8 flex flex-col items-center gap-3 cursor-pointer transition-colors',
                  isDragging
                    ? 'border-emerald-400 bg-emerald-50'
                    : selectedFile
                      ? 'border-emerald-300 bg-emerald-50/50'
                      : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50',
                )}
              >
                {selectedFile ? (
                  <>
                    <FileText className="w-8 h-8 text-emerald-500" />
                    <p className="font-medium text-slate-700">{selectedFile.name}</p>
                    <p className="text-xs text-slate-500">
                      {(selectedFile.size / 1024).toFixed(1)} KB — Clique para trocar
                    </p>
                  </>
                ) : (
                  <>
                    <Upload className="w-8 h-8 text-slate-400" />
                    <p className="font-medium text-slate-600">
                      Arraste o CSV aqui ou clique para selecionar
                    </p>
                    <p className="text-xs text-slate-400">Apenas arquivos .csv</p>
                  </>
                )}
              </div>
              <input
                ref={fileInputRef}
                type="file"
                accept=".csv"
                className="hidden"
                onChange={(e) => {
                  const f = e.target.files?.[0]
                  if (f) handleFileSelect(f)
                }}
              />
              {fileError && <p className="text-xs text-red-500 mt-1">{fileError}</p>}
            </div>

            <Button
              onClick={() => void handleAnalyze()}
              loading={uploading}
              disabled={!selectedFile || !selectedAccountId}
              size="lg"
            >
              Analisar CSV
            </Button>
          </div>
        )}

        {/* Step 2 */}
        {step === 2 && preview && (
          <div className="space-y-5">
            {/* Summary cards */}
            <div className="grid grid-cols-4 gap-4">
              {[
                { label: 'Total de linhas', value: preview.totalRows, color: 'text-slate-800' },
                { label: 'Válidas', value: preview.validRows, color: 'text-emerald-600' },
                { label: 'Inválidas', value: preview.invalidRows, color: 'text-red-500' },
                { label: 'Duplicatas', value: preview.duplicateRows, color: 'text-yellow-600' },
              ].map((item) => (
                <div
                  key={item.label}
                  className="bg-slate-50 rounded-xl p-4 text-center"
                >
                  <p className={`text-2xl font-bold ${item.color}`}>{item.value}</p>
                  <p className="text-xs text-slate-500 mt-0.5">{item.label}</p>
                </div>
              ))}
            </div>

            {preview.invalidRows > 0 && (
              <div className="flex items-center gap-2 px-4 py-3 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl text-sm">
                <AlertCircle className="w-4 h-4 shrink-0" />
                Existem {preview.invalidRows} linha(s) inválidas. Elas serão ignoradas na
                importação.
              </div>
            )}

            {/* Entries table */}
            <div className="border border-slate-100 rounded-xl overflow-hidden">
              <DataTable
                columns={entryColumns}
                data={preview.entries}
                rowKey={(row) => row.id}
                rowClassName={(row) =>
                  row.error && !row.isDuplicate ? 'bg-red-50/50' : ''
                }
              />
            </div>

            <div className="flex gap-3">
              <Button variant="secondary" onClick={() => setStep(1)}>
                Voltar
              </Button>
              <Button
                onClick={() => void handleConfirm()}
                loading={confirming}
                disabled={preview.validRows === 0}
              >
                Confirmar Importação ({preview.validRows} linhas)
              </Button>
            </div>
          </div>
        )}

        {/* Step 3 */}
        {step === 3 && result && (
          <div className="flex flex-col items-center gap-6 py-6">
            <div className="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center">
              <CheckCircle className="w-8 h-8 text-emerald-500" />
            </div>
            <div className="text-center">
              <h3 className="text-xl font-bold text-slate-800">Importação concluída!</h3>
              <p className="text-sm text-slate-500 mt-1">{result.filename}</p>
            </div>

            <div className="grid grid-cols-3 gap-6">
              <div className="text-center">
                <p className="text-3xl font-bold text-emerald-600">{result.imported}</p>
                <p className="text-sm text-slate-500 mt-1">Importadas</p>
              </div>
              <div className="text-center">
                <p className="text-3xl font-bold text-red-500">{result.rejected}</p>
                <p className="text-sm text-slate-500 mt-1">Rejeitadas</p>
              </div>
              <div className="text-center">
                <p className="text-3xl font-bold text-slate-700">{result.total}</p>
                <p className="text-sm text-slate-500 mt-1">Total</p>
              </div>
            </div>

            <div className="flex gap-3">
              <Button variant="secondary" onClick={resetWizard}>
                <Plus className="w-4 h-4" />
                Nova Importação
              </Button>
              <Button onClick={() => navigate('/transactions')}>
                <ExternalLink className="w-4 h-4" />
                Ver Transações
              </Button>
            </div>
          </div>
        )}
      </Card>

      {/* History */}
      <div>
        <h2 className="text-lg font-semibold text-slate-800 mb-4">Histórico de Importações</h2>
        <Card padding={false}>
          {historyLoading && <Loading label="Carregando histórico..." />}
          {!historyLoading && history.length === 0 && (
            <EmptyState
              icon={Upload}
              title="Nenhuma importação realizada"
              description="Realize sua primeira importação acima."
              className="py-10"
            />
          )}
          {!historyLoading && history.length > 0 && (
            <>
              <div className="px-6 py-4 border-b border-slate-50">
                <p className="text-xs text-slate-500">
                  Legenda linhas: <span className="text-emerald-600 font-medium">importadas</span>
                  {' / '}
                  <span className="text-red-500 font-medium">rejeitadas</span>
                  {' / '}
                  <span className="text-slate-600">total</span>
                </p>
              </div>
              <DataTable
                columns={historyColumns}
                data={history}
                rowKey={(row) => row.id}
              />
            </>
          )}
        </Card>
      </div>
    </div>
  )
}
