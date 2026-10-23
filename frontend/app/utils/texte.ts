/**
 * Accord singulier / pluriel à la française : 0 et 1 au singulier, au-delà au pluriel.
 * pluriel(3, 'absence') → 'absences' ; nombre(1, 'séance') → '1 séance'.
 */
export function pluriel(n: number | null | undefined, singulier: string, formePluriel = singulier + 's'): string {
  return Math.abs(Number(n) || 0) >= 2 ? formePluriel : singulier
}

export function nombre(n: number | null | undefined, singulier: string, formePluriel?: string): string {
  return `${Number(n) || 0} ${pluriel(n, singulier, formePluriel)}`
}
