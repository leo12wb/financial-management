import { useState, useEffect, useCallback } from 'react'
import { Plus, Pencil, Power, Landmark, AlertCircle } from 'lucide-react'
import type { Account, AccountType, CreateAccountDTO } from '../types'
import { accountsApi } from '../api/accountsApi'
import { Card } from '../components/ui/Card'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { Select } from '../components/ui/Select'
import { Badge } from '../components/ui/Badge'
import { Modal } from '../components/ui/Modal'
import { Loading } from '../components/ui/Loading'
import { EmptyState } from '../components/ui/EmptyState'
import { useToast } from '../components/ui/Toast'
import { formatCurrency } from '../utils/format'

const accountTypeOptions = [
  { value: 'checking', label: 'Conta Corrente' },
  { value: 'savings', label: 'Poupança' },
  { value: 'wallet', label: 'Carteira' },
]

const accountTypeLabels: Record<AccountType, string> = {
  checking: 'Conta Corrente',
  savings: 'Poupança',
  wallet: 'Carteira',
}

interface FormState {
  name: string
  institutionName: string
  accountType: AccountType
  initialBalance: string
  currency: string
}

const emptyForm: FormState = {
  name: '',
  institutionName: '',
  accountType: 'checking',
  initialBalance: '',
  currency: 'BRL',
}

interface FormErrors {
  name?: string
  institutionName?: string
  initialBalance?: string
}

