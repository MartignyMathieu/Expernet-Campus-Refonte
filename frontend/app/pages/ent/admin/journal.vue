<script setup lang="ts">
// Journal d'audit : traçabilité des actions sensibles (notes, absences, documents, comptes, double authentification).
definePageMeta({ layout: 'ent-admin', middleware: 'auth', role: 'admin' })
useSeoMeta({ title: 'Journal d\'audit' })
const api = useApi()
const filtres = reactive({ action: '', recherche: '' })
const page = ref(1)
const rechercheDiffere = ref('')
let minuteur: any
watch(() => filtres.recherche, (v) => { clearTimeout(minuteur); minuteur = setTimeout(() => { rechercheDiffere.value = v; page.value = 1 }, 350) })
watch(() => filtres.action, () => { page.value = 1 })

const { data, pending } = await useAsyncData('journal', () => api<any>('/api/admin/journal', {
  query: { page: page.value, ...(filtres.action ? { action: filtres.action } : {}), ...(rechercheDiffere.value ? { recherche: rechercheDiffere.value } : {}) },
}), { watch: [page, () => filtres.action, rechercheDiffere] })

const libelles: Record<string, string> = {
  'note.creation': 'Note saisie', 'rendu.correction': 'Correction de rendu', 'devoir.creation': 'Devoir publié',
  'absence.justification': 'Absence justifiée', 'absence.annulation': 'Justification annulée', 'justificatif.depot': 'Justificatif déposé',
  'document.suppression': 'Document supprimé', 'cahier.modification': 'Cahier de textes', 'emargement.ouverture': 'Émargement ouvert',
  'emargement.cloture': 'Émargement clôturé', 'compte.creation': 'Compte créé', 'compte.modification': 'Compte modifié',
  'mfa.reinitialisation': 'MFA réinitialisée',
}
const familles = [
  { valeur: '', libelle: 'Toutes les actions' }, { valeur: 'note.', libelle: 'Notes' }, { valeur: 'rendu.', libelle: 'Corrections' },
  { valeur: 'absence.', libelle: 'Absences' }, { valeur: 'document.', libelle: 'Documents' }, { valeur: 'emargement.', libelle: 'Émargement' },
  { valeur: 'compte.', libelle: 'Comptes' }, { valeur: 'mfa.', libelle: 'Double authentification' },
]
const couleur = (a: string) => a.startsWith('compte') || a.startsWith('mfa') ? 'pastille-rouge' : a.startsWith('note') || a.startsWith('rendu') ? 'pastille-verte' : a.startsWith('absence') || a.startsWith('justificatif') ? 'pastille-orange' : 'pastille-bleue'
const quand = (d: string) => new Date(d).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' })
const detailsOuverts = ref<number | null>(null)
</script>

<template>
  <div>
    <div class="titre-page">
      <div>
        <h1>Journal d'audit</h1>
        <p class="aide">Qui a fait quoi, quand et depuis quelle adresse. Les lignes ne peuvent être ni modifiées ni supprimées depuis l'application.</p>
      </div>
    </div>

    <div class="carte filtres">
      <div class="champ"><label for="f-action">Type d'action</label>
        <select id="f-action" v-model="filtres.action"><option v-for="f in familles" :key="f.valeur" :value="f.valeur">{{ f.libelle }}</option></select></div>
      <div class="champ"><label for="f-recherche">Rechercher</label><input id="f-recherche" v-model="filtres.recherche" type="search" placeholder="Nom, e-mail, devoir…"></div>
      <p class="total aide" aria-live="polite"><span v-if="pending" class="chargement" aria-hidden="true" />{{ data ? nombre(data.total, 'action enregistrée', 'actions enregistrées') : '' }}</p>
    </div>

    <div class="carte">
      <p v-if="!data?.data.length" class="vide">Aucune action ne correspond à ces critères.</p>
      <div v-else class="tableau-scroll">
        <table class="tableau">
          <caption class="sr-only">Journal d'audit</caption>
          <thead><tr><th scope="col">Date</th><th scope="col">Action</th><th scope="col">Détail</th><th scope="col">Auteur</th><th scope="col">Adresse IP</th></tr></thead>
          <tbody>
            <tr v-for="l in data.data" :key="l.id">
              <td class="date">{{ quand(l.date) }}</td>
              <td><span class="pastille" :class="couleur(l.action)">{{ libelles[l.action] || l.action }}</span></td>
              <td>
                {{ l.resume }}
                <button v-if="l.details" class="lien-details" :aria-expanded="detailsOuverts === l.id" @click="detailsOuverts = detailsOuverts === l.id ? null : l.id">détails</button>
                <pre v-if="detailsOuverts === l.id" class="details">{{ JSON.stringify(l.details, null, 2) }}</pre>
              </td>
              <td>{{ l.auteur ? l.auteur.nom : 'Système' }}<br v-if="l.auteur"><small v-if="l.auteur" class="aide">{{ l.auteur.email }}</small></td>
              <td><code>{{ l.ip || '-' }}</code></td>
            </tr>
          </tbody>
        </table>
      </div>
      <nav v-if="data && data.last_page > 1" class="pagination" aria-label="Pages du journal">
        <button class="btn btn-secondaire petit" :disabled="page <= 1" @click="page--">Précédent</button>
        <span>Page {{ data.current_page }} sur {{ data.last_page }}</span>
        <button class="btn btn-secondaire petit" :disabled="page >= data.last_page" @click="page++">Suivant</button>
      </nav>
    </div>
  </div>
</template>

<style scoped>
.titre-page { margin-bottom: var(--esp-4); }
.titre-page h1 { margin: 0 0 4px; }
.titre-page .aide { margin: 0; max-width: 70ch; }
.filtres { display: grid; grid-template-columns: 260px minmax(0, 360px) 1fr; gap: var(--esp-3); align-items: end; margin-bottom: var(--esp-4); padding: var(--esp-3) var(--esp-4); }
.filtres .champ { margin: 0; }
.total { margin: 0 0 12px; text-align: right; display: flex; justify-content: flex-end; align-items: center; gap: 8px; }
.date { white-space: nowrap; font-variant-numeric: tabular-nums; }
.lien-details { background: none; border: 0; color: var(--primaire-texte); font: inherit; font-size: .85rem; cursor: pointer; text-decoration: underline; padding: 0 0 0 6px; }
.details { margin: 6px 0 0; padding: 8px 10px; background: var(--gris-fond); border-radius: 8px; font-size: .8rem; white-space: pre-wrap; }
.pagination { display: flex; justify-content: center; align-items: center; gap: 16px; margin-top: var(--esp-3); }
.petit { min-height: 36px; padding: 4px 14px; }
@media (max-width: 800px) { .filtres { grid-template-columns: 1fr; } .total { text-align: left; justify-content: flex-start; } }
</style>
