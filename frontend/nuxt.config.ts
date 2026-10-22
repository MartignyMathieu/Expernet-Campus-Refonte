// Nuxt : rendu serveur (SSR) pour les pages publiques (SEO), application cliente pour l'ENT.
export default defineNuxtConfig({
  compatibilityDate: '2026-10-01',
  devtools: { enabled: false },
  css: ['~/assets/css/main.css'],
  app: {
    pageTransition: { name: 'page', mode: 'out-in' },
    head: {
      htmlAttrs: { lang: 'fr' },
      titleTemplate: '%s · Expernet Campus Informatique & Marketing Digital',
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'theme-color', content: '#009BDD' },
        { property: 'og:site_name', content: 'Expernet Campus' },
        { property: 'og:image', content: '/images/campus/campus-expernet.webp' },
      ],
      link: [
        { rel: 'icon', type: 'image/png', href: '/images/brand/favicon.png' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Roboto:wght@400;500;700&display=swap' },
      ],
    },
  },
  routeRules: {
    '/ent/**': { ssr: false },        // espace connecté : pas d'indexation, rendu client
    '/connexion': { ssr: false },
  },
  runtimeConfig: {
    // URL interne utilisée par le serveur Nuxt pour appeler l'API (réseau Docker).
    apiInternal: process.env.NUXT_API_INTERNAL || 'http://nginx',
    public: {
      siteUrl: process.env.NUXT_PUBLIC_SITE_URL || 'http://localhost:8080',
    },
  },
  nitro: {
    // En développement hors Docker : le front (3000) relaie /api et /sanctum vers Laravel (8000).
    devProxy: {
      '/api': { target: 'http://localhost:8000/api', changeOrigin: true },
      '/sanctum': { target: 'http://localhost:8000/sanctum', changeOrigin: true },
      '/openapi.json': { target: 'http://localhost:8000/openapi.json', changeOrigin: true },
    },
  },
})
