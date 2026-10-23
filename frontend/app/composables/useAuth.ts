export interface Utilisateur {
  id: number; prenom: string; nom: string; email: string
  role: 'admin' | 'formateur' | 'etudiant'
  permissions: string[]
  promotions: { id: number; libelle: string }[]
  mfa: { active: boolean; obligatoire: boolean }
}

export function useAuth() {
  const user = useState<Utilisateur | null>('user', () => null)
  const api = useApi()

  async function charger() {
    try { user.value = await api<Utilisateur>('/api/me') } catch { user.value = null }
    return user.value
  }

  /**
   * Étape 1 : identifiants. Renvoie true si un code TOTP est demandé ensuite.
   * Une session déjà ouverte (autre compte) est fermée d'abord : sinon le serveur ignore
   * la nouvelle connexion et l'on resterait sur le compte précédent.
   */
  async function connexion(email: string, password: string): Promise<boolean> {
    if (user.value || await charger()) await fermerSession()
    await csrf()
    const r: any = await api('/api/auth/login', { method: 'POST', body: { email, password } })
    if (r?.two_factor) return true
    await charger()
    return false
  }

  /** Étape 2 : code de l'application d'authentification (ou code de secours). */
  async function codeMfa(code: string, secours = false) {
    await api('/api/auth/two-factor-challenge', { method: 'POST', body: secours ? { recovery_code: code } : { code } })
    await charger()
  }

  /** Ferme la session côté serveur ; un nouveau jeton CSRF est ensuite nécessaire. */
  async function fermerSession() {
    await csrf().catch(() => {})
    await api('/api/auth/logout', { method: 'POST' }).catch(() => {})
    user.value = null
  }

  async function deconnexion() {
    await fermerSession()
    await navigateTo('/connexion')
  }

  const peut = (permission: string) => !!user.value?.permissions.includes(permission)
  const accueilRole = () => ({ admin: '/ent/admin', formateur: '/ent/formateur', etudiant: '/ent/etudiant' }[user.value?.role || 'etudiant'])

  return { user, charger, connexion, codeMfa, deconnexion, fermerSession, peut, accueilRole }
}
