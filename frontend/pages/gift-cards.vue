<script setup lang="ts">
import { useCurrencyStore } from '~/stores/currency'
import { isValidEmail } from '~/utils/validators'
import { formatMoney } from '~/utils/formatters'
import { whatsappMessage } from '~/utils/whatsapp'

/**
 * Gift cards.
 *
 * While the client has them on sale (Settings › Gift cards in admin), this is
 * where one is bought: pick an amount, say who it's for, pay on Paystack, and
 * the card is emailed to them once the payment lands. The amounts, any expiry
 * and the terms all come from that setting; nothing about the policy is
 * written here.
 *
 * While they're off, gifts are arranged on WhatsApp, as before. Either way, if
 * any card exists, the balance check works, since store credit can be issued
 * before cards go on sale.
 */
type Settings = {
  enabled: boolean
  amounts: number[]
  expiry_months: number | null
  terms: string | null
  redemption_open: boolean
}

const apiFetch = useApiFetch()
const api = useApi()
const currency = useCurrencyStore()

const { data: settingsData } = await useAsyncData('gift-card-settings', () =>
  apiFetch<{ data: Settings }>('/gift-cards/settings')
    .then((res) => res.data)
    .catch(() => null),
)
const settings = computed(() => settingsData.value)
const onSale = computed(() => !!settings.value?.enabled)
const termsParagraphs = computed(() =>
  (settings.value?.terms ?? '').split(/\n\s*\n/).map((p) => p.trim()).filter(Boolean),
)

// --- buying --------------------------------------------------------------

const form = reactive({
  amount: 0,
  recipientName: '',
  recipientEmail: '',
  senderName: '',
  message: '',
  codeWord: '',
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
})
const errors = reactive<Record<string, string | undefined>>({})
const submitting = ref(false)
const notice = ref<string | null>(null)

watch(settings, (s) => {
  if (s?.amounts.length && !s.amounts.includes(form.amount)) form.amount = s.amounts[0]!
}, { immediate: true })

function validate(): boolean {
  errors.recipientName = form.recipientName.trim() ? undefined : 'Who is it for?'
  errors.recipientEmail = !form.recipientEmail.trim()
    ? 'We email the card to them, so we need their address.'
    : isValidEmail(form.recipientEmail) ? undefined : 'That doesn’t look like a valid email address.'
  errors.codeWord = !form.codeWord.trim() || /^[A-Za-z0-9]{2,10}$/.test(form.codeWord.trim())
    ? undefined
    : 'Use 2 to 10 letters or numbers, with no spaces.'
  errors.firstName = form.firstName.trim() ? undefined : 'Enter your first name.'
  errors.email = !form.email.trim()
    ? 'Enter your email for the receipt.'
    : isValidEmail(form.email) ? undefined : 'That doesn’t look like a valid email address.'
  return !Object.values(errors).some(Boolean)
}

/** API field names back to this form's. */
const FIELD_MAP: Record<string, string> = {
  recipient_name: 'recipientName',
  recipient_email: 'recipientEmail',
  sender_name: 'senderName',
  code_word: 'codeWord',
  'purchaser.first_name': 'firstName',
  'purchaser.email': 'email',
  'purchaser.phone': 'phone',
}

async function buy() {
  notice.value = null
  if (!validate()) return

  submitting.value = true
  try {
    const res = await api<{ payment: { authorization_url: string | null } }>('/gift-cards/purchase', {
      method: 'POST',
      body: {
        amount: form.amount,
        currency: currency.displayCurrency,
        purchaser: {
          first_name: form.firstName,
          last_name: form.lastName || null,
          email: form.email,
          phone: form.phone || null,
        },
        recipient_name: form.recipientName,
        recipient_email: form.recipientEmail,
        sender_name: form.senderName || null,
        message: form.message || null,
        code_word: form.codeWord.trim() || null,
      },
    })
    if (!res.payment.authorization_url) throw { statusCode: 500 }
    // Paystack's page is another origin: a full navigation, not the router.
    window.location.assign(res.payment.authorization_url)
  } catch (err: unknown) {
    submitting.value = false
    const e = err as { statusCode?: number, data?: { message?: string, errors?: Record<string, string[]> } }
    const fieldErrors = Object.entries(e.data?.errors ?? {})
    let shownOnAField = false
    for (const [field, messages] of fieldErrors) {
      const key = FIELD_MAP[field]
      if (key) {
        errors[key] = messages[0]
        shownOnAField = true
      }
    }
    if (!shownOnAField) {
      notice.value = fieldErrors[0]?.[1]?.[0]
        ?? e.data?.message
        ?? 'Something went wrong and nothing was charged. Please try again.'
    }
  }
}

