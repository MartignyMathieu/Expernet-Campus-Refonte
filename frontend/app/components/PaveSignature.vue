<!-- Zone de signature au doigt / à la souris (Pointer Events). Émet une image PNG en data URL. -->
<script setup lang="ts">
const emit = defineEmits<{ (e: 'change', valeur: string | null): void }>()
const canvas = ref<HTMLCanvasElement | null>(null)
let ctx: CanvasRenderingContext2D | null = null
let dessin = false; let traits = 0

onMounted(() => {
  const c = canvas.value!
  const ratio = window.devicePixelRatio || 1
  c.width = c.offsetWidth * ratio; c.height = c.offsetHeight * ratio
  ctx = c.getContext('2d')!; ctx.scale(ratio, ratio)
  ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#191E23'
})
function pos(e: PointerEvent) { const r = canvas.value!.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top] }
function debut(e: PointerEvent) { dessin = true; canvas.value!.setPointerCapture(e.pointerId); const [x, y] = pos(e); ctx!.beginPath(); ctx!.moveTo(x, y) }
function trace(e: PointerEvent) { if (!dessin) return; const [x, y] = pos(e); ctx!.lineTo(x, y); ctx!.stroke() }
function fin() { if (!dessin) return; dessin = false; traits++; emit('change', traits ? canvas.value!.toDataURL('image/png') : null) }
function effacer() { ctx!.clearRect(0, 0, canvas.value!.width, canvas.value!.height); traits = 0; emit('change', null) }
defineExpose({ effacer })
</script>

<template>
  <div class="pave">
    <canvas ref="canvas" role="img" aria-label="Zone de signature : dessinez votre signature avec le doigt ou la souris"
            @pointerdown="debut" @pointermove="trace" @pointerup="fin" @pointerleave="fin" />
    <button type="button" class="btn btn-secondaire" @click="effacer">Effacer</button>
  </div>
</template>

<style scoped>
.pave { display: grid; gap: 8px; }
canvas { width: 100%; height: 180px; background: repeating-linear-gradient(#fff 0 139px, #C9CED4 139px 140px, #fff 140px 180px); border: 2px dashed #C9CED4; border-radius: 10px; touch-action: none; cursor: crosshair; }
</style>
