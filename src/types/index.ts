// Re-export Prisma types for convenient importing
export type {
  User,
  Saison,
  Adhesion,
  Equipe_Groupe,
  Document,
  Post,
  Event,
  Photo,
} from '@prisma/client'

export type {
  Role,
  Gender,
  CategorieAdhesion,
  StatutPaiement,
  TypeDocument,
  PermissionDocument,
} from '@prisma/client'

// Extended types for application logic

export interface AdhesionPreferences {
  indisponibilites?: string[] // ['Mardi soir', 'Mercredi soir', 'Vendredi soir']
  souhaits_equipe?: string // 'L1' | 'L2' | 'L3' | 'L4' | 'Indifferent'
  choix_coupes?: string[] // ['Challenge Loisir mixte', 'Coupe Heitz', 'Coupe Aïco']
}

export interface UserWithAdhesions extends User {
  adhesions: Adhesion[]
}

export interface UserProfile {
  id: number
  email: string
  firstName: string
  lastName: string
  dateOfBirth: Date
  gender: 'MASCULIN' | 'FEMININ' | 'AUTRE'
  phone?: string
  address: string
  city: string
  zipCode: string
}

export interface AuthSession {
  user?: {
    id: number
    email: string
    firstName: string
    lastName: string
    role: 'ADHERENT' | 'ENTRAINEUR' | 'BUREAU'
  }
  expires: string
}
