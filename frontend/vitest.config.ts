// Tests unitaires et de composants du front (Vitest + environnement Nuxt : auto-imports, composables).
// Installation (une fois) : docker compose exec frontend npm install -D vitest @nuxt/test-utils @vue/test-utils happy-dom
import { defineVitestConfig } from '@nuxt/test-utils/config'

export default defineVitestConfig({
  test: {
    environment: 'nuxt',
    environmentOptions: { nuxt: { domEnvironment: 'happy-dom' } },
    include: ['tests/unit/**/*.test.ts'],
  },
})
