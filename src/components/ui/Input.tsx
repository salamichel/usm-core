import { InputHTMLAttributes, forwardRef } from 'react'
import { cn } from '@/lib/utils'

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  error?: boolean
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ className, error, ...props }, ref) => (
    <input
      ref={ref}
      className={cn(
        'w-full px-4 py-3 font-kanit text-base',
        'bg-white border-4 border-gray-900',
        'focus:outline-none focus:ring-0 focus:border-primary-700',
        'focus:shadow-hard',
        'placeholder:text-gray-500',
        error && 'border-red-600 focus:border-red-600',
        className
      )}
      {...props}
    />
  )
)

Input.displayName = 'Input'
