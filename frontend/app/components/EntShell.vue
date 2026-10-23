<!-- Gabarit commun de l'ENT : barre latérale (bureau) / barre basse (mobile), barre supérieure, contenu. -->
<script setup lang="ts">
export interface EntreeMenu { libelle: string; to: string; icone: string }
const props = defineProps<{ menu: EntreeMenu[]; titre: string; theme?: 'banane' | 'standard' }>()
const { user, deconnexion, accueilRole } = useAuth()
const route = useRoute()
const actif = (to: string) => route.path === to || (to !== props.menu[0]?.to && route.path.startsWith(to))
const libelleRole = computed(() => ({ admin: 'Administrateur', formateur: 'Formateur', etudiant: 'Apprenant' } as any)[user.value?.role || ''] || '')
const date = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
const menuProfil = ref(false)
// Badge des messages non lus (interrogé toutes les 20 s)
const nonLus = ref(0)
const api = useApi()
const { peut } = useAuth()
async function majNonLus() { if (peut('messagerie.use') && !(user.value?.mfa.obligatoire && !user.value?.mfa.active)) nonLus.value = (await api<any>('/api/messagerie/non-lus').catch(() => ({ non_lus: 0 }))).non_lus }
let minuteur: any
onMounted(() => { majNonLus(); minuteur = setInterval(majNonLus, 20000) })
onBeforeUnmount(() => clearInterval(minuteur))
watch(() => route.fullPath, majNonLus)
</script>

