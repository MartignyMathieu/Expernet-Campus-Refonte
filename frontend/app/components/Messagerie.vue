<!--
  Messagerie de l'ENT : conversations privées et groupes.
  Rafraîchissement par interrogation périodique (4 s pour le fil ouvert, 10 s pour la liste).
  Pièces jointes (5 fichiers de 10 Mo max : bouton, glisser-déposer ou collage) et émoticônes.
  Évolution prévue : WebSocket (Laravel Reverb) pour le temps réel strict.
-->
<script setup lang="ts">
const api = useApi()
const { user, peut } = useAuth()
const peutCreerGroupe = computed(() => peut('messagerie.groupes'))

const conversations = ref<any[]>([])
const active = ref<any | null>(null)
const messages = ref<any[]>([])
const saisie = ref('')
const recherche = ref('')
const envoi = ref(false)
const erreur = ref('')
const fil = ref<HTMLElement | null>(null)
const zoneSaisie = ref<HTMLTextAreaElement | null>(null)

// ----- Pièces jointes et émoticônes -----
const TYPES_ACCEPTES = '.pdf,.docx,.pptx,.xlsx,.odt,.ods,.zip,.png,.jpg,.jpeg,.gif,.webp,.txt,.md'
const MAX_FICHIERS = 5
const MAX_OCTETS = 10 * 1024 * 1024
const fichiers = ref<File[]>([])
const selecteurFichiers = ref<HTMLInputElement | null>(null)
const emojiOuvert = ref(false)
const survol = ref(false)
function ajouterFichiers(liste: FileList | File[] | null | undefined) {
  if (!liste) return
  erreur.value = ''
  for (const f of Array.from(liste)) {
    if (fichiers.value.length >= MAX_FICHIERS) { erreur.value = `${MAX_FICHIERS} fichiers au maximum par message.`; break }
    if (f.size > MAX_OCTETS) { erreur.value = `« ${f.name} » dépasse 10 Mo.`; continue }
    fichiers.value.push(f)
  }
  if (selecteurFichiers.value) selecteurFichiers.value.value = ''
  zoneSaisie.value?.focus()
}
function coller(e: ClipboardEvent) {
  const f = Array.from(e.clipboardData?.files || [])
  if (f.length) { e.preventDefault(); ajouterFichiers(f) }
}
function deposer(e: DragEvent) { survol.value = false; if (active.value) ajouterFichiers(e.dataTransfer?.files) }
function insererEmoji(e: string) {
  const z = zoneSaisie.value
  const debut = z?.selectionStart ?? saisie.value.length
  const fin = z?.selectionEnd ?? debut
  saisie.value = saisie.value.slice(0, debut) + e + saisie.value.slice(fin)
  nextTick(() => { z?.focus(); const p = debut + e.length; z?.setSelectionRange(p, p); ajuster() })
}
const taille = (o: number) => o >= 1e6 ? (o / 1e6).toFixed(1).replace('.', ',') + ' Mo' : Math.max(1, Math.round(o / 1e3)) + ' Ko'
const vignettes = new WeakMap<File, string>()
function apercuLocal(f: File) {
  if (!vignettes.has(f)) vignettes.set(f, URL.createObjectURL(f))
  return vignettes.get(f)!
}
// Un message composé uniquement de 1 à 3 émoticônes est affiché en grand.
const emojiSeul = (t: string) => /^(?:\p{Extended_Pictographic}(?:\uFE0F|\u200D\p{Extended_Pictographic})*\s*){1,3}$/u.test(t.trim())

