<script setup lang="ts">
import type { SeriesPoint } from '~/types'

/**
 * Two-series line chart with a filled area under the primary — Figma 1:24956
 * (Dashboard "Revenue" panel) and 23:1972 (Team "Overall Performance").
 *
 * Hand-rolled SVG rather than a chart library: the kit's charts are flat,
 * hairline and unlabelled, so a library would cost ~150KB to draw what a
 * catmull-rom path draws in fifty lines — and this way stroke colours are
 * theme tokens, so dark mode needs no configuration.
 */
const props = withDefaults(defineProps<{
  primary: SeriesPoint[]
  secondary?: SeriesPoint[]
  height?: number
  /** Formats the y-axis ticks; defaults to a compact number. */
  formatY?: (n: number) => string
}>(), { height: 240, secondary: () => [] })

const W = 720
const PAD = { top: 12, right: 8, bottom: 26, left: 40 }

const H = computed(() => props.height)
const all = computed(() => [...props.primary, ...props.secondary].map((p) => p.value))
const maxY = computed(() => {
  const m = Math.max(1, ...all.value)
  // Round up to a clean tick so the axis labels aren't 17,432.
  const mag = 10 ** Math.floor(Math.log10(m))
  return Math.ceil(m / mag) * mag
})

const innerW = W - PAD.left - PAD.right
const innerH = computed(() => H.value - PAD.top - PAD.bottom)

const x = (i: number, n: number) => PAD.left + (n <= 1 ? innerW / 2 : (i / (n - 1)) * innerW)
const y = (v: number) => PAD.top + innerH.value - (v / maxY.value) * innerH.value

/** Catmull-rom → cubic bezier, for the soft curve the frames show. */
function smoothPath(points: SeriesPoint[]): string {
  const n = points.length
  if (!n) return ''
  const pts = points.map((p, i) => [x(i, n), y(p.value)] as const)
  if (n < 3) return `M${pts.map((p) => p.join(',')).join(' L')}`

  let d = `M${pts[0]![0]},${pts[0]![1]}`
  for (let i = 0; i < n - 1; i++) {
    const p0 = pts[Math.max(0, i - 1)]!
    const p1 = pts[i]!
    const p2 = pts[i + 1]!
    const p3 = pts[Math.min(n - 1, i + 2)]!
    const c1x = p1[0] + (p2[0] - p0[0]) / 6
    const c1y = p1[1] + (p2[1] - p0[1]) / 6
    const c2x = p2[0] - (p3[0] - p1[0]) / 6
    const c2y = p2[1] - (p3[1] - p1[1]) / 6
    d += ` C${c1x},${c1y} ${c2x},${c2y} ${p2[0]},${p2[1]}`
  }
  return d
}

const primaryPath = computed(() => smoothPath(props.primary))
const secondaryPath = computed(() => smoothPath(props.secondary))
const areaPath = computed(() =>
  props.primary.length
    ? `${primaryPath.value} L${x(props.primary.length - 1, props.primary.length)},${PAD.top + innerH.value} L${PAD.left},${PAD.top + innerH.value} Z`
    : '',
)

const ticks = computed(() => {
  const step = maxY.value / 3
  return [0, step, step * 2, maxY.value]
})

const fmt = (n: number) =>
  props.formatY ? props.formatY(n) : new Intl.NumberFormat('en-GB', { notation: 'compact' }).format(n)

const uid = useId()
</script>

<template>
  <svg
    :viewBox="`0 0 ${W} ${H}`" class="w-full" :style="{ height: `${H}px` }"
    preserveAspectRatio="none" role="img" aria-label="Line chart"
  >
    <defs>
      <linearGradient :id="`fill-${uid}`" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="rgb(var(--chart-1))" stop-opacity="0.20" />
        <stop offset="100%" stop-color="rgb(var(--chart-1))" stop-opacity="0" />
      </linearGradient>
    </defs>

    <!-- Gridlines + y ticks -->
    <g>
      <template v-for="t in ticks" :key="t">
        <line
          :x1="PAD.left" :x2="W - PAD.right" :y1="y(t)" :y2="y(t)"
          stroke="rgb(var(--chart-grid))" stroke-width="1"
        />
        <text
          :x="PAD.left - 8" :y="y(t) + 4" text-anchor="end"
          fill="rgb(var(--fg-faint))" font-size="11"
        >{{ fmt(t) }}</text>
      </template>
    </g>

    <!-- x labels -->
    <text
      v-for="(p, i) in primary" :key="p.label"
      :x="x(i, primary.length)" :y="H - 8" text-anchor="middle"
      fill="rgb(var(--fg-faint))" font-size="11"
    >{{ p.label }}</text>

    <path v-if="areaPath" :d="areaPath" :fill="`url(#fill-${uid})`" />
    <path
      v-if="secondaryPath" :d="secondaryPath" fill="none"
      stroke="rgb(var(--chart-6))" stroke-width="1.5" stroke-dasharray="4 4"
      vector-effect="non-scaling-stroke"
    />
    <path
      v-if="primaryPath" :d="primaryPath" fill="none"
      stroke="rgb(var(--chart-1))" stroke-width="2"
      stroke-linecap="round" vector-effect="non-scaling-stroke"
    />
  </svg>
</template>