// --- checking a balance --------------------------------------------------

const code = ref('')
const checking = ref(false)
const balance = ref<{ text: string, ok: boolean, message?: string | null, from?: string | null } | null>(null)

async function checkBalance() {
  if (!code.value.trim() || checking.value) return
  balance.value = null
  checking.value = true
  try {
    const res = await api<{ data: { balance: number, expires_at: string | null, sender_name?: string | null, message?: string | null } }>('/gift-cards/balance', {
      method: 'POST',
      body: { code: code.value },
    })
    const left = formatMoney(res.data.balance, 'GHS')
    const until = res.data.expires_at
      ? `, to spend by ${new Date(res.data.expires_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}`
      : ''
    balance.value = {
      text: `${left} left${until}. Enter the code at checkout to use it.`,
      ok: true,
      message: res.data.message ?? null,
      from: res.data.sender_name ?? null,
    }
  } catch (err: unknown) {
    const e = err as { statusCode?: number, data?: { message?: string } }
    balance.value = {
      text: e.statusCode === 429
        ? 'Too many tries. Wait a minute and try again.'
        : (e.data?.message ?? 'We couldn’t check that code just now. Try again in a moment.'),
      ok: false,
    }
  } finally {
    checking.value = false
  }
}

useSeoMeta({
  title: 'Gift cards | Gold Coast Tokota',
  description: 'Give a pair of handmade Ghanaian sandals with a Gold Coast Tokota gift card.',
  ogTitle: 'Gift cards | Gold Coast Tokota',
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
          Let them choose their own pair, and their own size, which is the part that usually
          goes wrong.
        </p>
        <p v-if="!onSale" class="w-full max-w-[720px] text-body text-graphite">
          Gift cards are arranged on WhatsApp. Tell us the amount and who it’s for, and we’ll
          send a card you can pass on.
        </p>
      </header>

      <div v-if="!onSale" class="flex w-full flex-col items-stretch gap-3 sm:flex-row sm:items-start">
        <CommonWhatsAppLink source="gift-cards" :message="whatsappMessage.giftEnquiry()" class="sm:w-[240px]">
          Arrange a gift
        </CommonWhatsAppLink>
        <CommonBrandButton to="/contact">Contact us</CommonBrandButton>
      </div>

      <div class="flex w-full flex-col items-start gap-12 md:flex-row">
        <!-- Buy one -->
        <form
          v-if="onSale"
          class="flex w-full min-w-0 flex-1 flex-col items-start gap-10"
          novalidate
          @submit.prevent="buy"
        >
          <fieldset class="flex w-full flex-col items-start gap-3">
            <legend class="mb-3 w-full text-display-sm font-normal text-black">Choose an amount</legend>
            <div class="flex w-full flex-wrap gap-2">
              <label
                v-for="amount in settings!.amounts"
                :key="amount"
                class="flex min-h-[44px] min-w-[96px] cursor-pointer items-center justify-center border px-4 text-label has-[:focus-visible]:outline has-[:focus-visible]:outline-2"
                :class="form.amount === amount ? 'border-ink bg-ink text-white' : 'border-line text-graphite hover:border-graphite'"
              >
                <input v-model="form.amount" type="radio" name="amount" :value="amount" class="sr-only">
                <CommonPriceDisplay :base-price-ghs="amount" compact />
              </label>
            </div>
            <p v-if="currency.displayCurrency === 'USD'" class="text-caption text-muted">
              The card holds the cedi amount; you pay the dollar price at today’s rate.
            </p>
          </fieldset>

          <fieldset class="flex w-full max-w-[520px] flex-col items-start gap-5">
            <legend class="mb-3 w-full text-display-sm font-normal text-black">Who it’s for</legend>
            <FormsFormField v-model="form.recipientName" label="Their name" name="recipient-name" required :error="errors.recipientName" />
            <FormsFormField
              v-model="form.recipientEmail" label="Their email" name="recipient-email" type="email" required
              hint="We email the card here once you’ve paid." :error="errors.recipientEmail"
            />
            <FormsFormField v-model="form.senderName" label="From" name="sender-name" hint="Optional. How the card is signed." :error="errors.senderName" />
            <FormsFormField v-model="form.message" label="Message" name="message" type="textarea" :rows="3" hint="Optional. They’ll see it when they use the card." />
            <FormsFormField
              v-model="form.codeWord" label="A word for the code" name="code-word" autocomplete="off"
              :hint="form.codeWord.trim()
                ? `Their code will read GCT-${form.codeWord.trim().toUpperCase()}-XXXX. The four random characters keep anyone else from guessing it.`
                : 'Optional. A name or word, 2 to 10 letters or numbers, e.g. MAMA. Leave blank for a random code.'"
              :error="errors.codeWord"
            />
          </fieldset>

          <fieldset class="flex w-full max-w-[520px] flex-col items-start gap-5">
            <legend class="mb-3 w-full text-display-sm font-normal text-black">Your details</legend>
            <div class="flex w-full flex-col gap-5 sm:flex-row">
              <FormsFormField v-model="form.firstName" label="First name" name="first-name" autocomplete="given-name" required :error="errors.firstName" />
              <FormsFormField v-model="form.lastName" label="Last name" name="last-name" autocomplete="family-name" />
            </div>
            <FormsFormField
              v-model="form.email" label="Your email" name="email" type="email" autocomplete="email" required
              hint="For your receipt." :error="errors.email"
            />
            <FormsFormField v-model="form.phone" label="Phone" name="phone" type="tel" autocomplete="tel" hint="Optional." :error="errors.phone" />
          </fieldset>

          <CommonInlineNotice v-if="notice" variant="warning" title="We couldn’t start the payment" role="alert">
            {{ notice }}
          </CommonInlineNotice>

          <div class="flex w-full max-w-[520px] flex-col items-start gap-2">
            <CommonBrandButton full type="submit" :disabled="submitting">
              <template v-if="submitting">Taking you to payment…</template>
              <template v-else>Buy a&nbsp;<CommonPriceDisplay :base-price-ghs="form.amount" compact />&nbsp;gift card</template>
            </CommonBrandButton>
            <p class="text-caption text-muted">
              Paid securely through Paystack, by card or mobile money.
              <template v-if="settings!.expiry_months">
                Cards can be used for {{ settings!.expiry_months }} months.
              </template>
              <template v-else>Cards don’t expire.</template>
            </p>
          </div>
        </form>

        <!-- Check a balance -->
        <div
          v-if="settings?.redemption_open || onSale"
          class="flex w-full min-w-0 flex-col items-start gap-4"
          :class="onSale ? 'md:w-[320px] md:shrink-0' : 'max-w-[520px]'"
        >
          <h2 class="w-full text-display-sm font-normal text-black">Check a balance</h2>
          <form class="flex w-full min-w-0 items-stretch gap-2" @submit.prevent="checkBalance">
            <label class="sr-only" for="balance-code">Gift card code</label>
            <input
              id="balance-code"
              v-model="code"
              name="balance-code"
              autocomplete="off"
              placeholder="GCT-XXXX-XXXX"
              class="min-h-[44px] w-full min-w-0 flex-1 border border-line bg-white px-3 text-body text-graphite placeholder:text-muted"
            >
            <button
              type="submit"
              :disabled="!code.trim() || checking"
              class="btn-outline caps-label min-h-[44px] shrink-0 px-5 disabled:cursor-not-allowed disabled:opacity-40"
            >
              {{ checking ? 'Checking…' : 'Check' }}
            </button>
          </form>
          <p v-if="balance" class="w-full text-caption" :class="balance.ok ? 'text-graphite' : 'text-sale'" role="status">
            {{ balance.text }}
          </p>
          <figure v-if="balance?.ok && (balance.message || balance.from)" class="w-full border-l-2 border-line pl-3">
            <blockquote v-if="balance.message" class="whitespace-pre-line text-caption italic text-graphite">“{{ balance.message }}”</blockquote>
            <figcaption v-if="balance.from" class="mt-1 text-caption text-muted">From {{ balance.from }}</figcaption>
          </figure>
        </div>
      </div>

      <section v-if="onSale && termsParagraphs.length" class="flex w-full max-w-[720px] flex-col items-start gap-3 border-t border-line pt-10">
        <h2 class="w-full text-display-sm font-normal text-black">Terms</h2>
        <p v-for="(paragraph, i) in termsParagraphs" :key="i" class="w-full whitespace-pre-line text-body text-graphite">
          {{ paragraph }}
        </p>
      </section>
    </section>
  </div>
</template>
