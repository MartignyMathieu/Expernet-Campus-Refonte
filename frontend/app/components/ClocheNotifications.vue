<!-- Cloche des notifications de l'ENT : compteur de non lues (rafraîchi toutes les 30 s) et liste déroulante. -->
<script setup lang="ts">
const api = useApi()
const { user } = useAuth()
const ouvert = ref(false)
const donnees = ref<{ non_lues: number; notifications: any[] }>({ non_lues: 0, notifications: [] })
const bloque = computed(() => !!(user.value?.mfa.obligatoire && !user.value?.mfa.active))

async function charger() {
  if (bloque.value) return
  donnees.value = await api<any>('/api/notifications').catch(() => donnees.value)
}
async function ouvrirNotification(n: any) {
  ouvert.value = false
  if (!n.lue) { n.lue = true; donnees.value.non_lues = Math.max(0, donnees.value.non_lues - 1); await csrf().catch(() => {}); api(`/api/notifications/${n.id}/lue`, { method: 'POST' }).catch(() => {}) }
  if (n.lien) await navigateTo(n.lien)
}
async function toutLire() {
  donnees.value.notifications.forEach((n) => (n.lue = true)); donnees.value.non_lues = 0
  await csrf().catch(() => {}); await api('/api/notifications/lues', { method: 'POST' }).catch(() => {})
}
const icones: Record<string, string> = { devoir: 'programme', note: 'notes', document: 'documents', absence: 'assiduite', emargement: 'signature', travail: 'lecture_article', rendu: 'deposer' }
const ilYa = (d: string) => {
  const min = Math.round((Date.now() - new Date(d).getTime()) / 60000)
  if (min < 1) return 'à l\'instant'
  if (min < 60) return `il y a ${min} min`
  if (min < 1440) return `il y a ${Math.round(min / 60)} h`
  return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
}
let minuteur: any
onMounted(() => { charger(); minuteur = setInterval(charger, 30000) })
onBeforeUnmount(() => clearInterval(minuteur))
const route = useRoute()
watch(() => route.fullPath, () => { ouvert.value = false; charger() })
function fermerSiDehors(e: MouseEvent) { if (!(e.target as HTMLElement).closest('.cloche')) ouvert.value = false }
onMounted(() => document.addEventListener('click', fermerSiDehors))
onBeforeUnmount(() => document.removeEventListener('click', fermerSiDehors))
</script>

<template>
  <div v-if="!bloque" class="cloche">
    <button class="bouton" :aria-expanded="ouvert" aria-controls="liste-notifications" :aria-label="donnees.non_lues ? `Notifications : ${nombre(donnees.non_lues, 'non lue', 'non lues')}` : 'Notifications'" @click="ouvert = !ouvert; ouvert && charger()">
      <Icone nom="cloche" :taille="22" :poids="donnees.non_lues ? 'fill' : 'regular'" />
      <span v-if="donnees.non_lues" :key="donnees.non_lues" class="pastille-compte">{{ donnees.non_lues > 9 ? '9+' : donnees.non_lues }}</span>
    </button>
    <div v-show="ouvert" id="liste-notifications" class="panneau" role="region" aria-label="Notifications">
      <div class="tete"><strong>Notifications</strong><button v-if="donnees.non_lues" class="lien" @click="toutLire">Tout marquer comme lu</button></div>
      <p v-if="!donnees.notifications.length" class="vide">Aucune notification pour l'instant.</p>
      <ul>
        <li v-for="n in donnees.notifications" :key="n.id">
          <button class="notif" :class="{ nonlue: !n.lue }" @click="ouvrirNotification(n)">
            <span class="ic" :class="'c-' + n.categorie"><Icone :nom="icones[n.categorie] || 'info'" :taille="18" /></span>
            <span class="texte"><strong>{{ n.titre }}</strong><span>{{ n.message }}</span><small>{{ ilYa(n.date) }}</small></span>
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.cloche { position: relative; }
.bouton { position: relative; width: 44px; height: 44px; border-radius: 50%; border: 1px solid var(--gris-bord); background: #fff; display: grid; place-items: center; cursor: pointer; color: var(--encre); transition: background .15s; }
.bouton:hover { background: var(--gris-fond); }
.pastille-compte { position: absolute; top: -2px; right: -2px; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 999px; background: var(--orange); color: var(--encre); font-size: .7rem; font-weight: 800; display: grid; place-items: center; animation: pulsation .45s ease; }
.panneau { position: absolute; right: 0; top: calc(100% + 8px); width: min(380px, calc(100vw - 32px)); max-height: 70vh; overflow-y: auto; background: #fff; border: 1px solid var(--gris-bord); border-radius: 14px; box-shadow: var(--ombre-forte); z-index: 35; }
.tete { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; border-bottom: 1px solid var(--gris-bord); position: sticky; top: 0; background: #fff; }
.lien { background: none; border: 0; color: var(--primaire-texte); font: inherit; font-size: .85rem; font-weight: 600; cursor: pointer; }
.vide { padding: 16px; margin: 0; }
ul { list-style: none; margin: 0; padding: 6px; display: grid; gap: 2px; }
.notif { width: 100%; display: grid; grid-template-columns: 34px minmax(0, 1fr); gap: 10px; text-align: left; padding: 10px; border: 0; border-radius: 10px; background: none; font: inherit; color: var(--texte); cursor: pointer; }
.notif:hover { background: var(--gris-fond); }
.notif.nonlue { background: #F1F9FD; }
.notif.nonlue strong::after { content: ''; display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--primaire); margin-left: 6px; vertical-align: middle; }
.ic { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: var(--gris-fond); color: var(--primaire-fonce); }
.c-note { background: #E4F5EA; color: #1E7A43; }
.c-devoir, .c-travail { background: #EDE7FB; color: #5B3FA8; }
.c-absence { background: #FFF1D6; color: #8A5A00; }
.c-emargement { background: #FBE4E5; color: var(--alerte); }
.texte { display: grid; gap: 2px; min-width: 0; }
.texte strong { color: var(--encre); font-size: .92rem; }
.texte span { font-size: .86rem; overflow-wrap: anywhere; }
.texte small { color: var(--texte-doux); font-size: .76rem; }
</style>
