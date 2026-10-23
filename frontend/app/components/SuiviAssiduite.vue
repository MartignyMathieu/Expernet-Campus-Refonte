<!--
  Suivi d'assiduité des promotions (formateur, administrateur) : tableau ou trombinoscope,
  fiche détaillée d'un apprenant avec justification des absences.
-->
<script setup lang="ts">
const api = useApi()
const { data, refresh } = await useAsyncData('assiduite', () => api<any[]>('/api/formateur/promotions'))
const vue = ref<'tableau' | 'trombi'>('tableau')

// Fiche d'un apprenant
const fiche = ref<any>(null); const chargement = ref(false); const erreur = ref('')
const motifs = reactive<Record<number, string>>({})
async function ouvrir(promotionId: number, etudiantId: number) {
  chargement.value = true; erreur.value = ''
  try { fiche.value = await api(`/api/promotions/${promotionId}/etudiants/${etudiantId}/assiduite`) } catch { erreur.value = 'Fiche indisponible.' } finally { chargement.value = false }
  nextTick(() => document.getElementById('fiche-apprenant')?.focus())
}
async function justifier(absence: any, justifiee: boolean) {
  erreur.value = ''
  try {
    await csrf()
    await api(`/api/emargements/${absence.id}/justification`, { method: 'PATCH', body: { justifiee, motif: motifs[absence.id] || null } })
    await Promise.all([ouvrir(fiche.value.promotion.id, fiche.value.etudiant.id), refresh()])
  } catch (e: any) { erreur.value = e?.data?.message || 'Mise à jour impossible.' }
}
const date = (d: string) => new Date(d).toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' })
</script>

<template>
  <div>
    <div class="titre-page">
      <h1>Assiduité</h1>
      <div class="bascule" role="group" aria-label="Affichage">
        <button :class="{ actif: vue === 'tableau' }" :aria-pressed="vue === 'tableau'" @click="vue = 'tableau'"><Icone nom="table" :taille="18" /> Tableau</button>
        <button :class="{ actif: vue === 'trombi' }" :aria-pressed="vue === 'trombi'" @click="vue = 'trombi'"><Icone nom="groupe" :taille="18" /> Trombinoscope</button>
      </div>
    </div>

    <section v-for="p in data" :key="p.promotion.id" class="carte bloc">
      <h2>{{ p.promotion.libelle }} <span class="aide">- {{ p.formation }}</span></h2>
      <p>Taux moyen : <strong>{{ p.taux_moyen ?? '-' }} %</strong> · seuil d'alerte : {{ p.seuil }} % · <span class="aide">cliquez sur un apprenant pour justifier ses absences</span></p>

      <div v-if="vue === 'tableau'" class="tableau-scroll">
        <table class="tableau">
          <caption class="sr-only">Assiduité par apprenant</caption>
          <thead><tr><th scope="col">Apprenant</th><th scope="col">Taux de présence</th><th scope="col">Absences</th><th scope="col">Statut</th></tr></thead>
          <tbody>
            <tr v-for="e in p.etudiants" :key="e.id">
              <td><button class="nom-bouton" @click="ouvrir(p.promotion.id, e.id)"><Avatar :url="e.avatar_url" :nom="e.nom" :taille="32" /> {{ e.nom }}</button></td>
              <td style="min-width: 220px"><JaugeAssiduite :taux="e.taux" :seuil="p.seuil" /></td>
              <td>{{ e.absences }}</td>
              <td><span v-if="e.alerte" class="pastille pastille-rouge">Alerte</span><span v-else class="pastille pastille-verte">OK</span></td>
            </tr>
          </tbody>
        </table>
      </div>
      <ul v-else class="trombi">
        <li v-for="e in p.etudiants" :key="e.id">
          <button class="vignette-apprenant" :class="{ alerte: e.alerte }" @click="ouvrir(p.promotion.id, e.id)">
            <Avatar :url="e.avatar_url" :nom="e.nom" :taille="84" />
            <strong>{{ e.nom }}</strong>
            <span class="pastille" :class="e.alerte ? 'pastille-rouge' : 'pastille-verte'">{{ e.taux ?? '-' }}{{ e.taux !== null ? ' %' : '' }}</span>
          </button>
        </li>
      </ul>
    </section>

    <!-- Fiche apprenant -->
    <Transition name="fiche">
      <aside v-if="fiche" id="fiche-apprenant" class="fiche" tabindex="-1" aria-labelledby="t-fiche" @keydown.esc="fiche = null">
        <button class="fermer" aria-label="Fermer la fiche" @click="fiche = null"><Icone nom="fermer" :taille="20" /></button>
        <div class="fiche-entete">
          <Avatar :url="fiche.etudiant.avatar_url" :nom="fiche.etudiant.nom" :taille="72" />
          <div>
            <h2 id="t-fiche">{{ fiche.etudiant.nom }}</h2>
            <p class="aide">{{ fiche.promotion.libelle }}</p>
            <p class="contacts"><a :href="`mailto:${fiche.etudiant.email}`"><Icone nom="email" :taille="16" /> {{ fiche.etudiant.email }}</a>
              <a v-if="fiche.etudiant.telephone" :href="`tel:${fiche.etudiant.telephone.replace(/\s/g, '')}`"><Icone nom="telephone" :taille="16" /> {{ fiche.etudiant.telephone }}</a></p>
          </div>
        </div>
        <JaugeAssiduite :taux="fiche.taux" :seuil="fiche.seuil" />
        <p class="aide">{{ nombre(fiche.presences, 'présence') }} · {{ nombre(fiche.absences, 'absence') }} · {{ nombre(fiche.excuses, 'justifiée') }}</p>
        <p v-if="erreur" class="message message-erreur" role="alert">{{ erreur }}</p>
        <h3>Demi-journées manquées</h3>
        <p v-if="!fiche.detail_absences.length" class="vide">Aucune absence.</p>
        <ul class="absences">
          <li v-for="a in fiche.detail_absences" :key="a.id" :class="a.statut">
            <div class="absence-texte"><strong>{{ date(a.date) }} · {{ a.creneau === 'matin' ? 'matin' : 'après-midi' }}</strong><span class="aide">{{ a.module }}</span>
              <span v-if="a.motif" class="motif">Motif : {{ a.motif }}</span>
              <a v-if="a.justificatif" :href="a.justificatif.url" target="_blank" rel="noopener" class="justif"><Icone nom="fichier" :taille="15" /> Justificatif : {{ a.justificatif.nom }}</a></div>
            <form v-if="a.statut === 'absent'" class="justifier" @submit.prevent="justifier(a, true)">
              <label :for="`motif-${a.id}`" class="sr-only">Motif</label>
              <input :id="`motif-${a.id}`" v-model="motifs[a.id]" :placeholder="a.justificatif ? 'Motif (ex. : certificat médical fourni)' : 'Motif (certificat…)'" maxlength="255">
              <button class="btn btn-primaire petit">Justifier</button>
            </form>
            <button v-else class="btn btn-secondaire petit" @click="justifier(a, false)">Annuler la justification</button>
          </li>
        </ul>
        <NuxtLink :to="`/ent/${$route.path.includes('/admin') ? 'admin' : 'formateur'}/documents?espace=rendus`" class="lien-fleche">Voir les justificatifs déposés <Icone nom="suivant" :taille="16" /></NuxtLink>
      </aside>
    </Transition>
    <div v-if="fiche" class="voile" aria-hidden="true" @click="fiche = null" />
    <p v-if="chargement" class="sr-only" role="status">Chargement de la fiche…</p>
  </div>
