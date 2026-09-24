# TASK-17 — Tasks

Executable task breakdown for [TASK_PLAN.md](TASK_PLAN.md) § `TASK-17 — Feedback batch 2026-09-24`.
Read that section first — it carries the root-cause analysis these tasks depend on, including two corrections to TASK-16's diagnoses and the deploy-history evidence that reprioritised US7/US8.

**Stack**: Laravel 12 / PHP 8.2 (`arab-contractors-union-api/`), Vue 3 + TypeScript + Vuetify 3 (`arab-contractors-union-front/`).
**Conventions**: file-based routing (no route table to edit); all admin UI strings in Arabic; `npm run typecheck` and `php artisan test` are the gates.
**Known baseline** (from TASK-16, re-confirm in T001): ~20 pre-existing test failures from a missing `Database\Factories\ContractorFactory`; `vue-tsc` carries a non-empty error baseline; `npm run lint` cannot run repo-wide (the script points at a non-existent `eslint-internal-rules/`).

**Story → sub-issue map**

| Story | Sub-issue | Priority | Independently testable? |
|---|---|---|---|
| US1 | #10 Bulk discount matches and previews wrongly | **P0** | Yes |
| US2 | #7 Due date must not be in the past | P1 | Yes |
| US3 | #8 Register a penalty with its status (+ list renders nothing) | P1 | Yes |
| US4 | #11 Modal must close after a successful save | P1 | Yes |
| US5 | #9 Summary totals cannot be reconciled with the list | P2 | Yes |
| US6 | #4 Excel export misses specializations and classifications | P2 | Yes |
| US7 | #2 One document still cannot be deleted | P2 | Yes |
| US8 | #3 Preview dialog field parity (re-audit) | P3 | Yes |
| US9 | #1 Upload ceiling — server config carried from TASK-16 | P1 | Yes (server-only) |
| US10 | #5 Membership certificate request invisible | P2 | Yes |
| US11 | #6 Company profile edits bypass the approval queue | P3 | Yes |

**US1 is first regardless of priority order elsewhere**: a criteria bulk discount currently accepts an empty criteria object and then matches every due in the database. That is live data loss waiting to happen, and the guard is a few lines.

---

## Phase 1: Setup

- [ ] T001 Record the current baseline: `php artisan test` in `arab-contractors-union-api/` and `npm run typecheck` in `arab-contractors-union-front/`. Note the failure/error set in the scratch notes — TASK-16 documented a non-empty baseline in both, so a clean run is not expected and later comparisons need the starting point
- [ ] T002 [P] Confirm on a local seed that `GET /api/v1/contractors` serialises `specialties`, `field_lk_type` and `specialization_lk_type` for a contractor created through the current add form — US6 depends on it, and it is the one unverified assumption there (the model casts `specialties` to `array` and `index()` returns the whole model, so this should hold)

---

## Phase 2: US1 — Bulk discount matches, previews and applies the same set (P0, #10)

**Goal**: A criteria bulk discount cannot run unscoped, can be limited to chosen contractors, and previews exactly the count and impact it then applies.
**Independent test**: Create 2 dues for year 2026 on 2 contractors plus 4 unrelated 2026 dues. A criteria discount scoped to those 2 contractors previews `matched_count: 2` and an impact equal to what the real run reports. An empty criteria object is refused. A discount that would push a due below its paid amount is reported as skipped in the preview, not counted as matched.