async function chargerConversations() {
  conversations.value = await api<any[]>('/api/messagerie/conversations')
  if (active.value) active.value = conversations.value.find((c) => c.id === active.value.id) || active.value
}
async function ouvrir(c: any) {
  active.value = c; messages.value = []; erreur.value = ''; fichiers.value = []
  messages.value = await api<any[]>(`/api/messagerie/conversations/${c.id}/messages`)
  c.non_lus = 0
  await nextTick(); defiler(); zoneSaisie.value?.focus()
}
async function nouveaux() {
  if (!active.value) return
  const dernier = messages.value.at(-1)?.id || 0
  const n = await api<any[]>(`/api/messagerie/conversations/${active.value.id}/messages`, { query: { apres: dernier } })
  if (n.length) { const enBas = estEnBas(); messages.value.push(...n); if (enBas) { await nextTick(); defiler() } }
}
async function envoyer() {
  const texte = saisie.value.trim()
  if ((!texte && !fichiers.value.length) || !active.value || envoi.value) return
  envoi.value = true; erreur.value = ''; emojiOuvert.value = false
  try {
    await csrf()
    let corps: FormData | { contenu: string } = { contenu: texte }
    if (fichiers.value.length) {
      corps = new FormData()
      corps.append('contenu', texte)
      fichiers.value.forEach((f) => (corps as FormData).append('fichiers[]', f))
    }
    const m = await api(`/api/messagerie/conversations/${active.value.id}/messages`, { method: 'POST', body: corps })
    messages.value.push(m); saisie.value = ''; fichiers.value = []
    await nextTick(); defiler(); ajuster(); chargerConversations()
  } catch (e: any) {
    erreur.value = e?.status === 429 ? 'Trop de messages envoyés, patientez un instant.'
      : e?.status === 413 ? 'Fichiers trop volumineux.'
      : Object.values(erreursValidation(e))[0] || e?.data?.message || 'Message non envoyé.'
  } finally { envoi.value = false }
}
function touche(e: KeyboardEvent) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); envoyer() } }
function ajuster() { const z = zoneSaisie.value; if (z) { z.style.height = 'auto'; z.style.height = Math.min(z.scrollHeight, 160) + 'px' } }
function defiler() { if (fil.value) fil.value.scrollTop = fil.value.scrollHeight }
function estEnBas() { const f = fil.value; return !f || f.scrollHeight - f.scrollTop - f.clientHeight < 80 }

let t1: any, t2: any
onMounted(async () => {
  await chargerConversations()
  t1 = setInterval(chargerConversations, 10000)
  t2 = setInterval(nouveaux, 4000)
})
onBeforeUnmount(() => { clearInterval(t1); clearInterval(t2) })

const filtrees = computed(() => conversations.value.filter((c) => !recherche.value || c.titre?.toLowerCase().includes(recherche.value.toLowerCase())))
const initiales = (n = '') => n.split(' ').map((p) => p[0]).join('').slice(0, 2).toUpperCase()
const heure = (d: string) => {
  const date = new Date(d), auj = new Date()
  return date.toDateString() === auj.toDateString() ? date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
}
const jour = (d: string) => new Date(d).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
const roles: Record<string, string> = { admin: 'Administration', formateur: 'Formateur', etudiant: 'Apprenant' }

// ----- Nouveau message / nouveau groupe -----
const dialogue = ref<HTMLDialogElement | null>(null)
const mode = ref<'message' | 'groupe' | 'membres'>('message')
const contacts = ref<any[]>([])
const rechercheContact = ref('')
const groupe = reactive({ nom: '', promotion: null as number | null, membres: [] as number[] })
const erreurDialogue = ref('')
async function ouvrirDialogue(m: 'message' | 'groupe' | 'membres') {
  mode.value = m; erreurDialogue.value = ''; rechercheContact.value = ''
  Object.assign(groupe, { nom: '', promotion: null, membres: m === 'membres' ? [] : [] })
  if (!contacts.value.length) contacts.value = await api<any[]>('/api/messagerie/contacts')
  dialogue.value?.showModal()
}
const contactsFiltres = computed(() => contacts.value.filter((c) => {
  if (mode.value === 'membres' && active.value?.membres.some((m: any) => m.id === c.id)) return false
  return !rechercheContact.value || c.nom.toLowerCase().includes(rechercheContact.value.toLowerCase())
}))
async function demarrer(c: any) {
  await csrf()
  const conv = await api('/api/messagerie/conversations', { method: 'POST', body: { user_id: c.id } })
  dialogue.value?.close(); await chargerConversations(); ouvrir(conversations.value.find((x) => x.id === conv.id) || conv)
}
async function creerGroupe() {
  erreurDialogue.value = ''
  try {
    await csrf()
    const g = await api('/api/messagerie/groupes', { method: 'POST', body: { nom: groupe.nom, membres: groupe.membres, promotion_id: groupe.promotion } })
    dialogue.value?.close(); await chargerConversations(); ouvrir(conversations.value.find((x) => x.id === g.id) || g)
  } catch (e: any) { erreurDialogue.value = e?.data?.message || Object.values(erreursValidation(e))[0] || 'Création impossible.' }
}
async function ajouterMembres() {
  try {
    await csrf()
    active.value = await api(`/api/messagerie/groupes/${active.value.id}/membres`, { method: 'PATCH', body: { ajouter: groupe.membres } })
    dialogue.value?.close(); chargerConversations()
  } catch (e: any) { erreurDialogue.value = e?.data?.message || 'Modification impossible.' }
}
async function retirer(id: number) {
  await csrf()
  active.value = await api(`/api/messagerie/groupes/${active.value.id}/membres`, { method: 'PATCH', body: { retirer: [id] } })
  chargerConversations()
}
</script>

