import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import Avatar from '~/components/Avatar.vue'
import JaugeAssiduite from '~/components/JaugeAssiduite.vue'

describe('Avatar', () => {
  it('affiche les initiales sans photo', async () => {
    const c = await mountSuspended(Avatar, { props: { nom: 'Emma Démo' } })
    expect(c.text()).toBe('ED')
    expect(c.find('img').exists()).toBe(false)
  })

  it('affiche la photo quand elle existe', async () => {
    const c = await mountSuspended(Avatar, { props: { nom: 'Emma Démo', url: '/api/utilisateurs/1/avatar?v=x' } })
    expect(c.find('img').attributes('src')).toBe('/api/utilisateurs/1/avatar?v=x')
  })
})

describe('JaugeAssiduite', () => {
  it('expose le taux aux technologies d\'assistance', async () => {
    const c = await mountSuspended(JaugeAssiduite, { props: { taux: 72.5, seuil: 80 } })
    const jauge = c.find('[role="meter"]')
    expect(jauge.attributes('aria-valuenow')).toBe('72.5')
    expect(c.text()).toContain('72.5 %')
  })

  it('indique un taux non calculé', async () => {
    const c = await mountSuspended(JaugeAssiduite, { props: { taux: null, seuil: 80 } })
    expect(c.text()).toContain('-')
  })
})
