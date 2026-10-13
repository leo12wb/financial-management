import { NavLink } from 'react-router-dom'
import {
  LayoutDashboard,
  Landmark,
  Tag,
  ArrowLeftRight,
  Upload,
  GitMerge,
  LogOut,
} from 'lucide-react'
import { cn } from '../../utils/cn'
import { useAuth } from '../../contexts/AuthContext'

const navItems = [
  { to: '/dashboard', icon: LayoutDashboard, label: 'Dashboard' },
  { to: '/accounts', icon: Landmark, label: 'Contas' },
  { to: '/categories', icon: Tag, label: 'Categorias' },
  { to: '/transactions', icon: ArrowLeftRight, label: 'Transações' },
  { to: '/imports', icon: Upload, label: 'Importar CSV' },
  { to: '/reconciliations', icon: GitMerge, label: 'Conciliação' },
]

export function Sidebar() {
  const { user, logout } = useAuth()

  return (
    <aside className="fixed inset-y-0 left-0 w-64 bg-slate-900 flex flex-col z-40">
      <div className="px-6 py-5 border-b border-slate-800">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center">
            <Landmark className="w-4 h-4 text-white" />
          </div>
          <span className="font-bold text-white text-base">Financial Manager</span>
        </div>
      </div>

      <nav className="flex-1 px-3 py-4 overflow-y-auto">
        <ul className="space-y-1">
          {navItems.map(({ to, icon: Icon, label }) => (
            <li key={to}>
              <NavLink
                to={to}
                className={({ isActive }) =>
                  cn(
                    'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
                    isActive
                      ? 'bg-emerald-500 text-white'
                      : 'text-slate-400 hover:bg-slate-800 hover:text-white',
                  )
                }
              >
                <Icon className="w-4 h-4 shrink-0" />
                {label}
              </NavLink>
            </li>
          ))}
        </ul>
      </nav>

      <div className="px-3 py-4 border-t border-slate-800">
        <div className="px-3 py-2 mb-1">
          <p className="text-xs text-slate-500">Conectado como</p>
          <p className="text-sm text-slate-300 font-medium truncate">{user?.email}</p>
        </div>
        <button
          onClick={() => void logout()}
          className="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white transition-colors w-full"
        >
          <LogOut className="w-4 h-4" />
          Sair
        </button>
      </div>
    </aside>
  )
}