<template>
  <div class="messagerie" :class="{ 'fil-ouvert': active }">
    <!-- Liste des conversations -->
    <aside class="liste" aria-label="Conversations">
      <div class="liste-tete">
        <h1>Messages</h1>
        <div class="actions">
          <button class="bouton-icone" title="Nouveau message" aria-label="Nouveau message" @click="ouvrirDialogue('message')"><Icone nom="ajouter" :taille="20" poids="bold" /></button>
          <button v-if="peutCreerGroupe" class="bouton-icone" title="Nouveau groupe" aria-label="Créer un groupe" @click="ouvrirDialogue('groupe')"><Icone nom="groupe" :taille="20" /></button>
        </div>
      </div>
      <label class="recherche"><Icone nom="recherche" :taille="18" /><span class="sr-only">Rechercher une conversation</span><input v-model="recherche" type="search" placeholder="Rechercher"></label>
      <p v-if="!conversations.length" class="vide">Aucune conversation. Démarrez-en une avec le bouton +.</p>
      <ul>
        <li v-for="c in filtrees" :key="c.id">
          <button class="conv" :class="{ active: active?.id === c.id, nonlu: c.non_lus }" :aria-current="active?.id === c.id" @click="ouvrir(c)">
            <span class="avatar" :class="c.type">
              <Icone v-if="c.type === 'groupe'" nom="groupe" :taille="20" poids="fill" />
              <template v-else>{{ initiales(c.titre) }}</template>
            </span>
            <span class="conv-texte">
              <span class="conv-ligne"><strong>{{ c.titre }}</strong><small v-if="c.dernier_message">{{ heure(c.dernier_message.date) }}</small></span>
              <span class="conv-ligne"><span class="extrait">{{ c.dernier_message ? `${c.dernier_message.auteur} : ${c.dernier_message.extrait}` : 'Nouvelle conversation' }}</span>
                <span v-if="c.non_lus" class="badge" :aria-label="`${c.non_lus} non lu(s)`">{{ c.non_lus }}</span></span>
            </span>
          </button>
        </li>
      </ul>
    </aside>

    <!-- Fil de discussion -->
    <section class="discussion" aria-live="polite">
      <template v-if="active">
        <header class="fil-tete">
          <button class="bouton-icone retour-mobile" aria-label="Retour aux conversations" @click="active = null"><Icone nom="retour" :taille="20" /></button>
          <span class="avatar" :class="active.type"><Icone v-if="active.type === 'groupe'" nom="groupe" :taille="20" poids="fill" /><template v-else>{{ initiales(active.titre) }}</template></span>
          <div class="fil-titre">
            <h2>{{ active.titre }}</h2>
            <small>{{ active.type === 'groupe' ? `${active.membres.length} membres` : roles[active.interlocuteur_role] || '' }}</small>
          </div>
          <button v-if="active.type === 'groupe'" class="btn btn-secondaire petit" @click="ouvrirDialogue('membres')"><Icone nom="utilisateurs" :taille="18" /><span class="libelle-membres">Membres</span></button>
        </header>
        <div ref="fil" class="fil" @dragover.prevent="survol = true" @dragleave.self="survol = false" @drop.prevent="deposer">
          <div v-if="survol" class="depot" aria-hidden="true"><Icone nom="trombone" :taille="32" />Déposez vos fichiers ici</div>
          <template v-for="(m, i) in messages" :key="m.id">
            <p v-if="i === 0 || jour(m.date) !== jour(messages[i - 1].date)" class="separateur"><span>{{ jour(m.date) }}</span></p>
            <div class="bulle-ligne" :class="{ moi: m.moi }">
              <div class="bulle">
                <span v-if="!m.moi && active.type === 'groupe'" class="auteur">{{ m.auteur.nom }}</span>
                <p v-if="m.contenu" :class="{ 'emoji-seul': emojiSeul(m.contenu) }">{{ m.contenu }}</p>
                <div v-if="m.pieces_jointes?.length" class="pj">
                  <template v-for="p in m.pieces_jointes" :key="p.id">
                    <a v-if="p.image" :href="p.url" target="_blank" rel="noopener" class="pj-image" :title="`Ouvrir ${p.nom}`"><img :src="p.url" :alt="p.nom" loading="lazy"></a>
                    <a v-else :href="p.mime === 'application/pdf' ? p.url : `${p.url}?telechargement=1`" target="_blank" rel="noopener" class="pj-fichier">
                      <span class="pj-icone"><Icone :nom="p.mime === 'application/pdf' ? 'pdf' : 'fichier'" :taille="22" /></span>
                      <span class="pj-texte"><strong>{{ p.nom }}</strong><small>{{ taille(p.taille) }}</small></span>
                      <Icone nom="telecharger" :taille="18" />
                    </a>
                  </template>
                </div>
                <time :datetime="m.date">{{ new Date(m.date).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}</time>
              </div>
            </div>
          </template>
        </div>
        <form class="saisie" @submit.prevent="envoyer">
          <p v-if="erreur" class="erreur" role="alert">{{ erreur }}</p>
          <ul v-if="fichiers.length" class="en-attente" aria-label="Fichiers à envoyer">
            <li v-for="(f, i) in fichiers" :key="i + f.name">
              <img v-if="f.type.startsWith('image/')" :src="apercuLocal(f)" alt="">
              <Icone v-else :nom="f.type === 'application/pdf' ? 'pdf' : 'fichier'" :taille="20" />
              <span class="nom-fichier">{{ f.name }}<small>{{ taille(f.size) }}</small></span>
              <button type="button" class="retirer" :aria-label="`Retirer ${f.name}`" @click="fichiers.splice(i, 1)"><Icone nom="fermer" :taille="14" /></button>
            </li>
          </ul>
          <div class="outils-saisie">
            <button type="button" class="bouton-icone" :class="{ actif: emojiOuvert }" aria-label="Émoticônes" :aria-expanded="emojiOuvert" @click.stop="emojiOuvert = !emojiOuvert"><Icone nom="emoji" :taille="22" /></button>
            <button type="button" class="bouton-icone" aria-label="Joindre des fichiers" title="Joindre des fichiers (5 max, 10 Mo chacun)" @click="selecteurFichiers?.click()"><Icone nom="trombone" :taille="22" /></button>
            <input ref="selecteurFichiers" type="file" multiple :accept="TYPES_ACCEPTES" class="sr-only" tabindex="-1" aria-hidden="true" @change="ajouterFichiers(($event.target as HTMLInputElement).files)">
            <SelecteurEmoji v-if="emojiOuvert" @choisir="insererEmoji" @fermer="emojiOuvert = false" />
          </div>
          <label for="message" class="sr-only">Votre message</label>
          <textarea id="message" ref="zoneSaisie" v-model="saisie" rows="1" maxlength="2000" placeholder="Écrire un message…" aria-describedby="aide-saisie" @keydown="touche" @input="ajuster" @paste="coller" />
          <span id="aide-saisie" class="sr-only">Entrée pour envoyer, Maj + Entrée pour aller à la ligne</span>
          <button class="envoyer" :disabled="(!saisie.trim() && !fichiers.length) || envoi" aria-label="Envoyer"><Icone nom="envoyer" :taille="20" poids="fill" /></button>
        </form>
      </template>
      <div v-else class="accueil-fil">
        <span class="grande-icone"><Icone nom="messages" :taille="44" poids="duotone" /></span>
        <h2>Vos échanges au même endroit</h2>
        <p>Écrivez à vos formateurs, à votre promotion ou à l'administration. {{ peutCreerGroupe ? 'Créez des groupes pour vos promotions et vos projets.' : '' }}</p>
        <button class="btn btn-bleu" @click="ouvrirDialogue('message')"><Icone nom="ajouter" :taille="18" poids="bold" />Nouveau message</button>
      </div>
    </section>

    <!-- Dialogue : nouveau message / groupe / membres -->
    <dialog ref="dialogue" class="dialogue" aria-labelledby="titre-dialogue">
      <form method="dialog" class="dialogue-tete">
        <h2 id="titre-dialogue">{{ { message: 'Nouveau message', groupe: 'Nouveau groupe', membres: 'Membres du groupe' }[mode] }}</h2>
        <button class="bouton-icone" aria-label="Fermer"><Icone nom="fermer" :taille="20" /></button>
      </form>
      <p v-if="erreurDialogue" class="message message-erreur" role="alert">{{ erreurDialogue }}</p>

      <template v-if="mode === 'groupe'">
        <div class="champ"><label for="g-nom">Nom du groupe</label><input id="g-nom" v-model="groupe.nom" maxlength="100" placeholder="ex. Projet fil rouge — équipe 2"></div>
        <div v-if="user?.promotions.length" class="champ">
          <label for="g-promo">Ajouter toute une promotion (facultatif)</label>
          <select id="g-promo" v-model="groupe.promotion"><option :value="null">— Aucune —</option><option v-for="p in user?.promotions" :key="p.id" :value="p.id">{{ p.libelle }}</option></select>
        </div>
      </template>
      <template v-if="mode === 'membres' && active">
        <ul class="membres-actuels">
          <li v-for="m in active.membres" :key="m.id"><span>{{ m.nom }} <small v-if="m.gestionnaire" class="pastille pastille-bleue">Gestionnaire</small></span>
            <button v-if="active.gestionnaire && !m.gestionnaire" class="lien-retirer" @click="retirer(m.id)">Retirer</button></li>
        </ul>
        <p v-if="active.gestionnaire" class="aide">Ajouter des membres :</p>
      </template>

      <template v-if="mode !== 'membres' || active?.gestionnaire">
        <label class="recherche"><Icone nom="recherche" :taille="18" /><span class="sr-only">Rechercher un contact</span><input v-model="rechercheContact" type="search" placeholder="Rechercher un contact"></label>
        <ul class="contacts">
          <li v-for="c in contactsFiltres" :key="c.id">
            <button v-if="mode === 'message'" class="contact" @click="demarrer(c)">
              <span class="avatar petit">{{ initiales(c.nom) }}</span><span>{{ c.nom }}</span><small class="pastille">{{ roles[c.role] }}</small>
            </button>
            <label v-else class="contact case">
              <input v-model="groupe.membres" type="checkbox" :value="c.id">
              <span class="avatar petit">{{ initiales(c.nom) }}</span><span>{{ c.nom }}</span><small class="pastille">{{ roles[c.role] }}</small>
            </label>
          </li>
        </ul>
        <button v-if="mode === 'groupe'" class="btn btn-primaire plein" :disabled="groupe.nom.trim().length < 2 || (!groupe.membres.length && !groupe.promotion)" @click="creerGroupe">Créer le groupe</button>
        <button v-if="mode === 'membres'" class="btn btn-primaire plein" :disabled="!groupe.membres.length" @click="ajouterMembres">Ajouter {{ groupe.membres.length || '' }} membre(s)</button>
      </template>
    </dialog>
  </div>
</template>

<style scoped>
.messagerie { display: grid; grid-template-columns: 340px minmax(0, 1fr); height: calc(100vh - 160px); min-height: 520px; background: #fff; border: 1px solid var(--gris-bord); border-radius: 18px; overflow: hidden; box-shadow: var(--ombre); }
.liste { border-right: 1px solid var(--gris-bord); display: flex; flex-direction: column; min-height: 0; }
.liste-tete { display: flex; justify-content: space-between; align-items: center; padding: 18px 18px 10px; }
.liste-tete h1 { font-size: 1.4rem; margin: 0; }
.actions { display: flex; gap: 6px; }
.bouton-icone { width: 40px; height: 40px; border-radius: 12px; border: 1px solid var(--gris-bord); background: #fff; display: grid; place-items: center; cursor: pointer; color: var(--primaire-fonce); }
.bouton-icone:hover { background: #E1F3FB; }
.recherche { display: flex; align-items: center; gap: 8px; margin: 0 18px 12px; padding: 0 12px; background: var(--gris-fond); border-radius: 12px; color: var(--texte-doux); }
.recherche input { border: 0; background: transparent; font: inherit; padding: 10px 0; flex: 1; min-width: 0; }
.recherche input:focus { outline: none; }
.liste ul { list-style: none; margin: 0; padding: 0 8px 12px; overflow-y: auto; flex: 1; }
.conv { width: 100%; display: grid; grid-template-columns: auto 1fr; gap: 12px; align-items: center; padding: 10px; border: 0; background: none; border-radius: 12px; cursor: pointer; text-align: left; font: inherit; color: var(--texte); }
.conv:hover { background: var(--gris-fond); }
.conv.active { background: #E1F3FB; }
.conv-texte { display: grid; gap: 2px; min-width: 0; }
.conv-ligne { display: flex; justify-content: space-between; align-items: center; gap: 8px; min-width: 0; }
.conv-ligne strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--encre); }
.conv-ligne small { color: var(--texte-doux); font-size: .76rem; flex-shrink: 0; }
.extrait { color: var(--texte-doux); font-size: .86rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.nonlu .extrait { color: var(--encre); font-weight: 500; }
.badge { background: var(--orange); color: var(--encre); font-weight: 700; font-size: .72rem; border-radius: 999px; min-width: 20px; height: 20px; display: grid; place-items: center; padding: 0 6px; flex-shrink: 0; }
.avatar { width: 44px; height: 44px; border-radius: 50%; display: grid; place-items: center; background: var(--primaire-fonce); color: #fff; font-weight: 700; font-size: .85rem; flex-shrink: 0; }
.avatar.groupe { border-radius: 14px; background: var(--orange); color: var(--encre); }
.avatar.petit { width: 34px; height: 34px; font-size: .75rem; }
.discussion { display: flex; flex-direction: column; min-height: 0; min-width: 0; background: linear-gradient(180deg, #F8FBFD, #F2F6F9); }
.fil-tete { display: flex; align-items: center; gap: 12px; padding: 14px 20px; background: #fff; border-bottom: 1px solid var(--gris-bord); }
.fil-titre { flex: 1; min-width: 0; }
.fil-titre h2 { font-size: 1.05rem; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fil-titre small { color: var(--texte-doux); }
.retour-mobile { display: none; }
.petit { min-height: 38px; padding: 6px 14px; font-size: .88rem; }
.fil { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 6px; }
.separateur { text-align: center; margin: 14px 0 8px; }
.separateur span { background: #fff; border: 1px solid var(--gris-bord); color: var(--texte-doux); font-size: .76rem; padding: 3px 12px; border-radius: 999px; text-transform: capitalize; }
.bulle-ligne { display: flex; }
.bulle-ligne.moi { justify-content: flex-end; }
.bulle { max-width: min(72%, 560px); background: #fff; border: 1px solid var(--gris-bord); border-radius: 16px 16px 16px 4px; padding: 10px 14px 6px; box-shadow: 0 1px 2px rgba(11,46,74,.05); }
.moi .bulle { background: var(--primaire-fonce); border-color: var(--primaire-fonce); color: #fff; border-radius: 16px 16px 4px 16px; }
.bulle p { margin: 0; white-space: pre-wrap; word-break: break-word; }
.auteur { display: block; font-size: .78rem; font-weight: 700; color: var(--orange-fonce); margin-bottom: 2px; }
.bulle time { display: block; text-align: right; font-size: .7rem; opacity: .65; margin-top: 2px; }
.saisie { display: flex; gap: 10px; align-items: flex-end; padding: 12px 16px; background: #fff; border-top: 1px solid var(--gris-bord); flex-wrap: wrap; }
.saisie .erreur { width: 100%; margin: 0; }
.outils-saisie { position: relative; display: flex; gap: 4px; }
.outils-saisie .bouton-icone { border: 0; color: var(--texte-doux); width: 42px; height: 48px; }
.outils-saisie .bouton-icone:hover, .outils-saisie .bouton-icone.actif { color: var(--primaire-fonce); background: #E1F3FB; }
.en-attente { width: 100%; list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 8px; }
.en-attente li { display: flex; align-items: center; gap: 8px; max-width: 260px; padding: 6px 6px 6px 8px; background: var(--gris-fond); border: 1px solid var(--gris-bord); border-radius: 12px; color: var(--primaire-fonce); }
.en-attente img { width: 34px; height: 34px; object-fit: cover; border-radius: 6px; }
.nom-fichier { display: grid; min-width: 0; font-size: .84rem; color: var(--encre); }
.nom-fichier, .nom-fichier small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.nom-fichier small { color: var(--texte-doux); font-size: .74rem; }
.retirer { width: 26px; height: 26px; flex-shrink: 0; border-radius: 50%; border: 0; background: #fff; color: var(--texte-doux); display: grid; place-items: center; cursor: pointer; }
.retirer:hover { color: var(--alerte); }
.fil { position: relative; }
.depot { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; justify-content: center; gap: 10px; min-height: 120px; margin-bottom: 8px; border: 2px dashed var(--primaire); border-radius: 16px; background: rgba(225,243,251,.92); color: var(--primaire-fonce); font-weight: 700; pointer-events: none; }
.emoji-seul { font-size: 2.4rem; line-height: 1.2; }
.pj { display: grid; gap: 6px; margin: 6px 0 2px; }
.pj-image img { max-width: min(280px, 100%); max-height: 240px; border-radius: 10px; object-fit: cover; display: block; }
.pj-fichier { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 10px; align-items: center; min-width: min(240px, 100%); padding: 8px 10px; border-radius: 10px; background: var(--gris-fond); color: var(--encre); text-decoration: none; }
.moi .pj-fichier { background: rgba(255,255,255,.14); color: #fff; }
.pj-icone { width: 36px; height: 36px; border-radius: 8px; display: grid; place-items: center; background: #fff; color: var(--primaire-fonce); }
.pj-texte { display: grid; min-width: 0; font-size: .86rem; }
.pj-texte strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pj-texte small { opacity: .75; }
.saisie textarea { flex: 1; resize: none; font: inherit; border: 1.5px solid #C6CFD8; border-radius: 14px; padding: 11px 14px; max-height: 160px; min-width: 0; }
.saisie textarea:focus { outline: none; border-color: var(--primaire); box-shadow: inset 0 0 0 1px var(--primaire); }
.envoyer { width: 48px; height: 48px; border-radius: 14px; border: 0; background: var(--orange); color: var(--encre); display: grid; place-items: center; cursor: pointer; flex-shrink: 0; }
.envoyer:disabled { opacity: .45; cursor: not-allowed; }
.accueil-fil { margin: auto; text-align: center; max-width: 420px; padding: 24px; display: grid; justify-items: center; gap: 10px; }
.accueil-fil h2 { margin: 0; font-size: 1.3rem; }
.accueil-fil p { margin: 0 0 8px; color: var(--texte-doux); }
.grande-icone { width: 84px; height: 84px; border-radius: 24px; display: grid; place-items: center; background: #E1F3FB; color: var(--primaire-fonce); }
.dialogue { border: 0; border-radius: 18px; padding: 0 22px 0; width: min(480px, calc(100vw - 32px)); max-height: min(640px, calc(100vh - 40px)); box-shadow: var(--ombre-forte); }
.dialogue::backdrop { background: rgba(8,32,52,.45); }
.dialogue-tete { display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: #fff; padding: 18px 0 12px; z-index: 1; }
.dialogue-tete h2 { margin: 0; font-size: 1.2rem; }
.dialogue .recherche { margin: 0 0 10px; }
.contacts { list-style: none; padding: 0; margin: 0 0 14px; display: grid; gap: 2px; max-height: 280px; overflow-y: auto; }
.contact { width: 100%; display: grid; grid-template-columns: auto auto 1fr auto; gap: 10px; align-items: center; padding: 8px 10px; border-radius: 10px; background: none; border: 0; font: inherit; text-align: left; cursor: pointer; color: var(--texte); }
button.contact { grid-template-columns: auto 1fr auto; }
.contact:hover { background: var(--gris-fond); }
.contact input { width: 18px; height: 18px; margin: 0; }
.membres-actuels { list-style: none; padding: 0; margin: 0 0 12px; display: grid; gap: 6px; }
.membres-actuels li { display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; background: var(--gris-fond); border-radius: 10px; }
.lien-retirer { background: none; border: 0; color: var(--alerte); cursor: pointer; font: inherit; font-size: .88rem; }
.plein { width: 100%; position: sticky; bottom: 0; margin-bottom: 18px; box-shadow: 0 -12px 16px #fff; }
@media (max-width: 900px) {
  .messagerie { grid-template-columns: minmax(0, 1fr); height: calc(100vh - 190px); border-radius: 14px; }
  .libelle-membres { display: none; }
  .fil-tete { padding: 10px 12px; gap: 8px; }
  .fil { padding: 14px 12px; }
  .saisie { padding: 10px; }
  .discussion { display: none; }
  .fil-ouvert .liste { display: none; }
  .fil-ouvert .discussion { display: flex; }
  .retour-mobile { display: grid; }
  .bulle { max-width: 86%; }
  .fil-tete .btn span, .fil-tete .petit { padding: 6px 10px; }
}
</style>
