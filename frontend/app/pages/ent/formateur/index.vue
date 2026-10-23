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
const promoId = user.value?.promotions[0]?.id
const { data: rendus } = await useAsyncData('rendus-formateur', () => promoId ? api<any[]>(`/api/promotions/${promoId}/documents`, { query: { espace: 'rendus' } }) : Promise.resolve([]))
const alertes = computed(() => (promotions.value || []).flatMap((p) => p.alertes.map((a: any) => ({ ...a, promotion: p.promotion.libelle }))))
</script>

<template>
  <div>
    <BanniereAccueil ton="banane" :titre="`Bonjour ${user?.prenom || ''}`" :texte="`${nombre(jour?.seances.length, 'séance')} aujourd'hui · ${nombre(alertes.length, 'alerte')} d'absentéisme`" image="/images/formations/cda-bac4.webp" />

    <div class="grille grille-4 indicateurs">
      <div class="carte"><Icone nom="planning" :taille="26" poids="duotone" class="tuile-icone" /><span class="chiffre">{{ jour?.seances.length ?? 0 }}</span><p class="libelle-chiffre">{{ pluriel(jour?.seances.length, 'séance') }} aujourd'hui</p></div>
      <div class="carte"><Icone nom="groupe" :taille="26" poids="duotone" class="tuile-icone" /><span class="chiffre">{{ promotions?.length ?? 0 }}</span><p class="libelle-chiffre">{{ pluriel(promotions?.length, 'promotion suivie', 'promotions suivies') }}</p></div>
      <div class="carte"><Icone nom="assiduite" :taille="26" poids="duotone" class="tuile-icone" /><span class="chiffre">{{ promotions?.[0]?.taux_moyen ?? '-' }}<small v-if="promotions?.[0]?.taux_moyen"> %</small></span><p class="libelle-chiffre">assiduité moyenne</p></div>
      <div class="carte" :class="{ critique: alertes.length }"><Icone nom="alerte" :taille="26" poids="duotone" class="tuile-icone" /><span class="chiffre">{{ alertes.length }}</span><p class="libelle-chiffre">{{ pluriel(alertes.length, 'alerte') }} d'absentéisme</p></div>
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
            <div><strong>{{ a.nom }}</strong><br><span class="aide">{{ a.promotion }} · {{ nombre(a.absences, 'demi-journée', 'demi-journées') }} d'absence</span></div>
            <span class="pastille pastille-rouge">{{ a.taux }} %</span>
          </li>
        </ul>
        <NuxtLink to="/ent/formateur/assiduite" class="lien-fleche">Détail par étudiant <Icone nom="suivant" :taille="16" /></NuxtLink>
      </section>
    </div>

    <section class="carte rendus" aria-labelledby="t-rendus">
      <h2 id="t-rendus">Derniers rendus des apprenants</h2>
      <p v-if="!rendus?.length" class="vide">Aucun rendu déposé pour l'instant.</p>
      <ul class="liste-rendus">
        <li v-for="r in rendus?.slice(0, 5)" :key="r.id">
          <Avatar :url="r.auteur.avatar_url" :nom="`${r.auteur.prenom} ${r.auteur.nom}`" :taille="36" />
          <div class="rendu-texte"><strong>{{ r.titre }}</strong><span class="aide">{{ r.auteur.prenom }} {{ r.auteur.nom }} · {{ new Date(r.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }}<template v-if="r.description"> · « {{ r.description }} »</template></span></div>
          <a :href="`/api/documents/${r.id}/telechargement`" class="btn btn-secondaire petit" :aria-label="`Télécharger ${r.titre}`"><Icone nom="telecharger" :taille="18" /></a>
        </li>
      </ul>
      <NuxtLink to="/ent/formateur/documents?espace=rendus" class="lien-fleche">Tous les rendus <Icone nom="suivant" :taille="16" /></NuxtLink>
    </section>
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
.rendus { margin-top: var(--esp-4); }
.liste-rendus { list-style: none; padding: 0; margin: 0 0 12px; display: grid; gap: 6px; }
.liste-rendus li { display: grid; grid-template-columns: 36px minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 8px 10px; border-radius: 10px; }
.liste-rendus li:hover { background: var(--gris-fond); }
.rendu-texte { display: grid; min-width: 0; }
.rendu-texte .aide { overflow-wrap: anywhere; }
.petit { min-height: 38px; padding: 6px 12px; display: inline-flex; align-items: center; }
@media (max-width: 1000px) { .deux { grid-template-columns: 1fr; } }
</style>
