<script setup lang="ts">
import { whatsappMessage } from '~/utils/whatsapp'
/**
 * Gift cards — BUILT BUT DELIBERATELY INACTIVE.
 *
 * There is no gift-card model, no balance ledger and no redemption at checkout;
 * the footer links here ("Redeem a Gift Card") and 404ing was worse than saying
 * so. Both forms below validate and then explain themselves, the same way the
 * sign-in pages do.
 *
 * Making this real is backend scope: a GiftCard model with a code and balance,
 * issuance on purchase, and a redemption step in the checkout session.
 */
const code = ref('')
const codeError = ref<string | undefined>()
const checking = ref(false)
const notice = ref<string | null>(null)


async function onRedeem() {
  notice.value = null
  codeError.value = !code.value.trim() ? 'Enter the code from your gift card.' : undefined
  if (codeError.value) return

  checking.value = true
  await new Promise((resolve) => setTimeout(resolve, 600))
  checking.value = false
  notice.value =
    'Gift cards aren’t live yet — there’s no redemption system on the API side. If you were ' +
    'given one, message us with the code and we’ll honour it against your order by hand.'
}

useSeoMeta({
  title: 'Gift cards — Gold Coast Tokota',
  description: 'Give a pair of handmade Ghanaian sandals — Gold Coast Tokota gift cards.',
  ogTitle: 'Gift cards — Gold Coast Tokota',
  ogImage: '/brand/og-image.png',
  ogType: 'website',
})
</script>

<template>
  <div class="w-full bg-white">
    <section class="page-gutter section-y mx-auto flex w-full max-w-[1044px] flex-col items-start gap-10">
      <header class="flex w-full flex-col items-start gap-3">
        <h1 class="w-full text-display-section font-normal text-black">Gift cards</h1>
        <p class="w-full max-w-[720px] text-body text-graphite">
          Let them choose their own pair — and their own size, which is the part that usually
          goes wrong.
        </p>
      </header>

      <CommonInlineNotice variant="warning" title="Gift cards aren’t live yet">
        We’re still building this. In the meantime we can arrange a gift by hand — message us and
        we’ll sort it out.
      </CommonInlineNotice>

      <div class="flex w-full flex-col items-start gap-10 md:flex-row md:gap-12">
        <div class="flex w-full min-w-0 flex-1 flex-col items-start gap-4">
          <h2 class="w-full text-display-sm font-normal text-black">Redeem a gift card</h2>

          <CommonInlineNotice v-if="notice" variant="warning" title="Not yet, sorry">
            {{ notice }}
          </CommonInlineNotice>

          <form class="flex w-full max-w-[440px] flex-col items-start gap-5" novalidate @submit.prevent="onRedeem">
            <FormsFormField
              v-model="code"
              label="Gift card code"
              name="gift-code"
              placeholder="e.g. GCT-XXXX-XXXX"
              required
              :error="codeError"
            />
            <CommonBrandButton full type="submit" :disabled="checking">
              {{ checking ? 'Checking…' : 'Check balance' }}
            </CommonBrandButton>
          </form>
        </div>

        <div class="flex w-full min-w-0 flex-col items-start gap-4 md:w-[320px] md:shrink-0">
          <h2 class="w-full text-display-sm font-normal text-black">Buy one</h2>
          <p class="w-full text-caption text-muted">
            Tell us the amount and who it’s for, and we’ll send a card you can pass on.
          </p>
          <CommonWhatsAppLink source="gift-cards" variant="solid" full :message="whatsappMessage.giftEnquiry()">
            Arrange a gift
          </CommonWhatsAppLink>
          <CommonBrandButton to="/contact" variant="white" full>Contact us</CommonBrandButton>
        </div>
      </div>
    </section>
  </div>
</template>
