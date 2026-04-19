'use client'

import { Container } from '@/components/ui/Container'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Grid } from '@/components/ui/Grid'

const ARTICLES = [
  {
    id: 1,
    title: 'Début de saison 2024-2025',
    date: '15 Août 2024',
    excerpt: 'Découvrez les nouveautés pour cette nouvelle saison...',
  },
  {
    id: 2,
    title: 'Victoire contre les Blagnacois',
    date: '10 Septembre 2024',
    excerpt: 'Notre équipe remporte une belle victoire 3-1...',
  },
  {
    id: 3,
    title: 'Tournoi interne réussi',
    date: '25 Septembre 2024',
    excerpt: 'Merci à tous les participants du tournoi estival...',
  },
]

export default function BlogPage() {
  return (
    <main className="min-h-screen bg-gray-50 py-20">
      <Container>
        <h1 className="font-kanit font-black italic uppercase text-5xl text-gray-900 mb-4 text-center">
          Blog
        </h1>
        <p className="font-kanit text-center text-gray-700 mb-12 max-w-2xl mx-auto">
          Suivez l'actualité du club et les résultats des matchs
        </p>

        <Grid cols={1} gap="lg">
          {ARTICLES.map((article) => (
            <Card
              key={article.id}
              shadow="lg"
              className="p-6 hover:shadow-hard-lg transition-all"
            >
              <span className="font-kanit text-sm text-primary-700 font-bold">
                {article.date}
              </span>
              <h2 className="font-kanit font-bold italic uppercase text-2xl text-gray-900 my-2">
                {article.title}
              </h2>
              <p className="font-kanit text-gray-600 mb-4">{article.excerpt}</p>
              <Button size="sm" variant="secondary">
                Lire la suite
              </Button>
            </Card>
          ))}
        </Grid>
      </Container>
    </main>
  )
}
