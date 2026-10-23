<script setup lang="ts">
// Devoirs de l'apprenant : à rendre, rendus, corrigés ; dépôt (ou remplacement) du rendu et lecture de la correction.
definePageMeta({ layout: 'ent-etudiant', middleware: 'auth', role: 'etudiant' })
useSeoMeta({ title: 'Devoirs' })
const api = useApi()
const { user } = useAuth()
const promo = computed(() => user.value?.promotions[0])
const { data: devoirs, refresh } = await useAsyncData('devoirs-etudiant', () => promo.value ? api<any[]>(`/api/promotions/${promo.value.id}/devoirs`) : Promise.resolve([]))

const onglet = ref<'a-rendre' | 'rendus' | 'corriges'>('a-rendre')
const groupes = computed(() => {
  const liste = devoirs.value || []
  return {
    'a-rendre': liste.filter((d) => !d.mon_rendu).sort((a, b) => a.echeance.localeCompare(b.echeance)),
    rendus: liste.filter((d) => d.mon_rendu && !d.mon_rendu.corrige_at),
    corriges: liste.filter((d) => d.mon_rendu?.corrige_at),
  }
})
const onglets = computed(() => [
  { code: 'a-rendre' as const, libelle: 'À rendre', n: groupes.value['a-rendre'].length },
  { code: 'rendus' as const, libelle: 'Rendus', n: groupes.value.rendus.length },
  { code: 'corriges' as const, libelle: 'Corrigés', n: groupes.value.corriges.length },
])

const dateHeure = (d: string) => new Date(d).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })
function restant(d: string) {
  const h = Math.round((new Date(d).getTime() - Date.now()) / 36e5)
  if (h < 0) return { texte: 'Échéance dépassée', classe: 'pastille-rouge' }
  if (h < 24) return { texte: `Plus que ${nombre(h || 1, 'heure')}`, classe: 'pastille-rouge' }
  const j = Math.round(h / 24)
  return { texte: `Dans ${nombre(j, 'jour')}`, classe: j <= 2 ? 'pastille-orange' : 'pastille-bleue' }
}
const noteFr = (n: number) => String(Number(n)).replace('.', ',')

// Dépôt
const ouvert = ref<number | null>(null)
const fichier = ref<File | null>(null); const commentaire = ref(''); const envoi = ref(false); const erreur = ref(''); const info = ref('')
function basculer(id: number) { ouvert.value = ouvert.value === id ? null : id; fichier.value = null; commentaire.value = ''; erreur.value = ''; info.value = '' }
async function rendre(d: any) {
  if (!fichier.value) { erreur.value = 'Choisissez un fichier.'; return }
  envoi.value = true; erreur.value = ''
  try {
    const fd = new FormData(); fd.append('fichier', fichier.value); if (commentaire.value) fd.append('commentaire', commentaire.value)
    await csrf(); await api(`/api/devoirs/${d.id}/rendu`, { method: 'POST', body: fd })
    info.value = d.mon_rendu ? 'Rendu remplacé.' : 'Rendu déposé : votre formateur est prévenu.'
    await refresh(); fichier.value = null
  } catch (e: any) { erreur.value = Object.values(erreursValidation(e))[0] || e?.data?.message || 'Dépôt impossible.' } finally { envoi.value = false }
}
const taille = (o: number) => o > 1e6 ? (o / 1e6).toFixed(1).replace('.', ',') + ' Mo' : Math.max(1, Math.ceil(o / 1e3)) + ' Ko'
</script>

