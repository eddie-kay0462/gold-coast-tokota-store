<script setup lang="ts">
import { PhUploadSimple, PhWarningCircle } from '@phosphor-icons/vue'

/**
 * Drag-and-drop upload target.
 *
 * Validates type and size on the client for immediate feedback, but the
 * comment matters more than the code: this is a convenience, not a control.
 * The server must reject the same things independently — README Feature 7 says
 * so for booking reference images, and it holds for every upload path.
 */
const props = withDefaults(defineProps<{
  accept?: string
  maxSizeMb?: number
  multiple?: boolean
}>(), { accept: 'image/jpeg,image/png,image/webp', maxSizeMb: 5, multiple: true })

const emit = defineEmits<{ (e: 'files', files: File[]): void }>()

const dragging = ref(false)
const error = ref<string | null>(null)
const input = ref<HTMLInputElement | null>(null)

const allowed = computed(() => props.accept.split(',').map((t) => t.trim()))

function accept(list: FileList | null) {
  error.value = null
  if (!list?.length) return

  const files = Array.from(list)
  const badType = files.find((f) => !allowed.value.includes(f.type))
  if (badType) {
    error.value = `${badType.name} isn't a supported format. Accepted: JPEG, PNG or WebP.`
    return
  }
  const tooBig = files.find((f) => f.size > props.maxSizeMb * 1024 * 1024)
  if (tooBig) {
    error.value = `${tooBig.name} is larger than ${props.maxSizeMb}MB.`
    return
  }
  emit('files', files)
}

function onDrop(e: DragEvent) {
  dragging.value = false
  accept(e.dataTransfer?.files ?? null)
}
</script>

<template>
  <div>
    <div
      class="flex flex-col items-center gap-2 rounded-lg border-2 border-dashed px-6 py-8 text-center transition-colors"
      :class="dragging ? 'border-accent bg-accent-soft' : 'border-border'"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <PhUploadSimple :size="24" class="text-fg-faint" />
      <p class="text-ui text-fg">
        Drop files here, or
        <button
          type="button" class="text-accent-text underline underline-offset-4"
          @click="input?.click()"
        >browse</button>
      </p>
      <p class="text-meta text-fg-faint">
        JPEG, PNG or WebP · up to {{ maxSizeMb }}MB each
      </p>
      <input
        ref="input" type="file" class="sr-only"
        :accept="accept" :multiple="multiple"
        @change="accept(($event.target as HTMLInputElement).files)"
      >
    </div>

    <p v-if="error" class="mt-2 flex items-start gap-1.5 text-meta text-danger" role="alert">
      <PhWarningCircle :size="14" class="mt-px shrink-0" />
      {{ error }}
    </p>
  </div>
</template>
