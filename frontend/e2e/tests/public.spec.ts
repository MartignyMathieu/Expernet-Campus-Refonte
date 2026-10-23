import { expect, test } from '@playwright/test'

test.describe('Site public', () => {
  test('la page d\'accueil présente le catalogue', async ({ page }) => {
    await page.goto('/')
    await expect(page).toHaveTitle(/Expernet Campus/)
    await expect(page.getByRole('heading', { level: 1 })).toContainText('numérique')
    await expect(page.getByRole('search')).toBeVisible()
  })

  test('la recherche mène au catalogue filtré', async ({ page }) => {
    await page.goto('/')
    await page.getByLabel('Rechercher une formation').fill('développeur')
    await page.getByRole('button', { name: 'Rechercher' }).click()
    await expect(page).toHaveURL(/\/formations\?.*q=/)
  })

  test('la formation continue propose catalogue, planning, actualités et à propos', async ({ page }) => {
    await page.goto('/formation-continue')
    const nav = page.getByRole('navigation', { name: 'Formation continue' })
    for (const lien of ['Catalogue', 'Planning', 'Actualités', 'À propos']) await expect(nav.getByRole('link', { name: lien })).toBeVisible()

    await nav.getByRole('link', { name: 'Planning' }).click()
    await expect(page.getByRole('heading', { level: 2, name: /session/ })).toBeVisible()

    await page.getByRole('navigation', { name: 'Formation continue' }).getByRole('link', { name: 'Actualités' }).click()
    await expect(page.locator('.article').first()).toBeVisible()

    await page.getByRole('navigation', { name: 'Formation continue' }).getByRole('link', { name: 'À propos' }).click()
    await expect(page.getByRole('heading', { name: 'Toujours à votre écoute' })).toBeVisible()
  })

  test('aucun « (s) » ni tiret long dans les pages publiques', async ({ page }) => {
    for (const url of ['/', '/formation-continue', '/formation-continue/a-propos']) {
      await page.goto(url)
      const texte = await page.locator('main').innerText()
      expect(texte, url).not.toMatch(/\w\(s\)/)
      expect(texte, url).not.toMatch(/[—–]/)
    }
  })

  test('la page de santé répond', async ({ request }) => {
    const r = await request.get('/api/sante')
    expect(r.status()).toBe(200)
    expect((await r.json()).statut).toBe('ok')
  })
})
