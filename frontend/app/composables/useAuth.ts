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

  /** Étape 1 : identifiants. Renvoie true si un code TOTP est demandé ensuite. */
  async function connexion(email: string, password: string): Promise<boolean> {
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

  async function deconnexion() {
    await api('/api/auth/logout', { method: 'POST' }).catch(() => {})
    user.value = null
    await navigateTo('/connexion')
  }

  const peut = (permission: string) => !!user.value?.permissions.includes(permission)
  const accueilRole = () => ({ admin: '/ent/admin', formateur: '/ent/formateur', etudiant: '/ent/etudiant' }[user.value?.role || 'etudiant'])

  return { user, charger, connexion, codeMfa, deconnexion, peut, accueilRole }
}
