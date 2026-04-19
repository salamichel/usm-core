interface ProgressBarProps {
  currentStep: number
  totalSteps: number
}

export function ProgressBar({ currentStep, totalSteps }: ProgressBarProps) {
  const percentage = (currentStep / totalSteps) * 100

  return (
    <div className="mb-8">
      <div className="mb-2 flex justify-between items-baseline">
        <h1 className="font-kanit font-black italic uppercase text-xl text-gray-900">
          Étape {currentStep}/{totalSteps}
        </h1>
        <span className="font-kanit font-bold text-sm text-gray-700">
          {percentage.toFixed(0)}%
        </span>
      </div>
      <div className="h-4 bg-gray-200 border-2 border-gray-900">
        <div
          className="h-full bg-primary-700 transition-all duration-300"
          style={{ width: `${percentage}%` }}
        />
      </div>
    </div>
  )
}
