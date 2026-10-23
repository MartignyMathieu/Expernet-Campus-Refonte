<!-- Liste des documents d'un espace, dépôt (si autorisé dans cet espace), aperçu et suppression. -->
<script setup lang="ts">
const props = defineProps<{
  promotionId: number
  documents: any[]
  espace?: { code: string; libelle: string; peut_deposer: boolean } | null
  afficherEspace?: boolean
  libelleEspace?: (code: string) => string
  recherche?: string
  modules?: { id: number; intitule: string }[]
  moduleDefaut?: number | null
}>()
const emit = defineEmits<{ maj: [] }>()
const api = useApi()
const { user } = useAuth()

const estRendu = computed(() => props.espace?.code === 'rendus')
const titre = ref(''); const description = ref(''); const fichier = ref<File | null>(null)
const moduleId = ref<number | null>(props.moduleDefaut ?? null)
const erreur = ref(''); const ok = ref(''); const envoi = ref(false); const survol = ref(false)
const champFichier = ref<HTMLInputElement | null>(null)
const taille = (o: number) => o > 1e6 ? (o / 1e6).toFixed(1).replace('.', ',') + ' Mo' : Math.max(1, Math.ceil(o / 1e3)) + ' Ko'

function choisirFichier(f?: File | null) {
  fichier.value = f || null
  if (f && !titre.value) titre.value = f.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').slice(0, 150)
}
function deposerGlisse(e: DragEvent) { survol.value = false; choisirFichier(e.dataTransfer?.files?.[0]) }

async function deposer() {
  erreur.value = ''; ok.value = ''
  if (!fichier.value) { erreur.value = 'Choisissez un fichier.'; return }
  const fd = new FormData()
  fd.append('titre', titre.value); fd.append('fichier', fichier.value); fd.append('espace', props.espace!.code)
  if (description.value) fd.append('description', description.value)
  if (moduleId.value) fd.append('module_id', String(moduleId.value))
  envoi.value = true
  try {
    await csrf(); await api(`/api/promotions/${props.promotionId}/documents`, { method: 'POST', body: fd })
    titre.value = ''; description.value = ''; fichier.value = null; if (champFichier.value) champFichier.value.value = ''
    ok.value = estRendu.value ? 'Rendu déposé : votre formateur peut le consulter.' : 'Document publié pour la promotion.'
    emit('maj')
  } catch (e: any) { erreur.value = Object.values(erreursValidation(e))[0] || e?.data?.message || 'Dépôt impossible.' } finally { envoi.value = false }
}

// Suppression en deux temps (pas de boîte de dialogue bloquante)
const aConfirmer = ref<number | null>(null)
async function supprimer(id: number) {
  if (aConfirmer.value !== id) { aConfirmer.value = id; return }
  try { await csrf(); await api(`/api/documents/${id}`, { method: 'DELETE' }); aConfirmer.value = null; emit('maj') } catch { erreur.value = 'Suppression impossible.' }
}

// Aperçu dans le portail
const apercus = computed(() => props.documents.map((d: any) => ({
  id: d.id, titre: d.titre, nom: d.nom_original, apercu: d.apercu,
  url: `/api/documents/${d.id}/apercu`, telechargement: `/api/documents/${d.id}/telechargement`,
  meta: `${taille(d.taille)} · ${d.auteur.prenom} ${d.auteur.nom}`,
})))
const ouvert = ref<number | null>(null)

const typeDe = (d: any) => d.apercu === 'pdf' ? { icone: 'pdf', libelle: 'PDF', classe: 'pdf' }
  : d.apercu === 'image' ? { icone: 'image', libelle: 'Image', classe: 'image' }
  : d.apercu === 'texte' ? { icone: 'fichier', libelle: 'Texte', classe: 'texte' }
  : { icone: 'fichier', libelle: (d.nom_original.split('.').pop() || 'Fichier').toUpperCase(), classe: 'autre' }
const estNouveau = (d: any) => Date.now() - new Date(d.created_at).getTime() < 7 * 864e5
const libelleVide = computed(() => props.recherche ? 'Aucun document ne correspond à votre recherche.'
  : estRendu.value && user.value?.role === 'etudiant' ? 'Vous n\'avez encore rien déposé. Devoirs, TP, justificatifs d\'absence : déposez-les ici.'
  : props.moduleDefaut ? 'Aucun document pour ce module dans cet espace pour l\'instant.'
  : 'Aucun document dans cet espace pour l\'instant.')