- [ ] T003 [US1] In `arab-contractors-union-api/app/Http/Requests/ContractorDue/ApplyDiscountBulkRequest.php`, reject a criteria mode with no usable criterion. `criteria` is currently `required_if:mode,criteria|array`, and `{}` passes — every `if (! empty(...))` in the service is then skipped and the query stays unfiltered. Add a rule (or `after` hook) requiring at least one of `year` / `status` / `source` / `contractor_ids`, with an Arabic message
- [ ] T004 [US1] Add `criteria.contractor_ids` (`nullable|array|max:200`, `.*` → `integer|exists:contractors,id`) to the same request, and apply it in `DuesDiscountService::applyBulk()` — criteria mode currently filters on `year`/`status`/`source` only, so «خصم جماعي بمعايير» reaches every contractor's dues for that year. This is the defect that made 2 dues report as 6
- [ ] T005 [US1] Drop `paid` from the allowed `criteria.status` values, or keep it and exclude fully-paid dues from the matched set — a `paid` due can only ever land in `skipped`, so counting it as matched is misreporting by construction. Decide one and state it in a comment
- [ ] T006 [US1] Make the dry run compute impact through the **same** path as the real apply. `ContractorDue::applyDiscount()` accumulates when the incoming type equals the stored `discount_type` (`$value += $this->discount_value`), while the preview recomputes `original − value`; on any already-discounted due the applied impact therefore exceeds the previewed impact. Extract the amount computation out of `applyDiscount()` into one pure helper both callers use, rather than duplicating the accumulation rule
- [ ] T007 [US1] Have the dry run also return `applicable_count` and the `skipped` list (with reasons), computed by the same guard the real run uses — a due whose discount would drop `amount_jod` below `paid_jod` throws `InvalidArgumentException` on apply but is currently previewed as matched *and* has its full impact added to the total
- [ ] T008 [US1] Feature test: criteria mode with an empty criteria object is rejected; `contractor_ids` scoping matches only those contractors' dues; the dry run's `matched_count`/`applicable_count`/impact equal the real run's on a set that includes one already-discounted due and one that must be skipped
- [ ] T009 [US1] In `arab-contractors-union-front/resources/ts/pages/dues/index.vue`, add a contractor picker to the criteria-discount dialog (reuse the debounced `VAutocomplete` pattern already in `openCreateDue`, including the `justSelectedContractor` guard), send it as `criteria.contractor_ids`, and block submit while every criterion is empty
- [ ] T010 [US1] Show the preview as "N ذمة قابلة للخصم من M مطابقة، لدى K مقاولاً" plus the skipped reasons, and require a preview before the real apply in criteria mode — the current dialog lets an unscoped 100% discount go straight through

**Checkpoint**: the data-loss path is closed and the preview is trustworthy. Ship this on its own.

---

## Phase 3: Live-state verification (blocks US7, US8, US9)

**⚠️ Do this before writing any code for US7/US8/US9.** Three sub-issues in this batch are verbatim repeats of TASK-16 items recorded as done. The deploy history (TASK_PLAN.md § *Deploy-history evidence*) suggests the frontend work is live and the gaps are real, but a frontend build failure restores the previous `dist/` while the workflow still reports success, so "run succeeded" is not "dist updated".

- [x] T011 `GET https://api.pcuorg.cloud/api/v1/app/specialties-catalog` → read `items.upload_limits`. `max_file_mb: 2` means TASK-16 Phase 2 never ran; the key being absent means the API deploy is stale too
- [x] T012 [P] On `srv1962001`: `php -i | grep -E "upload_max_filesize|post_max_size|max_file_uploads"` — confirms T011 independently of the API
- [x] T013 [P] `grep -R client_max_body_size /etc/nginx` (`-R`, not `-r` — symlinks under `sites-enabled` hide it)
- [x] T014 [P] Is the live frontend the TASK-16 build? `grep -l "سيُحذف عند الحفظ" /var/www/pcuorg/front/dist/assets/*.js`. Present ⇒ current build, US7/US8 are real code work. Absent ⇒ the front deploy rolled back and the first fix is a redeploy
- [x] T015 [P] `git log -1` in `/var/www/pcuorg/monorepo` — the only trustworthy checkout on the box; `/var/www/pcuorg/{api,front}` git state is vestigial and lies
- [x] T016 Write the five answers into this file before proceeding, and adjust US7/US8/US9 scope accordingly

