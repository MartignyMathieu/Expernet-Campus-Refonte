/**
 * Directive v-apparition : l'élément apparaît en fondu lorsqu'il entre dans la fenêtre.
 * - v-apparition="2" : décalage (× 80 ms) pour échelonner une série de cartes ;
 * - un élément déjà visible au chargement n'est pas masqué (pas de clignotement après le rendu serveur) ;
 * - sans JavaScript ou avec « réduire les animations », le contenu reste affiché normalement.
 */
import type { Directive } from 'vue'

export default defineNuxtPlugin((nuxtApp) => {
  let observateur: IntersectionObserver | null = null
  const reduit = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches

  const apparition: Directive<HTMLElement, number | undefined> = {
    getSSRProps: () => ({}),
    mounted(el, { value }) {
      if (reduit() || !('IntersectionObserver' in window)) return
      const r = el.getBoundingClientRect()
      if (r.top < window.innerHeight && r.bottom > 0) return // déjà à l'écran
      observateur ??= new IntersectionObserver((entrees) => {
        for (const e of entrees) {
          if (!e.isIntersecting) continue
          const cible = e.target as HTMLElement
          cible.classList.add('apparition-visible')
          observateur?.unobserve(cible)
          // Une fois apparu, l'élément retrouve ses propres transitions (survol, etc.)
          setTimeout(() => { cible.classList.remove('apparition', 'apparition-visible'); cible.style.removeProperty('--apparition-delai') }, 700 + (parseInt(cible.style.getPropertyValue('--apparition-delai')) || 0))
        }
      }, { rootMargin: '0px 0px -8% 0px' })
      el.style.setProperty('--apparition-delai', `${(value || 0) * 80}ms`)
      el.classList.add('apparition')
      observateur.observe(el)
    },
    unmounted(el) { observateur?.unobserve(el) },
  }
  nuxtApp.vueApp.directive('apparition', apparition)
})