</script>

<template>
  <div class="grille docs-grille">
    <form v-if="espace?.peut_deposer" class="carte depot-carte" @submit.prevent="deposer">
      <h3 class="titre-depot"><Icone nom="deposer" :taille="20" /> {{ estRendu ? 'Déposer un rendu ou un justificatif' : `Publier dans « ${espace.libelle} »` }}</h3>
      <p v-if="erreur" class="message message-erreur" role="alert">{{ erreur }}</p>
      <p v-if="ok" class="message message-succes" role="status">{{ ok }}</p>
      <label class="zone-fichier" :class="{ survol, rempli: fichier }" @dragover.prevent="survol = true" @dragleave="survol = false" @drop.prevent="deposerGlisse">
        <input ref="champFichier" type="file" class="sr-only" accept=".pdf,.docx,.pptx,.xlsx,.odt,.ods,.odp,.zip,.png,.jpg,.jpeg,.txt,.md,.csv" @change="choisirFichier(($event.target as HTMLInputElement).files?.[0])">
        <Icone :nom="fichier ? 'fichier' : 'deposer'" :taille="26" />
        <span v-if="fichier"><strong>{{ fichier.name }}</strong> · {{ taille(fichier.size) }}</span>
        <span v-else><strong>Glissez un fichier ici</strong> ou cliquez pour parcourir<br><small>PDF, Office, OpenDocument, ZIP, images, texte · 10 Mo max</small></span>
      </label>
      <div class="depot">
        <div class="champ"><label :for="`titre-${espace.code}`">Titre</label><input :id="`titre-${espace.code}`" v-model="titre" required maxlength="150" :placeholder="estRendu ? 'Ex. : TP 2 - Modélisation' : 'Ex. : Support séance 4'"></div>
        <div v-if="modules?.length" class="champ"><label :for="`module-${espace.code}`">Module <span class="aide">(facultatif)</span></label>
          <select :id="`module-${espace.code}`" v-model="moduleId"><option :value="null">Aucun module en particulier</option><option v-for="m in modules" :key="m.id" :value="m.id">{{ m.intitule }}</option></select></div>
        <div class="champ"><label :for="`desc-${espace.code}`">{{ estRendu ? 'Message pour le formateur' : 'Description' }} <span class="aide">(facultatif)</span></label><input :id="`desc-${espace.code}`" v-model="description" maxlength="500"></div>
      </div>
      <button class="btn btn-primaire" :disabled="envoi"><span v-if="envoi" class="chargement" aria-hidden="true" />{{ estRendu ? 'Déposer' : 'Publier' }}</button>
    </form>

    <div class="carte">
      <p v-if="!documents.length" class="vide">{{ libelleVide }}</p>
      <TransitionGroup tag="ul" name="doc" class="docs">
        <li v-for="(d, i) in documents" :key="d.id" class="doc">
          <button class="vignette" :class="typeDe(d).classe" :aria-label="`Aperçu : ${d.titre}`" @click="ouvert = i">
            <img v-if="d.apercu === 'image'" :src="`/api/documents/${d.id}/apercu`" alt="" loading="lazy">
            <template v-else><Icone :nom="typeDe(d).icone" :taille="26" /><small>{{ typeDe(d).libelle }}</small></template>
          </button>
          <div class="doc-texte">
            <div class="doc-ligne">
              <button class="doc-titre" @click="ouvert = i">{{ d.titre }}</button>
              <span v-if="estNouveau(d)" class="pastille pastille-bleue">Nouveau</span>
              <span v-if="afficherEspace && libelleEspace" class="pastille">{{ libelleEspace(d.espace) }}</span>
            </div>
            <span v-if="d.description" class="description">{{ d.description }}</span>
            <span class="aide meta">
              <Avatar :url="d.auteur.avatar_url" :nom="`${d.auteur.prenom} ${d.auteur.nom}`" :taille="20" />
              {{ d.auteur.prenom }} {{ d.auteur.nom }} · {{ new Date(d.created_at).toLocaleDateString('fr-FR') }} · {{ taille(d.taille) }}<template v-if="d.module"> · {{ d.module.intitule }}</template>
            </span>
          </div>
          <div class="doc-actions">
            <button v-if="d.apercu" class="btn btn-secondaire petit" @click="ouvert = i"><Icone nom="oeil" :taille="18" /><span class="libelle">Aperçu</span></button>
            <a :href="`/api/documents/${d.id}/telechargement`" class="btn btn-secondaire petit" :aria-label="`Télécharger ${d.titre}`"><Icone nom="telecharger" :taille="18" /><span class="libelle">Télécharger</span></a>
            <button v-if="d.peut_supprimer" class="btn petit" :class="aConfirmer === d.id ? 'btn-danger' : 'btn-secondaire'" :aria-label="aConfirmer === d.id ? `Confirmer la suppression de ${d.titre}` : `Supprimer ${d.titre}`" @click="supprimer(d.id)" @blur="aConfirmer = null">
              <Icone nom="supprimer" :taille="18" /><span v-if="aConfirmer === d.id" class="libelle">Confirmer</span>
            </button>
          </div>
        </li>
      </TransitionGroup>
    </div>
    <ApercuDocument v-model:index="ouvert" :documents="apercus" />
  </div>
