// ─── Auth ─────────────────────────────────────────────────────────────────────
export interface User {
  id: number
  email: string
  roles: string[]
}

export interface LoginCredentials {
  email: string
  password: string
}

export interface AuthResponse {
  token: string
  user: User
}

// ─── Account ──────────────────────────────────────────────────────────────────
export type AccountType = 'checking' | 'savings' | 'wallet'

export interface Account {
  id: number
  name: string
  institutionName: string
  accountType: AccountType
  currency: string
  initialBalance: string
  currentBalance: string
  isActive: boolean
  createdAt: string
  updatedAt: string
}

export interface CreateAccountDTO {
  name: string
  institutionName: string
  accountType: AccountType
  currency: string
  initialBalance: string
}

// ─── Category ─────────────────────────────────────────────────────────────────
export type CategoryType = 'income' | 'expense'

export interface Category {
  id: number
  name: string
  type: CategoryType
  isActive: boolean
  createdAt: string
}

export interface CreateCategoryDTO {
  name: string
  type: CategoryType
}

// ─── Transaction ──────────────────────────────────────────────────────────────
export type TransactionType = 'income' | 'expense'
export type TransactionSource = 'manual' | 'imported'
export type ReconciliationStatus = 'pending' | 'reconciled' | 'divergent' | 'ignored'

export interface Transaction {
  id: number
  account: Pick<Account, 'id' | 'name'>
  category: Pick<Category, 'id' | 'name' | 'type'> | null
  description: string
  amount: string
  type: TransactionType
  transactionDate: string
  externalReference: string | null
  source: TransactionSource
  reconciliationStatus: ReconciliationStatus
  createdAt: string
  updatedAt: string
}

export interface CreateTransactionDTO {
  accountId: number
  categoryId?: number
  description: string
  amount: string
  type: TransactionType
  transactionDate: string
  externalReference?: string
}

// ─── Import ───────────────────────────────────────────────────────────────────
export type ImportStatus = 'pending' | 'previewed' | 'confirmed' | 'failed'

export interface ImportedStatement {
  id: number
  account: Pick<Account, 'id' | 'name'>
  originalFilename: string
  status: ImportStatus
  totalRows: number
  importedRows: number
  rejectedRows: number
  createdAt: string
}

export interface StatementEntry {
  id: number
  transactionDate: string
  description: string
  amount: string
  externalReference: string | null
  reconciliationStatus: ReconciliationStatus
  isDuplicate: boolean
  error: string | null
}

export interface ImportPreviewResponse {
  importId: number
  accountId: number
  totalRows: number
  validRows: number
  invalidRows: number
  duplicateRows: number
  entries: StatementEntry[]
}

// ─── Reconciliation ───────────────────────────────────────────────────────────
export type ReconciliationMatchStatus = 'pending' | 'reconciled' | 'rejected' | 'undone'

export interface ReconciliationSuggestion {
  statementEntry: StatementEntry
  transaction: Transaction
  score: number
  matchReasons: string[]
}

export interface Reconciliation {
  id: number
  transaction: Pick<Transaction, 'id' | 'description' | 'amount' | 'transactionDate'>
  statementEntry: Pick<StatementEntry, 'id' | 'description' | 'amount' | 'transactionDate'>
  status: ReconciliationMatchStatus
  reconciledAt: string
  undoneAt: string | null
  createdAt: string
}

// ─── Dashboard ────────────────────────────────────────────────────────────────
export interface DashboardSummary {
  totalBalance: string
  totalIncome: string
  totalExpenses: string
  pendingReconciliationCount: number
  reconciledCount: number
  periodStart: string
  periodEnd: string
}

export interface DashboardChartPoint {
  month: string
  income: number
  expense: number
}

export interface CategoryExpense {
  categoryName: string
  amount: number
  percentage: number
}

export interface DashboardData {
  summary: DashboardSummary
  chartData: DashboardChartPoint[]
  categoryExpenses: CategoryExpense[]
  recentTransactions: Transaction[]
}

// ─── Pagination ───────────────────────────────────────────────────────────────
export interface PaginatedResponse<T> {
  data: T[]
  total: number
  page: number
  limit: number
  totalPages: number
}

// ─── API Error ────────────────────────────────────────────────────────────────
export interface ApiViolation {
  field: string
  message: string
}

export interface ApiError {
  error: string
  violations?: ApiViolation[]
}
