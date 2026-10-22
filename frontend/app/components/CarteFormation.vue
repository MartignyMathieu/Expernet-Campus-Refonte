<script setup lang="ts">
defineProps<{ f: any }>()
</script>

<template>
  <article class="carte-formation">
    <div class="visuel">
      <img :src="`/images/formations/${f.slug}.webp`" alt="" loading="lazy" width="720" height="347">
      <span class="niveau">{{ f.niveau_libelle }}</span>
    </div>
    <div class="corps">
      <p class="meta">
        <span class="pastille" :class="f.filiere?.code === 'marketing' ? 'pastille-orange' : 'pastille-bleue'">{{ f.filiere?.libelle }}</span>
        <span v-if="f.eligible_cpf" class="pastille pastille-jaune">CPF</span>
      </p>
      <h3><NuxtLink :to="`/formations/${f.slug}`" class="lien-etire">{{ f.intitule }}</NuxtLink></h3>
      <p class="accroche">{{ f.accroche }}</p>
      <p class="bas">
        <span class="rentree"><Icone nom="certification" :taille="15" />{{ f.code_rncp }}</span>
        <span v-if="f.prochaine_rentree" class="rentree"><Icone nom="planning" :taille="15" />Rentrée {{ new Date(f.prochaine_rentree).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' }) }}</span>
      </p>
    </div>
  </article>
</template>

<style scoped>
.carte-formation { position: relative; display: flex; flex-direction: column; background: #fff; border: 1px solid var(--gris-bord); border-radius: var(--rayon); overflow: hidden; box-shadow: var(--ombre); transition: transform .2s, box-shadow .2s; }
.carte-formation:hover { transform: translateY(-4px); box-shadow: var(--ombre-forte); }
.carte-formation:focus-within { outline: 3px solid var(--orange); outline-offset: 2px; }
.visuel { position: relative; aspect-ratio: 16 / 8; overflow: hidden; background: var(--gris-fond); }
.visuel img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s; }
.carte-formation:hover .visuel img { transform: scale(1.05); }
.niveau { position: absolute; left: 14px; bottom: 14px; background: var(--primaire); color: #fff; font-family: var(--police-titre); font-weight: 800; padding: 4px 12px; border-radius: 8px; font-size: .9rem; box-shadow: 0 4px 12px rgba(0,0,0,.2); }
.corps { padding: 18px 20px 16px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
.meta { display: flex; gap: 6px; margin: 0; }
h3 { margin: 0; font-size: 1.08rem; }
.lien-etire { color: var(--encre); text-decoration: none; }
.lien-etire::after { content: ''; position: absolute; inset: 0; }
.accroche { margin: 0; color: var(--texte-doux); font-size: .93rem; }
.rentree { display: inline-flex; align-items: center; gap: 5px; }
.bas { margin: auto 0 0; display: flex; justify-content: space-between; font-size: .82rem; padding-top: 12px; border-top: 1px solid var(--gris-bord); color: var(--texte-doux); }
</style>
