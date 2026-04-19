import { ReactNode } from 'react'
import { cn } from '@/lib/utils'

interface FormErrorProps {
  children: ReactNode
  className?: string
}

export function FormError({ children, className }: FormErrorProps) {
  return (
    <div
      className={cn(
        'flex items-center gap-2 text-red-600 font-kanit font-medium text-sm mt-2',
        className
      )}
      role="alert"
    >
      <svg
        className="w-5 h-5 flex-shrink-0"
        fill="currentColor"
        viewBox="0 0 20 20"
      >
        <path
          fillRule="evenodd"
          d="M18.101 12.93a1 1 0 00-1.414-1.414L11 16.586V9.5a1 1 0 10-2 0v7.086L3.313 11.516a1 1 0 00-1.414 1.414l9 9a1 1 0 001.414 0l9-9z"
          clipRule="evenodd"
        />
      </svg>
      {children}
    </div>
  )
}
