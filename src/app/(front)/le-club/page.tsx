'use client'

import { Container } from '@/components/ui/Container'
import { Card } from '@/components/ui/Card'

export default function LeClubPage() {
  return (
    <main className="min-h-screen bg-gray-50 py-20">
      <Container>
        <h1 className="font-kanit font-black italic uppercase text-5xl text-gray-900 mb-12 text-center">
          Le Club
        </h1>

        <div className="max-w-3xl mx-auto space-y-8">
          <Card shadow="lg" className="p-8">
            <h2 className="font-kanit font-bold italic uppercase text-2xl text-gray-900 mb-4">
              Bienvenue au USM Volley
            </h2>
            <p className="font-kanit text-gray-700 leading-relaxed mb-4">
              L'Union Salles Mios Volley Ball est un club dynamique de volleyball
              fondé en 1985. Nous accueillons les passionnés de tous les niveaux.
            </p>
            <p className="font-kanit text-gray-700 leading-relaxed">
              Nos équipes composées de jeunes et d'adultes participent à des
              compétitions régionales et pratiquent le volleyball loisir.
            </p>
          </Card>

          <Card shadow="lg" className="p-8">
            <h2 className="font-kanit font-bold italic uppercase text-2xl text-gray-900 mb-4">
              Nos Valeurs
            </h2>
            <ul className="font-kanit text-gray-700 space-y-2">
              <li>✓ L'esprit d'équipe et la camaraderie</li>
              <li>✓ L'excellence sportive</li>
              <li>✓ L'inclusion et l'égalité</li>
              <li>✓ Le partage et le plaisir</li>
            </ul>
          </Card>
        </div>
      </Container>
    </main>
  )
}