<template>
  <div>
    <h1>Devoirs</h1>
    <p v-if="!promo" class="vide">Vous n'êtes inscrit dans aucune promotion.</p>
    <template v-else>
      <div class="onglets" role="tablist" aria-label="Devoirs">
        <button v-for="o in onglets" :key="o.code" role="tab" :aria-selected="onglet === o.code" :class="{ actif: onglet === o.code }" @click="onglet = o.code">
          {{ o.libelle }} <span class="compteur">{{ o.n }}</span>
        </button>
      </div>

      <p v-if="!groupes[onglet].length" class="vide carte">{{ onglet === 'a-rendre' ? 'Aucun devoir à rendre : tout est à jour.' : onglet === 'rendus' ? 'Aucun rendu en attente de correction.' : 'Aucun devoir corrigé pour l\'instant.' }}</p>
      <ul class="devoirs">
        <li v-for="d in groupes[onglet]" :key="d.id" class="carte devoir">
          <div class="tete-devoir">
            <div>
              <p class="module"><Icone nom="lecture_article" :taille="16" /> {{ d.module?.intitule }} · coefficient {{ String(d.coefficient).replace('.', ',') }}</p>
              <h2>{{ d.titre }}</h2>
              <p class="aide">À rendre avant le {{ dateHeure(d.echeance) }} · donné par {{ d.auteur }}</p>
            </div>
            <span v-if="onglet === 'a-rendre'" class="pastille" :class="restant(d.echeance).classe">{{ restant(d.echeance).texte }}</span>
            <span v-else-if="d.mon_rendu.corrige_at" class="note-finale">{{ noteFr(d.mon_rendu.note) }}<small>/20</small></span>
            <span v-else class="pastille" :class="d.mon_rendu.en_retard ? 'pastille-orange' : 'pastille-verte'">{{ d.mon_rendu.en_retard ? 'Rendu en retard' : 'Rendu à temps' }}</span>
          </div>
          <p v-if="d.consignes" class="consignes">{{ d.consignes }}</p>

          <div v-if="d.mon_rendu" class="mon-rendu">
            <a :href="d.mon_rendu.telechargement" class="fichier"><Icone nom="fichier" :taille="18" /> {{ d.mon_rendu.nom_original }} <small>({{ taille(d.mon_rendu.taille) }}, le {{ new Date(d.mon_rendu.depose_at).toLocaleDateString('fr-FR') }})</small></a>
            <blockquote v-if="d.mon_rendu.corrige_at" class="correction">
              <strong>Correction</strong>
              <p>{{ d.mon_rendu.commentaire || 'Pas de commentaire.' }}</p>
              <NuxtLink to="/ent/etudiant/notes" class="lien-fleche">Voir dans mes notes <Icone nom="suivant" :taille="16" /></NuxtLink>
            </blockquote>
          </div>

          <template v-if="!d.mon_rendu?.corrige_at">
            <button class="btn btn-secondaire" :aria-expanded="ouvert === d.id" @click="basculer(d.id)"><Icone nom="deposer" :taille="18" /> {{ d.mon_rendu ? 'Remplacer mon rendu' : 'Déposer mon travail' }}</button>
            <form v-if="ouvert === d.id" class="depot" @submit.prevent="rendre(d)">
              <p v-if="erreur" class="message message-erreur" role="alert">{{ erreur }}</p>
              <p v-if="info" class="message message-succes" role="status">{{ info }}</p>
              <div class="champ"><label :for="`f-${d.id}`">Fichier (10 Mo max : PDF, Office, ZIP, images, texte)</label><input :id="`f-${d.id}`" type="file" required @change="fichier = ($event.target as HTMLInputElement).files?.[0] || null"></div>
              <div class="champ"><label :for="`c-${d.id}`">Message pour le formateur <span class="aide">(facultatif)</span></label><input :id="`c-${d.id}`" v-model="commentaire" maxlength="500"></div>
              <button class="btn btn-primaire" :disabled="envoi"><span v-if="envoi" class="chargement" aria-hidden="true" />{{ d.mon_rendu ? 'Remplacer' : 'Déposer' }}</button>
            </form>
          </template>
        </li>
      </ul>
    </template>
  </div>
</template>

<style scoped>
.onglets { display: inline-flex; gap: 4px; background: #fff; border: 1px solid var(--gris-bord); border-radius: 999px; padding: 4px; margin-bottom: var(--esp-4); flex-wrap: wrap; }
.onglets button { border: 0; background: none; font: inherit; padding: 8px 16px; border-radius: 999px; cursor: pointer; color: var(--texte); display: inline-flex; align-items: center; gap: 8px; }
.onglets button.actif { background: var(--primaire-fonce); color: #fff; font-weight: 600; }
.compteur { min-width: 22px; height: 22px; border-radius: 999px; background: var(--gris-fond); color: var(--texte-doux); font-size: .75rem; font-weight: 700; display: grid; place-items: center; padding: 0 6px; }
.actif .compteur { background: rgba(255, 255, 255, .2); color: #fff; }
.devoirs { list-style: none; padding: 0; margin: 0; display: grid; gap: var(--esp-3); }
.devoir { display: grid; gap: 12px; }
.tete-devoir { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
.tete-devoir h2 { margin: 2px 0 4px; font-size: 1.2rem; }
.tete-devoir .aide { margin: 0; }
.module { margin: 0; display: flex; align-items: center; gap: 6px; color: var(--primaire-fonce); font-weight: 600; font-size: .88rem; }
.consignes { white-space: pre-line; margin: 0; padding: 12px 14px; background: var(--gris-fond); border-radius: 10px; }
.mon-rendu { display: grid; gap: 10px; }
.fichier { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; }
.fichier small { color: var(--texte-doux); font-weight: 400; }
.correction { margin: 0; padding: 12px 16px; border-left: 4px solid var(--succes); background: #F1FAF4; border-radius: 0 10px 10px 0; }
.correction p { margin: 4px 0 8px; white-space: pre-line; }
.note-finale { font-family: var(--police-titre); font-size: 2rem; font-weight: 800; color: var(--succes); line-height: 1; white-space: nowrap; }
.note-finale small { font-size: 1rem; color: var(--texte-doux); }
.depot { display: grid; gap: 0; padding: 14px; border: 1px dashed #C6CFD8; border-radius: 12px; }
.btn { display: inline-flex; align-items: center; gap: 8px; justify-self: start; }
.lien-fleche { display: inline-flex; align-items: center; gap: 6px; }
@media (max-width: 600px) { .tete-devoir { flex-direction: column; } }
</style>
