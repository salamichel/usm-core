'use client'

import Link from 'next/link'
import { Button } from './ui/Button'
import { Container } from './ui/Container'

export function Header() {
  return (
    <header className="bg-white border-b-4 border-gray-900 sticky top-0 z-50 shadow-hard">
      <Container>
        <div className="flex items-center justify-between py-4">
          <Link href="/" className="font-kanit font-black italic uppercase text-2xl text-gray-900">
            USM Volley
          </Link>
          <nav className="hidden sm:flex items-center gap-8">
            <Link href="/le-club" className="font-kanit font-medium text-gray-900 hover:text-primary-700">
              Le Club
            </Link>
            <Link href="/equipes" className="font-kanit font-medium text-gray-900 hover:text-primary-700">
              Équipes
            </Link>
            <Link href="/blog" className="font-kanit font-medium text-gray-900 hover:text-primary-700">
              Blog
            </Link>
            <Link href="/login" className="font-kanit font-medium text-gray-900 hover:text-primary-700">
              Connexion
            </Link>
          </nav>
          <Button size="md">Adhérer</Button>
        </div>
      </Container>
    </header>
  )
}
