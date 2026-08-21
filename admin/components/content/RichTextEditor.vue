<script setup lang="ts">
import { EditorContent, useEditor } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Placeholder from '@tiptap/extension-placeholder'
import Link from '@tiptap/extension-link'
import {
  PhTextB, PhTextItalic, PhTextStrikethrough, PhCode, PhLink, PhListBullets,
  PhListNumbers, PhQuotes, PhArrowCounterClockwise, PhArrowClockwise, PhMinus,
} from '@phosphor-icons/vue'

/**
 * WYSIWYG editor — Figma node 26:1297 (Blog frame): a formatting toolbar over
 * a writing surface with a character counter and Publish in the corner.
 *
 * The scaffold's `PageEditor.vue` was a bare `<textarea>` labelled "Rich text
 * editor", which the CMS acceptance criteria cannot be met with. Tiptap gives
 * a real document model; the toolbar is the kit's, mapped onto it.
 *
 * Output is HTML. It MUST still be sanitised server-side on save — README
 * Feature 9 names stored XSS explicitly, and no amount of client-side
 * escaping substitutes for that.
 */
const props = withDefaults(defineProps<{
  placeholder?: string
  maxLength?: number
  editable?: boolean
}>(), { placeholder: 'Begin writing here…', maxLength: 8000, editable: true })

const model = defineModel<string>({ default: '' })

const editor = useEditor({
  content: model.value,
  editable: props.editable,
  extensions: [
    StarterKit.configure({ heading: { levels: [2, 3, 4] } }),
    Placeholder.configure({ placeholder: props.placeholder }),
    Link.configure({ openOnClick: false, autolink: true }),
  ],
  editorProps: {
    attributes: {
      class: 'prose-admin min-h-[320px] focus:outline-none',
    },
  },
  onUpdate: ({ editor: e }) => { model.value = e.getHTML() },
})

// Keep in sync when the parent swaps documents (navigating between posts).
watch(model, (v) => {
  if (editor.value && v !== editor.value.getHTML()) editor.value.commands.setContent(v, { emitUpdate: false })
})

onBeforeUnmount(() => editor.value?.destroy())

const charCount = computed(() => editor.value?.storage.characterCount?.characters?.() ?? editor.value?.getText().length ?? 0)
const overLimit = computed(() => charCount.value > props.maxLength)

const is = (name: string, attrs?: Record<string, unknown>) => editor.value?.isActive(name, attrs) ?? false

function setLink() {
  const previous = editor.value?.getAttributes('link').href as string | undefined
  const url = window.prompt('Link URL', previous ?? 'https://')
  if (url === null) return
  if (url === '') { editor.value?.chain().focus().extendMarkRange('link').unsetLink().run(); return }
  editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

interface Tool { icon: unknown; label: string; run: () => void; active?: () => boolean }
const tools = computed<(Tool | 'divider')[]>(() => [
  { icon: PhTextB, label: 'Bold', run: () => editor.value?.chain().focus().toggleBold().run(), active: () => is('bold') },
  { icon: PhTextItalic, label: 'Italic', run: () => editor.value?.chain().focus().toggleItalic().run(), active: () => is('italic') },
  { icon: PhTextStrikethrough, label: 'Strikethrough', run: () => editor.value?.chain().focus().toggleStrike().run(), active: () => is('strike') },
  'divider',
  { icon: PhListBullets, label: 'Bullet list', run: () => editor.value?.chain().focus().toggleBulletList().run(), active: () => is('bulletList') },
  { icon: PhListNumbers, label: 'Numbered list', run: () => editor.value?.chain().focus().toggleOrderedList().run(), active: () => is('orderedList') },
  { icon: PhQuotes, label: 'Quote', run: () => editor.value?.chain().focus().toggleBlockquote().run(), active: () => is('blockquote') },
  { icon: PhMinus, label: 'Divider', run: () => editor.value?.chain().focus().setHorizontalRule().run() },
  'divider',
  { icon: PhLink, label: 'Link', run: setLink, active: () => is('link') },
  { icon: PhCode, label: 'Code', run: () => editor.value?.chain().focus().toggleCode().run(), active: () => is('code') },
  'divider',
  { icon: PhArrowCounterClockwise, label: 'Undo', run: () => editor.value?.chain().focus().undo().run() },
  { icon: PhArrowClockwise, label: 'Redo', run: () => editor.value?.chain().focus().redo().run() },
])

const headings = [
  { level: 0, label: 'Body' },
  { level: 2, label: 'H2' },
  { level: 3, label: 'H3' },
  { level: 4, label: 'H4' },
]

function setHeading(level: number) {
  if (level === 0) editor.value?.chain().focus().setParagraph().run()
  else editor.value?.chain().focus().toggleHeading({ level: level as 2 | 3 | 4 }).run()
}
</script>

<template>
  <div class="card flex flex-col overflow-hidden">
    <!-- Toolbar (Figma 26:1297) -->
    <div class="flex flex-wrap items-center gap-0.5 border-b border-border p-2">
      <button
        v-for="h in headings" :key="h.level"
        type="button"
        class="h-8 rounded px-2 text-meta transition-colors"
        :class="(h.level === 0 ? is('paragraph') : is('heading', { level: h.level }))
          ? 'bg-bg-sunken font-medium text-fg-strong'
          : 'text-fg-muted hover:bg-bg-sunken hover:text-fg-strong'"
        @click="setHeading(h.level)"
      >{{ h.label }}</button>

      <span class="mx-1 h-5 w-px bg-border" />

      <template v-for="(t, i) in tools" :key="i">
        <span v-if="t === 'divider'" class="mx-1 h-5 w-px bg-border" />
        <button
          v-else
          type="button" class="toolbar-btn size-8"
          :class="t.active?.() && 'bg-bg-sunken text-fg-strong'"
          :aria-label="t.label" :title="t.label"
          @click="t.run()"
        >
          <component :is="t.icon" :size="16" />
        </button>
      </template>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto p-4 md:p-5">
      <EditorContent :editor="editor" />
    </div>

    <div class="flex items-center gap-3 border-t border-border px-4 py-2.5">
      <span class="text-meta" :class="overLimit ? 'text-danger' : 'text-fg-faint'">
        {{ charCount }}/{{ maxLength }}
      </span>
      <span class="ml-auto"><slot name="footer" /></span>
    </div>
  </div>
</template>

<style>
/* Editor typography. Scoped to the editor rather than added to the global
   base layer, because nothing else in the admin renders long-form prose. */
.prose-admin { @apply text-ui leading-relaxed text-fg; }
.prose-admin > * + * { @apply mt-3; }
.prose-admin h2 { @apply mt-6 text-title font-medium text-fg-strong; }
.prose-admin h3 { @apply mt-5 text-section font-medium text-fg-strong; }
.prose-admin h4 { @apply mt-4 text-ui font-medium text-fg-strong; }
.prose-admin ul { @apply list-disc space-y-1 pl-5; }
.prose-admin ol { @apply list-decimal space-y-1 pl-5; }
.prose-admin blockquote { @apply border-l-2 border-accent pl-4 text-fg-muted; }
.prose-admin a { @apply text-accent-text underline underline-offset-4; }
.prose-admin code { @apply rounded-sm bg-bg-sunken px-1 py-0.5 font-mono text-meta; }
.prose-admin hr { @apply my-6 border-border; }
.prose-admin p.is-editor-empty:first-child::before {
  content: attr(data-placeholder);
  @apply pointer-events-none float-left h-0 text-fg-faint;
}
</style>
