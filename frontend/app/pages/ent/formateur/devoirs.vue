<script setup lang="ts">
// Devoirs du formateur : publication, suivi des rendus par apprenant, correction (note reportée dans les notes).
definePageMeta({ layout: 'ent-formateur', middleware: 'auth', role: 'formateur' })
useSeoMeta({ title: 'Devoirs' })
const api = useApi()
const route = useRoute()
const router = useRouter()
const { user } = useAuth()
const promo = ref<number | undefined>(user.value?.promotions[0]?.id)

const { data: devoirs, refresh } = await useAsyncData('devoirs-formateur', () => promo.value ? api<any[]>(`/api/promotions/${promo.value}/devoirs`) : Promise.resolve([]), { watch: [promo] })
const { data: modules } = await useAsyncData('modules-devoirs', () => promo.value ? api<any[]>(`/api/promotions/${promo.value}/modules`) : Promise.resolve([]), { watch: [promo] })

// ---------- Publication ----------
const creation = ref(false)
const demain = () => { const d = new Date(Date.now() + 7 * 864e5); d.setHours(18, 0, 0, 0); return new Date(d.getTime() - d.getTimezoneOffset() * 6e4).toISOString().slice(0, 16) }
const form = reactive({ titre: '', consignes: '', module_id: null as number | null, echeance: demain(), coefficient: 1 })
const erreurs = ref<Record<string, string>>({}); const info = ref(''); const envoi = ref(false)
async function publier() {
  erreurs.value = {}; info.value = ''; envoi.value = true
  try {
    await csrf()
    await api(`/api/promotions/${promo.value}/devoirs`, { method: 'POST', body: { ...form, echeance: new Date(form.echeance).toISOString() } })
    info.value = 'Devoir publié : les apprenants sont prévenus (ENT et e-mail).'
    Object.assign(form, { titre: '', consignes: '', echeance: demain(), coefficient: 1 }); creation.value = false
    await refresh()
  } catch (e: any) { erreurs.value = erreursValidation(e); if (!Object.keys(erreurs.value).length) erreurs.value = { general: e?.data?.message || 'Publication impossible.' } } finally { envoi.value = false }
}

// ---------- Détail et correction ----------
const actif = ref<any>(null); const chargement = ref(false)
const saisies = reactive<Record<number, { note: string; commentaire: string; etat: string }>>({})
async function ouvrir(id: number) {
  chargement.value = true
  try {
    actif.value = await api(`/api/devoirs/${id}`)
    for (const a of actif.value.apprenants) if (a.rendu) saisies[a.rendu.id] = { note: a.rendu.note != null ? String(a.rendu.note) : '', commentaire: a.rendu.commentaire || '', etat: '' }
    router.replace({ query: { ...route.query, devoir: id } })
    nextTick(() => document.getElementById('detail-devoir')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))
  } finally { chargement.value = false }
}
async function corriger(rendu: any) {
  const s = saisies[rendu.id]
  s.etat = 'envoi'
  try {
    await csrf()
    await api(`/api/rendus/${rendu.id}/correction`, { method: 'POST', body: { note: Number(String(s.note).replace(',', '.')), commentaire: s.commentaire || null } })
    s.etat = 'ok'; await Promise.all([ouvrir(actif.value.id), refresh()])
  } catch (e: any) { s.etat = Object.values(erreursValidation(e))[0] || e?.data?.message || 'Erreur' }
}
onMounted(() => { if (route.query.devoir) ouvrir(Number(route.query.devoir)) })

const dateHeure = (d: string) => new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
const avancement = (d: any) => d.effectif ? Math.round(d.nb_rendus / d.effectif * 100) : 0
</script>

