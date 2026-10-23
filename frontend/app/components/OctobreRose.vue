<!--
  Octobre rose : encart de sensibilisation au dépistage du cancer du sein, affiché uniquement en octobre.
  Il peut être masqué ; ce choix est mémorisé dans le navigateur (simple confort, sans conséquence).
-->
<script setup lang="ts">
const enOctobre = new Date().getMonth() === 9
const masque = ref(false)
const CLE = `octobre-rose-${new Date().getFullYear()}`
onMounted(() => { try { masque.value = localStorage.getItem(CLE) === '1' } catch { /* stockage indisponible */ } })
function masquer() {
  masque.value = true
  try { localStorage.setItem(CLE, '1') } catch { /* stockage indisponible */ }
}
</script>

<template>
  <Transition name="rose">
    <section v-if="enOctobre && !masque" class="conteneur octobre-rose" aria-labelledby="t-octobre-rose">
      <div class="encart">
        <svg class="ruban" viewBox="0 0 64 64" aria-hidden="true">
          <path d="M37 30 18 58M27 30l19 28" />
          <path d="M32 34c-6-6-9.5-12-9.5-17a9.5 9.5 0 0 1 19 0c0 5-3.5 11-9.5 17Z" />
        </svg>
        <div class="texte">
          <p class="sur-titre">Octobre rose</p>
          <h2 id="t-octobre-rose">Expernet Campus se mobilise contre le cancer du sein</h2>
          <p>De 50 à 74 ans, une mammographie de dépistage est recommandée <strong>tous les 2 ans</strong>, prise en charge à 100 % par l'Assurance maladie. Un dépistage précoce sauve des vies : parlez-en autour de vous.</p>
          <a href="https://www.service-public.gouv.fr/particuliers/actualites/A15216" target="_blank" rel="noopener" class="lien">S'informer sur le dépistage <Icone nom="externe" :taille="16" /></a>
        </div>
        <button type="button" class="fermer" aria-label="Masquer le message Octobre rose" @click="masquer"><Icone nom="fermer" :taille="18" /></button>
      </div>
    </section>
  </Transition>
</template>

<style scoped>
.octobre-rose { margin-top: var(--esp-5); }
.encart { position: relative; display: grid; grid-template-columns: 72px minmax(0, 1fr); gap: var(--esp-4); align-items: center; padding: var(--esp-4) var(--esp-5) var(--esp-4) var(--esp-4); border-radius: var(--rayon); background: linear-gradient(110deg, #FFF0F5 0%, #FFE1EC 55%, #FFD0E1 100%); border: 1px solid #F7B9CF; overflow: hidden; }
.encart::after { content: ''; position: absolute; right: -40px; top: -40px; width: 180px; height: 180px; border-radius: 50%; background: radial-gradient(circle, rgba(233, 30, 99, .14), transparent 70%); pointer-events: none; }
.ruban { width: 72px; height: 72px; fill: none; stroke: #D81B60; stroke-width: 7; stroke-linecap: round; stroke-linejoin: round; animation: battement 2.4s ease-in-out infinite; transform-origin: 50% 40%; }
.sur-titre { color: #AD1457; margin: 0 0 4px; }
h2 { margin: 0 0 8px; color: #6A0F35; font-size: 1.35rem; }
.texte p { margin: 0 0 10px; color: #4A1027; max-width: 75ch; }
.lien { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; color: #AD1457; }
.lien:hover { color: #6A0F35; }
.fermer { position: absolute; top: 10px; right: 10px; width: 36px; height: 36px; border-radius: 50%; border: 0; background: rgba(255, 255, 255, .7); color: #6A0F35; cursor: pointer; display: grid; place-items: center; z-index: 1; }
.fermer:hover { background: #fff; }
@keyframes battement { 0%, 100% { transform: scale(1); } 15% { transform: scale(1.08); } 30% { transform: scale(1); } }
.rose-leave-active { transition: opacity .25s, transform .25s; }
.rose-leave-to { opacity: 0; transform: translateY(-8px); }
@media (prefers-reduced-motion: reduce) { .ruban { animation: none; } }
@media (max-width: 640px) { .encart { grid-template-columns: 1fr; padding: var(--esp-4); } .ruban { width: 52px; height: 52px; } }
</style>