> **The five answers (2026-09-24).** Phase 3 was run *after* Phase 4, not before it, because the server change was already in flight. The answers are unaffected — T011/T012/T013 record the post-change state, and the "before" column is the TASK-16 baseline verified 2026-09-22.
>
> | # | Check | Answer |
> |---|---|---|
> | T011 | `items.upload_limits` | `max_file_kb: 12288`, `max_post_kb: 61440`, `max_files: 30` on **both** staging and production. Key present ⇒ API deploy is current |
> | T012 | PHP-FPM ini | `12M` / `60M` / `30` — was `2M` / `8M` / `20` |
> | T013 | `grep -R client_max_body_size` | `64M` in `api.pcuorg.cloud` and `api-production.pcuorg.cloud`. The "3 places" is 2 live blocks + 1 `.bak-2026-09-20` file |
> | T014 | Live `dist` is the TASK-16 build? | **Yes**, both environments (`front/dist/assets/_id_-D90bmqj7.js`, `production/front/dist/assets/_id_-dPF0plmR.js`) |
> | T015 | `monorepo` checkout | `9113efc` (2026-09-24), clean — matches the team-branch head before this merge |
>
> **Scope impact:** US9 is done (T017–T021). **US7 and US8 are genuine code work** — the front deploy did *not* roll back, so the reported gaps are real and a redeploy will not fix them.
>
> **Two corrections to this file's own instructions**, found while executing. The box runs **PHP 8.5**, so `/etc/php/*/fpm/` resolves to `/etc/php/8.5/fpm/` — not the 8.2 CLAUDE.md claimed. And **T012/T019's `php -i` is wrong**: it reads the CLI ini, not FPM's, so it would report the old values while the web path already served the new ones — use `php-fpm8.5 -i`. Also, the FPM pool is **shared by both environments**, so the PHP half of US9 was never scopeable to one environment alone.

---

## Phase 4: US9 — Upload ceiling raised (P1, #1) — carried unchanged from TASK-16 Phase 2

**⚠️ Deploy-invisible server state.** `deploy-vps.sh` does not manage `php.ini`, so this must be applied on the VPS directly and documented, or a rebuild silently reverts it. No application code is expected to change: TASK-16 already derives the ceiling from `ini_get()` at runtime via `App\Support\UploadLimits`, so the forms self-correct the moment the server is raised.

- [x] T017 [US9] Set `upload_max_filesize = 12M`, `post_max_size = 60M`, `max_file_uploads = 30` in the PHP-FPM ini under `/etc/php/*/fpm/` (was `2M` / `8M` / `20`). `post_max_size` must stay well above `upload_max_filesize` so a 13-document submit fits; 60M covers realistic scan sizes without permitting a 168M request
- [x] T018 [US9] Raise nginx `client_max_body_size` from `20M` to `64M` in the API server block so nginx is never the limiting factor (3 places — use `grep -R`)
- [x] T019 [US9] Reload both (`systemctl reload php*-fpm nginx`), verify with `php -i`, then smoke-test `GET /api/v1/tenders-public` — the FPM pool is shared by every upload path in the app, not just contractors
- [ ] T020 [US9] Verify end to end: a 9 MB PDF saves, and a submit carrying all documents at realistic sizes completes. Re-read `items.upload_limits` and confirm the form now advertises 12 MB with no redeploy
- [x] T021 [US9] Document the values and the fact that they are not managed by `deploy-vps.sh` in [CLAUDE.md](CLAUDE.md), alongside the existing Reverb/FCM "server state the deploy does not create" notes. Tick TASK-16 T003–T006 as done, or mark them superseded by these

**Checkpoint**: the loudest complaint in two consecutive batches is relieved, with zero code risk.

---

## Phase 5: US2 — Due dates cannot be backdated by accident (P1, #7)

**Goal**: A manually added due defaults to today-or-later, with an explicit opt-in for genuine arrears.
**Decision (2026-09-24, with the reporter)**: `after_or_equal:today` by default plus an explicit override, not a hard rule — the reporter raised legacy arrears themselves.

