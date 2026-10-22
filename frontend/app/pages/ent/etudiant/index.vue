<script setup lang="ts">
definePageMeta({ layout: 'ent-etudiant', middleware: 'auth', role: 'etudiant' })
useSeoMeta({ title: 'Aujourd\'hui' })
const api = useApi()
const { user } = useAuth()
const auj = new Date().toISOString().slice(0, 10)
const { data: planning, refresh } = await useAsyncData('planning-jour', () => api<any>('/api/planning', { query: { du: auj, au: auj } }))
const { data: notes } = await useAsyncData('notes-resume', () => api<any>('/api/me/notes'))
const { data: semaine } = await useAsyncData('planning-semaine', () => api<any>('/api/planning'))
onMounted(() => { const t = setInterval(refresh, 15000); onBeforeUnmount(() => clearInterval(t)) })   // la fenêtre d'émargement peut s'ouvrir à tout moment
const prochaine = computed(() => semaine.value?.seances.find((s: any) => s.date > auj))
</script>

<template>
  <div>
    <BanniereAccueil :titre="`Bonjour ${user?.prenom || ''}`" :texte="user?.promotions[0] ? `Promotion ${user.promotions[0].libelle}` : 'Bienvenue sur votre espace'" image="/images/campus/hero-marketing.webp" />
    <div class="grille grille-dashboard">
      <section class="carte" aria-labelledby="t-jour">
        <h2 id="t-jour">Aujourd'hui</h2>
        <PlanningSemaine :seances="planning?.seances || []" role="etudiant" />
        <p v-if="prochaine" class="aide">Prochaine séance : {{ new Date(prochaine.date).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric' }) }} — {{ prochaine.module }}</p>
      </section>
      <section class="carte" aria-labelledby="t-notes">
        <h2 id="t-notes">Mes résultats</h2>
        <p><span class="chiffre">{{ notes?.moyenne ?? '—' }}</span><span class="libelle-chiffre"> / 20 de moyenne</span></p>
        <ul class="liste">
          <li v-for="n in notes?.notes.slice(0, 4)" :key="n.id"><span>{{ n.module.intitule }}</span><strong>{{ Number(n.valeur) }}/20</strong></li>
        </ul>
        <NuxtLink to="/ent/etudiant/notes" class="lien-fleche">Toutes mes notes <Icone nom="suivant" :taille="16" /></NuxtLink>
      </section>
    </div>
  </div>
</template>

<style scoped>
.grille-dashboard { grid-template-columns: 1.6fr 1fr; }
.lien-fleche { display: inline-flex; align-items: center; gap: 6px; }
.liste { list-style: none; padding: 0; display: grid; gap: 8px; }
.liste li { display: flex; justify-content: space-between; gap: 8px; border-bottom: 1px solid var(--gris-bord); padding-bottom: 6px; }
@media (max-width: 1000px) { .grille-dashboard { grid-template-columns: 1fr; } }
</style>
