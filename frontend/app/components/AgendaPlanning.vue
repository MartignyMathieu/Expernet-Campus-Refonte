<!--
  Planning de l'ENT en trois vues : agenda de la semaine (grille horaire), mois, liste.
  Navigation semaine / mois, retour à aujourd'hui, export .ics vers Outlook ou Google Agenda.
  La vue choisie est mémorisée dans le navigateur (simple confort, sans conséquence si indisponible).
-->
<script setup lang="ts">
const props = defineProps<{ role: 'etudiant' | 'formateur' }>()
const api = useApi()

type Vue = 'semaine' | 'mois' | 'liste'
const vues: { id: Vue; libelle: string; icone: string }[] = [
  { id: 'semaine', libelle: 'Semaine', icone: 'planning' },
  { id: 'mois', libelle: 'Mois', icone: 'session' },
  { id: 'liste', libelle: 'Liste', icone: 'menu' },
]
const vue = ref<Vue>('semaine')
onMounted(() => {
  try { const v = localStorage.getItem('planning-vue') as Vue | null; if (v && vues.some((x) => x.id === v)) vue.value = v } catch {}
  if (window.matchMedia('(max-width: 700px)').matches && vue.value === 'semaine') vue.value = 'liste'
})
watch(vue, (v) => { try { localStorage.setItem('planning-vue', v) } catch {} })

// ----- Dates (calculs en heure locale, sans bibliothèque) -----
const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const ajouter = (d: Date, n: number) => { const r = new Date(d); r.setDate(r.getDate() + n); return r }
const lundi = (d: Date) => ajouter(d, -((d.getDay() + 6) % 7))
const aujourdHui = iso(new Date())
const reference = ref(new Date())

const periode = computed(() => {
  if (vue.value === 'mois') {
    const premier = new Date(reference.value.getFullYear(), reference.value.getMonth(), 1)
    const dernier = new Date(reference.value.getFullYear(), reference.value.getMonth() + 1, 0)
    return { du: lundi(premier), au: ajouter(lundi(dernier), 6) }
  }
  const l = lundi(reference.value)
  return { du: l, au: ajouter(l, 6) }
})
const titre = computed(() => {
  if (vue.value === 'mois') return reference.value.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })
  const { du, au } = periode.value
  const fin = au.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
  return du.getMonth() === au.getMonth() ? `Semaine du ${du.getDate()} au ${fin}` : `Semaine du ${du.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })} au ${fin}`
})
function naviguer(sens: number) {
  const r = new Date(reference.value)
  if (vue.value === 'mois') r.setMonth(r.getMonth() + sens, 1)
  else r.setDate(r.getDate() + 7 * sens)
  reference.value = r
}

// ----- Données -----
const seances = ref<any[]>([])
const chargement = ref(false)
const erreur = ref('')
async function charger() {
  chargement.value = true; erreur.value = ''
  try {
    const r = await api<any>('/api/planning', { query: { du: iso(periode.value.du), au: iso(periode.value.au) } })
    seances.value = r.seances
  } catch { erreur.value = 'Planning indisponible.' } finally { chargement.value = false }
}
watch(() => [iso(periode.value.du), iso(periode.value.au)], charger, { immediate: true })
const parJour = computed(() => {
  const g: Record<string, any[]> = {}
  for (const s of seances.value) (g[s.date] ||= []).push(s)
  return g
})
const lienIcs = computed(() => `/api/planning/ics?du=${iso(periode.value.du)}&au=${iso(ajouter(periode.value.au, 56))}`)

