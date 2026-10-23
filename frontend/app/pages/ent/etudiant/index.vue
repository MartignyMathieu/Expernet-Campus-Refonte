<script setup lang="ts">
definePageMeta({ layout: 'ent-etudiant', middleware: 'auth', role: 'etudiant' })
useSeoMeta({ title: 'Aujourd\'hui' })
const api = useApi()
const { user } = useAuth()
const auj = new Date().toISOString().slice(0, 10)
const { data: planning, refresh } = await useAsyncData('planning-jour', () => api<any>('/api/planning', { query: { du: auj, au: auj } }))
const { data: notes } = await useAsyncData('notes-resume', () => api<any>('/api/me/notes'))
const { data: semaine } = await useAsyncData('planning-semaine', () => api<any>('/api/planning'))
const { data: assiduite } = await useAsyncData('assiduite-resume', () => api<any[]>('/api/me/assiduite'))
const promo = computed(() => user.value?.promotions[0])
const { data: documents } = await useAsyncData('documents-recents', () => promo.value ? api<any[]>(`/api/promotions/${promo.value.id}/documents`) : Promise.resolve([]))
const recents = computed(() => (documents.value || []).filter((d: any) => d.espace !== 'rendus').slice(0, 4))
const bilan = computed(() => assiduite.value?.[0])
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
        <p v-if="prochaine" class="aide">Prochaine séance : {{ new Date(prochaine.date).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric' }) }} - {{ prochaine.module }}</p>
      </section>
      <section class="carte" aria-labelledby="t-notes">
        <h2 id="t-notes">Mes résultats</h2>
        <p><span class="chiffre">{{ notes?.moyenne ?? '-' }}</span><span class="libelle-chiffre"> / 20 de moyenne</span></p>
        <ul class="liste">
          <li v-for="n in notes?.notes.slice(0, 4)" :key="n.id"><span>{{ n.module.intitule }}</span><strong>{{ Number(n.valeur) }}/20</strong></li>
        </ul>
        <NuxtLink to="/ent/etudiant/notes" class="lien-fleche">Toutes mes notes <Icone nom="suivant" :taille="16" /></NuxtLink>
      </section>
    </div>
    <div class="grille grille-dashboard suite">
      <section class="carte" aria-labelledby="t-docs">
        <h2 id="t-docs">Dernières ressources</h2>
        <p v-if="!recents.length" class="vide">Aucun document publié pour l'instant.</p>
        <ul class="liste docs-recents">
          <li v-for="d in recents" :key="d.id">
            <NuxtLink :to="`/ent/etudiant/documents?espace=${d.espace}`" class="doc-lien"><Icone :nom="d.apercu === 'pdf' ? 'pdf' : 'fichier'" :taille="20" /> {{ d.titre }}</NuxtLink>
            <span class="aide">{{ new Date(d.created_at).toLocaleDateString('fr-FR') }}</span>
          </li>
        </ul>
        <NuxtLink to="/ent/etudiant/documents" class="lien-fleche">Tous les documents <Icone nom="suivant" :taille="16" /></NuxtLink>
      </section>
      <section v-if="bilan" class="carte" aria-labelledby="t-assiduite" :class="{ critique: bilan.taux !== null && bilan.taux < bilan.seuil }">
        <h2 id="t-assiduite">Mon assiduité</h2>
        <JaugeAssiduite :taux="bilan.taux" :seuil="bilan.seuil" />
        <p class="aide">{{ nombre(bilan.absences, 'absence non justifiée', 'absences non justifiées') }} · {{ nombre(bilan.excuses, 'justifiée') }}</p>
        <NuxtLink to="/ent/etudiant/assiduite" class="lien-fleche">Détail et justificatifs <Icone nom="suivant" :taille="16" /></NuxtLink>
      </section>
    </div>
  </div>
</template>

<style scoped>
.grille-dashboard { grid-template-columns: 1.6fr 1fr; }
.lien-fleche { display: inline-flex; align-items: center; gap: 6px; }
.suite { margin-top: var(--esp-4); }
.critique { border-color: #F2B8BB; background: #FFF7F7; }
.doc-lien { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; min-width: 0; }
.docs-recents li { align-items: center; }
.liste { list-style: none; padding: 0; display: grid; gap: 8px; }
.liste li { display: flex; justify-content: space-between; gap: 8px; border-bottom: 1px solid var(--gris-bord); padding-bottom: 6px; }
@media (max-width: 1000px) { .grille-dashboard { grid-template-columns: 1fr; } }
</style>
