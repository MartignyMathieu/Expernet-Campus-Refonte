/**
 * Client HTTP de l'API Laravel.
 * - Navigateur : même origine (/api), cookie de session httpOnly + jeton CSRF (XSRF-TOKEN → en-tête X-XSRF-TOKEN).
 * - Serveur (SSR des pages publiques) : appel direct au réseau interne Docker.
 */
function lireCookie(nom: string): string | null {
  if (import.meta.server) return null
  const m = document.cookie.match(new RegExp('(?:^|; )' + nom + '=([^;]*)'))
  return m ? decodeURIComponent(m[1]) : null
}

export async function csrf() {
  await $fetch('/sanctum/csrf-cookie', { credentials: 'include' })
}

export function useApi() {
  const config = useRuntimeConfig()
  const base = import.meta.server ? `${config.apiInternal}` : ''

  return <T = any>(chemin: string, options: Record<string, any> = {}) => {
    const methode = (options.method || 'GET').toUpperCase()
    const entetes: Record<string, string> = { Accept: 'application/json', ...(options.headers || {}) }
    const xsrf = lireCookie('XSRF-TOKEN')
    if (xsrf && methode !== 'GET') entetes['X-XSRF-TOKEN'] = xsrf
    return $fetch<T>(base + chemin, { ...options, method: methode as any, headers: entetes, credentials: 'include' })
  }
}

/** Extrait les messages de validation Laravel (422) par champ. */
export function erreursValidation(e: any): Record<string, string> {
  const errs = e?.data?.errors || {}
  return Object.fromEntries(Object.entries(errs).map(([k, v]: any) => [k, Array.isArray(v) ? v[0] : String(v)]))
}