<template>
  <div class="ent" :class="'theme-' + (theme || 'standard')">
    <a href="#contenu" class="lien-evitement">Aller au contenu</a>
    <aside class="barre" aria-label="Navigation de l'espace">
      <NuxtLink :to="accueilRole()" class="logo"><img src="/images/brand/logo-expernet.png" alt="Expernet Campus - accueil de mon espace" width="900" height="241"></NuxtLink>
      <p class="espace"><span class="point" aria-hidden="true" />{{ titre }}</p>
      <nav>
        <ul>
          <li v-for="e in menu" :key="e.to">
            <NuxtLink :to="e.to" class="entree" :class="{ actif: actif(e.to) }" :aria-current="actif(e.to) ? 'page' : undefined">
              <IconsIconBanane v-if="theme === 'banane'" :variante="e.icone as any" :taille="30" />
              <Icone v-else :nom="e.icone" :taille="22" :poids="actif(e.to) ? 'fill' : 'regular'" />
              <span>{{ e.libelle }}</span>
              <span v-if="e.icone === 'messages' && nonLus" :key="nonLus" class="badge-nav" :aria-label="`${nombre(nonLus, 'message')} ${pluriel(nonLus, 'non lu', 'non lus')}`">{{ nonLus > 99 ? '99+' : nonLus }}</span>
            </NuxtLink>
          </li>
        </ul>
      </nav>
      <div class="aide-bloc">
        <span class="aide-icone"><Icone nom="telephone" :taille="20" poids="duotone" /></span>
        <p><strong>Besoin d'aide ?</strong><br><a href="tel:+262262219045">02 62 21 90 45</a></p>
      </div>
    </aside>

    <div class="cadre">
      <header class="haut">
        <p class="date">{{ date }}</p>
        <div class="profil" v-if="user">
          <button class="chip" :aria-expanded="menuProfil" aria-controls="menu-profil" @click="menuProfil = !menuProfil">
            <Avatar class="avatar" :url="user.avatar_url" :nom="`${user.prenom} ${user.nom}`" :taille="36" />
            <span class="nom"><strong>{{ user.prenom }} {{ user.nom }}</strong><small>{{ libelleRole }}</small></span>
            <Icone nom="deplier" :taille="14" />
          </button>
          <div v-show="menuProfil" id="menu-profil" class="menu-profil">
            <NuxtLink to="/ent/profil" @click="menuProfil = false"><Icone nom="utilisateur" /> Mon profil</NuxtLink>
            <button @click="deconnexion"><Icone nom="deconnexion" /> Se déconnecter</button>
          </div>
        </div>
      </header>
      <main id="contenu" class="contenu" tabindex="-1">
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
.ent { display: grid; grid-template-columns: 272px minmax(0, 1fr); min-height: 100vh; background: var(--gris-fond); }
.barre { background: #fff; border-right: 1px solid var(--gris-bord); padding: 24px 16px; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; }
.theme-banane .barre { background: linear-gradient(180deg, #FFFBEC 0%, #fff 45%); border-right-color: #F3DD94; }
.logo img { width: 190px; height: auto; margin: 0 8px; }
.espace { display: flex; align-items: center; gap: 8px; margin: 18px 8px 22px; color: var(--texte-doux); font-size: .74rem; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; }
.point { width: 8px; height: 8px; border-radius: 50%; background: var(--primaire); }
.theme-banane .point { background: var(--jaune); }
ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 4px; }
.entree { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 12px; color: var(--texte); text-decoration: none; font-weight: 500; min-height: 46px; transition: background .15s; }
.entree:hover { background: var(--gris-fond); }
.entree { position: relative; }
.badge-nav { margin-left: auto; background: var(--orange); color: var(--encre); font-size: .72rem; font-weight: 700; border-radius: 999px; min-width: 22px; height: 22px; display: grid; place-items: center; padding: 0 6px; }
.badge-nav { animation: pulsation .45s ease; }
.entree :deep(.icone), .entree :deep(.banane) { transition: transform .2s ease; }
.entree:hover :deep(.icone) { transform: translateX(2px) scale(1.06); }
.entree { transition: background .2s, color .2s, box-shadow .2s; }
/* Contenu de l'espace : blocs échelonnés à chaque changement de page */
.contenu > :deep(div) > * { animation: monter .5s cubic-bezier(.2,.7,.2,1) both; }
.contenu > :deep(div) > :nth-child(2) { animation-delay: .06s; }
.contenu > :deep(div) > :nth-child(3) { animation-delay: .12s; }
.contenu > :deep(div) > :nth-child(4) { animation-delay: .18s; }
.contenu > :deep(div) > :nth-child(n+5) { animation-delay: .24s; }
.contenu :deep(.grille > .carte) { animation: monter .5s cubic-bezier(.2,.7,.2,1) both; }
.contenu :deep(.grille > .carte:nth-child(2)) { animation-delay: .1s; }
.contenu :deep(.grille > .carte:nth-child(3)) { animation-delay: .16s; }
.contenu :deep(.grille > .carte:nth-child(n+4)) { animation-delay: .22s; }
.menu-profil { transform-origin: top right; animation: deroule .16s ease-out; }
@keyframes deroule { from { opacity: 0; transform: translateY(-4px) scale(.98); } to { opacity: 1; transform: none; } }
.entree.actif { background: #E1F3FB; color: var(--primaire-fonce); font-weight: 700; box-shadow: inset 4px 0 0 var(--primaire); }
.theme-banane .entree:hover :deep(.banane) { transform: rotate(-14deg) scale(1.08); }
.theme-banane .entree.actif { background: #FFEFC2; color: #5A4100; box-shadow: inset 4px 0 0 var(--jaune); }
.aide-bloc { margin-top: auto; display: flex; gap: 12px; align-items: center; background: var(--gris-fond); border-radius: 12px; padding: 14px; font-size: .84rem; }
.aide-bloc p { margin: 0; }
.aide-icone { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; background: #E1F3FB; color: var(--primaire-fonce); }
.cadre { min-width: 0; display: flex; flex-direction: column; }
.haut { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 14px 40px; background: #fff; border-bottom: 1px solid var(--gris-bord); position: sticky; top: 0; z-index: 20; }
.date { margin: 0; color: var(--texte-doux); text-transform: capitalize; font-size: .9rem; }
.profil { position: relative; }
.chip { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid var(--gris-bord); border-radius: 999px; padding: 5px 14px 5px 5px; cursor: pointer; font: inherit; color: var(--encre); }
.theme-banane .avatar { background: var(--jaune); color: var(--encre); }
.nom { display: grid; text-align: left; line-height: 1.2; }
.nom small { color: var(--texte-doux); }
.menu-profil { position: absolute; right: 0; top: calc(100% + 8px); background: #fff; border: 1px solid var(--gris-bord); border-radius: 12px; box-shadow: var(--ombre-forte); padding: 6px; display: grid; min-width: 220px; z-index: 30; }
.menu-profil a, .menu-profil button { display: flex; align-items: center; gap: 10px; text-align: left; padding: 10px 12px; border-radius: 8px; color: var(--texte); text-decoration: none; background: none; border: 0; font: inherit; cursor: pointer; }
.menu-profil a:hover, .menu-profil button:hover { background: var(--gris-fond); }
.contenu { padding: 32px 40px 48px; outline: none; }
@media (max-width: 900px) {
  .ent { grid-template-columns: minmax(0, 1fr); }
  .barre { position: fixed; bottom: 0; top: auto; left: 0; right: 0; height: auto; padding: 6px; z-index: 50; border-right: 0; border-top: 1px solid var(--gris-bord); flex-direction: row; }
  .logo, .espace, .aide-bloc { display: none; }
  nav { width: 100%; min-width: 0; }
  ul { grid-auto-flow: column; grid-auto-columns: minmax(56px, 1fr); overflow-x: auto; scrollbar-width: none; }
  .entree span:not(.badge-nav) { overflow-wrap: normal; word-break: normal; line-height: 1.15; letter-spacing: -.01em; }
  .entree { flex-direction: column; gap: 2px; font-size: .64rem; padding: 6px 2px; text-align: center; min-height: 56px; }
  .entree.actif, .theme-banane .entree.actif { box-shadow: inset 0 3px 0 var(--primaire); }
  .badge-nav { position: absolute; top: 2px; right: calc(50% - 22px); min-width: 18px; height: 18px; font-size: .64rem; }
  .theme-banane .entree.actif { box-shadow: inset 0 3px 0 var(--jaune); }
  .haut { padding: 10px 16px; }
  .date { display: none; }
  .nom { display: none; }
  .haut::before { content: ''; width: 120px; height: 32px; background: url('/images/brand/logo-expernet.png') no-repeat left center / contain; }
  .contenu { padding: 18px 16px 100px; }
}
</style>
