import { ReactNode } from 'react'
import { cn } from '@/lib/utils'

interface CardProps {
  children: ReactNode
  className?: string
  shadow?: 'sm' | 'md' | 'lg'
}

export function Card({ children, className, shadow = 'md' }: CardProps) {
  return (
    <div
      className={cn(
        'bg-white border-4 border-gray-900',
        shadow === 'sm' && 'shadow-hard-sm',
        shadow === 'md' && 'shadow-hard',
        shadow === 'lg' && 'shadow-hard-lg',
        className
      )}
    >
      {children}
    </div>
  )
}
