import { ReactNode } from 'react'
import { cn } from '@/lib/utils'

interface SelectCardProps {
  id: string
  title: string
  description?: ReactNode
  price?: number
  selected?: boolean
  onClick?: () => void
  className?: string
}

export function SelectCard({
  id,
  title,
  description,
  price,
  selected = false,
  onClick,
  className,
}: SelectCardProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'w-full text-left p-6 border-4 border-gray-900',
        'bg-white transition-all duration-200',
        'hover:shadow-hard-sm active:shadow-none',
        selected ? 'bg-primary-700 text-white border-primary-700 shadow-none translate-hard-sm' : 'shadow-hard hover:translate-hard-sm',
        className
      )}
    >
      <div className="flex items-start justify-between gap-4">
        <div className="flex-1">
          <h3 className="font-kanit font-bold uppercase text-lg mb-1">
            {title}
          </h3>
          {description && (
            <p className={cn(
              'font-kanit text-sm',
              selected ? 'text-primary-100' : 'text-gray-600'
            )}>
              {description}
            </p>
          )}
        </div>
        {price !== undefined && (
          <div className="flex-shrink-0">
            <span className={cn(
              'font-kanit font-black text-2xl',
              selected ? 'text-white' : 'text-primary-700'
            )}>
              {price}€
            </span>
          </div>
        )}
      </div>
    </button>
  )
}
