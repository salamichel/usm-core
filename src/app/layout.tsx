import type { Metadata } from 'next'
import './globals.css'

export const metadata: Metadata = {
  title: 'USM Volley - Union Salles Mios Volley Ball',
  description: 'Site officiel et plateforme de gestion pour l\'Union Salles Mios Volley Ball',
  icons: {
    icon: '/images/favicon.ico',
  },
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="fr">
      <body>
        {children}
      </body>
    </html>
  )
}
