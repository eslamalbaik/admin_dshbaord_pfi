# TASK-17 US11 — Company-profile edits become reviewable requests

Design plan for sub-issue #6 of [TASK_PLAN.md](TASK_PLAN.md) § `TASK-17`:
*«تم تعديل ملف الشركة من خلال التطبيق لكن لن يتم عرض الطلب في لوحة رغم التحديث»*.

Read this before writing code. It carries six decisions that are cheaper to settle now than to
unpick later, and one finding that is more serious than the bug that was reported.

**Status**: ✅ **implemented 2026-09-24, shipped behind `PROFILE_EDITS_REQUIRE_APPROVAL` (default off)**.
Task list: [TASK-17-tasks.md](TASK-17-tasks.md) Phase 13 (T064–T073).

All six decisions were implemented as recommended. Two things changed during the work:

- **§2's finding was closed first**, separately and ahead of everything else, on the reporter's
  instruction. `classification` and `specialties` are now rejected by the portal entirely rather
  than routed through review.
- **D2's staging revealed a bug the design did not anticipate.** The first implementation split
  request fields into instant vs reviewed by name, and document fields — being in neither text list
  — fell into *instant*, so `$contractor->update()` wrote the raw PHP temp path (`/tmp/php…`) into
  the document column, destroying the live document. `partition()` now excludes document fields from
  **both** sides; their only route is `stageDocuments()`. Caught by
  `test_a_document_is_staged_and_the_live_document_is_untouched`.

Also worth recording: the enum widening needed the driver-split pattern from
`2026_09_19_000003_expand_penalty_statuses` — tests run on SQLite, which has no `MODIFY COLUMN` and
emulates `enum()` with a CHECK constraint that cannot be altered without `doctrine/dbal`. The
migration rebuilds the table on SQLite and uses `ALTER MODIFY` on MariaDB.

**Before enabling on production**: the app must read `pending_review` (§5), or a contractor sees
«تم الحفظ» on an edit that is actually awaiting review.

---

## 1. In one paragraph

A contractor edits their company profile in the app. The data changes, and no request appears in
the dashboard. The approval queue is not broken — it is simply not the endpoint the app calls. The
app posts to `POST contractor/auth/profile/update`, which writes straight to the `contractors`
table; the queue lives behind `POST contractor/profile-update-requests`, which nothing calls. The
fix is to make the direct route file a request instead of writing. That is a one-line change in
spirit and a substantial one in practice, because the queue was built for five short text fields
and the direct route accepts about twenty-five fields plus sixteen documents.

---

## 2. ⚠️ Finding: this is also a financial-integrity hole, not only a missing request

