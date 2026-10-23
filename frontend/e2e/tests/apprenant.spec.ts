import { expect, test } from '@playwright/test'

// Parcours d'un apprenant (compte de démonstration, sans double authentification).
const MOT_DE_PASSE = process.env.E2E_PASSWORD

test.describe('Espace apprenant', () => {
  test.skip(!MOT_DE_PASSE, 'E2E_PASSWORD non défini (mot de passe des comptes de démonstration)')

  test.beforeEach(async ({ page }) => {
    await page.goto('/connexion')
    await page.getByLabel('Adresse e-mail').fill('etudiant@expernet.test')
    await page.getByLabel('Mot de passe').fill(MOT_DE_PASSE!)
    await page.getByRole('button', { name: 'Se connecter' }).click()
    await expect(page).toHaveURL(/\/ent\/etudiant/)
  })

  test('le tableau de bord affiche la journée, les résultats et le travail à faire', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Aujourd\'hui', level: 2 })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Mes résultats' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'À faire' })).toBeVisible()
  })

  test('un module du planning ouvre ses documents', async ({ page }) => {
    const lien = page.locator('.lien-module').first()
    test.skip(await lien.count() === 0, 'Pas de séance aujourd\'hui (week-end)')
    await lien.click()
    await expect(page).toHaveURL(/\/ent\/etudiant\/documents\?module=\d+/)
    await expect(page.locator('.bandeau-module')).toContainText('Documents du module')
  })

  test('les devoirs sont classés en « À rendre », « Rendus » et « Corrigés »', async ({ page }) => {
    await page.getByRole('link', { name: 'Devoirs' }).first().click()
    for (const onglet of ['À rendre', 'Rendus', 'Corrigés']) await expect(page.getByRole('tab', { name: new RegExp(onglet) })).toBeVisible()
  })

  test('le menu du profil et la cloche de notifications', async ({ page }) => {
    await page.locator('.chip').click()
    await expect(page.getByRole('link', { name: 'Mon profil' })).toBeVisible()
    await page.getByRole('link', { name: 'Mon profil' }).click()
    await expect(page.getByRole('heading', { name: 'Sécurité du compte' })).toBeVisible()
    await page.getByRole('button', { name: /Notifications/ }).click()
    await expect(page.getByRole('region', { name: 'Notifications' })).toBeVisible()
  })
})
