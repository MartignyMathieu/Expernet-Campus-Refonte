<!--
  Sélecteur d'émoticônes de la messagerie : jeu d'émoticônes Unicode courantes, sans dépendance.
  Émet « choisir » avec le caractère ; se ferme avec Échap ou un clic à l'extérieur.
-->
<script setup lang="ts">
const emit = defineEmits<{ choisir: [emoji: string]; fermer: [] }>()

const categories = [
  { nom: 'Fréquents', icone: '🕘', emojis: ['👍', '😀', '😂', '🙏', '👏', '🎉', '✅', '❤️', '😉', '🔥', '💪', '👌'] },
  { nom: 'Visages', icone: '😀', emojis: ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🙂', '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😋', '😜', '🤓', '😎', '🤔', '🤨', '😐', '😑', '😶', '🙄', '😏', '😬', '😌', '😴', '🤒', '🤯', '🥳', '😕', '😟', '😮', '😲', '😳', '🥺', '😢', '😭', '😱', '😤', '😡', '🤗', '🤭', '🫡', '😷', '🤐'] },
  { nom: 'Gestes', icone: '👋', emojis: ['👋', '🤚', '✋', '👌', '🤌', '✌️', '🤞', '🤟', '🤙', '👈', '👉', '👆', '👇', '☝️', '👍', '👎', '✊', '👊', '👏', '🙌', '🫶', '👐', '🤝', '🙏', '💪', '🧠', '👀'] },
  { nom: 'Campus', icone: '🎓', emojis: ['🎓', '📚', '📖', '📝', '✏️', '📎', '📌', '📅', '⏰', '💻', '🖥️', '⌨️', '🖱️', '📱', '📊', '📈', '🗂️', '📁', '📄', '🔒', '🔑', '🛡️', '🧪', '💡', '🚀', '☕', '🍌', '🏝️', '🌋', '🌴'] },
  { nom: 'Symboles', icone: '✅', emojis: ['✅', '☑️', '❌', '⚠️', '❗', '❓', '💯', '➕', '➖', '🔴', '🟠', '🟡', '🟢', '🔵', '⭐', '✨', '🎯', '🏆', '🎉', '🎊', '❤️', '🧡', '💛', '💚', '💙', '💜', '🤍', '🔥'] },
]
const active = ref(0)
const racine = ref<HTMLElement | null>(null)

function surClic(e: MouseEvent) { if (racine.value && !racine.value.contains(e.target as Node)) emit('fermer') }
function surTouche(e: KeyboardEvent) { if (e.key === 'Escape') emit('fermer') }
onMounted(() => { setTimeout(() => document.addEventListener('click', surClic)); document.addEventListener('keydown', surTouche) })
onBeforeUnmount(() => { document.removeEventListener('click', surClic); document.removeEventListener('keydown', surTouche) })
</script>

<template>
  <div ref="racine" class="selecteur" role="dialog" aria-label="Choisir une émoticône">
    <div class="onglets" role="tablist">
      <button v-for="(c, i) in categories" :key="c.nom" type="button" role="tab" :aria-selected="active === i" :title="c.nom" :class="{ actif: active === i }" @click="active = i">
        <span aria-hidden="true">{{ c.icone }}</span><span class="sr-only">{{ c.nom }}</span>
      </button>
    </div>
    <p class="titre">{{ categories[active].nom }}</p>
    <div class="grille-emoji" role="tabpanel">
      <button v-for="e in categories[active].emojis" :key="e" type="button" class="emoji" :aria-label="`Insérer ${e}`" @click="emit('choisir', e)">{{ e }}</button>
    </div>
  </div>
</template>

<style scoped>
.selecteur { position: absolute; bottom: calc(100% + 8px); left: 0; width: min(320px, calc(100vw - 32px)); background: #fff; border: 1px solid var(--gris-bord); border-radius: 16px; box-shadow: var(--ombre-forte); padding: 8px; z-index: 30; animation: apparition .16s ease-out; transform-origin: bottom left; }
.onglets { display: flex; gap: 2px; border-bottom: 1px solid var(--gris-bord); padding-bottom: 6px; }
.onglets button { flex: 1; height: 34px; border: 0; background: none; border-radius: 8px; cursor: pointer; font-size: 1.1rem; }
.onglets button:hover { background: var(--gris-fond); }
.onglets button.actif { background: #E1F3FB; }
.titre { margin: 8px 4px 4px; font-size: .74rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--texte-doux); }
.grille-emoji { display: grid; grid-template-columns: repeat(8, 1fr); max-height: 196px; overflow-y: auto; }
.emoji { aspect-ratio: 1; border: 0; background: none; border-radius: 8px; font-size: 1.35rem; cursor: pointer; line-height: 1; transition: transform .1s; }
.emoji:hover { background: var(--gris-fond); transform: scale(1.18); }
@keyframes apparition { from { opacity: 0; transform: translateY(6px) scale(.97); } to { opacity: 1; transform: none; } }
</style>
