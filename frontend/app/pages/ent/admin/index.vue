<script setup lang="ts">
definePageMeta({ layout: 'ent-admin', middleware: 'auth', role: 'admin' })
useSeoMeta({ title: 'Administration' })
const api = useApi()
const { data: s } = await useAsyncData('admin-tdb', () => api<any>('/api/admin/tableau-de-bord'))
const { data: promos } = await useAsyncData('admin-promos', () => api<any[]>('/api/formateur/promotions'))
const tuiles = computed(() => s.value ? [
  ['Utilisateurs', s.value.utilisateurs, 'utilisateurs'], ['Formations publiées', s.value.formations, 'diplome'], ['Promotions', s.value.promotions, 'groupe'],
  ['Candidatures à traiter', s.value.candidatures_a_traiter, 'candidatures'], ['Séances cette semaine', s.value.seances_semaine, 'planning'],
  ['Absences cette semaine', s.value.absences_semaine, 'alerte'], ['Documents publiés cette semaine', s.value.documents_semaine, 'documents'],
  ['Rendus déposés cette semaine', s.value.rendus_semaine, 'deposer'], ['Comptes à privilèges sans MFA', s.value.comptes_sans_mfa, 'securite'],
] : [])
</script>
<template>
  <div>
    <BanniereAccueil titre="Administration du campus" texte="Comptes, promotions, examens et candidatures" image="/images/campus/hero-blog.webp" />
    <div class="grille grille-4">
      <div v-for="[l, v, ic] in tuiles" :key="l" class="carte" :class="{ alerte: l.includes('MFA') && v > 0 }">
        <Icone :nom="ic" :taille="26" poids="duotone" class="ic" /><span class="chiffre">{{ v }}</span><p class="libelle-chiffre">{{ l }}</p>
      </div>
    </div>
    <section class="carte bloc">
      <h2>Assiduité par promotion</h2>
      <div v-for="p in promos" :key="p.promotion.id" class="ligne">
        <span>{{ p.promotion.libelle }}</span>
        <JaugeAssiduite :taux="p.taux_moyen" :seuil="p.seuil" />
        <span class="pastille" :class="p.alertes.length ? 'pastille-rouge' : 'pastille-verte'">{{ p.alertes.length }} alerte(s)</span>
      </div>
    </section>
  </div>
</template>
<style scoped>
.carte p { margin: 6px 0 0; }
.ic { display: block; color: var(--primaire); margin-bottom: 10px; }
.alerte { background: #FFF7F7; border-color: #F2B8BB; }
.bloc { margin-top: var(--esp-4); }
.ligne { display: grid; grid-template-columns: 200px 1fr auto; gap: 16px; align-items: center; padding: 10px 0; }
@media (max-width: 700px) { .ligne { grid-template-columns: 1fr; } }
</style>
