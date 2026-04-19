import { ReactNode, ButtonHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'danger'
  size?: 'sm' | 'md' | 'lg'
  children: ReactNode
}

export function Button({
  variant = 'primary',
  size = 'md',
  className,
  children,
  ...props
}: ButtonProps) {
  return (
    <button
      className={cn(
        'font-kanit font-bold uppercase italic transition-all duration-200 cursor-pointer',
        'border-4 border-gray-900 active:translate-hard-sm',
        'hover:translate-hard-sm hover:shadow-none',
        'active:shadow-none',
        size === 'sm' && 'px-4 py-2 text-sm shadow-hard-sm',
        size === 'md' && 'px-6 py-3 text-base shadow-hard',
        size === 'lg' && 'px-8 py-4 text-lg shadow-hard-lg',
        variant === 'primary' && 'bg-primary-700 text-white hover:bg-primary-600 active:bg-primary-600',
        variant === 'secondary' && 'bg-white text-gray-900 hover:bg-gray-100 active:bg-gray-100',
        variant === 'danger' && 'bg-red-600 text-white hover:bg-red-700 active:bg-red-700',
        className
      )}
      {...props}
    >
      {children}
    </button>
  )
}