<template>
  <div>
    <div class="titre-page">
      <h1>Devoirs</h1>
      <div class="actions">
        <div v-if="(user?.promotions.length || 0) > 1" class="champ selecteur">
          <label for="promo" class="sr-only">Promotion</label>
          <select id="promo" v-model="promo"><option v-for="p in user?.promotions" :key="p.id" :value="p.id">{{ p.libelle }}</option></select>
        </div>
        <button class="btn btn-primaire" :aria-expanded="creation" @click="creation = !creation"><Icone nom="ajouter" :taille="18" /> Nouveau devoir</button>
      </div>
    </div>
    <p v-if="info" class="message message-succes" role="status">{{ info }}</p>

    <form v-if="creation" class="carte creation" @submit.prevent="publier">
      <h2>Publier un devoir</h2>
      <p v-if="erreurs.general" class="message message-erreur" role="alert">{{ erreurs.general }}</p>
      <div class="grille-form">
        <div class="champ large"><label for="d-titre">Titre</label><input id="d-titre" v-model="form.titre" required maxlength="150" placeholder="Ex. : TP 4 - Authentification" :aria-invalid="!!erreurs.titre"><p v-if="erreurs.titre" class="erreur">{{ erreurs.titre }}</p></div>
        <div class="champ"><label for="d-module">Module</label>
          <select id="d-module" v-model="form.module_id" required :aria-invalid="!!erreurs.module_id"><option :value="null" disabled>Choisir un module</option><option v-for="m in modules" :key="m.id" :value="m.id">{{ m.intitule }}</option></select>
          <p v-if="erreurs.module_id" class="erreur">{{ erreurs.module_id }}</p></div>
        <div class="champ"><label for="d-echeance">À rendre avant le</label><input id="d-echeance" v-model="form.echeance" type="datetime-local" required :aria-invalid="!!erreurs.echeance"><p v-if="erreurs.echeance" class="erreur">{{ erreurs.echeance }}</p></div>
        <div class="champ"><label for="d-coef">Coefficient</label><input id="d-coef" v-model.number="form.coefficient" type="number" min="0.5" max="10" step="0.5"></div>
        <div class="champ large"><label for="d-consignes">Consignes</label><textarea id="d-consignes" v-model="form.consignes" rows="4" maxlength="5000" placeholder="Attendus, format du rendu, critères d'évaluation…" /></div>
      </div>
      <div class="boutons">
        <button class="btn btn-primaire" :disabled="envoi"><span v-if="envoi" class="chargement" aria-hidden="true" />Publier et prévenir les apprenants</button>
        <button type="button" class="btn btn-secondaire" @click="creation = false">Annuler</button>
      </div>
    </form>

    <p v-if="!devoirs?.length" class="vide carte">Aucun devoir publié pour cette promotion.</p>
    <ul class="liste">
      <li v-for="d in devoirs" :key="d.id">
        <button class="carte ligne" :class="{ selection: actif?.id === d.id }" @click="ouvrir(d.id)">
          <span class="infos"><strong>{{ d.titre }}</strong><span class="aide">{{ d.module?.intitule }} · échéance {{ dateHeure(d.echeance) }}</span></span>
          <span class="progression" :aria-label="`${d.nb_rendus} rendus sur ${d.effectif}`">
            <span class="barre"><span :style="{ width: avancement(d) + '%' }" /></span>
            <small>{{ d.nb_rendus }}/{{ d.effectif }} {{ pluriel(d.nb_rendus, 'rendu') }} · {{ nombre(d.nb_corriges, 'corrigé') }}</small>
          </span>
          <span class="pastille" :class="d.echu ? '' : 'pastille-bleue'">{{ d.echu ? 'Échu' : 'En cours' }}</span>
        </button>
      </li>
    </ul>

    <section v-if="actif" id="detail-devoir" class="carte detail" aria-labelledby="t-detail">
      <div class="tete-detail">
        <div><h2 id="t-detail">{{ actif.titre }}</h2><p class="aide">{{ actif.module?.intitule }} · coefficient {{ String(actif.coefficient).replace('.', ',') }} · échéance {{ dateHeure(actif.echeance) }}</p></div>
        <button class="fermer" aria-label="Fermer le détail" @click="actif = null; router.replace({ query: {} })"><Icone nom="fermer" :taille="18" /></button>
      </div>
      <p v-if="actif.consignes" class="consignes">{{ actif.consignes }}</p>
      <div class="tableau-scroll">
        <table class="tableau">
          <caption class="sr-only">Rendus et correction</caption>
          <thead><tr><th scope="col">Apprenant</th><th scope="col">Rendu</th><th scope="col">Note /20</th><th scope="col">Commentaire</th><th scope="col"><span class="sr-only">Action</span></th></tr></thead>
          <tbody>
            <tr v-for="a in actif.apprenants" :key="a.id">
              <td><span class="personne"><Avatar :url="a.avatar_url" :nom="a.nom" :taille="30" />{{ a.nom }}</span></td>
              <td>
                <template v-if="a.rendu">
                  <a :href="a.rendu.telechargement" class="fichier"><Icone nom="telecharger" :taille="16" /> {{ a.rendu.nom_original }}</a>
                  <span class="pastille" :class="a.rendu.en_retard ? 'pastille-orange' : 'pastille-verte'">{{ a.rendu.en_retard ? 'En retard' : 'À temps' }}</span>
                  <p v-if="a.rendu.commentaire_apprenant" class="aide">« {{ a.rendu.commentaire_apprenant }} »</p>
                </template>
                <span v-else class="aide">Pas encore rendu</span>
              </td>
              <template v-if="a.rendu && actif.peut_corriger">
                <td><label class="sr-only" :for="`n-${a.rendu.id}`">Note de {{ a.nom }}</label><input :id="`n-${a.rendu.id}`" v-model="saisies[a.rendu.id].note" class="note" inputmode="decimal" placeholder="-"></td>
                <td><label class="sr-only" :for="`c-${a.rendu.id}`">Commentaire pour {{ a.nom }}</label><input :id="`c-${a.rendu.id}`" v-model="saisies[a.rendu.id].commentaire" class="commentaire" maxlength="1000"></td>
                <td>
                  <button class="btn btn-secondaire petit" :disabled="saisies[a.rendu.id].etat === 'envoi' || saisies[a.rendu.id].note === ''" @click="corriger(a.rendu)">{{ a.rendu.corrige_at ? 'Mettre à jour' : 'Valider' }}</button>
                  <small v-if="saisies[a.rendu.id].etat && !['envoi', 'ok'].includes(saisies[a.rendu.id].etat)" class="erreur">{{ saisies[a.rendu.id].etat }}</small>
                  <Icone v-if="a.rendu.corrige_at" nom="valide" :taille="18" poids="fill" class="ok" libelle="Corrigé" />
                </td>
              </template>
              <template v-else><td>{{ a.rendu?.note ?? '-' }}</td><td>{{ a.rendu?.commentaire || '' }}</td><td /></template>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="aide">La note validée est ajoutée automatiquement au carnet de notes de l'apprenant, avec le coefficient du devoir.</p>
    </section>
  </div>