- [ ] T022 [US2] In `StoreContractorDueRequest`, make `due_date` `nullable|date|after_or_equal:today` unless an `allow_backdate` boolean is set, with an Arabic message naming the rule. Add `allow_backdate` as `boolean` (it is not a column — strip it before `ContractorDue::create()` in `ContractorDueController::store()`, the way `remove_documents` is stripped in `ContractorController`)
- [ ] T023 [US2] Require a reason when `allow_backdate` is set, and record it: append to the due's `notes` and include `allow_backdate` + reason in the existing `financeLog('due.created', …)` context. No migration
- [ ] T024 [US2] Leave `LegacyDuesImporter` and the `dashboard/dues/import` path untouched — it exists to load exactly these historical rows and must not inherit the rule. Add a comment saying so, since the next reader will wonder
- [ ] T025 [US2] Decide and state whether `UpdateContractorDueRequest` inherits the rule. Recommended: no — blocking an unrelated edit to an old due because its date is in the past would repeat the TASK-01 pattern of a new rule locking out existing records
- [ ] T026 [P] [US2] In `dues/index.vue`, bind `min` on the due-date field to today, add the «ذمة سابقة/متأخرة» checkbox that clears `min` and reveals the reason field, and send `allow_backdate`
- [ ] T027 [US2] Feature test: a past `due_date` is rejected without the flag, accepted with it, and the reason reaches the notes

---

## Phase 6: US3 — Penalties can be registered in any status, and the list actually renders (P1, #8)

