<script setup lang="ts">
definePageMeta({ layout: 'ent-formateur', middleware: 'auth', role: 'formateur' })
useSeoMeta({ title: 'Tableau de bord formateur' })
const api = useApi()
const { user } = useAuth()
const auj = new Date().toISOString().slice(0, 10)
const [{ data: promotions }, { data: jour }] = await Promise.all([
  useAsyncData('promos-formateur', () => api<any[]>('/api/formateur/promotions')),
  useAsyncData('jour-formateur', () => api<any>('/api/planning', { query: { du: auj, au: auj } })),
])
const alertes = computed(() => (promotions.value || []).flatMap((p) => p.alertes.map((a: any) => ({ ...a, promotion: p.promotion.libelle }))))
</script>

<template>
  <div>
    <BanniereAccueil ton="banane" :titre="`Bonjour ${user?.prenom || ''}`" :texte="`${jour?.seances.length || 0} séance(s) aujourd'hui · ${alertes.length} alerte(s) d'absentéisme`" image="/images/formations/cda-bac4.webp" />

    <div class="grille grille-4 indicateurs">
      <div class="carte"><Icone nom="planning" :taille="26" poids="duotone" class="tuile-icone" /><span class="chiffre">{{ jour?.seances.length ?? 0 }}</span><p class="libelle-chiffre">séance(s) aujourd'hui</p></div>
      <div class="carte"><Icone nom="groupe" :taille="26" poids="duotone" class="tuile-icone" /><span class="chiffre">{{ promotions?.length ?? 0 }}</span><p class="libelle-chiffre">promotion(s) suivie(s)</p></div>
      <div class="carte"><Icone nom="assiduite" :taille="26" poids="duotone" class="tuile-icone" /><span class="chiffre">{{ promotions?.[0]?.taux_moyen ?? '—' }}<small v-if="promotions?.[0]?.taux_moyen"> %</small></span><p class="libelle-chiffre">assiduité moyenne</p></div>
      <div class="carte" :class="{ critique: alertes.length }"><Icone nom="alerte" :taille="26" poids="duotone" class="tuile-icone" /><span class="chiffre">{{ alertes.length }}</span><p class="libelle-chiffre">alerte(s) d'absentéisme</p></div>
    </div>

    <div class="grille deux">
      <section class="carte" aria-labelledby="t-jour">
        <h2 id="t-jour">Mes séances du jour</h2>
        <PlanningSemaine :seances="jour?.seances || []" role="formateur" />
      </section>
      <section class="carte" aria-labelledby="t-alertes">
        <h2 id="t-alertes">Alertes d'absentéisme</h2>
        <p v-if="!alertes.length" class="vide">Aucun étudiant sous le seuil d'alerte.</p>
        <ul class="alertes">
          <li v-for="a in alertes" :key="a.id + a.promotion">
            <div><strong>{{ a.nom }}</strong><br><span class="aide">{{ a.promotion }} · {{ a.absences }} demi-journée(s) d'absence</span></div>
            <span class="pastille pastille-rouge">{{ a.taux }} %</span>
          </li>
        </ul>
        <NuxtLink to="/ent/formateur/assiduite" class="lien-fleche">Détail par étudiant <Icone nom="suivant" :taille="16" /></NuxtLink>
      </section>
    </div>
  </div>
</template>

<style scoped>
.indicateurs { margin-bottom: var(--esp-4); }
.indicateurs .carte { padding: var(--esp-3) var(--esp-4); }
.indicateurs p { margin: 6px 0 0; }
.tuile-icone { display: block; color: var(--primaire); margin-bottom: 10px; }
.critique .tuile-icone { color: var(--alerte); }
.lien-fleche { display: inline-flex; align-items: center; gap: 6px; }
.critique { border-color: #F2B8BB; background: #FFF7F7; }
.critique .chiffre { color: var(--alerte); }
.deux { grid-template-columns: 1.4fr 1fr; }
.alertes { list-style: none; padding: 0; display: grid; gap: 10px; }
.alertes li { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 8px; background: #FFF7F7; }
@media (max-width: 1000px) { .deux { grid-template-columns: 1fr; } }
</style>