> **✅ CLOSED 2026-09-24, ahead of the rest of this plan, on the reporter's instruction («لا اجعله لا يستطيع»).**
> `classification` and `specialties` were removed from `UpdateFullProfileRequest`, so the contractor
> portal can no longer write either. Editing them is an administrative action only:
> `ContractorController` (admin dashboard), or the approval queue once US11 lands. An attempt to
> change them from the app is ignored and recorded in the finance log
> (`contractor.fee_field_change_rejected`, with the contractor's membership number and both values),
> which is the audit trail that was missing. Covered by `tests/Feature/ContractorFeeFieldLockTest.php`.
>
> The rejection is deliberately **silent rather than a 422**: the app's profile form submits all its
> fields on every save, so refusing the request would have broken every profile edit in production
> instead of closing one hole. Moamen should stop sending the two fields, but nothing breaks until
> he does.
>
> The rest of this section is kept as the record of what the hole was, and §11 question 3 still
> stands: closing it does not tell you whether it was already used.

`UpdateFullProfileRequest` accepts `classification` and `specialties`, and `updateFullProfile()`
writes both directly to `contractors`. Those two columns are the **inputs to the membership fee
calculation** — `MembershipFeeCalculator` reads `$contractor->specialties`
([MembershipFeeCalculator.php:26](arab-contractors-union-api/app/Services/MembershipFeeCalculator.php)),
and `CLAUDE.md` records that these codes also feed certificate generation.

**So a contractor can currently lower their own classification from the app and reduce the fee the
engine will charge them, with no review and no audit trail.** They can also alter the grade printed
on a generated certificate.

Nobody reported this; it was found while tracing the reported bug. It is the strongest argument for
doing US11 properly rather than patching the symptom, and it means `classification` and
`specialties` belong in the reviewed set no matter what else is decided. Worth telling the union
explicitly — if any contractor's grade looks wrong relative to their fee history, this is a
mechanism that could explain it.

---

## 3. Current state

Three write paths exist. Only the third asks for approval, and the app uses the first.

| Path | Handler | Accepts | Review? |
|---|---|---|---|
| `POST contractor/auth/profile/update` | `ContractorAuthController::updateFullProfile` | ~25 text fields + 16 documents | **No — direct write** |
| `PATCH contractor/auth/profile` | `ContractorAuthController::updateProfile` | `phone`, `governorate_id`, `city_id`, `address`, `fcm_token` | **No — direct write** |
| `POST contractor/profile-update-requests` | `ProfileUpdateRequestController::store` | 5 fields (`ProfileUpdateRequest::ALLOWED_FIELDS`) | Yes |

`profile_update_requests` today: `proposed_data` (JSON), a single `attachment` (the contractor's
*evidence*, not the payload), `phone_otp_verified_at`, `status`, `reject_reason`, `reviewed_by`,
`reviewed_at`. `approve()` applies the change with `$contractor->update($proposed_data)` verbatim.

**Closing only the first path leaves the second as the bypass.** Both, or neither.

---

## 4. Decisions

Each has a recommendation. Nothing here is implemented yet, so any of them can be overruled cheaply
— but not after the migration ships.

### D1 — Which fields need review

Three tiers rather than two, because "everything needs approval" makes the app hostile and
"nothing does" is today's bug.

**Tier A — reviewed (identity, legal, financial):** `trade`, `classification`, `specialties`,
`established_year`, `established_date`, `owner_name`, `partners`, `capital`, `registration_date`,
`legal_form`, `company_purposes`, `authorized_person`, `authorized_person_title`,
`authorized_person_id_number`, **and all 16 documents**.

**Tier B — reviewed but low-friction (contact):** `email`, `address`, `fax`, `district`,
`building`, `floor`, `authorized_person_phone`, `authorized_person_whatsapp`.
These are the existing queue's territory and already work this way. Keep them reviewed for
consistency, but they are the candidates to relax first if the union finds review too slow.

**Tier C — stays instant:** `logo` (its own endpoint, cosmetic), `fcm_token` (a device token, not
profile data — it must **never** go through review or push notifications break), `governorate_id` /
`city_id` (they drive `applyLocation()` and the denormalised `city`; reviewing them means the
contractor's own address display lags behind reality for days), and `phone` (identity-critical but
already protected by a mandatory OTP that is stronger evidence than an admin's glance).

> `name`, `membership_number` and the operator number remain hard-locked and appear in no tier —
> unchanged from today, and the company-name change has its own dedicated queue.

**Recommended.** The important lines are that Tier A exists at all, and that `fcm_token` is
explicitly excluded — sending it through review would silently kill contractor push notifications,
which is exactly the class of failure `PushChannelWiringTest` was written to catch.

### D2 — Where staged documents live

`proposed_data` is JSON and absorbs the text fields for free. The documents have nowhere to go.

- **Option 1 — `proposed_files` JSON column** mapping field name → staged path. No join, mirrors
  the existing `proposed_data` pattern, and a request is short-lived.
- **Option 2 — child table** `profile_update_request_files`. Cleaner relationally, more code, and
  buys nothing while only one request can be pending.

**Recommended: Option 1.** Stage uploads under `contractors/profile-update/staged/{request_id}/`,
which sits inside the `storage/app/public/contractors` tree that `deploy-vps.sh` already excludes
from its rsync — so a deploy cannot wipe a pending request's files.

**The live document is never touched before approval.** On approve, each staged file moves into the
real document path and the superseded file is deleted. On reject, staged files are deleted.

**Orphans are a real cost of this design**: a request neither approved nor rejected leaves files on
disk forever. Needs a prune command (`profile-requests:prune-staged`, files older than N days whose
request is no longer pending) scheduled alongside the existing console tasks. Do not skip this — it
is the part that gets forgotten and shows up a year later as a full disk.

### D3 — One OTP path, not two

Today the two handlers verify a phone change differently: `updateFullProfile()` reads a
pre-verified result (`getVerifiedResult`, valid 15 minutes, set by the two-step
`request-otp` → `verify-otp` flow), while `store()` consumes a raw `otp` in the submit itself.

**Recommended: keep the two-step pre-verified flow and drop the raw-`otp` parameter.** A long
profile form should not have to hold a 6-digit code that expires while the user is still typing,
and the two-step flow is what the app already implements. This changes `profile-update-requests`'s
own contract, so it must be in the same coordination note as everything else.

Per D1 the phone stays instant, so the OTP result is consumed by the direct phone update and never
reaches the request — but the queue keeps `phone_otp_verified_at` for the rows already in the table.

### D4 — A second edit supersedes the first

`store()` currently refuses when a pending request exists. With five optional fields that is
reasonable. With the whole profile it means a contractor who mistypes one field waits days before
they can correct it — and their only visible state is "قيد المراجعة" on data they know is wrong.

**Recommended: a new submission supersedes the pending one** (mark the old `superseded`, file the
new one, delete the old staged files). Needs a fourth enum value on `status`.

Alternative if you would rather not touch the enum: keep the block and return the pending request's
contents so the app can at least show what is queued and offer "withdraw". Weaker, but no
migration risk.

### D5 — Response shape: 200 with a flag, not 202

The breaking change for the app is that the response no longer reflects the new values.

`202 Accepted` is the honest status code and the wrong engineering choice here: a client that
checks `status === 200` breaks the moment this ships, and we do not control the app's release
schedule.

**Recommended: keep `200`, keep returning the contractor's *current* (unchanged) profile so the
existing app keeps rendering correctly, and add two keys** — `pending_review: true` and
`pending_request` (id, status, `proposed_data`, submitted timestamp). An app that has not been
updated shows the old values and a success message: mildly wrong, not broken. An updated app reads
the flag and shows «قيد المراجعة».

Also add `pending_profile_update` to `GET contractor/auth/profile`, so the app can badge individual
fields without a second call.

### D6 — Ship behind a flag

This is the decision that makes the rollout safe, and it is what removes the dependency on Moamen
from the critical path.

**Recommended: a setting — `PROFILE_EDITS_REQUIRE_APPROVAL`, default `false`.** Off, behaviour is
byte-for-byte today's. On, the direct routes file requests. It can then land on staging, be enabled
there for testing, be enabled on production the day the app is ready, and be turned off in seconds
if something is wrong — without a deploy, a revert, or a migration rollback.

Without the flag, this change and the app release must land together, and one of them will be late.

---

## 5. Contract for the app (hand this to Moamen)

Nothing below changes while `PROFILE_EDITS_REQUIRE_APPROVAL` is off.

**`POST contractor/auth/profile/update`** — same request, multipart, same field names.

```jsonc
// 200 — flag ON
{
  "status": true,
  "message": "تم إرسال طلب تعديل البيانات، وستبقى بياناتك الحالية سارية لحين المراجعة.",
  "items": {
    "...": "الملف الشخصي كما هو الآن — بالقيم القديمة، غير معدَّلة",
    "pending_review": true,
    "pending_request": {
      "id": 41,
      "status": "pending",
      "proposed_data": { "capital": "250000", "legal_form": "شركة عادية" },
      "proposed_files": ["cr_file"],
      "created_at": "2026-09-24T10:00:00Z"
    }
  }
}
```

What the app needs to do:
1. Stop treating the response body as the saved profile. Read `pending_review`.
2. Show «قيد المراجعة» rather than «تم الحفظ» when it is `true`.
3. Optionally badge the fields named in `pending_request.proposed_data`.
4. `GET contractor/auth/profile` now carries `pending_profile_update` — same shape, or `null`.
5. Phone, logo, city/governorate and `fcm_token` still save instantly. No change there.
6. A second submission while one is pending **replaces** it (D4) — no longer a 422.

Update the `Contractor_App_API.postman_collection.json` entries in the same change. TASK-16's
Postman pass exists because one wrong entry there cost the app developer real time.

---

## 6. Migration and rollback

One migration, three additive changes to `profile_update_requests`:

```
proposed_files   json     nullable   // field name → staged path (D2)
superseded_at    dateTime nullable   // D4
status enum: add 'superseded'
```

All additive; existing rows keep working, and `ProfileUpdateRequestController::mine()` and the
admin list are unaffected. Rollback is the flag, not the migration — turning
`PROFILE_EDITS_REQUIRE_APPROVAL` off restores today's behaviour instantly while the columns sit
unused.

MariaDB rewrites the table to widen an enum. `profile_update_requests` is small, so this is
seconds; still worth running during a quiet window on production, and note that the staging deploy
runs `migrate --force` automatically on push.

---

## 7. Admin dashboard

`contractors/profile-update-requests.vue` renders the diff from a **fixed five-key Arabic label
map** (TASK-16 T014, which was correct for five fields). Widening the field set breaks it silently
— unmapped keys render blank, so an admin approves a change they cannot see.

- Replace the fixed map with one covering every Tier A/B field, and fall back to the raw key with a
  visible marker rather than blank.
- Render document changes as before/after links — the staged file must be openable, or the admin is
  approving a filename.
- Group the diff (identity / contact / documents); a 25-row flat list is not reviewable.
- Show `superseded` rows as their own status, and make clear they need no action.

---

## 8. Tests

- A text edit files a request and leaves `contractors` **unchanged** until approval.
- A document edit stages the file, leaves the live document path untouched, and approval moves it
  and deletes the superseded file.
- Rejection leaves both the record and the live document untouched, and deletes the staged file.
- `classification` / `specialties` cannot reach `contractors` without approval — assert directly
  against the finding in §2, since that is the one with money attached.
- `fcm_token` still writes instantly and never creates a request.
- `PATCH contractor/auth/profile` files a request too (no bypass).
- A second submission supersedes the first and deletes its staged files.
- With the flag **off**, every existing test still passes unchanged — this is the safety net for the
  whole design.
- Prune command deletes orphaned staged files and spares pending ones.

---

## 9. Rollout

1. Land backend + admin UI with the flag **off**. Behaviour identical; safe to deploy any time.
2. Enable on staging. Test from the app against staging.
3. Moamen ships the app change (§5). The flag being off on production means no deadline pressure.
4. Enable on production. Watch the queue for a day — the first real load is unknown.
5. If review proves too slow for contact fields, relax Tier B (D1) rather than turning the flag off.

---

## 10. Risks

| Risk | Mitigation |
|---|---|
| Contractors lose instant saves and perceive the app as broken | D5 keeps the old app rendering; D6 controls when it starts |
| Review queue becomes a bottleneck nobody drains | Tier B relaxable (D1); watch the queue in step 4 |
| Staged files accumulate forever | Prune command (D2) — the item most likely to be dropped |
| Admin approves a diff they cannot read | §7 label map is not optional |
| `fcm_token` routed through review, killing push silently | D1 excludes it; a test asserts it |
| Enum widening locks the table | Small table; quiet window |

---

## 11. Open questions

1. **Tier B** — should contact details (email, address, fax, building, floor) really need approval,
   or save instantly? They are the least risky fields and the most frequently corrected. The queue
   treats them as reviewed today, so keeping that is the status quo, not a new burden.
2. **Who reviews?** Admin only, or accountants too? The existing routes are admin-only; the union
   may not have the admin hours for a 25-field queue.
3. **Is the §2 finding new, or known?** If contractors have been self-editing their classification,
   the union may want the change history audited before the hole is closed.
