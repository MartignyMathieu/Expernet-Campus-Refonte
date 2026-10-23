// Garde de navigation de l'ENT : session valide + MFA activée si le rôle l'exige.
export default defineNuxtRouteMiddleware(async (to) => {
  const { user, charger } = useAuth()
  if (!user.value) await charger()
  if (!user.value) return navigateTo('/connexion?retour=' + encodeURIComponent(to.fullPath))
  if (user.value.mfa.obligatoire && !user.value.mfa.active && to.path !== '/ent/profil') {
    return navigateTo('/ent/profil#securite')
  }
  const role = to.meta.role as string | undefined
  if (role && user.value.role !== role) return navigateTo('/ent')
})
