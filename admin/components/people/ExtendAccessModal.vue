<script setup lang="ts">
import type { AdminUser } from '~/types'

/**
 * Extend an intern's access window.
 *
 * The current expiry is shown alongside the resulting new one, because "+30
 * days" from an *already lapsed* date is not the same as thirty days from
 * today — and getting that wrong silently is how someone ends up still locked
 * out after being told they were extended. Extending a lapsed account runs
 * from today; extending a live one runs from its existing expiry.
 */
const props = defineProps<{ member: AdminUser | null }>()
const open = defineModel<boolean>('open', { default: false })
const { formatDate, daysUntil, now } = useFormatters()

const days = ref(30)
const presets = [7, 30, 90]

const lapsed = computed(() => {
  const d = props.member?.accessExpiresAt ? daysUntil(props.member.accessExpiresAt) : null
  return d !== null && d < 0
})

const newExpiry = computed(() => {
  if (!props.member) return null
  const base = lapsed.value || !props.member.accessExpiresAt
    ? now.value
    : new Date(props.member.accessExpiresAt)
  return new Date(base.getTime() + days.value * 864e5).toISOString()
})
</script>

<template>
  <UiModal
    v-model:open="open" size="sm"
    title="Extend access"
    :description="member ? `${member.name} · ${member.jobTitle}` : undefined"
  >
    <div v-if="member" class="flex flex-col gap-4">
      <dl class="space-y-2 text-ui">
        <div class="flex justify-between gap-3">
          <dt class="text-fg-muted">Current expiry</dt>
          <dd :class="lapsed ? 'text-danger' : 'text-fg-strong'">
            {{ formatDate(member.accessExpiresAt) }}
            <span v-if="lapsed" class="text-meta">(lapsed)</span>
          </dd>
        </div>
        <div class="flex justify-between gap-3 border-t border-border pt-2">
          <dt class="text-fg-muted">New expiry</dt>
          <dd class="font-medium text-fg-strong">{{ formatDate(newExpiry) }}</dd>
        </div>
      </dl>

      <p v-if="lapsed" class="rounded-lg bg-warning-soft px-3 py-2.5 text-meta text-warning">
        This account has already lapsed, so the extension runs from today rather than from the
        old expiry date.
      </p>

      <div>
        <p class="field-label">Extend by</p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="p in presets" :key="p"
            type="button"
            class="rounded-lg border px-3 py-2 text-ui transition-colors"
            :class="days === p
              ? 'border-accent bg-accent-soft font-medium text-accent-text'
              : 'border-border text-fg-muted hover:bg-bg-sunken'"
            @click="days = p"
          >{{ p }} days</button>
          <input
            v-model.number="days" type="number" min="1" max="365"
            aria-label="Custom number of days"
            class="field w-24"
          >
        </div>
      </div>

      <div v-if="member.accessExtensions.length">
        <p class="field-label">Previous extensions</p>
        <ul class="space-y-1.5 text-meta text-fg-muted">
          <li v-for="(e, i) in member.accessExtensions" :key="i">
            +{{ e.days }} days by {{ e.extendedByName }} on {{ formatDate(e.extendedAt) }}
          </li>
        </ul>
      </div>
    </div>

    <template #footer>
      <UiButton variant="secondary" size="sm" @click="open = false">Cancel</UiButton>
      <UiButton size="sm" @click="open = false">Extend access</UiButton>
    </template>
  </UiModal>
</template>