// ----- Vue semaine : grille horaire -----
const HAUTEUR_HEURE = 56
const minutes = (h: string) => { const [a, b] = h.split(':').map(Number); return a * 60 + b }
const joursSemaine = computed(() => {
  const jours = Array.from({ length: 7 }, (_, i) => ajouter(periode.value.du, i))
  // Samedi et dimanche n'apparaissent que s'ils portent une séance
  return jours.filter((d, i) => i < 5 || parJour.value[iso(d)]?.length)
})
const plage = computed(() => {
  const visibles = seances.value.filter((s) => joursSemaine.value.some((d) => iso(d) === s.date))
  const debut = Math.min(8, ...visibles.map((s) => Math.floor(minutes(s.heure_debut) / 60)))
  const fin = Math.max(18, ...visibles.map((s) => Math.ceil(minutes(s.heure_fin) / 60)))
  return { debut, fin, heures: Array.from({ length: fin - debut }, (_, i) => debut + i) }
})
const positionner = (s: any) => ({
  top: `${(minutes(s.heure_debut) - plage.value.debut * 60) / 60 * HAUTEUR_HEURE}px`,
  height: `${Math.max(28, (minutes(s.heure_fin) - minutes(s.heure_debut)) / 60 * HAUTEUR_HEURE - 4)}px`,
})
// Ligne « maintenant », mise à jour chaque minute
const maintenant = ref(new Date())
let minuteur: any
onMounted(() => { minuteur = setInterval(() => (maintenant.value = new Date()), 60000) })
onBeforeUnmount(() => clearInterval(minuteur))
const ligneMaintenant = computed(() => {
  const m = maintenant.value.getHours() * 60 + maintenant.value.getMinutes()
  if (m < plage.value.debut * 60 || m > plage.value.fin * 60) return null
  return `${(m - plage.value.debut * 60) / 60 * HAUTEUR_HEURE}px`
})

// ----- Vue mois -----
const joursMois = computed(() => {
  const n = Math.round((periode.value.au.getTime() - periode.value.du.getTime()) / 864e5) + 1
  return Array.from({ length: n }, (_, i) => ajouter(periode.value.du, i))
})
function ouvrirJour(d: Date) { reference.value = d; vue.value = 'semaine' }

// ----- Détail d'une séance -----
const detail = ref<any | null>(null)
const dialogue = ref<HTMLDialogElement | null>(null)
function voir(s: any) { detail.value = s; editionCahier.value = false; nextTick(() => dialogue.value?.showModal()) }

