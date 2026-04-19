import { ReactNode } from 'react'
import { cn } from '@/lib/utils'

interface FormSectionProps {
  title: string
  description?: string
  children: ReactNode
  className?: string
}

export function FormSection({
  title,
  description,
  children,
  className,
}: FormSectionProps) {
  return (
    <div className={cn('mb-8', className)}>
      <h2 className="font-kanit font-black italic text-2xl text-gray-900 mb-2 uppercase">
        {title}
      </h2>
      {description && (
        <p className="font-kanit text-base text-gray-700 mb-4">{description}</p>
      )}
      <div className="space-y-4">{children}</div>
    </div>
  )
}
