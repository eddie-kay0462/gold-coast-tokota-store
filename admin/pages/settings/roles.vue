<script setup lang="ts">
import { PhCheck, PhMinus } from '@phosphor-icons/vue'
import { ADMIN_ROLE_LABELS } from '~/types'
import { CAPABILITIES, ROLE_CAPABILITIES, ROLE_DESCRIPTIONS, ROLE_ORDER, type Capability } from '~/utils/permissions'
import { humanise } from '~/utils/formatters'

/**
 * Roles & access — the permission matrix, rendered from the same table the app
 * enforces rather than a hand-maintained copy. If someone changes a capability
 * in `utils/permissions.ts`, this page cannot silently disagree with reality.
 */
useHead({ title: 'Roles & access' })

/** Group capabilities by their prefix so the matrix reads in sections. */
const groups = computed(() => {
  const map = new Map<string, Capability[]>()
  for (const c of CAPABILITIES) {
    const [area] = c.split('.')
    const list = map.get(area!) ?? []
    list.push(c)
    map.set(area!, list)
  }
  return [...map.entries()].map(([area, caps]) => ({ area: humanise(area), caps }))
})

const has = (role: typeof ROLE_ORDER[number], cap: Capability) =>
  ROLE_CAPABILITIES[role].includes(cap)
</script>

<template>
  <SettingsShell
    title="Roles & access"
    description="What each tier can do. The dashboard hides what you cannot use; the API enforces it."
  >
    <div class="admin-stack">
      <div class="grid gap-4 sm:grid-cols-2">
        <div v-for="role in ROLE_ORDER" :key="role" class="card card-pad">
          <div class="flex items-baseline justify-between gap-2">
            <h2 class="text-ui font-medium text-fg-strong">{{ ADMIN_ROLE_LABELS[role] }}</h2>
            <span class="text-meta text-fg-faint">
              {{ ROLE_CAPABILITIES[role].length }}/{{ CAPABILITIES.length }}
            </span>
          </div>
          <p class="mt-1.5 text-meta text-fg-muted">{{ ROLE_DESCRIPTIONS[role] }}</p>
        </div>
      </div>

      <section class="card overflow-hidden">
        <div class="border-b border-border p-4 md:p-5">
          <h2 class="card-title">Permission matrix</h2>
          <p class="mt-1 text-ui text-fg-muted">
            Generated from the permission table the application actually checks, so it cannot
            drift out of date.
          </p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full min-w-[560px] border-collapse">
            <thead class="table-head">
              <tr class="border-b border-border">
                <th class="px-4 py-3 text-left font-medium">Capability</th>
                <th v-for="role in ROLE_ORDER" :key="role" class="w-24 px-2 py-3 text-center font-medium">
                  {{ ADMIN_ROLE_LABELS[role].replace(' ', ' ') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <template v-for="g in groups" :key="g.area">
                <tr class="border-t border-border bg-bg-sunken">
                  <td :colspan="ROLE_ORDER.length + 1" class="px-4 py-1.5 text-meta font-medium uppercase tracking-wide text-fg-muted">
                    {{ g.area }}
                  </td>
                </tr>
                <tr v-for="cap in g.caps" :key="cap" class="border-t border-border">
                  <td class="px-4 py-2 text-ui text-fg">{{ humanise(cap.split('.')[1] ?? cap) }}</td>
                  <!--
                    The allowed/denied state rides on the cell's aria-label
                    rather than a visually-hidden <span>. Tailwind's `.sr-only`
                    is `position: absolute`, and inside a horizontally-scrolled
                    table that text extended the *document's* scroll region —
                    the page gained 139px of sideways scroll at 390px, which
                    the responsive sweep caught. Labelling the cell conveys the
                    same thing to a screen reader with nothing in the layout.
                  -->
                  <td
                    v-for="role in ROLE_ORDER" :key="role" class="px-2 py-2 text-center"
                    :aria-label="`${ADMIN_ROLE_LABELS[role]}: ${has(role, cap) ? 'allowed' : 'not allowed'}`"
                  >
                    <PhCheck v-if="has(role, cap)" :size="14" weight="bold" class="mx-auto text-success" aria-hidden="true" />
                    <PhMinus v-else :size="14" class="mx-auto text-fg-faint" aria-hidden="true" />
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </section>

      <p class="text-meta text-fg-faint">
        Intern accounts additionally carry an expiry date. Once it passes, the account keeps its
        role but drops to read-only until an Admin extends it from the Team page.
      </p>
    </div>
  </SettingsShell>
</template>
