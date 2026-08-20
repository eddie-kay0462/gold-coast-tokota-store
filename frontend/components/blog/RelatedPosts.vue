<script setup lang="ts">
import type { ApiPost } from '~/utils/newsPosts'

defineProps<{ posts: ApiPost[] }>()
</script>

<template>
  <section v-if="posts.length" class="w-full">
    <h2 class="sr-only">More stories</h2>
    <ul class="grid w-full grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <li v-for="post in posts" :key="post.slug" class="min-w-0">
        <NuxtLink :to="`/blog/${post.slug}`" class="group flex w-full flex-col gap-5">
          <img
            :src="post.cover_image || '/design/news-placeholder.png'"
            :alt="post.title"
            class="h-[280px] w-full object-cover lg:h-[413px]"
            loading="lazy"
          >
          <div class="flex w-full flex-col items-start gap-3">
            <h3 class="w-full text-display-md font-light text-black group-hover:underline">
              {{ post.title }}
            </h3>
            <span
              v-if="post.category"
              class="flex items-center justify-center rounded-[30px] border border-line px-5 py-1 text-center text-caption font-normal text-black"
            >
              {{ post.category }}
            </span>
          </div>
        </NuxtLink>
      </li>
    </ul>
  </section>
</template>
