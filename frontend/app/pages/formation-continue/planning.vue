<script setup lang="ts">
// Planning inter-entreprises : prochaines sessions programmées, regroupées par mois.
const api = useApi()
const { data } = await useAsyncData('fc-planning', () => api<any>('/api/formation-continue/planning'))
useSeoMeta({
  title: 'Planning des formations - formation continue',
  description: 'Prochaines sessions inter-entreprises Expernet à La Réunion et à Mayotte : dates, durées et tarifs. Toutes les formations sont aussi disponibles en intra, sur demande.',
})

const filtreDomaine = ref('')
const domaines = computed(() => {
  const vus = new Map<string, any>()
  for (const s of data.value?.sessions || []) vus.set(s.domaine.slug, s.domaine)
  return [...vus.values()]
})
const mois = computed(() => {
  const groupes = new Map<string, any[]>()
  for (const s of (data.value?.sessions || []).filter((s: any) => !filtreDomaine.value || s.domaine.slug === filtreDomaine.value)) {
    const cle = s.prochaine_session.slice(0, 7)
    groupes.set(cle, [...(groupes.get(cle) || []), s])
  }
  return [...groupes.entries()].map(([cle, sessions]) => ({
    cle, sessions,
    libelle: new Date(cle + '-01T00:00:00').toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' }),
  }))
})
const jour = (d: string) => new Date(d + 'T00:00:00')
const devis = (titre: string) => `/contact?sujet=${encodeURIComponent(`Inscription - ${titre}`.slice(0, 150))}`
</script>

<template>
  <div v-if="data">
    <EntetePage titre="Planning des formations" sur-titre="Formation continue · inter-entreprises" texte="Les prochaines sessions programmées. Une date ne vous convient pas ? Toutes nos formations sont aussi organisées sur demande, en inter ou en intra-entreprise." image="/images/campus/filiere-informatique.webp">
      <div class="cta-entete">
        <NuxtLink to="/contact?sujet=Demande de devis - formation continue" class="btn btn-primaire">Demander une date</NuxtLink>
      </div>
    </EntetePage>
    <NavFormationContinue />

    <section class="conteneur bloc" aria-labelledby="t-planning">
      <div class="tete">
        <div>
          <h2 id="t-planning">{{ nombre(data.sessions.length, 'session programmée', 'sessions programmées') }}</h2>
          <p class="aide">{{ data.note }}</p>
        </div>
        <div v-if="domaines.length > 1" class="champ filtre">
          <label for="domaine">Domaine</label>
          <select id="domaine" v-model="filtreDomaine">
            <option value="">Tous les domaines</option>
            <option v-for="d in domaines" :key="d.slug" :value="d.slug">{{ d.libelle }}</option>
          </select>
        </div>
      </div>

      <p v-if="!mois.length" class="vide">Aucune session programmée pour le moment : contactez-nous pour organiser une date.</p>
      <div v-for="m in mois" :key="m.cle" class="mois">
        <h3>{{ m.libelle }}</h3>
        <ul class="sessions">
          <li v-for="(s, i) in m.sessions" :key="s.slug" v-apparition="i % 3" class="session">
            <span class="date" aria-hidden="true"><small>{{ jour(s.prochaine_session).toLocaleDateString('fr-FR', { weekday: 'short' }) }}</small><strong>{{ jour(s.prochaine_session).getDate() }}</strong></span>
            <div class="session-texte">
              <NuxtLink :to="`/formation-continue/${s.slug}`" class="titre">{{ s.intitule }}</NuxtLink>
              <span class="meta"><span class="sr-only">Le {{ dateLongue(s.prochaine_session) }} · </span><Icone :nom="s.domaine.icone" :taille="15" />{{ s.domaine.libelle.split(' - ')[0] }} · {{ s.duree || 'Durée sur demande' }} · {{ resumeTarif(s.tarifs) }}</span>
            </div>
            <NuxtLink :to="devis(s.intitule)" class="btn btn-secondaire petit">S'inscrire</NuxtLink>
          </li>
        </ul>
      </div>
    </section>

    <section class="conteneur bloc lieux" aria-labelledby="t-lieux">
      <h2 id="t-lieux">Lieux de formation</h2>
      <ul class="grille grille-3">
        <li v-for="l in data.locaux" :key="l.ville" class="carte"><Icone nom="adresse" :taille="22" poids="duotone" class="ic" /><strong>{{ l.ville }}</strong><span class="aide">{{ l.adresse }}</span></li>
      </ul>
      <p class="contact">Une question sur une session ? <a :href="`tel:${data.contact.telephone.replace(/\s/g, '')}`">{{ data.contact.telephone }}</a> · <a :href="`mailto:${data.contact.email}`">{{ data.contact.email }}</a></p>
    </section>
  </div>
</template>

<style scoped>
.cta-entete { display: flex; gap: 12px; flex-wrap: wrap; margin-top: var(--esp-4); }
.bloc { margin-top: var(--esp-5); }
.tete { display: flex; justify-content: space-between; align-items: flex-end; gap: var(--esp-3); flex-wrap: wrap; margin-bottom: var(--esp-4); }
.tete h2 { margin: 0 0 4px; }
.tete .aide { margin: 0; max-width: 60ch; }
.filtre { margin: 0; min-width: 260px; }
.mois { margin-bottom: var(--esp-5); }
.mois h3 { text-transform: capitalize; color: var(--primaire-fonce); border-bottom: 2px solid var(--gris-bord); padding-bottom: 8px; margin-bottom: var(--esp-3); }
.sessions { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
.session { display: grid; grid-template-columns: 64px minmax(0, 1fr) auto; gap: 16px; align-items: center; padding: 12px 16px 12px 12px; background: #fff; border: 1px solid var(--gris-bord); border-radius: var(--rayon); transition: border-color .15s, box-shadow .15s; }
.session:hover { border-color: var(--primaire); box-shadow: var(--ombre); }
.date { display: grid; place-items: center; width: 64px; height: 64px; border-radius: 12px; background: #E1F3FB; color: var(--primaire-fonce); line-height: 1.05; }
.date small { text-transform: uppercase; font-size: .7rem; font-weight: 700; letter-spacing: .06em; }
.date strong { font-size: 1.5rem; }
.session-texte { display: grid; gap: 4px; min-width: 0; }
.titre { font-weight: 700; color: var(--encre); text-decoration: none; }
.titre:hover { color: var(--primaire-texte); text-decoration: underline; }
.meta { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; color: var(--texte-doux); font-size: .9rem; }
.petit { min-height: 40px; padding: 6px 16px; }
.lieux ul { list-style: none; padding: 0; }
.lieux li { display: grid; gap: 4px; }
.ic { color: var(--primaire); margin-bottom: 6px; }
.contact { margin-top: var(--esp-3); }
@media (max-width: 640px) {
  .session { grid-template-columns: 52px minmax(0, 1fr); }
  .date { width: 52px; height: 52px; }
  .session .btn { grid-column: 2; justify-self: start; }
}
</style>
