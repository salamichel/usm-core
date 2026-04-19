'use client'

import { Container } from '@/components/ui/Container'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Grid } from '@/components/ui/Grid'

const TEAMS = [
  { id: 1, name: 'M13 Féminines', description: 'Jeunes minimes filles' },
  { id: 2, name: 'M15 Féminines', "description": 'Jeunes cadettes filles' },
  { id: 3, name: 'M18 Féminines', description: 'Jeunes seniors filles' },
  { id: 4, name: 'Compet Loisir', description: 'Équipe loisir mixte' },
  { id: 5, name: 'DEP', description: 'Équipe masculine' },
  { id: 6, name: 'Loisir Mixte', description: 'Groupe loisir' },
]

export default function EquipesPage() {
  return (
    <main className="min-h-screen bg-gray-50 py-20">
      <Container>
        <h1 className="font-kanit font-black italic uppercase text-5xl text-gray-900 mb-4 text-center">
          Nos Équipes
        </h1>
        <p className="font-kanit text-center text-gray-700 mb-12 max-w-2xl mx-auto">
          Découvre les équipes de l'USM Volley et leurs compositions
        </p>

        <Grid cols={3} gap="lg">
          {TEAMS.map((team) => (
            <Card
              key={team.id}
              shadow="lg"
              className="p-6 hover:shadow-hard-lg transition-all flex flex-col"
            >
              <div className="flex-1">
                <h2 className="font-kanit font-bold uppercase text-xl text-gray-900 mb-2">
                  {team.name}
                </h2>
                <p className="font-kanit text-gray-600 mb-4">{team.description}</p>
              </div>
              <Button size="sm" variant="secondary" className="w-full">
                Voir l'équipe
              </Button>
            </Card>
          ))}
        </Grid>
      </Container>
    </main>
  )
}
