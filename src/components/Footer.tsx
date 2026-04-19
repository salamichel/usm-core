import { Container } from './ui/Container'

export function Footer() {
  const currentYear = new Date().getFullYear()

  return (
    <footer className="bg-gray-900 text-white border-t-4 border-gray-900 mt-20">
      <Container className="py-12">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
          <div>
            <h3 className="font-kanit font-black italic uppercase mb-4">Contact</h3>
            <p className="font-kanit text-sm text-gray-300">
              Email: contact@usmvolley.fr<br />
              Tél: +33 5 XX XX XX XX
            </p>
          </div>
          <div>
            <h3 className="font-kanit font-black italic uppercase mb-4">Navigation</h3>
            <ul className="font-kanit text-sm space-y-2 text-gray-300">
              <li><a href="/le-club" className="hover:text-primary-700">Le Club</a></li>
              <li><a href="/equipes" className="hover:text-primary-700">Équipes</a></li>
              <li><a href="/blog" className="hover:text-primary-700">Blog</a></li>
            </ul>
          </div>
          <div>
            <h3 className="font-kanit font-black italic uppercase mb-4">Légal</h3>
            <ul className="font-kanit text-sm space-y-2 text-gray-300">
              <li><a href="/mentions-legales" className="hover:text-primary-700">Mentions légales</a></li>
              <li><a href="/cgu" className="hover:text-primary-700">CGU</a></li>
              <li><a href="/politique-confidentialite" className="hover:text-primary-700">Confidentialité</a></li>
            </ul>
          </div>
        </div>
        <div className="border-t-2 border-gray-700 pt-8">
          <p className="font-kanit text-center text-sm text-gray-400">
            © {currentYear} Union Salles Mios Volley Ball. Tous droits réservés.
          </p>
        </div>
      </Container>
    </footer>
  )
}
