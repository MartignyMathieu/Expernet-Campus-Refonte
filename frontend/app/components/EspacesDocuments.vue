<!--
  Espace documentaire d'une promotion : navigation par espace (cours, pédagogie, TP, examens, administratif,
  stage, rendus), recherche, dépôt selon les droits de l'utilisateur dans l'espace choisi.
-->
<script setup lang="ts">
const props = defineProps<{ promotionId: number; espaceInitial?: string }>()
const api = useApi()
const route = useRoute()
const router = useRouter()

const [{ data: espaces }, { data: documents, refresh }, { data: modules }] = await Promise.all([
  useAsyncData('docs-espaces', () => api<any[]>('/api/documents/espaces')),
  useAsyncData(`docs-${props.promotionId}`, () => api<any[]>(`/api/promotions/${props.promotionId}/documents`)),
  useAsyncData(`modules-${props.promotionId}`, () => api<any[]>(`/api/promotions/${props.promotionId}/modules`).catch(() => [])),
])

// Filtre par module : ouvert depuis une séance du planning (?module=ID), modifiable ici
const moduleActif = ref<number | null>(route.query.module ? Number(route.query.module) : null)
const libelleModule = computed(() => modules.value?.find((m) => m.id === moduleActif.value)?.intitule || '')
watch(moduleActif, (id) => router.replace({ query: { ...route.query, module: id || undefined } }))

const codeActif = ref<string>((route.query.espace as string) || props.espaceInitial || 'tous')
const espaceActif = computed(() => espaces.value?.find((e) => e.code === codeActif.value) || null)
watch(codeActif, (code) => router.replace({ query: { ...route.query, espace: code === 'tous' ? undefined : code } }))

const recherche = ref('')
const normaliser = (t: string) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const duModule = computed(() => (documents.value || []).filter((d) => !moduleActif.value || d.module?.id === moduleActif.value))
const compte = (code: string) => duModule.value.filter((d) => d.espace === code).length
const visibles = computed(() => {
  const q = normaliser(recherche.value.trim())
  return duModule.value
    .filter((d) => codeActif.value === 'tous' || d.espace === codeActif.value)
    .filter((d) => !q || normaliser(`${d.titre} ${d.description || ''} ${d.nom_original} ${d.auteur.prenom} ${d.auteur.nom}`).includes(q))
})
const libelleEspace = (code: string) => espaces.value?.find((e) => e.code === code)?.libelle || code
</script>

<template>
  <div class="espaces">
    <nav class="carte onglets" aria-label="Espaces documentaires">
      <button class="onglet" :class="{ actif: codeActif === 'tous' }" :aria-current="codeActif === 'tous' ? 'true' : undefined" @click="codeActif = 'tous'">
        <span class="onglet-icone"><Icone nom="documents" :taille="20" /></span>
        <span class="onglet-libelle">Tous les documents</span>
        <span class="compteur">{{ duModule.length }}</span>
      </button>
      <button v-for="e in espaces" :key="e.code" class="onglet" :class="{ actif: codeActif === e.code }" :aria-current="codeActif === e.code ? 'true' : undefined" @click="codeActif = e.code">
        <span class="onglet-icone" :class="'e-' + e.code"><Icone :nom="e.icone" :taille="20" /></span>
        <span class="onglet-libelle">{{ e.libelle }}</span>
        <span class="compteur">{{ compte(e.code) }}</span>
      </button>
    </nav>

    <div class="panneau">
      <header class="entete-espace">
        <div>
          <h2>{{ espaceActif?.libelle || 'Tous les documents' }}</h2>
          <p class="aide">{{ espaceActif?.description || 'L\'ensemble des ressources de la promotion, tous espaces confondus.' }}</p>
        </div>
        <div v-if="modules?.length" class="champ filtre-module">
          <label for="filtre-module" class="sr-only">Module</label>
          <select id="filtre-module" v-model="moduleActif">
            <option :value="null">Tous les modules</option>
            <option v-for="m in modules" :key="m.id" :value="m.id">{{ m.intitule }}</option>
          </select>
        </div>
        <div class="champ recherche">
          <label for="recherche-docs" class="sr-only">Rechercher un document</label>
          <Icone nom="recherche" :taille="18" class="loupe" />
          <input id="recherche-docs" v-model="recherche" type="search" placeholder="Rechercher un document…">
        </div>
      </header>

      <p v-if="moduleActif && libelleModule" class="bandeau-module">
        <Icone nom="lecture_article" :taille="18" /> Documents du module <strong>{{ libelleModule }}</strong>
        <button type="button" class="retirer" @click="moduleActif = null">Voir tous les modules <Icone nom="fermer" :taille="14" /></button>
      </p>
      <ListeDocuments
        :key="codeActif"
        :promotion-id="promotionId"
        :documents="visibles"
        :espace="espaceActif"
        :afficher-espace="codeActif === 'tous'"
        :libelle-espace="libelleEspace"
        :recherche="recherche"
        :modules="modules || []"
        :module-defaut="moduleActif"
        @maj="refresh"
      />
    </div>
  </div>
