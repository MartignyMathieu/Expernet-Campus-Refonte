<!--
  Aperçu d'un document sans quitter le portail : PDF (lecteur du navigateur), image, texte brut.
  Navigation au clavier : Échap pour fermer, ← → pour passer d'un document à l'autre.
-->
<script setup lang="ts">
export interface DocumentApercu { id: number | string; titre: string; nom: string; apercu: 'pdf' | 'image' | 'texte' | null; url: string; telechargement: string; meta?: string }
const props = defineProps<{ documents: DocumentApercu[]; index: number | null }>()
const emit = defineEmits<{ 'update:index': [i: number | null] }>()

const dialogue = ref<HTMLDialogElement | null>(null)
const doc = computed(() => props.index === null ? null : props.documents[props.index] || null)
const texte = ref(''); const chargement = ref(false); const erreur = ref('')

async function charger() {
  texte.value = ''; erreur.value = ''
  if (doc.value?.apercu !== 'texte') return
  chargement.value = true
  try {
    const r = await fetch(doc.value.url, { credentials: 'include' })
    if (!r.ok) throw new Error()
    texte.value = await r.text()
  } catch { erreur.value = 'Impossible d\'afficher ce document.' } finally { chargement.value = false }
}

watch(() => props.index, async (i) => {
  if (i === null) { dialogue.value?.open && dialogue.value.close(); return }
  if (!dialogue.value?.open) dialogue.value?.showModal()
  await charger()
})
function fermer() { emit('update:index', null) }
function aller(pas: number) {
  if (props.index === null || props.documents.length < 2) return
  emit('update:index', (props.index + pas + props.documents.length) % props.documents.length)
}
function touche(e: KeyboardEvent) {
  if (e.key === 'ArrowRight') aller(1)
  if (e.key === 'ArrowLeft') aller(-1)
}
</script>

<template>
  <dialog ref="dialogue" class="apercu" aria-labelledby="titre-apercu" @close="fermer" @keydown="touche" @click.self="fermer">
    <div v-if="doc" class="cadre">
      <header class="tete">
        <div class="titre">
          <h2 id="titre-apercu">{{ doc.titre }}</h2>
          <p>{{ doc.nom }}<template v-if="doc.meta"> · {{ doc.meta }}</template></p>
        </div>
        <span v-if="documents.length > 1" class="position">{{ (index ?? 0) + 1 }} / {{ documents.length }}</span>
        <a :href="doc.telechargement" class="btn btn-secondaire petit"><Icone nom="telecharger" :taille="18" /><span class="libelle">Télécharger</span></a>
        <button class="bouton-icone" aria-label="Fermer l'aperçu" @click="fermer"><Icone nom="fermer" :taille="20" /></button>
      </header>

      <Transition name="vue" mode="out-in">
        <div :key="doc.id" class="corps" :class="doc.apercu || 'aucun'">
          <iframe v-if="doc.apercu === 'pdf'" :src="doc.url" :title="`Aperçu : ${doc.titre}`" />
          <img v-else-if="doc.apercu === 'image'" :src="doc.url" :alt="doc.titre">
          <template v-else-if="doc.apercu === 'texte'">
            <p v-if="chargement" class="etat"><span class="chargement" aria-hidden="true" /> Chargement…</p>
            <p v-else-if="erreur" class="etat">{{ erreur }}</p>
            <pre v-else>{{ texte }}</pre>
          </template>
          <div v-else class="etat aucun-apercu">
            <span class="grande-icone"><Icone nom="fichier" :taille="40" poids="duotone" /></span>
            <p><strong>Aperçu indisponible pour ce format.</strong><br>Téléchargez le document pour l'ouvrir avec l'application adaptée.</p>
            <a :href="doc.telechargement" class="btn btn-primaire"><Icone nom="telecharger" :taille="18" />Télécharger</a>
          </div>
        </div>
      </Transition>

      <template v-if="documents.length > 1">
        <button class="nav precedent" aria-label="Document précédent" @click="aller(-1)"><Icone nom="retour" :taille="22" /></button>
        <button class="nav suivant" aria-label="Document suivant" @click="aller(1)"><Icone nom="suivant" :taille="22" /></button>
      </template>
    </div>
  </dialog>
</template>

<style scoped>
.apercu { border: 0; padding: 0; width: min(1100px, calc(100vw - 32px)); height: min(860px, calc(100vh - 32px)); max-width: none; max-height: none; border-radius: 18px; box-shadow: var(--ombre-forte); overflow: hidden; background: #fff; }
.apercu:focus-visible { outline: none; }
.apercu[open] { animation: ouverture .22s cubic-bezier(.2,.7,.2,1); }
.apercu::backdrop { background: rgba(8,32,52,.6); animation: fondu .2s ease; }
@keyframes ouverture { from { opacity: 0; transform: translateY(14px) scale(.98); } to { opacity: 1; transform: none; } }
.cadre { position: relative; height: 100%; display: flex; flex-direction: column; }
.tete { display: flex; align-items: center; gap: 12px; padding: 12px 14px 12px 22px; border-bottom: 1px solid var(--gris-bord); }
.titre { flex: 1; min-width: 0; }
.titre h2 { margin: 0; font-size: 1.1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.titre p { margin: 0; color: var(--texte-doux); font-size: .85rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.position { color: var(--texte-doux); font-size: .85rem; font-variant-numeric: tabular-nums; }
.petit { min-height: 40px; padding: 6px 14px; font-size: .9rem; }
.bouton-icone { width: 40px; height: 40px; border-radius: 12px; border: 1px solid var(--gris-bord); background: #fff; display: grid; place-items: center; cursor: pointer; color: var(--texte); }
.bouton-icone:hover { background: var(--gris-fond); }
.corps { flex: 1; min-height: 0; display: grid; place-items: center; background: var(--gris-fond); overflow: auto; }
.corps.pdf { background: #525659; }
.corps iframe { width: 100%; height: 100%; border: 0; }
.corps img { max-width: 100%; max-height: 100%; object-fit: contain; padding: 16px; }
.corps pre { place-self: stretch; margin: 0; padding: 24px 28px; background: #fff; font: .92rem/1.6 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; white-space: pre-wrap; word-break: break-word; color: var(--encre); }
.etat { display: flex; align-items: center; gap: 10px; color: var(--texte-doux); }
.aucun-apercu { flex-direction: column; text-align: center; max-width: 380px; padding: 24px; }
.grande-icone { width: 80px; height: 80px; border-radius: 22px; display: grid; place-items: center; background: #E1F3FB; color: var(--primaire-fonce); }
.nav { position: absolute; top: 50%; width: 44px; height: 44px; border-radius: 50%; border: 1px solid var(--gris-bord); background: #fff; display: grid; place-items: center; cursor: pointer; color: var(--encre); box-shadow: var(--ombre); transition: transform .15s; }
.nav:hover { transform: scale(1.08); }
.precedent { left: 12px; }
.suivant { right: 12px; }
.vue-enter-active, .vue-leave-active { transition: opacity .18s ease, transform .18s ease; }
.vue-enter-from { opacity: 0; transform: translateX(14px); }
.vue-leave-to { opacity: 0; transform: translateX(-14px); }
@media (max-width: 640px) {
  .apercu { width: 100vw; height: 100dvh; border-radius: 0; }
  .libelle, .position { display: none; }
  .tete { padding: 10px 10px 10px 16px; }
  .nav { top: auto; bottom: 14px; }
}
</style>
