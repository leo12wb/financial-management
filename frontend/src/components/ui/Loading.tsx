import { Loader2 } from 'lucide-react'
import { cn } from '../../utils/cn'

interface LoadingProps {
  className?: string
  size?: 'sm' | 'md' | 'lg'
  label?: string
}

export function Loading({ className, size = 'md', label = 'Carregando...' }: LoadingProps) {
  const sizes = { sm: 'w-4 h-4', md: 'w-6 h-6', lg: 'w-8 h-8' }

  return (
    <div className={cn('flex flex-col items-center justify-center gap-3 py-12', className)}>
      <Loader2 className={cn('animate-spin text-emerald-500', sizes[size])} />
      <p className="text-sm text-slate-500">{label}</p>
    </div>
  )
}