</template>

<style scoped>
.titre-page { display: flex; justify-content: space-between; align-items: center; gap: var(--esp-3); flex-wrap: wrap; margin-bottom: var(--esp-4); }
.titre-page h1 { margin: 0; }
.bascule { display: inline-flex; background: #fff; border: 1px solid var(--gris-bord); border-radius: 999px; padding: 4px; }
.bascule button { display: inline-flex; align-items: center; gap: 6px; border: 0; background: none; font: inherit; padding: 8px 14px; border-radius: 999px; cursor: pointer; color: var(--texte); }
.bascule button.actif { background: var(--primaire-fonce); color: #fff; font-weight: 600; }
.bloc { margin-bottom: var(--esp-4); }
.nom-bouton { display: inline-flex; align-items: center; gap: 10px; background: none; border: 0; padding: 0; font: inherit; color: var(--primaire-texte); font-weight: 600; cursor: pointer; text-align: left; }
.nom-bouton:hover { text-decoration: underline; }
.trombi { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: var(--esp-3); }
.vignette-apprenant { width: 100%; display: grid; justify-items: center; gap: 8px; padding: 18px 10px; border: 1px solid var(--gris-bord); border-radius: 14px; background: #fff; font: inherit; color: var(--encre); cursor: pointer; transition: transform .15s, box-shadow .15s, border-color .15s; }
.vignette-apprenant:hover { transform: translateY(-3px); box-shadow: var(--ombre); border-color: var(--primaire); }
.vignette-apprenant.alerte { border-color: #F2B8BB; background: #FFF7F7; }
.vignette-apprenant strong { text-align: center; }
.voile { position: fixed; inset: 0; background: rgba(20, 32, 43, .35); z-index: 60; }
.fiche { position: fixed; top: 0; right: 0; bottom: 0; width: min(460px, 100%); background: #fff; z-index: 61; padding: 28px; overflow-y: auto; box-shadow: var(--ombre-forte); outline: none; display: grid; align-content: start; gap: 12px; }
.fermer { position: absolute; top: 14px; right: 14px; width: 40px; height: 40px; border-radius: 50%; border: 0; background: var(--gris-fond); cursor: pointer; display: grid; place-items: center; }
.fiche-entete { display: flex; gap: 16px; align-items: center; padding-right: 40px; }
.fiche-entete h2 { margin: 0; }
.fiche-entete p { margin: 2px 0 0; }
.contacts { display: grid; gap: 2px; font-size: .9rem; }
.contacts a { display: inline-flex; align-items: center; gap: 6px; word-break: break-all; }
.fiche h3 { margin: 10px 0 0; font-size: 1.05rem; }
.absences { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; }
.absences li { display: grid; gap: 8px; padding: 12px; border-radius: 10px; background: #FFF7F7; border: 1px solid #F2D4D6; }
.absences li.excuse { background: #F1FAF4; border-color: #CBE9D5; }
.absence-texte { display: grid; gap: 2px; }
.motif { font-size: .88rem; }
.justif { display: inline-flex; align-items: center; gap: 4px; font-size: .88rem; font-weight: 600; }
.justifier { display: flex; gap: 8px; }
.justifier input { flex: 1; min-width: 0; font: inherit; padding: 8px 12px; border: 1.5px solid #C6CFD8; border-radius: var(--rayon-s); }
.petit { min-height: 38px; padding: 6px 14px; font-size: .88rem; }
.lien-fleche { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; }
.fiche-enter-active, .fiche-leave-active { transition: transform .25s ease, opacity .25s; }
.fiche-enter-from, .fiche-leave-to { transform: translateX(30px); opacity: 0; }
@media (max-width: 600px) { .fiche { padding: 20px 16px 100px; } .justifier { flex-direction: column; } }
</style>
