import { describe, expect, it } from 'vitest'
import { lignesTarifs, resumeTarif, sessionAVenir } from '~/utils/formationContinue'

describe('formation continue : tarifs et sessions', () => {
  it('affiche « Sur devis » sans tarif', () => {
    expect(resumeTarif(null)).toBe('Sur devis')
  })

  it('liste les tarifs renseignés uniquement', () => {
    const lignes = lignesTarifs({ inter: 450, intra: null, unique: null })
    expect(lignes).toHaveLength(1)
    expect(lignes[0].valeur).toMatch(/450/)
  })

  it('distingue une session future d\'une session passée', () => {
    const demain = new Date(Date.now() + 864e5).toISOString().slice(0, 10)
    const hier = new Date(Date.now() - 864e5).toISOString().slice(0, 10)
    expect(sessionAVenir(demain)).toBe(true)
    expect(sessionAVenir(hier)).toBe(false)
    expect(sessionAVenir(null)).toBe(false)
  })
})