**Goal**: An existing paper penalty can be entered in one step with its real status.
**Note**: the four statuses already exist end to end (`Penalty::STATUS_LABELS`, `PenaltyController::updateStatus`, TASK-06's `expand_penalty_statuses` migration, and the frontend `statusOptions`/`statusColor` maps). Only creation and the list read are missing.

- [ ] T028 [US3] **Fix the list first — it renders nothing today.** `fetchPenalties()` in `arab-contractors-union-front/resources/ts/pages/contractors/penalties.vue` reads `data.data || data || []`, but `ApiResponseTrait::paginated()` emits `{status, message, status_code, items, meta}`. There is no `data.data`, so the whole response **object** lands in `penalties.value` and `:items` receives a non-array. Read `data.items` and `data.meta.total`
- [ ] T029 [US3] Wire the page's `page` ref to a paginator control (it is incremented nowhere, so only page 1 is ever fetched) and pass `per_page`; `PenaltyController::index()` hardcodes `paginate(15)` — accept `per_page` there, capped, like `ContractorDueController::index()` does
- [ ] T030 [US3] Extract the status-transition mapping out of `PenaltyController::updateStatus()` into one place (a model method or small service): `paid → paid_amount = amount, paid_at = now()`, `partially_paid → paid_amount = input, paid_at = null`, `rejected → paid_amount = 0 + reject_reason`, `unpaid → zeroed`. Both create and update must use it — duplicating it is how the two drift
- [ ] T031 [US3] Extend `PenaltyController::store()` to accept `status` (`in:unpaid,paid,partially_paid,rejected`, defaulting to `unpaid`), `paid_amount` (`required_if:status,partially_paid`) and `reject_reason`, applying T030's mapping. Keep the existing guard that `paid_amount` for `partially_paid` must be **below** `amount`, and keep the `->refresh()` that TASK-06 added so DB defaults reach the response
- [ ] T032 [P] [US3] Add the status select to the add dialog in `penalties.vue`, revealing `paid_amount` only for `partially_paid` and `reject_reason` only for `rejected` — mirror how the existing `statusDialog` already does it, so the two dialogs behave identically
- [ ] T033 [US3] Feature test: a penalty created directly as `partially_paid` stores the paid amount and no `paid_at`; created as `paid` it stores `paid_amount = amount` and a `paid_at`; `paid_amount >= amount` on `partially_paid` is rejected 422
- [ ] T034 [P] [US3] Check whether any other admin page reads `data.data` from a `paginated()` endpoint — `penalties.vue` is the third found doing it. See the cross-cutting note; a quick `grep -rn "data.data" resources/ts/pages/` scopes it

---

## Phase 7: US4 — Every dues/penalty dialog closes on success (P1, #11)

**Goal**: No saved action leaves a modal open or the screen stale.
**⚠️ Confirm with the reporter first**: «تنفيذ ذمة» may mean *settlement*, and `POST dashboard/dues/{due}/settle` has **zero frontend consumers** — in that case this is a missing feature, not a modal bug. T035 decides which story this is.

- [ ] T035 [US4] Ask the reporter which screen and which button. If settlement: raise it as its own story (a settle dialog against the existing endpoint) rather than folding it in here
- [ ] T036 [US4] Close `bulkGenDialog` («توليد رسوم للكل») on a successful non-dry run in `dues/index.vue`, and flash an outcome **including the zero case** — it currently stays open and only calls `refreshAll()` when `created_count > 0`, so a run that creates nothing reports nothing at all
- [ ] T037 [US4] Close `importDialog` after a successful non-dry-run import and keep the result summary in the flash or a persistent alert on the page, rather than inside a dialog the user must dismiss
- [ ] T038 [P] [US4] Add the missing `onError` to `deleteDueMutation` (`dues/index.vue`) — a rejected delete is currently completely silent, which is exactly the shape that convinces a user rows are gone when they are not. This also matters for US5
- [ ] T039 [P] [US4] Audit the remaining dialogs on both pages for the close-and-refresh contract and note any that deliberately stay open, with the reason — `criteriaDiscountDialog` already carries such a comment from an earlier round of this same complaint

---

## Phase 8: US5 — The summary and the list can be reconciled (P2, #9)

**Goal**: Deleting every due visibly leaves the cards at 0, and the cards can be explained from the list.
**Note**: the delete path itself is sound — `destroy()` soft-deletes, `contractor_dues` has `deleted_at` (`2026_07_18_000004`), `summary()` runs through `ContractorDue::query()` so the global scope excludes them, and nothing caches it. The defect is that the screen is grouped by contractor and paginated at 15 while the cards are system-wide, so "I deleted them all" and "the total is not 0" can both be true.

- [ ] T040 [US5] Feature test: create dues across several contractors, delete them all, assert `GET dashboard/dues/summary` returns `outstanding_total_jod: 0`, `collected_total_jod: 0` and `contractors_with_dues: 0`. This pins the soft-delete path for good and settles the question if it is ever re-reported
- [ ] T041 [US5] Add `dues_count` (and the number of contractors covered) to the `summary()` payload so the cards state the population they are computed over, not just a money figure
- [ ] T042 [US5] Give the dues screen a flat all-dues view against the existing `GET dashboard/dues` (filters: contractor, year, status, source; it already supports all four), so an admin can see every due rather than page 1 of contractors. This is what makes the two numbers reconcilable
- [ ] T043 [US5] Surface the count next to the cards in `dues/index.vue`, and show the active filter set on the flat view so a filtered total is never mistaken for the system total

---

## Phase 9: US6 — Export carries the classification data the add form collects (P2, #4)

**Goal**: A contractor added today exports with their specializations and classifications, in Arabic.
**Note**: the export is client-side CSV (`exportContractors()`, `contractors/index.vue:74`), not a backend endpoint. Its «التخصص» column reads `c.trade` and «التصنيف» reads raw `c.classification`, while the add form stores everything in the `specialties[]` JSON column. The three resolvers needed (`getFieldTitle`, `getSpecializationTitle`, `getGradeTitle`) already exist on the same page, fed by `specialties-catalog`.

- [ ] T044 [US6] Make `exportContractors()` await the catalog before building rows — the resolvers return codes or blanks until it has loaded, and the export currently does not wait for it
- [ ] T045 [US6] Replace the single «التخصص»/«التصنيف» pair with columns built from `specialties[]`: one cell joining «المجال: التخصص (الدرجة)» per entry, resolved to Arabic labels. Keep `trade` as its own «التخصص (نص حر)» column so legacy rows do not lose data
- [ ] T046 [US6] Resolve «التصنيف العام» through `getGradeTitle(c.classification)` instead of emitting the raw code, matching what the preview dialog already shows (`activityRows`)
- [ ] T047 [P] [US6] Handle `specialties` arriving as a JSON **string** as well as an array — the preview dialog already guards for both (`index.vue:338`), so the export must too
- [ ] T048 [US6] Verify by creating a contractor through the add form with 2 specialty rows and a general classification, then exporting: both appear as Arabic labels, and an older `trade`-only contractor still exports its trade

---

## Phase 10: US7 — Every stored document can be deleted (P2, #2)

**Goal**: No document is reachable for upload but unreachable for removal.
**Note**: TASK-16's `remove_documents[]` contract and the per-document 🗑 control both exist and work. The gap is coverage: the backend `FILE_FIELDS` map has 14 entries and the frontend `documentKeys` list has 13.

- [ ] T049 [US7] Add `id_file` to `documentKeys`, `documentLabels` and the document blocks in `arab-contractors-union-front/resources/ts/pages/contractors/edit/[id].vue` — `grep -c id_file` returns **0** there today, so صورة الهوية has no preview link, no replace input and no delete control at all. It is in the backend `FILE_FIELDS` and on the create form, so this is a pure omission
- [ ] T050 [US7] Decide on `authorized_signature`: it is accepted by the contractor-app profile route (`UpdateFullProfileRequest`) and listed in `Contractor::FILE_FIELDS`, but is **not** in `ContractorController::FILE_FIELDS`, so admins can neither replace nor remove it. Either add it to the admin map (and both forms) or document why it is contractor-managed only
- [ ] T051 [US7] Re-run the count as a guard: assert in a test that `ContractorController::FILE_FIELDS`'s keys and the frontend document list have the same membership, so the next added document cannot land on one side only
- [ ] T052 [US7] Feature test extension in `ContractorDocumentRemovalTest`: removing `id_file` nulls the column, deletes the file from the `public` disk, and leaves the other documents' paths untouched

---

## Phase 11: US8 — Preview dialog parity, re-audited (P3, #3)

**Goal**: The preview shows what the add form collects, verified rather than asserted.
**Note**: both fields the sheet names are already rendered — «التصنيف العام» in `activityRows` (line 401) and «رقم هوية المفوض» in `managementRows` (line 374, TASK-16 T026). The sub-issue ends in "…", so treat it as an audit. Depends on T014: if the live bundle predates `61f5b71`, the first action is a redeploy.

- [ ] T053 [US8] Diff every key in `create.vue`'s `form` ref against the dialog's row builders in `contractors/index.vue` (`membershipRows`, `managementRows`, `contactRows`, `activityRows`, the specialties section). Record the result — including fields deliberately excluded — so the next batch does not re-audit from scratch
- [ ] T054 [US8] Add whatever the diff finds, binding `:dir="row.dir"` for any LTR value (the management section needed that binding added in TASK-16 and it is the easy thing to miss)
- [ ] T055 [P] [US8] Do the same diff for `edit/[id].vue`, which is not identical to `create.vue` (documents are required on create but not on edit, and the two forms' rule arrays differ) — a field present only on edit would be invisible in the dialog too

---

## Phase 12: US10 — A membership certificate request reaches the panel while its payment is pending (P2, #5)

**Goal**: A contractor who pays the fee from the app sees their request reach the dashboard; an admin sees it with its payment state and cannot issue before the money is confirmed.
**Root cause**: `CertificateRequestController::store()` calls `checkEligibility()`, which for `membership` requires `isEligibleForMembershipCertificate()` — 95% of the current year's dues paid. A fee paid from the app is a `Payment{status: 'pending'}`, and `paid_jod` only moves when an accountant confirms it, so the request is refused **403 `dues_below_threshold`** and no row is ever created.
**Decision (2026-09-24, with the reporter)**: surface the request; keep issuance gated.

- [ ] T056 [US10] Read the `certificate_requests` migration and decide the representation **before** coding: prefer an additive column (e.g. a nullable `pending_payment_id`, or a flag) over widening the `status` enum, which `CertificateRequestResource`, the admin filters and the contractor app all read
- [ ] T057 [US10] In `CertificateEligibilityService::checkEligibility()`, allow the `membership` case through when the **only** remaining blocker is `dues_below_threshold` and the contractor has a pending `membership_fee` payment whose amount covers the gap to 95%. Every other blocker (incomplete profile, frozen account, the non-dues `issues`) must still refuse — the relaxation is narrow and must read that way in code
- [ ] T058 [US10] Record the linked payment on the created request in `store()`, and return a message that says the request is filed and awaiting payment confirmation — so the app stops having to guess
- [ ] T059 [US10] Block `approve()`, `issue()` and `regenerate()` while the linked payment is unconfirmed, with an explicit Arabic reason. **This is the load-bearing half**: a certificate must never issue against unconfirmed money
- [ ] T060 [P] [US10] Show the payment state on the row and in the detail dialog in `arab-contractors-union-front/resources/ts/pages/certificate-requests.vue`, and disable the approve/issue actions with a tooltip explaining why, rather than letting the admin discover it through a 422
- [ ] T061 [US10] Feature tests: the request is created while the payment is pending; `approve`/`issue` are refused until it is confirmed and succeed after; a contractor blocked for a non-dues reason is still refused at submit
- [ ] T062 [P] [US10] Update the `Certificate Requests` descriptions in [Contractor_App_API.postman_collection.json](Contractor_App_API.postman_collection.json) — contractor-facing contract change
- [ ] T063 [US10] Record in TASK_PLAN.md that «طلبات الانتساب» (`memberships.vue`) reads a table nothing in the app writes to and is therefore structurally empty — out of scope here, but it will be re-reported otherwise. TASK-16 #6 closed on a restored nav link to that page

---

## Phase 13: US11 — Company profile edits become reviewable requests (P3, #6)

**Goal**: An edit made from the app leaves the contractor record unchanged and appears in «طلبات تعديل البيانات», documents included.
**Root cause**: the app posts to `POST contractor/auth/profile/update` → `ContractorAuthController::updateFullProfile()`, which writes straight to `contractors`. `PATCH contractor/auth/profile` (`updateProfile`) is a second such path. The approval queue is a different endpoint the app never calls.
**Decision (2026-09-24, with the reporter)**: close it in the backend. This is the largest item in the batch and the only one needing a migration.
**⚠️ Design plan: [TASK-17-US11-profile-edit-design.md](TASK-17-US11-profile-edit-design.md).** Read it before T064 — it settles six decisions (field tiers, document staging, OTP, supersede, response shape, feature flag), carries the app-side contract to hand to Moamen, and records a finding more serious than the reported bug: the app can currently write `classification` and `specialties`, which are the membership-fee calculator's own inputs.

- [ ] T064 [US11] Decide and document the field split first: which fields file a request (company identity, documents) versus which stay instant (logo, password, and phone, which already has its own OTP flow). Write it as one list in code that both the request builder and `ALLOWED_FIELDS` derive from — two lists will drift
- [ ] T065 [US11] Widen `ProfileUpdateRequest::ALLOWED_FIELDS` (5 keys today) to the reviewable set from T064. `proposed_data` is JSON, so the ~25 text fields need no schema change
- [ ] T066 [US11] Migration: give `profile_update_requests` somewhere to carry **staged document uploads**. The existing `attachment` column is the contractor's *evidence*, not the payload. Stage the files under their own path on submit and keep the live document untouched until approval
- [ ] T067 [US11] Rewrite `updateFullProfile()` to file a request instead of writing, preserving: the existing phone OTP pre-verification (it uses `getVerifiedResult`, while `ProfileUpdateRequestController::store()` uses `verifyOtp` — reconcile to one path, do not stack both), `applyLocation()`, and the `specialties`/`partners` JSON decoding
- [ ] T068 [US11] Close `updateProfile()` (`PATCH profile`) the same way, or it becomes the bypass that makes the whole story pointless
- [ ] T069 [US11] Make `approve()` apply staged files safely: move each staged file into place, delete the superseded one, and never splat a raw path onto the model without validating it came from this request. It currently does `$contractor->update($proposed_data)` verbatim
- [ ] T070 [US11] Keep the single-pending-request guard from `store()` (`hasPending`) and give a clear Arabic message when an edit is attempted while one is open — the app will hit this immediately
- [ ] T071 [US11] Extend `contractors/profile-update-requests.vue`'s current-vs-proposed diff: it is built off a fixed 5-key Arabic label map (TASK-16 T014), which the widened field set breaks. Render document changes as before/after links, not paths
- [ ] T072 [US11] Feature tests: a text edit and a document edit each create a pending request and leave the contractor unchanged; approval applies both and moves the file; rejection leaves everything untouched and stores the reason
- [ ] T073 [US11] **Breaking app contract** — the response no longer reflects the new values. Update [Contractor_App_API.postman_collection.json](Contractor_App_API.postman_collection.json) and tell Moamen, so the app shows «قيد المراجعة» instead of a saved profile. TASK-16's own Postman pass exists because a wrong entry there cost the app developer real time

---

## Phase 14: Polish & Cross-Cutting

- [ ] T074 [P] `npm run typecheck` — no new errors against T001's baseline
- [ ] T075 [P] `php artisan test` — all new tests green, pre-existing failures unchanged in count and identity
- [ ] T076 [P] Attempt `npm run lint`; if it still cannot run repo-wide (`--rulesdir eslint-internal-rules/`), leave it and note it rather than reporting it as passed
- [ ] T077 Consider the shared `ConfirmDeleteDialog` component that the cross-cutting notes have recommended since TASK-01 — this batch adds more one-off dialogs. Scope it separately, do not fold it into a story
- [ ] T078 Write the «Execution summary (2026-XX-XX)» block into TASK-17 in TASK_PLAN.md in the house format, recording what was actually found versus planned — including which of T011–T015 came back which way
- [ ] T079 **Post-deploy verification, not just green tests.** TASK-16 was marked done and four of its sub-issues returned three days later. After the deploy reports green, confirm on staging that each acceptance criterion actually holds, and confirm the frontend really updated (a front build failure rolls back to the old `dist/` while still reporting success)
- [ ] T080 Before pushing: `VITE_API_BASE_URL` in `.env.production` is baked in at build time. Ask which environment the push is meant to reach — `CLAUDE.md` requires it every time

---

## Dependencies

```
Phase 1 (Setup)
   ├─→ Phase 2  US1  (P0 — do first, independent)
   ├─→ Phase 3  Live-state verification
   │      ├─→ Phase 4  US9  (server limits)
   │      ├─→ Phase 10 US7
   │      └─→ Phase 11 US8
   ├─→ Phase 5  US2   independent
   ├─→ Phase 6  US3   independent  ← T028 blocks UI verification of the rest
   ├─→ Phase 7  US4   independent  ← T035 may reclassify the story
   ├─→ Phase 8  US5   independent  ← T002 unrelated; reads US1's criteria work
   ├─→ Phase 9  US6   independent  ← T002 confirms its one assumption
   ├─→ Phase 12 US10  independent  ← T056 decides the schema before any code
   └─→ Phase 13 US11  independent  ← T064 decides the field split before any code
                            └─→ Phase 14 (Polish)
```

Within stories: T003→T004→T006→T007 then T009/T010 (server contract before UI). T028→T029→T031→T032 (the list must render before creation can be verified). T056→T057→T059 and T064→T065→T066→T067 (decide the representation before writing against it).

## Parallel opportunities

- **Across stories**: US2, US3, US4, US5, US6, US10 and US11 touch disjoint files once Phase 1 is done. US1 is independent too and should simply go first.
- **Sequencing caution**: US1, US4 and US5 all modify `dues/index.vue`; US3 and US4 both modify `penalties.vue`; US7 and US8 both modify the contractor forms and `contractors/index.vue`. Run each of those groups in series, not in parallel, or expect conflicts.
- **Server-only**: Phase 4 is disjoint from all code work and can run alongside anything.

## Suggested MVP

**US1 + US3's T028.** US1 closes a path where an empty criteria object discounts every due in the database — the only item here that can destroy data. T028 is a one-line read fix that makes a whole admin screen stop rendering blank. Neither needs a migration, a server change, or any coordination.

Add **Phase 4** to the first push if a quiet window allows: it is config-only, carries no code risk, and relieves the complaint that has now arrived twice.
