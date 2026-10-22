# WhatsApp rehearsal before connecting the brand number

Run this before the Gold Coast Tokota number is connected (FOR_THE_TEAM.md,
decision 49). It proves the inbox against real WhatsApp traffic and checks
the webhook field names the code reads, which were written from Meta's
partner documentation rather than real deliveries.

It runs against **your local API through a tunnel**, so test chats never
reach the production database.

There are two rounds, because they need different numbers:

| Round | Number | What it proves |
|---|---|---|
| A | Meta's free **test number** (API only) | Inbound messages, delivery ticks, replies, the 24-hour window, templates, the Pay button, STOP |
| B | A **spare SIM** running the WhatsApp Business app, connected with coexistence | Replies typed on the phone appear in the inbox, chat-history import, phone contact names |

Round B can't be done on the test number: it isn't in the WhatsApp Business
app, so it never sends coexistence events. Use a spare number, not the
brand's, so a mistake costs nothing.

## Setup (both rounds)

1. **Meta app.** At developers.facebook.com, create an app of type
   *Business* and add the **WhatsApp** product. The WhatsApp → API Setup page
   gives you a test number, its **phone number ID**, the **WhatsApp Business
   Account ID** and a temporary access token (24 hours; a system-user token
   lasts). Add your own phone as a recipient there (up to five).
2. **App secret.** App settings → Basic → App secret.
3. **Local `.env`** in `backend/`:

   ```
   WHATSAPP_PHONE_NUMBER_ID=...
   WHATSAPP_WABA_ID=...
   WHATSAPP_ACCESS_TOKEN=...
   WHATSAPP_APP_SECRET=...
   WHATSAPP_VERIFY_TOKEN=pick-any-long-random-string
   WHATSAPP_LOG_PAYLOADS=true
   ```

   Then `php artisan config:clear`.
4. **Run the API, a worker and a tunnel**, each in its own terminal:

   ```bash
   cd backend && php artisan serve
   cd backend && php artisan queue:work
   cloudflared tunnel --url http://localhost:8000
   ```

   `cloudflared` prints an `https://….trycloudflare.com` address (ngrok works
   too).
5. **Webhook.** In the Meta app, WhatsApp → Configuration → Webhook:
   - Callback URL: `https://<tunnel address>/api/v1/webhooks/whatsapp`
   - Verify token: the `WHATSAPP_VERIFY_TOKEN` above
   - Subscribe to `messages` and `message_template_status_update`; for
     round B also `smb_message_echoes`, `history` and `smb_app_state_sync`.

   "Verify and save" succeeding proves the verify route.
6. In the admin, `/settings/whatsapp` should now say **Connected**, and the
   inbox banner should be gone.

## Round A: the test number

From your phone, message the test number, then work through the inbox at
`http://localhost:3001/inbox`. Tick each one off.

| # | Do | Expect |
|---|---|---|
| A1 | Send "Hello" | A new chat within 15 s; unread badge; reply countdown |
| A2 | Send a photo with a caption | The photo opens from the chat (stored in the private bucket) |
| A3 | Share a location | A location line with its name or coordinates |
| A4 | Reply from the inbox | Ticks go sent → delivered → read as you open it on the phone |
| A5 | Templates tab → Refresh from WhatsApp | `hello_world` appears, approved |
| A6 | Order builder: add an item, price delivery, place | A "Pay GH₵…" button arrives on the phone; tapping it opens Paystack (test keys) |
| A7 | Pay with a Paystack test card | The order turns Paid in `/orders`, with its WhatsApp badge |
| A8 | Send "STOP" | The chat shows Opted out; templates are refused |
| A9 | Wait 24 h after your last message, or message from a second phone and wait | The text box becomes the template picker |

## Round B: a spare Business-app number

Connect the spare number to the same Meta app through Embedded Signup,
choosing to keep using the WhatsApp Business app (coexistence). Point the
`.env` at its phone number ID, `config:clear`, and:

| # | Do | Expect |
|---|---|---|
| B1 | Onboarding finishes | Past chats import into the inbox, none marked unread |
| B2 | Reply to a customer from the phone app | The reply appears in the admin thread, labelled "Sent from the brand phone" |
| B3 | Save a contact name on the phone | The inbox shows that name |

## Checking the field names

Every verified delivery is now in `backend/storage/logs/whatsapp-payloads.log`.
For each event type, compare the real body with what
`App\Services\WhatsApp\WhatsAppWebhookProcessor` reads:

| Event (`field`) | Paths the code reads |
|---|---|
| `messages` | `value.metadata.phone_number_id`, `value.contacts[].wa_id`, `value.contacts[].profile.name`, `value.messages[].{from,id,timestamp,type}` and the type's object (`text.body`, `image.id`, `image.caption`, `location.*`, `interactive.button_reply.title`), `value.statuses[].{id,status,errors[]}` |
| `smb_message_echoes` | `value.message_echoes[].{to,id,timestamp,type}` and the type's object |
| `history` | `value.history[].threads[].id`, `.messages[].{from,id,timestamp,type,history_context.status}`, and `value.history[].errors[]` when sharing is declined |
| `smb_app_state_sync` | `value.state_sync[].{type,action}`, `.contact.{full_name,first_name,phone_number}` |
| `message_template_status_update` | `value.{event,message_template_id,message_template_name,message_template_language,reason}` |

If a name or level differs, fix the processor, add the real body as a test
case in `tests/Feature/WhatsAppInboxTest.php`, and note it in FOR_THE_TEAM.md.

## Afterwards

- Set `WHATSAPP_LOG_PAYLOADS=false` and delete
  `storage/logs/whatsapp-payloads*.log`: it holds real people's numbers and
  messages.
- Remove the tunnel URL from the Meta app's webhook.
- Disconnect the spare number from the Meta app before connecting the
  brand's.