</template>

<style scoped>
.espaces { display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: var(--esp-4); align-items: start; }
.onglets { display: grid; gap: 2px; padding: 10px; position: sticky; top: 90px; }
.onglet { display: grid; grid-template-columns: 36px minmax(0, 1fr) auto; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 10px; background: none; border: 0; font: inherit; color: var(--texte); cursor: pointer; text-align: left; transition: background .15s; }
.onglet:hover { background: var(--gris-fond); }
.onglet.actif { background: #E1F3FB; color: var(--primaire-fonce); font-weight: 700; }
.onglet-icone { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; background: var(--gris-fond); color: var(--primaire-fonce); }
.onglet-icone.e-pedagogie { background: #EDE7FB; color: #5B3FA8; }
.onglet-icone.e-exercices { background: #E4F5EA; color: #1E7A43; }
.onglet-icone.e-examens { background: #FBE4E5; color: var(--alerte); }
.onglet-icone.e-administratif { background: #EEF1F4; color: var(--marine); }
.onglet-icone.e-stage { background: #FFF1D6; color: #8A5A00; }
.onglet-icone.e-rendus { background: #FFE9D9; color: var(--orange-fonce); }
.onglet-libelle { min-width: 0; overflow-wrap: anywhere; }
.compteur { min-width: 26px; height: 22px; padding: 0 7px; border-radius: 999px; background: var(--gris-fond); font-size: .75rem; font-weight: 700; display: grid; place-items: center; color: var(--texte-doux); }
.actif .compteur { background: #fff; color: var(--primaire-fonce); }
.panneau { min-width: 0; display: grid; gap: var(--esp-3); }
.entete-espace { display: flex; justify-content: space-between; align-items: flex-end; gap: var(--esp-3); flex-wrap: wrap; }
.entete-espace h2 { margin: 0 0 4px; }
.entete-espace .aide { margin: 0; }
.recherche { position: relative; margin: 0; width: min(320px, 100%); }
.entete-espace > .champ.filtre-module { margin: 0; width: min(260px, 100%); }
.entete-espace { flex-wrap: wrap; }
.bandeau-module { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin: 0; padding: 10px 14px; border-radius: 10px; background: #E1F3FB; color: var(--primaire-fonce); }
.retirer { margin-left: auto; display: inline-flex; align-items: center; gap: 4px; background: #fff; border: 1px solid #B9DDF0; border-radius: 999px; padding: 4px 12px; font: inherit; font-size: .85rem; color: var(--primaire-fonce); cursor: pointer; }
.recherche input { padding-left: 40px; }
.loupe { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--texte-doux); pointer-events: none; }
@media (max-width: 1000px) {
  .espaces { grid-template-columns: minmax(0, 1fr); }
  .onglets { position: static; display: flex; overflow-x: auto; scrollbar-width: thin; padding: 8px; }
  .onglet { grid-template-columns: auto auto auto; flex-shrink: 0; white-space: nowrap; }
  .onglet-icone { width: 30px; height: 30px; }
}
</style>
