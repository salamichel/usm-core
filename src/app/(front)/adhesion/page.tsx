'use client'

import { useState } from 'react'
import { Container } from '@/components/ui/Container'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Input } from '@/components/ui/Input'
import { ProgressBar } from '@/components/form/ProgressBar'
import { FormField } from '@/components/form/FormField'
import { FormSection } from '@/components/form/FormSection'
import { SelectCard } from '@/components/ui/SelectCard'

const CATEGORIES = [
  {
    id: 'sans-compet',
    title: 'Sans Compétition',
    description: 'Loisir, plus de 15 ans',
    price: 60,
  },
  {
    id: 'minimes-feminines',
    title: 'M13/M15/M18 Féminines',
    description: 'Compétition volley',
    price: 100,
  },
  {
    id: 'compet-lib',
    title: 'Compétition Loisir',
    description: 'Loisir + compétition',
    price: 100,
  },
  {
    id: 'dep',
    title: 'DEP (Masculin)',
    description: 'Compétition masculine',
    price: 150,
  },
]

interface FormData {
  firstName: string
  lastName: string
  email: string
  birthDate: string
  category: string
}

export default function AdhesionPage() {
  const [currentStep, setCurrentStep] = useState(1)
  const [formData, setFormData] = useState<FormData>({
    firstName: '',
    lastName: '',
    email: '',
    birthDate: '',
    category: '',
  })
  const [errors, setErrors] = useState<Partial<FormData>>({})

  const selectedCategory = CATEGORIES.find((c) => c.id === formData.category)
  const totalPrice = selectedCategory?.price || 0

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target
    setFormData((prev) => ({ ...prev, [name]: value }))
    if (errors[name as keyof FormData]) {
      setErrors((prev) => ({ ...prev, [name]: '' }))
    }
  }

  const validateStep1 = () => {
    const newErrors: Partial<FormData> = {}
    if (!formData.firstName) newErrors.firstName = 'Le prénom est requis'
    if (!formData.lastName) newErrors.lastName = 'Le nom est requis'
    if (!formData.email) newErrors.email = 'L\'email est requis'
    if (!formData.birthDate) newErrors.birthDate = 'La date de naissance est requise'

    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  const handleNextStep = () => {
    if (currentStep === 1 && validateStep1()) {
      setCurrentStep(2)
    } else if (currentStep === 2 && formData.category) {
      setCurrentStep(3)
    }
  }

  return (
    <main className="min-h-screen bg-gray-50">
      <Container className="py-12">
        <Card shadow="lg" className="p-8 md:p-12 max-w-2xl mx-auto">
          <ProgressBar currentStep={currentStep} totalSteps={3} />

          {/* Step 1: Personal Info */}
          {currentStep === 1 && (
            <FormSection
              title="Informations Personnelles"
              description="Remplis tes coordonnées de base"
            >
              <FormField
                label="Prénom"
                htmlFor="firstName"
                error={errors.firstName}
              >
                <Input
                  id="firstName"
                  name="firstName"
                  type="text"
                  value={formData.firstName}
                  onChange={handleInputChange}
                  error={!!errors.firstName}
                  placeholder="Jean"
                />
              </FormField>

              <FormField
                label="Nom"
                htmlFor="lastName"
                error={errors.lastName}
              >
                <Input
                  id="lastName"
                  name="lastName"
                  type="text"
                  value={formData.lastName}
                  onChange={handleInputChange}
                  error={!!errors.lastName}
                  placeholder="Dupont"
                />
              </FormField>

              <FormField
                label="Email"
                htmlFor="email"
                error={errors.email}
              >
                <Input
                  id="email"
                  name="email"
                  type="email"
                  value={formData.email}
                  onChange={handleInputChange}
                  error={!!errors.email}
                  placeholder="jean@example.com"
                />
              </FormField>

              <FormField
                label="Date de Naissance"
                htmlFor="birthDate"
                error={errors.birthDate}
              >
                <Input
                  id="birthDate"
                  name="birthDate"
                  type="date"
                  value={formData.birthDate}
                  onChange={handleInputChange}
                  error={!!errors.birthDate}
                />
              </FormField>
            </FormSection>
          )}

          {/* Step 2: Category Selection */}
          {currentStep === 2 && (
            <FormSection
              title="Choisir une Catégorie"
              description="Sélectionne le type d'adhésion qui te convient"
            >
              <div className="space-y-4">
                {CATEGORIES.map((category) => (
                  <SelectCard
                    key={category.id}
                    id={category.id}
                    title={category.title}
                    description={category.description}
                    price={category.price}
                    selected={formData.category === category.id}
                    onClick={() =>
                      setFormData((prev) => ({
                        ...prev,
                        category: category.id,
                      }))
                    }
                  />
                ))}
              </div>
            </FormSection>
          )}

          {/* Step 3: Review & Payment */}
          {currentStep === 3 && (
            <FormSection
              title="Récapitulatif"
              description="Vérifie tes informations avant de passer au paiement"
            >
              <Card shadow="sm" className="p-4 mb-6 bg-gray-50">
                <h4 className="font-kanit font-bold text-sm uppercase text-gray-900 mb-3">
                  Résumé de l'adhésion
                </h4>
                <div className="space-y-2 font-kanit text-sm">
                  <div className="flex justify-between">
                    <span>Nom:</span>
                    <span className="font-medium">
                      {formData.firstName} {formData.lastName}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span>Email:</span>
                    <span className="font-medium">{formData.email}</span>
                  </div>
                  <div className="flex justify-between border-t-2 border-gray-300 pt-2 mt-2">
                    <span className="font-bold">Total:</span>
                    <span className="font-black text-primary-700 text-lg">
                      {totalPrice}€
                    </span>
                  </div>
                </div>
              </Card>
              <Button size="lg" className="w-full">
                Procéder au Paiement
              </Button>
            </FormSection>
          )}

          {/* Navigation Buttons */}
          <div className="flex gap-4 mt-8">
            {currentStep > 1 && (
              <Button
                size="md"
                variant="secondary"
                onClick={() => setCurrentStep(currentStep - 1)}
              >
                Retour
              </Button>
            )}
            {currentStep < 3 && (
              <Button
                size="md"
                onClick={handleNextStep}
                className="flex-1"
              >
                Suivant
              </Button>
            )}
          </div>
        </Card>

        {/* Sticky Price Bar (Mobile) */}
        {formData.category && (
          <div className="fixed bottom-0 left-0 right-0 bg-white border-t-4 border-gray-900 shadow-hard-lg md:hidden">
            <Container className="py-4 flex items-center justify-between">
              <span className="font-kanit font-bold text-lg">Total:</span>
              <span className="font-kanit font-black text-2xl text-primary-700">
                {totalPrice}€
              </span>
            </Container>
          </div>
        )}
      </Container>
    </main>
  )
}