// ----- Cahier de textes (lecture pour tous, saisie par le formateur de la séance) -----
const editionCahier = ref(false); const cahierEnvoi = ref(false); const cahierErreur = ref('')
const cahier = reactive({ contenu_vu: '', travail_a_faire: '', travail_pour: '' })
function editerCahier() {
  Object.assign(cahier, { contenu_vu: detail.value.cahier?.contenu_vu || '', travail_a_faire: detail.value.cahier?.travail_a_faire || '', travail_pour: detail.value.cahier?.travail_pour || '' })
  cahierErreur.value = ''; editionCahier.value = true
}
async function enregistrerCahier() {
  cahierEnvoi.value = true; cahierErreur.value = ''
  try {
    await csrf()
    const r: any = await api(`/api/seances/${detail.value.id}/cahier`, { method: 'PATCH', body: { contenu_vu: cahier.contenu_vu || null, travail_a_faire: cahier.travail_a_faire || null, travail_pour: cahier.travail_pour || null } })
    detail.value.cahier = r; editionCahier.value = false
  } catch (e: any) { cahierErreur.value = Object.values(erreursValidation(e))[0] || e?.data?.message || 'Enregistrement impossible.' } finally { cahierEnvoi.value = false }
}
const dateCourteFr = (d: string) => new Date(d + 'T12:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
const statut: Record<string, [string, string]> = { present: ['Présent', 'pastille-verte'], absent: ['Absent', 'pastille-rouge'], excuse: ['Excusé', 'pastille-jaune'] }
const etat = (s: any) => props.role === 'etudiant'
  ? (s.emargement.mon_statut ? statut[s.emargement.mon_statut] : s.emargement.ouvert ? ['À signer', 'pastille-orange'] : null)
  : (s.emargement.cloture ? ['Clôturée', 'pastille-verte'] : s.emargement.ouvert ? ['Émargement ouvert', 'pastille-orange'] : null)
const dateLongue = (d: string) => new Date(d + 'T12:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
</script>

<template>
  <div class="agenda">
    <div class="barre-outils">
      <div class="navigation">
        <button class="bouton-icone" :aria-label="vue === 'mois' ? 'Mois précédent' : 'Semaine précédente'" @click="naviguer(-1)"><Icone nom="retour" :taille="18" /></button>
        <button class="btn btn-secondaire petit" @click="reference = new Date()">Aujourd'hui</button>
        <button class="bouton-icone" :aria-label="vue === 'mois' ? 'Mois suivant' : 'Semaine suivante'" @click="naviguer(1)"><Icone nom="suivant" :taille="18" /></button>
        <h2 class="titre" aria-live="polite">{{ titre }} <span v-if="chargement" class="chargement" aria-hidden="true" /></h2>
      </div>
      <div class="droite">
        <div class="choix-vue" role="group" aria-label="Affichage du planning">
          <button v-for="v in vues" :key="v.id" :class="{ actif: vue === v.id }" :aria-pressed="vue === v.id" @click="vue = v.id"><Icone :nom="v.icone" :taille="16" />{{ v.libelle }}</button>
        </div>
        <a :href="lienIcs" class="btn btn-secondaire petit" title="Importer dans Outlook, Google Agenda ou Apple Calendrier"><Icone nom="telecharger" :taille="16" /><span class="libelle-ics">Exporter (.ics)</span></a>
      </div>
    </div>
    <p v-if="erreur" class="message message-erreur" role="alert">{{ erreur }}</p>

    <Transition name="vue-agenda" mode="out-in">
      <!-- Semaine -->
      <div v-if="vue === 'semaine'" key="semaine" class="carte grille-semaine-cadre">
        <div class="grille-semaine" :style="{ '--colonnes': joursSemaine.length }">
          <div class="coin" />
          <div v-for="d in joursSemaine" :key="'t' + iso(d)" class="entete-jour" :class="{ aujourdhui: iso(d) === aujourdHui }">
            <span>{{ d.toLocaleDateString('fr-FR', { weekday: 'short' }) }}</span><strong>{{ d.getDate() }}</strong>
          </div>
          <div class="heures" :style="{ height: plage.heures.length * HAUTEUR_HEURE + 'px' }">
            <span v-for="h in plage.heures" :key="h" :style="{ top: (h - plage.debut) * HAUTEUR_HEURE + 'px' }">{{ String(h).padStart(2, '0') }}:00</span>
          </div>
          <div v-for="d in joursSemaine" :key="'c' + iso(d)" class="colonne" :class="{ aujourdhui: iso(d) === aujourdHui }" :style="{ height: plage.heures.length * HAUTEUR_HEURE + 'px', '--pas': HAUTEUR_HEURE + 'px' }">
            <div v-if="iso(d) === aujourdHui && ligneMaintenant" class="maintenant" :style="{ top: ligneMaintenant }" aria-hidden="true" />
            <button v-for="(s, i) in parJour[iso(d)] || []" :key="s.id" class="evenement" :class="s.creneau" :style="{ ...positionner(s), animationDelay: i * 60 + 'ms' }" @click="voir(s)">
              <strong>{{ s.module }}</strong>
              <span>{{ s.heure_debut }} - {{ s.heure_fin }}</span>
              <span class="secondaire">{{ role === 'etudiant' ? s.formateur : s.promotion }}</span>
              <span v-if="etat(s)" class="pastille" :class="etat(s)![1]">{{ etat(s)![0] }}</span>
            </button>
          </div>
        </div>
        <p v-if="!chargement && !seances.length" class="vide centre">Aucune séance cette semaine.</p>
      </div>

      <!-- Mois -->
      <div v-else-if="vue === 'mois'" key="mois" class="carte mois">
        <div class="mois-grille">
          <span v-for="j in ['lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.']" :key="j" class="nom-jour">{{ j }}</span>
          <div v-for="d in joursMois" :key="iso(d)" class="case-jour" :class="{ autre: d.getMonth() !== reference.getMonth(), aujourdhui: iso(d) === aujourdHui, weekend: d.getDay() % 6 === 0 }">
            <button class="numero" :aria-label="`Voir la semaine du ${d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })}`" @click="ouvrirJour(d)">{{ d.getDate() }}</button>
            <button v-for="s in (parJour[iso(d)] || []).slice(0, 3)" :key="s.id" class="puce-seance" :class="s.creneau" @click="voir(s)"><span class="heure">{{ s.heure_debut }}</span> {{ s.module }}</button>
            <button v-if="(parJour[iso(d)] || []).length > 3" class="plus" @click="ouvrirJour(d)">+ {{ parJour[iso(d)].length - 3 }}</button>
          </div>
        </div>
      </div>

      <!-- Liste -->
      <div v-else key="liste">
        <PlanningSemaine :seances="seances" :role="role" />
      </div>
    </Transition>

    <dialog ref="dialogue" class="detail" aria-labelledby="titre-seance" @click.self="dialogue?.close()">
      <template v-if="detail">
        <div class="detail-tete">
          <p class="sur-titre">{{ dateLongue(detail.date) }}</p>
          <button class="bouton-icone" aria-label="Fermer" @click="dialogue?.close()"><Icone nom="fermer" :taille="18" /></button>
        </div>
        <h2 id="titre-seance">{{ detail.module }}</h2>
        <dl>
          <div><dt><Icone nom="horloge" :taille="16" />Horaire</dt><dd>{{ detail.heure_debut }} - {{ detail.heure_fin }} ({{ detail.creneau === 'matin' ? 'matin' : 'après-midi' }})</dd></div>
          <div><dt><Icone nom="adresse" :taille="16" />Lieu</dt><dd>{{ detail.lieu || '-' }}</dd></div>
          <div v-if="role === 'etudiant'"><dt><Icone nom="formateur" :taille="16" />Formateur</dt><dd>{{ detail.formateur }}</dd></div>
          <div v-else><dt><Icone nom="groupe" :taille="16" />Promotion</dt><dd>{{ detail.promotion }}</dd></div>
          <div v-if="etat(detail)"><dt><Icone nom="signature" :taille="16" />Émargement</dt><dd><span class="pastille" :class="etat(detail)![1]">{{ etat(detail)![0] }}</span></dd></div>
        </dl>
        <section class="cahier" aria-labelledby="t-cahier">
          <div class="cahier-tete">
            <h3 id="t-cahier"><Icone nom="cahier" :taille="18" /> Cahier de textes</h3>
            <button v-if="detail.cahier?.modifiable && !editionCahier" class="lien-cahier" @click="editerCahier"><Icone nom="modifier" :taille="15" /> {{ detail.cahier?.contenu_vu || detail.cahier?.travail_a_faire ? 'Modifier' : 'Remplir' }}</button>
          </div>
          <form v-if="editionCahier" class="form-cahier" @submit.prevent="enregistrerCahier">
            <p v-if="cahierErreur" class="message message-erreur" role="alert">{{ cahierErreur }}</p>
            <div class="champ"><label for="c-vu">Ce qui a été vu</label><textarea id="c-vu" v-model="cahier.contenu_vu" rows="3" maxlength="5000" /></div>
            <div class="champ"><label for="c-travail">Travail à faire <span class="aide">(les apprenants sont prévenus)</span></label><textarea id="c-travail" v-model="cahier.travail_a_faire" rows="2" maxlength="2000" /></div>
            <div v-if="cahier.travail_a_faire" class="champ"><label for="c-pour">Pour le</label><input id="c-pour" v-model="cahier.travail_pour" type="date" required></div>
            <div class="boutons-cahier">
              <button class="btn btn-primaire petit" :disabled="cahierEnvoi">Enregistrer</button>
              <button type="button" class="btn btn-secondaire petit" @click="editionCahier = false">Annuler</button>
            </div>
          </form>
          <template v-else>
            <p v-if="detail.cahier?.contenu_vu" class="vu">{{ detail.cahier.contenu_vu }}</p>
            <p v-if="detail.cahier?.travail_a_faire" class="travail"><strong>À faire pour le {{ dateCourteFr(detail.cahier.travail_pour) }} :</strong> {{ detail.cahier.travail_a_faire }}</p>
            <p v-if="!detail.cahier?.contenu_vu && !detail.cahier?.travail_a_faire" class="aide">Rien n'a encore été renseigné pour cette séance.</p>
          </template>
        </section>
        <NuxtLink v-if="detail.module_id" :to="`/ent/${role}/documents?module=${detail.module_id}`" class="btn btn-secondaire plein bouton-docs"><Icone nom="documents" :taille="18" /> {{ role === 'formateur' ? 'Déposer un cours ou un TP' : 'Cours et TP du module' }}</NuxtLink>
        <NuxtLink v-if="role === 'etudiant' && detail.emargement.ouvert && !detail.emargement.mon_statut" :to="`/ent/etudiant/emargement/${detail.id}`" class="btn btn-primaire plein">Signer ma présence</NuxtLink>
        <NuxtLink v-else-if="role === 'formateur' && detail.date === aujourdHui && !detail.emargement.cloture" :to="`/ent/formateur/seance/${detail.id}`" class="btn btn-primaire plein">{{ detail.emargement.ouvert ? 'Suivre l\'émargement' : 'Ouvrir l\'émargement' }}</NuxtLink>
      </template>
    </dialog>
  </div>
</template>

<style scoped>
.barre-outils { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
.navigation, .droite { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.titre { margin: 0 0 0 8px; font-size: 1.15rem; display: flex; align-items: center; gap: 10px; }
.titre::first-letter { text-transform: uppercase; }
.bouton-icone { width: 40px; height: 40px; border-radius: 12px; border: 1px solid var(--gris-bord); background: #fff; display: grid; place-items: center; cursor: pointer; color: var(--encre); }
.bouton-icone:hover { background: var(--gris-fond); }
.petit { min-height: 40px; padding: 6px 14px; font-size: .9rem; }
.choix-vue { display: inline-flex; background: #fff; border: 1px solid var(--gris-bord); border-radius: 999px; padding: 3px; gap: 2px; }
.choix-vue button { display: inline-flex; align-items: center; gap: 6px; border: 0; background: none; border-radius: 999px; padding: 7px 14px; font: inherit; font-size: .88rem; font-weight: 500; cursor: pointer; color: var(--texte-doux); transition: background .15s, color .15s; }
.choix-vue button.actif { background: var(--primaire-fonce); color: #fff; }

/* Semaine */
.grille-semaine-cadre { padding: 0; overflow: auto; }
.grille-semaine { display: grid; grid-template-columns: 58px repeat(var(--colonnes), minmax(120px, 1fr)); min-width: 680px; }
.coin { border-bottom: 1px solid var(--gris-bord); }
.entete-jour { display: grid; justify-items: center; padding: 10px 4px; border-bottom: 1px solid var(--gris-bord); border-left: 1px solid var(--gris-bord); text-transform: capitalize; color: var(--texte-doux); font-size: .82rem; position: sticky; top: 0; background: #fff; z-index: 2; }
.entete-jour strong { font-family: var(--police-titre); font-size: 1.3rem; color: var(--encre); width: 38px; height: 38px; display: grid; place-items: center; border-radius: 50%; }
.entete-jour.aujourdhui strong { background: var(--primaire-fonce); color: #fff; }
.heures { position: relative; }
.heures span { position: absolute; right: 8px; transform: translateY(-50%); font-size: .72rem; color: var(--texte-doux); font-variant-numeric: tabular-nums; }
.heures span:first-child { transform: none; }
.colonne { position: relative; border-left: 1px solid var(--gris-bord); background-image: repeating-linear-gradient(to bottom, transparent 0, transparent calc(var(--pas) - 1px), var(--gris-bord) calc(var(--pas) - 1px), var(--gris-bord) var(--pas)); }
.colonne.aujourdhui { background-color: #F7FBFE; }
.evenement { position: absolute; left: 4px; right: 4px; display: flex; flex-direction: column; align-items: flex-start; gap: 1px; padding: 6px 8px; border: 0; border-left: 4px solid var(--primaire); border-radius: 8px; background: #E1F3FB; color: var(--encre); font: inherit; font-size: .8rem; text-align: left; cursor: pointer; overflow: hidden; animation: monter .35s cubic-bezier(.2,.7,.2,1) both; transition: transform .15s, box-shadow .15s; }
.evenement.apres_midi { border-left-color: var(--orange); background: #FDEFD9; }
.evenement:hover { transform: translateY(-1px); box-shadow: var(--ombre); z-index: 1; }
.evenement strong { font-size: .84rem; line-height: 1.25; }
.evenement .secondaire { color: var(--texte-doux); }
.evenement .pastille { margin-top: auto; font-size: .7rem; }
.maintenant { position: absolute; left: 0; right: 0; height: 2px; background: var(--alerte); z-index: 3; pointer-events: none; }
.maintenant::before { content: ''; position: absolute; left: -5px; top: -4px; width: 10px; height: 10px; border-radius: 50%; background: var(--alerte); }
.centre { text-align: center; padding: 12px; }

/* Mois */
.mois { padding: 12px; overflow-x: auto; }
.mois-grille { display: grid; grid-template-columns: repeat(7, minmax(92px, 1fr)); gap: 4px; min-width: 660px; }
.nom-jour { text-align: center; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--texte-doux); padding: 4px 0; }
.case-jour { min-height: 104px; border: 1px solid var(--gris-bord); border-radius: 10px; padding: 4px; display: flex; flex-direction: column; gap: 3px; background: #fff; }
.case-jour.autre { background: var(--gris-fond); opacity: .65; }
.case-jour.weekend:not(.autre) { background: #FBFCFD; }
.case-jour.aujourdhui { border-color: var(--primaire); box-shadow: inset 0 0 0 1px var(--primaire); }
.numero { align-self: flex-end; width: 28px; height: 28px; border-radius: 50%; border: 0; background: none; font: inherit; font-weight: 700; font-size: .85rem; cursor: pointer; color: var(--encre); }
.numero:hover { background: var(--gris-fond); }
.aujourdhui .numero { background: var(--primaire-fonce); color: #fff; }
.puce-seance { display: block; width: 100%; text-align: left; border: 0; border-left: 3px solid var(--primaire); background: #E1F3FB; border-radius: 6px; padding: 3px 6px; font: inherit; font-size: .74rem; cursor: pointer; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--encre); }
.puce-seance.apres_midi { border-left-color: var(--orange); background: #FDEFD9; }
.puce-seance .heure { font-weight: 700; }
.plus { border: 0; background: none; font: inherit; font-size: .74rem; font-weight: 700; color: var(--primaire-texte); cursor: pointer; text-align: left; }

/* Détail */
.detail { border: 0; border-radius: 18px; padding: 20px 22px; width: min(460px, calc(100vw - 32px)); max-height: 90vh; overflow-y: auto; box-shadow: var(--ombre-forte); }
.detail[open] { animation: monter .2s ease; }
.detail::backdrop { background: rgba(8,32,52,.45); }
.detail-tete { display: flex; justify-content: space-between; align-items: center; }
.detail-tete .sur-titre { margin: 0; }
.detail h2 { font-size: 1.25rem; margin: 6px 0 14px; }
.detail dl { margin: 0 0 16px; display: grid; gap: 10px; }
.detail dl > div { display: grid; grid-template-columns: 130px 1fr; gap: 8px; align-items: center; }
.detail dt { display: flex; align-items: center; gap: 6px; color: var(--texte-doux); font-size: .88rem; }
.detail dd { margin: 0; color: var(--encre); font-weight: 500; }
.plein { width: 100%; }
.cahier { border-top: 1px solid var(--gris-bord); margin: 12px 0; padding-top: 12px; }
.cahier-tete { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.cahier h3 { display: flex; align-items: center; gap: 6px; font-size: 1rem; margin: 0 0 6px; }
.lien-cahier { display: inline-flex; align-items: center; gap: 4px; background: none; border: 0; color: var(--primaire-texte); font: inherit; font-size: .88rem; font-weight: 600; cursor: pointer; }
.vu { white-space: pre-line; margin: 0 0 8px; }
.travail { margin: 0; padding: 8px 12px; background: #EDE7FB; border-radius: 8px; color: #3D2A7A; white-space: pre-line; }
.form-cahier .champ { margin-bottom: 10px; }
.boutons-cahier { display: flex; gap: 8px; }
.bouton-docs { display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 8px; }
.vue-agenda-enter-active, .vue-agenda-leave-active { transition: opacity .18s ease, transform .18s ease; }
.vue-agenda-enter-from { opacity: 0; transform: translateY(8px); }
.vue-agenda-leave-to { opacity: 0; }
@media (max-width: 700px) {
  .titre { width: 100%; margin: 4px 0 0; font-size: 1rem; }
  .libelle-ics { display: none; }
  .choix-vue button { padding: 7px 10px; }
}
</style>