</template>

<style scoped>
.docs-grille { grid-template-columns: minmax(0, 1fr); }
.titre-depot { display: flex; align-items: center; gap: 8px; margin: 0 0 var(--esp-3); font-size: 1.05rem; }
.zone-fichier { display: flex; align-items: center; gap: 14px; padding: 18px; border: 2px dashed #C6CFD8; border-radius: 12px; cursor: pointer; color: var(--texte-doux); margin-bottom: var(--esp-3); transition: border-color .15s, background .15s; }
.zone-fichier:hover, .zone-fichier.survol { border-color: var(--primaire); background: #F3FAFE; color: var(--primaire-fonce); }
.zone-fichier.rempli { border-style: solid; border-color: var(--primaire); color: var(--encre); }
.zone-fichier:focus-within { outline: 3px solid var(--primaire); outline-offset: 2px; }
.depot { display: grid; grid-template-columns: 1fr 1fr; gap: 0 var(--esp-3); align-items: start; }
.docs { list-style: none; padding: 0; margin: 0; display: grid; gap: 4px; }
.doc { display: grid; grid-template-columns: 64px minmax(0, 1fr) auto; gap: 16px; align-items: center; padding: 10px; border-radius: 12px; transition: background .15s; }
.doc:hover { background: var(--gris-fond); }
.vignette { width: 64px; height: 64px; border-radius: 12px; border: 1px solid var(--gris-bord); background: #fff; display: grid; place-items: center; align-content: center; gap: 2px; cursor: pointer; overflow: hidden; padding: 0; color: var(--primaire-fonce); transition: transform .15s, border-color .15s; }
.vignette:hover { transform: scale(1.05); border-color: var(--primaire); }
.vignette small { font-size: .62rem; font-weight: 700; letter-spacing: .04em; }
.vignette.pdf { background: #FBE4E5; color: var(--alerte); border-color: #F3C7CA; }
.vignette.texte { background: #E1F3FB; }
.vignette.autre { background: var(--gris-fond); color: var(--texte-doux); }
.vignette img { width: 100%; height: 100%; object-fit: cover; }
.doc-texte { display: grid; gap: 3px; min-width: 0; }
.doc-ligne { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.doc-titre { background: none; border: 0; padding: 0; font: inherit; font-weight: 700; color: var(--primaire-texte); text-align: left; cursor: pointer; word-break: break-word; }
.doc-titre:hover { text-decoration: underline; }
.description { color: var(--texte); font-size: .92rem; }
.meta { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.meta :deep(.avatar-photo) { font-size: 9px !important; }
.doc-actions { display: flex; gap: 6px; }
.petit { min-height: 40px; padding: 6px 14px; font-size: .88rem; display: inline-flex; align-items: center; gap: 6px; }
.doc-enter-active { transition: opacity .3s, transform .3s; }
.doc-enter-from { opacity: 0; transform: translateY(-8px); }
@media (max-width: 700px) {
  .depot { grid-template-columns: 1fr; }
  .doc { grid-template-columns: 52px minmax(0, 1fr); gap: 12px; }
  .vignette { width: 52px; height: 52px; }
  .doc-actions { grid-column: 2; }
  .libelle { display: none; }
}
</style>