export default function AccountsPage() {
  const { toast } = useToast()
  const [accounts, setAccounts] = useState<Account[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const [modalOpen, setModalOpen] = useState(false)
  const [editingAccount, setEditingAccount] = useState<Account | null>(null)
  const [form, setForm] = useState<FormState>(emptyForm)
  const [formErrors, setFormErrors] = useState<FormErrors>({})
  const [saving, setSaving] = useState(false)
  const [togglingId, setTogglingId] = useState<number | null>(null)

  const fetchAccounts = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const result = await accountsApi.list()
      setAccounts(result)
    } catch {
      setError('Não foi possível carregar as contas.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void fetchAccounts()
  }, [fetchAccounts])

  function openCreate() {
    setEditingAccount(null)
    setForm(emptyForm)
    setFormErrors({})
    setModalOpen(true)
  }

  function openEdit(account: Account) {
    setEditingAccount(account)
    setForm({
      name: account.name,
      institutionName: account.institutionName,
      accountType: account.accountType,
      initialBalance: account.initialBalance,
      currency: account.currency,
    })
    setFormErrors({})
    setModalOpen(true)
  }

  function validateForm(): boolean {
    const errors: FormErrors = {}
    if (!form.name.trim()) errors.name = 'Nome é obrigatório'
    if (!form.institutionName.trim()) errors.institutionName = 'Instituição é obrigatória'
    if (form.initialBalance !== '' && isNaN(parseFloat(form.initialBalance))) {
      errors.initialBalance = 'Informe um valor numérico válido'
    }
    setFormErrors(errors)
    return Object.keys(errors).length === 0
  }

  async function handleSave() {
    if (!validateForm()) return
    setSaving(true)
    try {
      const dto: CreateAccountDTO = {
        name: form.name.trim(),
        institutionName: form.institutionName.trim(),
        accountType: form.accountType,
        currency: form.currency,
        initialBalance: form.initialBalance || '0',
      }
      if (editingAccount) {
        const updated = await accountsApi.update(editingAccount.id, dto)
        setAccounts((prev) => prev.map((a) => (a.id === updated.id ? updated : a)))
        toast('Conta atualizada com sucesso')
      } else {
        const created = await accountsApi.create(dto)
        setAccounts((prev) => [...prev, created])
        toast('Conta criada com sucesso')
      }
      setModalOpen(false)
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { error?: string } } }).response?.data?.error ??
        'Erro ao salvar conta.'
      toast(msg, 'error')
    } finally {
      setSaving(false)
    }
  }

  async function handleToggle(account: Account) {
    setTogglingId(account.id)
    try {
      const updated = await accountsApi.toggle(account.id)
      setAccounts((prev) => prev.map((a) => (a.id === updated.id ? updated : a)))
      toast(updated.isActive ? 'Conta ativada' : 'Conta desativada')
    } catch {
      toast('Erro ao alterar status da conta.', 'error')
    } finally {
      setTogglingId(null)
    }
  }

  function setField<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((prev) => ({ ...prev, [key]: value }))
    setFormErrors((prev) => ({ ...prev, [key]: undefined }))
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Contas Financeiras</h1>
          <p className="text-sm text-slate-500 mt-0.5">Gerencie suas contas bancárias e carteiras</p>
        </div>
        <Button onClick={openCreate}>
          <Plus className="w-4 h-4" />
          Nova Conta
        </Button>
      </div>

      {error && (
        <div className="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
          <AlertCircle className="w-4 h-4 shrink-0" />
          {error}
          <button onClick={() => void fetchAccounts()} className="ml-auto underline text-xs">
            Tentar novamente
          </button>
        </div>
      )}

      {loading && <Loading label="Carregando contas..." />}

      {!loading && !error && accounts.length === 0 && (
        <EmptyState
          icon={Landmark}
          title="Nenhuma conta cadastrada"
          description="Crie sua primeira conta para começar a gerenciar suas finanças."
          action={
            <Button onClick={openCreate}>
              <Plus className="w-4 h-4" />
              Nova Conta
            </Button>
          }
        />
      )}

      {!loading && accounts.length > 0 && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {accounts.map((account) => (
            <Card key={account.id}>
              <div className="flex items-start justify-between mb-4">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center">
                    <Landmark className="w-5 h-5 text-slate-500" />
                  </div>
                  <div>
                    <p className="font-semibold text-slate-800">{account.name}</p>
                    <p className="text-xs text-slate-500">{account.institutionName}</p>
                  </div>
                </div>
                <Badge variant={account.isActive ? 'green' : 'gray'}>
                  {account.isActive ? 'Ativa' : 'Inativa'}
                </Badge>
              </div>

              <div className="flex items-center justify-between mb-1">
                <Badge variant="blue">{accountTypeLabels[account.accountType]}</Badge>
                <span className="text-xs text-slate-500">{account.currency}</span>
              </div>

              <div className="mt-3 pt-3 border-t border-slate-50">
                <p className="text-xs text-slate-500">Saldo atual</p>
                <p className="text-xl font-bold text-slate-800 mt-0.5">
                  {formatCurrency(account.currentBalance)}
                </p>
              </div>

              <div className="flex gap-2 mt-4">
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => openEdit(account)}
                  className="flex-1"
                >
                  <Pencil className="w-3.5 h-3.5" />
                  Editar
                </Button>
                <Button
                  variant={account.isActive ? 'danger' : 'secondary'}
                  size="sm"
                  onClick={() => void handleToggle(account)}
                  loading={togglingId === account.id}
                  className="flex-1"
                >
                  <Power className="w-3.5 h-3.5" />
                  {account.isActive ? 'Desativar' : 'Ativar'}
                </Button>
              </div>
            </Card>
          ))}
        </div>
      )}

      {/* Modal */}
      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingAccount ? 'Editar Conta' : 'Nova Conta'}
        size="md"
      >
        <div className="space-y-4">
          <Input
            label="Nome da conta *"
            value={form.name}
            onChange={(e) => setField('name', e.target.value)}
            placeholder="Ex: Conta Principal"
            error={formErrors.name}
          />
          <Input
            label="Instituição *"
            value={form.institutionName}
            onChange={(e) => setField('institutionName', e.target.value)}
            placeholder="Ex: Banco do Brasil"
            error={formErrors.institutionName}
          />
          <Select
            label="Tipo de conta"
            value={form.accountType}
            onChange={(e) => setField('accountType', e.target.value as AccountType)}
            options={accountTypeOptions}
          />
          <Input
            label="Saldo inicial"
            type="number"
            step="0.01"
            min="0"
            value={form.initialBalance}
            onChange={(e) => setField('initialBalance', e.target.value)}
            placeholder="0,00"
            error={formErrors.initialBalance}
          />
          <Input
            label="Moeda"
            value={form.currency}
            readOnly
            disabled
          />

          <div className="flex gap-3 pt-2">
            <Button variant="secondary" onClick={() => setModalOpen(false)} className="flex-1">
              Cancelar
            </Button>
            <Button onClick={() => void handleSave()} loading={saving} className="flex-1">
              {editingAccount ? 'Salvar alterações' : 'Criar conta'}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
