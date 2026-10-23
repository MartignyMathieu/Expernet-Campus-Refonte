import { describe, expect, it } from 'vitest'
import { nombre, pluriel } from '~/utils/texte'

describe('accord singulier / pluriel', () => {
  it('garde le singulier pour 0 et 1', () => {
    expect(pluriel(0, 'absence')).toBe('absence')
    expect(pluriel(1, 'absence')).toBe('absence')
    expect(nombre(1, 'séance')).toBe('1 séance')
  })

  it('accorde au pluriel à partir de 2', () => {
    expect(pluriel(2, 'absence')).toBe('absences')
    expect(nombre(3, 'alerte')).toBe('3 alertes')
  })

  it('accepte une forme plurielle irrégulière', () => {
    expect(nombre(2, 'absent enregistré', 'absents enregistrés')).toBe('2 absents enregistrés')
    expect(nombre(1, 'non lu', 'non lus')).toBe('1 non lu')
  })

  it('traite les valeurs absentes comme zéro', () => {
    expect(nombre(undefined, 'message')).toBe('0 message')
    expect(pluriel(null, 'message')).toBe('message')
  })
})
