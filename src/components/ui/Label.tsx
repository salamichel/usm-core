import { LabelHTMLAttributes, ReactNode } from 'react'
import { cn } from '@/lib/utils'

interface LabelProps extends LabelHTMLAttributes<HTMLLabelElement> {
  children: ReactNode
}

export function Label({ className, children, ...props }: LabelProps) {
  return (
    <label
      className={cn(
        'font-kanit font-medium text-base text-gray-900 block mb-2',
        className
      )}
      {...props}
    >
      {children}
    </label>
  )
}
