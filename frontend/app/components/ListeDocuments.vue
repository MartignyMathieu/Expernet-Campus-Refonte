<script setup lang="ts">
const props = defineProps<{ promotionId: number; peutDeposer?: boolean }>()
const api = useApi()
const { data, refresh } = await useAsyncData(`docs-${props.promotionId}`, () => api<any[]>(`/api/promotions/${props.promotionId}/documents`))
const titre = ref(''); const fichier = ref<File | null>(null); const erreur = ref(''); const ok = ref(false)
const taille = (o: number) => o > 1e6 ? (o / 1e6).toFixed(1) + ' Mo' : Math.ceil(o / 1e3) + ' Ko'
async function deposer() {
  erreur.value = ''; ok.value = false
  if (!fichier.value) { erreur.value = 'Choisissez un fichier.'; return }
  const fd = new FormData(); fd.append('titre', titre.value); fd.append('fichier', fichier.value)
  try { await csrf(); await api(`/api/promotions/${props.promotionId}/documents`, { method: 'POST', body: fd }); titre.value = ''; ok.value = true; await refresh() }
  catch (e: any) { erreur.value = Object.values(erreursValidation(e))[0] || 'Dépôt impossible.' }
}
</script>

<template>
  <div class="grille docs-grille">
    <form v-if="peutDeposer" class="carte" @submit.prevent="deposer">
      <h2>Déposer un support</h2>
      <p v-if="erreur" class="message message-erreur" role="alert">{{ erreur }}</p>
      <p v-if="ok" class="message message-succes" role="status">Document publié pour la promotion.</p>
      <div class="champ"><label for="titre">Titre</label><input id="titre" v-model="titre" required maxlength="150"></div>
      <div class="champ"><label for="fichier">Fichier (10 Mo max : PDF, Office, ZIP, images)</label><input id="fichier" type="file" required @change="fichier = ($event.target as HTMLInputElement).files?.[0] || null"></div>
      <button class="btn btn-primaire">Publier</button>
    </form>
    <div class="carte">
      <h2>Ressources de la promotion</h2>
      <p v-if="!data?.length" class="vide">Aucun document pour l'instant.</p>
      <ul class="docs">
        <li v-for="d in data" :key="d.id">
          <a :href="`/api/documents/${d.id}/telechargement`">{{ d.titre }}</a>
          <span class="aide">{{ d.nom_original }} · {{ taille(d.taille) }} · {{ d.auteur.prenom }} {{ d.auteur.nom }} · {{ new Date(d.created_at).toLocaleDateString('fr-FR') }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.docs-grille { grid-template-columns: minmax(0, 1fr); }
.docs li a { word-break: break-word; }
.docs { list-style: none; padding: 0; display: grid; gap: 10px; }
.docs li { display: grid; gap: 2px; border-bottom: 1px solid var(--gris-bord); padding-bottom: 8px; }
</style>
