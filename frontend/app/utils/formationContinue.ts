// Mise en forme des données de formation continue (tarifs HT en euros, dates de session).
export interface Tarifs { inter: number | 'sur demande' | null; intra: number | null; unique: number | null }

const euros = (n: number) => n.toLocaleString('fr-FR') + ' €'

/** Lignes de tarif lisibles : « Inter-entreprises : 550 € », « Intra-entreprise : 1 400 € ». */
export function lignesTarifs(t?: Tarifs | null): { libelle: string; valeur: string }[] {
  if (!t) return [{ libelle: 'Tarif', valeur: 'Sur devis' }]
  const l: { libelle: string; valeur: string }[] = []
  if (t.inter !== null) l.push({ libelle: 'Inter-entreprises', valeur: t.inter === 'sur demande' ? 'Sur demande' : euros(t.inter) })
  if (t.intra !== null) l.push({ libelle: 'Intra-entreprise', valeur: euros(t.intra) })
  if (t.unique !== null) l.push({ libelle: 'Tarif', valeur: euros(t.unique) })
  return l.length ? l : [{ libelle: 'Tarif', valeur: 'Sur devis' }]
}

/** Résumé court pour une carte : « Inter dès 550 € » ou « Intra 1 400 € ». */
export function resumeTarif(t?: Tarifs | null): string {
  if (!t) return 'Sur devis'
  if (typeof t.inter === 'number') return `Inter ${euros(t.inter)}`
  if (t.intra !== null) return `Intra ${euros(t.intra)}`
  if (t.unique !== null) return euros(t.unique)
  return 'Sur devis'
}

/** Une session n'est affichée que si elle n'est pas passée. */
export function sessionAVenir(d?: string | null): boolean {
  if (!d) return false
  return d >= new Date().toISOString().slice(0, 10)
}

export const dateCourte = (d: string) => new Date(d + 'T00:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
export const dateLongue = (d: string) => new Date(d + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
