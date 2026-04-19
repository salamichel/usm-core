import { ReactNode } from 'react'

interface FormFieldProps {
  label: string
  htmlFor: string
  error?: string
  children: ReactNode
}

export function FormField({
  label,
  htmlFor,
  error,
  children,
}: FormFieldProps) {
  return (
    <div className="mb-6">
      <label htmlFor={htmlFor} className="font-kanit font-medium text-base text-gray-900 block mb-2">
        {label}
      </label>
      {children}
      {error && (
        <div className="flex items-center gap-2 text-red-600 font-kanit font-medium text-sm mt-2">
          <span>{error}</span>
        </div>
      )}
    </div>
  )
}
