# Phone agent: Retell + n8n setup

The voice agent runs in **Retell**, workflows run in **n8n**, and this Laravel
site is the system of record both of them call through `/api/v1`.

```
Caller ──► Retell voice agent ──(during the call: custom functions)──► Laravel /api/v1
                │
                └──(after the call: webhook)──► n8n ──► Laravel /api/v1/calls
                                                   │         │
                                                   │         └─ returns: lead, score, matches, next_actions
                                                   └─► WhatsApp / email / alerts, by next_actions
```

Laravel owns the decisions (who the lead is, how qualified, what fits, what
to do next) so the phone, the website chat and the email replies all agree.
n8n does the delivery.

---

## 1. Switch the API on

The API is closed until a token is set. Generate one and put it in the
server's `.env`:

```bash
ssh hostinger
cd ~/domains/estate.brealstate.example/app
echo "AUTOMATION_API_TOKEN=$(openssl rand -hex 32)" >> .env
/opt/alt/php84/usr/bin/php artisan config:cache
grep AUTOMATION_API_TOKEN .env      # copy this value into n8n and Retell
```

Send it on every request, in any one of these ways:

| How | Use it from |
|---|---|
| `Authorization: Bearer <token>` | n8n HTTP Request node (Header Auth) |
| `X-Api-Key: <token>` | anything that sets custom headers |
| `?token=<token>` on the URL | Retell custom functions (URL only) |

Base URL: `https://estate.brealstate.example/api/v1`

---

## 2. Retell: post-call analysis fields

In the Retell agent → **Post-Call Analysis**, add these fields. The names
matter - Laravel reads them by name. Spoken forms are fine ("2.5 crore",
"10 marla", "next month"); Laravel normalises them. Leave a field empty when
the caller did not say.

| Field name | Type | Description to give Retell |
|---|---|---|
| `name` | text | The caller's name |
| `email` | text | Email address, only if they gave one |
| `intent` | selector: buy / sell / rent | Whether they want to buy, sell or rent |
| `property_type` | text | House, flat, plot, portion or farmhouse |
| `areas` | text | Areas or societies they want, comma-separated (e.g. "DHA, Bahria Town") |
| `budget` | text | Their budget as they said it (e.g. "2 to 3 crore", "under 80 lakh") |
| `bedrooms` | number | Minimum bedrooms |
| `size` | text | Plot size wanted (e.g. "10 marla", "1 kanal") |
| `timeline` | text | When they want to buy or sell (e.g. "this month", "in 3 months", "just looking") |
| `payment` | text | Cash, bank loan or installments |
| `appointment_requested` | boolean | Did they ask for a viewing or meeting? |
| `preferred_time` | text | When they would like the viewing, as they said it |
| `property_of_interest` | text | The specific listing they asked about (title or slug) |
| `selling_property` | text | For sellers: the property they want to sell |
| `notes` | text | Anything else the agent should know |

Also accepted instead: `budget_min_pkr` / `budget_max_pkr` as numbers (whole rupees).

---

## 3. Retell: custom functions (during the call)

Add these as **custom functions** in the Retell agent. Method `POST`; Retell
sends `{name, call, args}` and the endpoints read that format directly. Put
the token on the URL.

### `search_properties`
- URL: `https://estate.brealstate.example/api/v1/properties/search?token=<token>`
- Description: *Search the agency's listings for sale. Prices in whole rupees: 2.5 crore = 25000000.*
```json
{
  "type": "object",
  "properties": {
    "area":         { "type": "string",  "description": "Area or society, e.g. DHA, Bahria Town" },
    "type":         { "type": "string",  "enum": ["house", "apartment", "townhouse", "land", "acreage"] },
    "min_bedrooms": { "type": "integer" },
    "min_price":    { "type": "integer", "description": "Rupees" },
    "max_price":    { "type": "integer", "description": "Rupees" },
    "keywords":     { "type": "string" }
  }
}
```

### `get_property`
- URL: `https://estate.brealstate.example/api/v1/properties/details?token=<token>`
- Description: *Full recorded details of one listing, by the slug from search results.*
```json
{ "type": "object", "properties": { "slug": { "type": "string" } }, "required": ["slug"] }
```

### `check_availability`
- URL: `https://estate.brealstate.example/api/v1/appointments/availability?token=<token>`
- Description: *Free viewing times. Offer two or three of the labels.*
```json
{
  "type": "object",
  "properties": {
    "date": { "type": "string", "description": "YYYY-MM-DD to start from; omit for the next available" },
    "days": { "type": "integer", "description": "How many days to look across (default 3)" }
  }
}
```

### `book_viewing`
- URL: `https://estate.brealstate.example/api/v1/appointments?token=<token>`
- Description: *Book a viewing at one of the starts_at times from check_availability. The caller's number is taken from the call automatically. If booked is false, offer the alternatives. Say the `say` field back to the caller.*
```json
{
  "type": "object",
  "properties": {
    "starts_at":     { "type": "string", "description": "Exact starts_at value from check_availability" },
    "name":          { "type": "string", "description": "Caller's name" },
    "property_slug": { "type": "string", "description": "Listing to view, if any" },
    "notes":         { "type": "string" }
  },
  "required": ["starts_at"]
}
```

Bookings are **requests** by default - you confirm them in **Admin →
Appointments** (this calendar does not know about the rest of your day). Set
`APPOINTMENTS_AUTO_CONFIRM=true` to confirm instantly. Viewing hours, slot
length and notice are in `config/agent.php` → `appointments`.

