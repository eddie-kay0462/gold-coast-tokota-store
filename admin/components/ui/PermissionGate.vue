<script setup lang="ts">
import { PhLock } from '@phosphor-icons/vue'
import type { Capability } from '~/utils/permissions'

/**
 * Renders its slot only if the viewer holds the capability, otherwise a plain
 * explanation. README Feature 9: staff hitting a restricted action must get
 * "a clear, non-technical error message (not a raw 403 JSON blob)".
 *
 * `quiet` renders nothing at all — for cases where the absence is not worth
 * remarking on, like a single toolbar button.
 */
const props = defineProps<{ capability: Capability; quiet?: boolean }>()
const { can, whyNot } = useAuth()
const allowed = computed(() => can(props.capability))
</script>

<template>
  <slot v-if="allowed" />
  <template v-else-if="!quiet">
    <slot name="denied">
      <div class="card flex items-start gap-3 px-4 py-3.5">
        <span class="mt-px shrink-0 text-fg-faint"><PhLock :size="18" /></span>
        <p class="text-ui text-fg-muted">{{ whyNot(capability) }}</p>
      </div>
    </slot>
  </template>
</template>
