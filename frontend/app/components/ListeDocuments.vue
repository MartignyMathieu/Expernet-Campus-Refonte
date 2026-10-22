<script setup lang="ts">
const props = defineProps<{ promotionId: number; peutDeposer?: boolean }>()
const api = useApi()
const { data, refresh } = await useAsyncData(`docs-${props.promotionId}`, () => api<any[]>(`/api/promotions/${props.promotionId}/documents`))
const titre = ref(''); const fichier = ref<File | null>(null); const erreur = ref(''); const ok = ref(false); const envoi = ref(false)
const champFichier = ref<HTMLInputElement | null>(null)
const taille = (o: number) => o > 1e6 ? (o / 1e6).toFixed(1).replace('.', ',') + ' Mo' : Math.max(1, Math.ceil(o / 1e3)) + ' Ko'
async function deposer() {
  erreur.value = ''; ok.value = false
  if (!fichier.value) { erreur.value = 'Choisissez un fichier.'; return }
  const fd = new FormData(); fd.append('titre', titre.value); fd.append('fichier', fichier.value)
  envoi.value = true
  try {
    await csrf(); await api(`/api/promotions/${props.promotionId}/documents`, { method: 'POST', body: fd })
    titre.value = ''; fichier.value = null; if (champFichier.value) champFichier.value.value = ''
    ok.value = true; await refresh()
  } catch (e: any) { erreur.value = Object.values(erreursValidation(e))[0] || 'Dépôt impossible.' } finally { envoi.value = false }
}

// Aperçu dans le portail
const apercus = computed(() => (data.value || []).map((d: any) => ({
  id: d.id, titre: d.titre, nom: d.nom_original, apercu: d.apercu,
  url: `/api/documents/${d.id}/apercu`, telechargement: `/api/documents/${d.id}/telechargement`,
  meta: `${taille(d.taille)} · ${d.auteur.prenom} ${d.auteur.nom}`,
})))
const ouvert = ref<number | null>(null)

const typeDe = (d: any) => d.apercu === 'pdf' ? { icone: 'pdf', libelle: 'PDF', classe: 'pdf' }
  : d.apercu === 'image' ? { icone: 'image', libelle: 'Image', classe: 'image' }
  : d.apercu === 'texte' ? { icone: 'fichier', libelle: 'Texte', classe: 'texte' }
  : { icone: 'fichier', libelle: (d.nom_original.split('.').pop() || 'Fichier').toUpperCase(), classe: 'autre' }
</script>

<template>
  <div class="grille docs-grille">
    <form v-if="peutDeposer" class="carte" @submit.prevent="deposer">
      <h2>Déposer un support</h2>
      <p v-if="erreur" class="message message-erreur" role="alert">{{ erreur }}</p>
      <p v-if="ok" class="message message-succes" role="status">Document publié pour la promotion.</p>
      <div class="depot">
        <div class="champ"><label for="titre">Titre</label><input id="titre" v-model="titre" required maxlength="150"></div>
        <div class="champ"><label for="fichier">Fichier (10 Mo max : PDF, Office, ZIP, images, texte)</label><input id="fichier" ref="champFichier" type="file" required @change="fichier = ($event.target as HTMLInputElement).files?.[0] || null"></div>
      </div>
      <button class="btn btn-primaire" :disabled="envoi"><span v-if="envoi" class="chargement" aria-hidden="true" />Publier</button>
    </form>
    <div class="carte">
      <h2>Ressources de la promotion</h2>
      <p v-if="!data?.length" class="vide">Aucun document pour l'instant.</p>
      <TransitionGroup tag="ul" name="doc" class="docs">
        <li v-for="(d, i) in data" :key="d.id" class="doc">
          <button class="vignette" :class="typeDe(d).classe" :aria-label="`Aperçu : ${d.titre}`" @click="ouvert = i">
            <img v-if="d.apercu === 'image'" :src="`/api/documents/${d.id}/apercu`" alt="" loading="lazy">
            <template v-else><Icone :nom="typeDe(d).icone" :taille="26" /><small>{{ typeDe(d).libelle }}</small></template>
          </button>
          <div class="doc-texte">
            <button class="doc-titre" @click="ouvert = i">{{ d.titre }}</button>
            <span class="aide">{{ d.nom_original }} · {{ taille(d.taille) }} · {{ d.auteur.prenom }} {{ d.auteur.nom }} · {{ new Date(d.created_at).toLocaleDateString('fr-FR') }}</span>
          </div>
          <div class="doc-actions">
            <button v-if="d.apercu" class="btn btn-secondaire petit" @click="ouvert = i"><Icone nom="oeil" :taille="18" /><span class="libelle">Aperçu</span></button>
            <a :href="`/api/documents/${d.id}/telechargement`" class="btn btn-secondaire petit" :aria-label="`Télécharger ${d.titre}`"><Icone nom="telecharger" :taille="18" /><span class="libelle">Télécharger</span></a>
          </div>
        </li>
      </TransitionGroup>
    </div>
    <ApercuDocument v-model:index="ouvert" :documents="apercus" />
  </div>
</template>

<style scoped>
.docs-grille { grid-template-columns: minmax(0, 1fr); }
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
.doc-texte { display: grid; gap: 2px; min-width: 0; }
.doc-titre { background: none; border: 0; padding: 0; font: inherit; font-weight: 700; color: var(--primaire-texte); text-align: left; cursor: pointer; word-break: break-word; }
.doc-titre:hover { text-decoration: underline; }
.doc-texte .aide { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.doc-actions { display: flex; gap: 6px; }
.petit { min-height: 40px; padding: 6px 14px; font-size: .88rem; }
.doc-enter-active { transition: opacity .3s, transform .3s; }
.doc-enter-from { opacity: 0; transform: translateY(-8px); }
@media (max-width: 700px) {
  .depot { grid-template-columns: 1fr; }
  .doc { grid-template-columns: 52px minmax(0, 1fr); gap: 12px; }
  .vignette { width: 52px; height: 52px; }
  .doc-actions { grid-column: 2; }
  .libelle { display: none; }
  .doc-texte .aide { white-space: normal; }
}
</style>