### Optional: recognise returning callers
Point Retell's **inbound call webhook** at n8n, have n8n call
`POST /api/v1/leads/lookup` with `{"call": {"from_number": "..."}}`, and pass
the returned `dynamic_variables` (`caller_known`, `caller_name`,
`caller_last_interest`, `caller_next_viewing`) back to Retell. Use them in the
prompt: *"If {{caller_known}} is yes, greet {{caller_name}} by name."*

---

## 4. n8n: the post-call workflow

1. **Webhook** node - give its URL to Retell as the agent's webhook URL.
2. **IF** `{{$json.body.event}}` equals `call_analyzed` (other events can be
   forwarded too; they are stored but only the analysed call qualifies the lead).
3. **HTTP Request** - `POST https://estate.brealstate.example/api/v1/calls`,
   Header Auth `Authorization: Bearer <token>`, body = the webhook body
   **unchanged** (`{{$json.body}}`).
4. **IF** `{{$json.already_processed}}` is true → stop (a retried webhook -
   messages were already sent).
5. **Split Out** `next_actions`, then **Switch** on `action`:

| `action` | `for` | Do this | Useful fields |
|---|---|---|---|
| `notify_agent` | agent | WhatsApp/email yourself now | `summary`, `urgency` |
| `send_matches` | lead | Send the caller their listings | `message` (ready to send), `properties` |
| `confirm_appointment` | lead | Confirm the viewing to the caller | `message`, `when`, `status` |
| `approve_appointment` | agent | Ask yourself to confirm the requested viewing | `appointment_id`, `when` |
| `schedule_appointment` | agent | They want a viewing but none was booked - call to fix a time | `preferred_time`, `phone` |
| `book_valuation` | agent | Seller - arrange a valuation visit | `selling_property`, `phone` |
| `agent_follow_up` | agent | Nothing listed fits - follow up personally | `requirements` |
| `call_back` | agent | No real conversation (voicemail / too short) - try again | `phone` |
| `nurture` | agent | Early-stage lead - check back in a week | |

The lead's phone is `{{$json.lead.phone}}`, its admin page `{{$json.lead.admin_url}}`.

### Example response from `POST /api/v1/calls`
```json
{
  "call_id": "call_abc123",
  "processed": true,
  "lead": { "id": 61, "name": "Ayesha Khan", "phone": "+923001234567", "email": null, "is_new": true,
            "admin_url": "https://estate.brealstate.example/admin/enquiries/61" },
  "requirements": { "name": "Ayesha Khan", "intent": "buy", "property_types": ["house"], "areas": ["DHA"],
                    "budget_max": 35000000, "bedrooms_min": 4, "timeline": "1_3_months", "payment": "cash" },
  "qualification": { "score": 80, "grade": "hot",
                     "summary": "Phone lead scored 80/100: wants to move within 1-3 months, gave a budget",
                     "reasons": [["reachable on the phone number they called from", 10], ["gave their name", 5], ["wants to buy", 10],
                                 ["wants to move within 1-3 months", 18], ["gave a budget", 12], ["named the areas they want", 5],
                                 ["1 current listing fits", 10], ["paying cash", 10]] },
  "matches": { "exact": true, "properties": [ { "slug": "10-marla-house-in-dha-phase-6", "title": "10 Marla House in DHA Phase 6",
               "price": "PKR 3.25 Crore", "url": "https://estate.brealstate.example/properties/10-marla-house-in-dha-phase-6",
               "fit": "within budget, bedrooms as asked", "close": false } ] },
  "appointment": null,
  "next_actions": [
    { "action": "notify_agent", "for": "agent", "reason": "hot lead - call back today", "urgency": "high", "summary": "..." },
    { "action": "send_matches", "for": "lead", "reason": "1 listing fit what they asked for", "message": "Hi Ayesha Khan, ..." }
  ],
  "follow_up_at": "2026-10-06T14:00:00+00:00"
}
```

---

## 5. How leads are scored

0-100, rule-based, every point explained on the lead's page. **Hot from 65,
warm from 35.** The grade becomes the lead's hot / warm / cold priority in
the inbox.

| Signal | Points |
|---|---|
| Reachable (called from a number) | +10 |
| Gave their name | +5 |
| Wants to sell (a potential listing) | +30 |
| Wants to buy | +10 |
| Timeline: ASAP / 1-3 months / 3-6 months / 6+ months / browsing | +25 / +18 / +8 / +3 / 0 |
| Gave a budget *(buyers)* | +12 |
| Named areas *(buyers)* | +5 |
| A current listing fits *(buyers)* | +10 |
| Paying cash / loan or installments *(buyers)* | +10 / +4 |
| Booked a viewing / asked for one | +15 / +12 |
| Sounded unhappy | −5 |
| Voicemail, or call under 20 seconds | score 0 - call back |

A repeat caller (same number, open lead, last 30 days) joins their existing
lead: new answers update it, unspoken ones keep their earlier values, and it
is re-scored.

---

## 6. All endpoints

| Method | Path | Called by | Purpose |
|---|---|---|---|
| GET/POST | `/properties/search` | Retell, n8n | Search listings |
| GET/POST | `/properties/details/{slug?}` | Retell, n8n | One listing's details |
| GET/POST | `/leads/lookup` | n8n | Recognise a caller by phone |
| GET/POST | `/appointments/availability` | Retell, n8n | Free viewing slots |
| POST | `/appointments` | Retell, n8n | Book a viewing |
| POST | `/calls` | n8n | Ingest a call webhook; returns lead, score, matches, next actions |
| GET | `/leads/{id}/matches` | n8n | Current matches for a lead |

Errors: `401` wrong token, `503` API not configured, `422` invalid input.
Limit: 120 requests a minute.

Quick test once the token is set:
```bash
curl -H "Authorization: Bearer $TOKEN" "https://estate.brealstate.example/api/v1/properties/search?area=DHA"
```
