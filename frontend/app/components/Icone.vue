<!--
  Icônes du projet : jeu Phosphor Icons (licence MIT), cohérent et dessiné par des designers.
  Usage : <Icone nom="planning" /> — décoratives par défaut (aria-hidden), le texte voisin porte le sens.
-->
<script setup lang="ts">
import {
  PhHouse, PhCalendarBlank, PhChartBar, PhFolderOpen, PhSealCheck, PhShieldCheck, PhChatsCircle, PhUsersThree, PhTray,
  PhPhone, PhEnvelopeSimple, PhMapPin, PhMagnifyingGlass, PhArrowRight, PhArrowLeft, PhCheckCircle, PhClock, PhGraduationCap,
  PhBriefcase, PhCertificate, PhPlus, PhPaperPlaneRight, PhSignOut, PhGlobe, PhLockKey, PhWheelchair, PhQuestion, PhNewspaper,
  PhCaretDown, PhSignature, PhWarningCircle, PhDownloadSimple, PhList, PhX, PhPause, PhPlay, PhDesktop, PhTable, PhChartLine,
  PhKanban, PhMegaphone, PhCode, PhPenNib, PhCube, PhTerminalWindow, PhHardDrives, PhNetwork, PhBrain, PhUserPlus, PhStudent,
  PhChalkboardTeacher, PhBuildings, PhHandshake, PhLaptop, PhArrowUpRight, PhFilePdf, PhInfo, PhCaretRight, PhBookOpenText,
  PhTimer, PhFingerprint, PhIdentificationBadge, PhUsersFour, PhUserCircle,
  PhCurrencyEur, PhCalendarCheck, PhTarget, PhListChecks, PhFunnel, PhClipboardText, PhTag,
} from '@phosphor-icons/vue'

const props = withDefaults(defineProps<{ nom: string; taille?: number | string; poids?: 'thin' | 'light' | 'regular' | 'bold' | 'fill' | 'duotone'; libelle?: string }>(), { taille: 20, poids: 'regular' })

const icones: Record<string, any> = {
  accueil: PhHouse, planning: PhCalendarBlank, notes: PhChartBar, documents: PhFolderOpen, examens: PhSealCheck, securite: PhShieldCheck,
  messages: PhChatsCircle, utilisateurs: PhUsersThree, candidatures: PhTray, telephone: PhPhone, email: PhEnvelopeSimple, adresse: PhMapPin,
  recherche: PhMagnifyingGlass, suivant: PhArrowRight, retour: PhArrowLeft, valide: PhCheckCircle, horloge: PhClock, diplome: PhGraduationCap,
  alternance: PhBriefcase, certification: PhCertificate, ajouter: PhPlus, envoyer: PhPaperPlaneRight, deconnexion: PhSignOut, site: PhGlobe,
  cadenas: PhLockKey, accessibilite: PhWheelchair, faq: PhQuestion, blog: PhNewspaper, deplier: PhCaretDown, signature: PhSignature,
  alerte: PhWarningCircle, telecharger: PhDownloadSimple, menu: PhList, fermer: PhX, pause: PhPause, lecture: PhPlay,
  desktop: PhDesktop, table: PhTable, chart: PhChartLine, kanban: PhKanban, globe: PhGlobe, megaphone: PhMegaphone, code: PhCode,
  pen: PhPenNib, cube: PhCube, terminal: PhTerminalWindow, server: PhHardDrives, network: PhNetwork, shield: PhShieldCheck, brain: PhBrain,
  'ajouter-membre': PhUserPlus, etudiant: PhStudent, formateur: PhChalkboardTeacher, entreprise: PhBuildings, partenaires: PhHandshake,
  ordinateur: PhLaptop, externe: PhArrowUpRight, pdf: PhFilePdf, info: PhInfo, chevron: PhCaretRight, lecture_article: PhBookOpenText,
  minuteur: PhTimer, empreinte: PhFingerprint, badge: PhIdentificationBadge, groupe: PhUsersFour, assiduite: PhChartLine,
  utilisateur: PhUserCircle,
  euro: PhCurrencyEur, session: PhCalendarCheck, objectif: PhTarget, prerequis: PhListChecks, filtre: PhFunnel, programme: PhClipboardText, tarif: PhTag,
}
const composant = computed(() => icones[props.nom] || PhInfo)
</script>

<template>
  <component :is="composant" :size="taille" :weight="poids" :aria-hidden="libelle ? undefined : 'true'" :aria-label="libelle" :role="libelle ? 'img' : undefined" class="icone" />
</template>

<style scoped>
.icone { flex-shrink: 0; vertical-align: middle; }
</style>