</template>

<style scoped>
.titre-page { display: flex; justify-content: space-between; align-items: center; gap: var(--esp-3); flex-wrap: wrap; margin-bottom: var(--esp-4); }
.titre-page h1 { margin: 0; }
.actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.selecteur { margin: 0; }
.btn { display: inline-flex; align-items: center; gap: 8px; }
.creation { margin-bottom: var(--esp-4); }
.grille-form { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0 var(--esp-3); }
.grille-form .large { grid-column: 1 / -1; }
.boutons { display: flex; gap: 10px; flex-wrap: wrap; }
.erreur { color: var(--alerte); margin: 4px 0 0; font-size: .85rem; }
.liste { list-style: none; padding: 0; margin: 0 0 var(--esp-4); display: grid; gap: 10px; }
.ligne { width: 100%; display: grid; grid-template-columns: minmax(0, 1fr) 220px auto; gap: 16px; align-items: center; text-align: left; font: inherit; color: var(--texte); cursor: pointer; padding: var(--esp-3) var(--esp-4); transition: border-color .15s, box-shadow .15s; }
.ligne:hover, .ligne.selection { border-color: var(--primaire); box-shadow: var(--ombre-forte); }
.infos { display: grid; gap: 2px; min-width: 0; }
.infos strong { color: var(--encre); }
.progression { display: grid; gap: 4px; }
.progression small { color: var(--texte-doux); }
.barre { height: 8px; border-radius: 99px; background: var(--gris-fond); overflow: hidden; }
.barre span { display: block; height: 100%; background: var(--primaire); border-radius: 99px; transition: width .4s ease; }
.detail { scroll-margin-top: 90px; }
.tete-detail { display: flex; justify-content: space-between; gap: 12px; }
.tete-detail h2 { margin: 0 0 4px; }
.fermer { width: 38px; height: 38px; border-radius: 50%; border: 0; background: var(--gris-fond); cursor: pointer; display: grid; place-items: center; flex-shrink: 0; }
.consignes { white-space: pre-line; padding: 12px 14px; background: var(--gris-fond); border-radius: 10px; }
.personne { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; white-space: nowrap; }
.fichier { display: inline-flex; align-items: center; gap: 6px; margin-right: 6px; }
.note { width: 70px; font: inherit; padding: 6px 8px; border: 1.5px solid #C6CFD8; border-radius: var(--rayon-s); }
.commentaire { width: 100%; min-width: 180px; font: inherit; padding: 6px 8px; border: 1.5px solid #C6CFD8; border-radius: var(--rayon-s); }
.petit { min-height: 36px; padding: 4px 12px; font-size: .88rem; }
.ok { color: var(--succes); margin-left: 6px; vertical-align: middle; }
@media (max-width: 900px) { .grille-form { grid-template-columns: 1fr; } .ligne { grid-template-columns: 1fr; } }
</style>
