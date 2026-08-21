<script setup lang="ts">
import { PhClock, PhWarningCircle } from '@phosphor-icons/vue'

/**
 * Intern access countdown.
 *
 * Interns hold time-boxed access that an Admin can extend. Someone whose
 * account lapses mid-shift needs to understand why the buttons stopped
 * working, so the state is announced rather than inferred from a silent
 * read-only mode.
 */
const { accessWarningLevel, accessDaysRemaining, accessExpiresAt, isImpersonating, role } = useAuth()
const { formatDate } = useFormatters()
</script>

<template>
  <div
    v-if="accessWarningLevel !== 'none'"
    class="flex items-start gap-3 border-b px-4 py-2.5 md:px-6"
    :class="accessWarningLevel === 'lapsed'
      ? 'border-danger/30 bg-danger-soft text-danger'
      : accessWarningLevel === 'urgent'
        ? 'border-warning/30 bg-warning-soft text-warning'
        : 'border-accent/30 bg-accent-soft text-accent-text'"
    role="status"
  >
    <component
      :is="accessWarningLevel === 'lapsed' ? PhWarningCircle : PhClock"
      :size="18" class="mt-px shrink-0"
    />
    <p class="text-ui">
      <template v-if="accessWarningLevel === 'lapsed'">
        <strong class="font-medium">This account’s access period ended</strong>
        on {{ formatDate(accessExpiresAt) }}. Everything is read-only until an Admin extends it
        from the Team page.
      </template>
      <template v-else>
        <strong class="font-medium">
          {{ role === 'intern' ? 'Intern' : 'Temporary' }} access ends in
          {{ accessDaysRemaining }} {{ accessDaysRemaining === 1 ? 'day' : 'days' }}
        </strong>
        — on {{ formatDate(accessExpiresAt) }}. Ask an Admin to extend it if you need longer.
      </template>
      <span v-if="isImpersonating" class="ml-1 opacity-70">(previewing a role)</span>
    </p>
  </div>
</template>
