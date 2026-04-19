'use client'

import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Container } from '@/components/ui/Container'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'

export default function Home() {
  return (
    <>
      <Header />
      <main className="min-h-screen w-full bg-white">
        {/* Hero Section */}
        <section className="relative py-20 overflow-hidden">
          <div className="absolute inset-0 -z-10">
            <div className="absolute top-10 left-10 w-96 h-96 bg-primary-700 skew-y-12 opacity-20" />
            <div className="absolute bottom-20 right-20 w-96 h-96 bg-red-600 -skew-x-12 opacity-20" />
          </div>
          <Container>
            <div className="text-center">
              <h1 className="font-kanit font-black italic uppercase text-5xl md:text-6xl text-gray-900 mb-4 leading-tight">
                Prêt à Smasher ?
              </h1>
              <p className="font-kanit text-xl text-gray-700 mb-8 max-w-2xl mx-auto">
                Rejoins l'Union Salles Mios Volley Ball et deviens champion
              </p>
              <Button size="lg" className="gap-2">
                Adhérer Maintenant
              </Button>
            </div>
          </Container>
        </section>

        {/* Prochains Matchs Section */}
        <section className="py-20 bg-gray-50">
          <Container>
            <h2 className="font-kanit font-black italic uppercase text-4xl text-gray-900 mb-12 text-center">
              Prochains Matchs
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {[1, 2, 3].map((match) => (
                <Card key={match} shadow="lg" className="p-6 hover:shadow-hard-lg transition-all">
                  <div className="text-center mb-4">
                    <span className="font-kanit font-bold text-primary-700 text-sm uppercase">
                      Samedi 25 Mai
                    </span>
                  </div>
                  <h3 className="font-kanit font-black uppercase text-xl text-gray-900 mb-2 text-center">
                    USM Volley vs Équipe {match}
                  </h3>
                  <p className="font-kanit text-center text-gray-600 mb-4">
                    Salle de Mios • 20h00
                  </p>
                  <Button size="sm" variant="secondary" className="w-full">
                    Plus d'infos
                  </Button>
                </Card>
              ))}
            </div>
          </Container>
        </section>

        {/* CTA Final Section */}
        <section className="py-20 bg-gray-900 border-t-4 border-gray-900">
          <Container>
            <div className="text-center">
              <h2 className="font-kanit font-black italic uppercase text-4xl text-white mb-4">
                Rejoins l'Équipe
              </h2>
              <p className="font-kanit text-lg text-gray-300 mb-8 max-w-2xl mx-auto">
                Devenez membre de la plus grande communauté de volleyeurs de Salles-les-Mios
              </p>
              <Button size="lg" className="gap-2">
                S'inscrire Maintenant
              </Button>
            </div>
          </Container>
        </section>
      </main>
      <Footer />
    </>
  )
}
