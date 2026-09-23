# TASK-16 — Tasks

Executable task breakdown for [TASK_PLAN.md](TASK_PLAN.md) § `TASK-16 — Feedback batch 2026-09-22`.
Read that section first — it carries the root-cause analysis these tasks depend on.

**Stack**: Laravel 12 / PHP 8.2 (`arab-contractors-union-api/`), Vue 3 + TypeScript + Vuetify 3 (`arab-contractors-union-front/`).
**Conventions**: file-based routing (no route table to edit); all admin UI strings in Arabic; `npm run typecheck` and `php artisan test` are the gates.

**Story → sub-issue map**

| Story | Sub-issue(s) | Priority | Independently testable? |
|---|---|---|---|
| US1 | #6 Membership requests invisible | P1 | Yes |
| US2 | #7 Profile edit requests invisible | P1 | Yes |
| US3 | #1 Home feed 5 + "عرض المزيد" | P2 | Yes |
| US4 | #5 Preview dialog field parity | P2 | Yes |
| US5 | #2 + #3 Upload size limits & error clarity | P3 | Yes (after Phase 2) |
| US6 | #4 Delete documents on edit | P3 | Yes |

---

## Phase 1: Setup

- [x] T001 Record the current green baseline: run `php artisan test` in `arab-contractors-union-api/` and `npm run typecheck` in `arab-contractors-union-front/`, and note the pre-existing failure/error set in the scratch notes for this task — TASK-01 documented that this project carries a non-empty `vue-tsc` baseline, so a clean run is not expected and later comparisons need the starting point
- [x] T002 [P] Confirm the admin contractors list/show endpoint actually serializes `authorized_person_id_number`, `authorized_person_phone` and `authorized_person_whatsapp` by hitting `GET /api/v1/dashboard/contractors` against a local seed, since US4 assumes they are present in the payload (they are on `Contractor::$fillable` at `arab-contractors-union-api/app/Models/Contractor.php:29` and have no API Resource filtering, but this is the one unverified assumption in US4)

---

## Phase 2: Foundational (blocks US5; do first — highest pain relief per unit of risk)

**⚠️ Production server configuration. This is deploy-invisible state — `deploy-vps.sh` does not manage `php.ini`, so it must be applied on the VPS directly and documented, or a server rebuild silently reverts it.**

- [ ] T003 On the production VPS (`srv1962001`), set `upload_max_filesize = 12M`, `post_max_size = 60M`, `max_file_uploads = 30` in the PHP-FPM ini under `/etc/php/*/fpm/` (current values are `2M` / `8M` / `20` — verified 2026-09-22). `post_max_size` must stay comfortably above `upload_max_filesize` so a multi-document submit fits; 60M covers all 14 document fields at realistic scan sizes without permitting a 168M request
- [ ] T004 Raise nginx `client_max_body_size` from `20M` to `64M` in the API server block so nginx stays above `post_max_size` and is never the limiting factor. Note the setting appears in 3 places — use `grep -R` (not `grep -r`; symlinks under `sites-enabled` hide it from `-r`)
- [ ] T005 Reload both services (`systemctl reload php*-fpm nginx`) and verify with `php -i | grep -E "upload_max_filesize|post_max_size|max_file_uploads"`, then smoke-test the API with `GET /api/v1/tenders-public` to confirm nothing else broke — PHP-FPM config is shared by every upload path in the app, not just contractors
- [ ] T006 Document the new limits, their values, and the fact that they are not managed by `deploy-vps.sh` in `CLAUDE.md`, alongside the existing Reverb/FCM "server state the deploy does not create" notes

**Checkpoint**: A >2 MB document now reaches Laravel at all. US5's code work is verifiable from here; US1–US4 and US6 do not depend on this phase.

---

## Phase 3: US1 — Membership requests reachable again (P1, #6)

**Goal**: An admin can navigate to «طلبات الانتساب» and action the requests that have been arriving all along.
**Independent test**: Log in as admin → the sidebar «المقاولون» group lists «طلبات الانتساب» → clicking it loads the existing page with real pending rows → approve and reject both work.
**Note**: This reverses the TASK-02 decision, confirmed with the reporter on 2026-09-22. Backend, routes and the page itself were never touched by TASK-02 and need no changes.

- [x] T007 [P] [US1] Restore the `{ title: 'طلبات الانتساب', to: 'contractors-memberships' }` child entry in the «المقاولون» group in `arab-contractors-union-front/resources/ts/navigation/vertical/pcu.ts` (currently a two-line comment at line 36), placing it between «تسجيل مقاول جديد» and «طلبات تعديل اسم الشركة» to match the original order
- [x] T008 [P] [US1] Restore the matching entry in `arab-contractors-union-front/resources/ts/navigation/horizontal/index.ts` (commented out at line 20)
- [x] T009 [P] [US1] Restore the «طلبات الانتساب» quick-action card in `arab-contractors-union-front/resources/ts/pages/dashboards/index.vue` (commented out at line 61)
- [x] T010 [US1] Verify `arab-contractors-union-front/resources/ts/pages/contractors/memberships.vue` still renders and that `GET/POST dashboard/memberships*` (`arab-contractors-union-api/routes/api.php:386-390`) respond — the page has had no traffic since 2026-09-19, so confirm it did not silently rot against later API changes before declaring this done
- [x] T011 [US1] Update TASK-02's status line in `TASK_PLAN.md` to record the reversal and its date, following the same format TASK-04 uses for its own reversal

**Checkpoint**: US1 is independently shippable.

---

## Phase 4: US2 — Profile edit requests visible and actionable (P1, #7)

**Goal**: A profile edit submitted from the Flutter app appears in the admin panel and can be approved or rejected.
**Independent test**: Submit a profile edit from the mobile app (or `POST /api/v1/contractor/profile-update-requests` directly) → it appears in the new admin list with the proposed values → approve applies them to the contractor record → reject with a reason stores the reason and leaves the contractor untouched.
**Note**: The entire backend already exists and is covered by 13 tests in `tests/Feature/ProfileUpdateRequestTest.php`. This story is **frontend-only**. Rows have been accumulating in `profile_update_requests` with no consumer.

- [x] T012 [US2] Create `arab-contractors-union-front/resources/ts/pages/contractors/profile-update-requests.vue`, modelled on `arab-contractors-union-front/resources/ts/pages/contractors/name-change-requests.vue` (248 lines, same approve/reject shape) — `useQuery` against `GET /api/v1/dashboard/profile-update-requests` with `page` and `status` params, a status filter with options `pending` / `approved` / `rejected`, and the same `pending`/`approved`/`rejected` → color map
- [x] T013 [US2] Render each row from the endpoint's `format()` payload (`arab-contractors-union-api/app/Http/Controllers/Api/ProfileUpdateRequestController.php:32`), which returns exactly: `id`, `contractor_id`, `contractor` (name string, not an object), `proposed_data` (JSON), `attachment_url`, `status`, `status_label`, `reject_reason`, `reviewed_by` (name string), `reviewed_at`, `created_at` — do not assume a nested contractor object as other admin pages have
- [x] T014 [US2] In the detail dialog, render `proposed_data` as a **current-vs-proposed diff** rather than a raw JSON dump. Only the five keys in `ProfileUpdateRequest::ALLOWED_FIELDS` can ever appear (`email`, `phone`, `address`, `authorized_person`, `authorized_person_title`) — the controller hard-whitelists via `array_intersect_key` at line 100, so the diff can be driven off a fixed Arabic label map instead of dynamic keys
- [x] T015 [US2] Add the approve action: `useMutation` → `POST /api/v1/dashboard/profile-update-requests/{id}/approve`, invalidating the list query on success
- [x] T016 [US2] Add the reject action: `POST /api/v1/dashboard/profile-update-requests/{id}/reject` with a `reject_reason` body, gated behind the same client-side guard `name-change-requests.vue` uses — reason required, minimum 5 characters, Arabic error «سبب الرفض إلزامي (5 أحرف على الأقل) لإبلاغ المقاول به.»
- [x] T017 [US2] Show `attachment_url` as a view/download link when non-null — the contractor endpoint requires an attachment on submit (`test_store_fails_validation_without_attachment`), so it should be present on every real row and is the admin's evidence for approving
- [x] T018 [US2] Add a «طلبات تعديل البيانات» entry to the «المقاولون» children in `arab-contractors-union-front/resources/ts/navigation/vertical/pcu.ts`, next to the existing «طلبات تعديل اسم الشركة», guarded by the same `if (!isAccountant)` block
- [x] T019 [P] [US2] Add the route-name → Arabic breadcrumb title mapping for the new page in `arab-contractors-union-front/resources/ts/.../guards.ts`, matching how `contractors-name-change-requests` is registered
- [x] T020 [US2] Confirm the page's auto-generated route name is `contractors-profile-update-requests` (PascalCase → kebab-case conversion in `arab-contractors-union-front/vite.config.ts`) and that the nav `to:` in T018 matches it exactly — a mismatch renders a dead link with no build error

**Checkpoint**: US2 is independently shippable and clears the backlog of unseen requests.

---

## Phase 5: US3 — Home feed shows 5 with a "عرض المزيد" signal (P2, #1)

**Goal**: `GET contractor/home` returns 5 updates plus enough metadata for the app to decide whether to draw the «عرض المزيد» button.
**Independent test**: Seed >5 feed-eligible records for a contractor → `GET /api/v1/contractor/home` returns exactly 5 in `latest_updates` and `has_more: true`; seed 3 → returns 3 and `has_more: false`.
**Note**: The "separate page" target already exists and is fully built — `GET contractor/home/updates` paginates the same feed at 20/page. No new endpoint is needed; the mobile app wires the button to it.

- [x] T021 [US3] Change `HOME_UPDATES_LIMIT` from `3` to `5` in `arab-contractors-union-api/app/Http/Controllers/Api/ContractorHomeController.php:39` (TASK-15 set it to 3; the sheet now asks for 5)
- [x] T022 [US3] In `index()` (line 45), compute the feed once into a local variable and add `latest_updates_total` (full `$feed->count()`) and `has_more` (`total > HOME_UPDATES_LIMIT`) to the response array — currently `buildFeed()` is called inline inside the `take()`, so capture it first rather than building the feed twice
- [x] T023 [US3] Add a feature test to `arab-contractors-union-api/tests/Feature/ContractorHomeTest.php` asserting `latest_updates` caps at 5 and that `has_more` is `true` above the cap and `false` at or below it
- [x] T024 [US3] Update the `Home` request description in `Contractor_App_API.postman_collection.json` to document the new `latest_updates_total` / `has_more` keys and point at `contractor/home/updates` as the «عرض المزيد» target — contractor-facing contract changes must stay in sync with this collection
- [ ] T025 [US3] Notify the mobile-app side (Moamen) that `has_more` is now available, since the button itself is theirs to build — TASK-15 already flagged this half as app-side

**Checkpoint**: US3 is independently shippable; the button appears once the app consumes it.

---

## Phase 6: US4 — Preview dialog shows every field the add form collects (P2, #5)

**Goal**: «معاينة بيانات المقاول» displays the same data set that was entered on the add form.
**Independent test**: Create a contractor filling every field → open the preview dialog → every non-document field entered is visible.
**Note**: «التصنيف العام» is **already present** (`activityRows`, `arab-contractors-union-front/resources/ts/pages/contractors/index.vue:398`) — added by TASK-03. A full form-vs-dialog diff found exactly three genuinely missing fields, all in the management group.

- [x] T026 [US4] Add «رقم هوية المفوض» (`authorized_person_id_number`) to `managementRows` in `arab-contractors-union-front/resources/ts/pages/contractors/index.vue:367`, directly after «المفوض بالتوقيع», with the `|| '—'` fallback and an icon consistent with the neighbouring rows (`tabler-id`)
- [x] T027 [US4] Add «رقم جوال المفوض» (`authorized_person_phone`) to the same `managementRows`, with `dir: 'ltr'` to match how `phone`/`fax` are rendered in `contactRows`
- [x] T028 [US4] Add «رقم واتساب المفوض» (`authorized_person_whatsapp`) to `managementRows`, also `dir: 'ltr'`
- [x] T029 [US4] Re-run the form-vs-dialog diff after the additions to confirm nothing else is missing: every key in `create.vue`'s `form` ref should map to a dialog row, a dedicated section (`specialties`, documents), or be deliberately excluded (`notes` is already handled — 3 references exist in `index.vue`). The sub-issue says «مثل» (e.g.), so completeness matters more than the two named examples

**Checkpoint**: US4 is independently shippable.

---

## Phase 7: US5 — Upload limits visible and failures explained (P3, #2 + #3)

**Goal**: Users see the size ceiling before choosing a file, oversize files are rejected client-side before any upload starts, and a server-side rejection states the reason.
**Independent test**: Attach a file above the ceiling → rejected instantly with an Arabic size message, no network request. Attach a 9 MB PDF → saves successfully. Force an oversize total body → a clear 413 message, not a spurious "required" cascade.
**Depends on**: Phase 2. Without it the server still silently drops anything over 2 MB and none of this is verifiable.

- [x] T030 [US5] Update the 14 document rules in `ContractorController::getValidationRules()` (`arab-contractors-union-api/app/Http/Controllers/Api/ContractorController.php:117-130`) so `max:` matches the `upload_max_filesize` set in T003, keeping `mimes:pdf,doc,docx,jpg,jpeg,png` unchanged — TASK-01 #3 established that images stay allowed by stakeholder decision
- [x] T031 [US5] Expose the effective ceiling (in KB) and the allowed extension list from the API so the forms cannot drift from the server — extend the existing `GET /api/v1/app/specialties-catalog` payload (already fetched by both forms) rather than adding a new endpoint and a second round-trip
- [x] T032 [US5] Add a middleware that detects a discarded request body — non-GET request, `Content-Length` header present and non-zero, yet `$_POST` and `$_FILES` both empty — and returns HTTP 413 with an explicit Arabic message. Register it in the `api` group in `arab-contractors-union-api/bootstrap/app.php` (see the existing `appendToGroup('api', …)` at line 43). It must run **before** validation, otherwise Laravel reports "required" on every field and masks the real cause
- [x] T033 [P] [US5] Add a `rules` size check plus `hint` + `persistent-hint` stating the max size and allowed types to all 14 `VFileInput`s in `arab-contractors-union-front/resources/ts/pages/contractors/create.vue`, sourced from T031. The client-side rule is what actually fixes #3 — it stops the browser transferring a file that will be rejected
- [x] T034 [P] [US5] Apply the identical change to all 14 `VFileInput`s in `arab-contractors-union-front/resources/ts/pages/contractors/edit/[id].vue`
- [x] T035 [US5] Handle 413 explicitly in both forms' catch blocks (`create.vue:216` and the edit equivalent) with a specific Arabic message about total attachment size, instead of falling through to the generic «فشل تسجيل المقاول. يرجى التحقق من المدخلات.»
- [x] T036 [US5] Verify a failed submit preserves all entered form state in both forms — the reporter lost a large amount of typed input on failure. Confirm no reset/clear runs on the error path and that the step-jump logic at `create.vue:223-227` does not discard other steps' values
- [x] T037 [US5] Add a feature test asserting an oversize file returns a size-specific validation error naming the field, not a generic failure

**Checkpoint**: US5 is independently shippable.

---

## Phase 8: US6 — Documents can be deleted, not only replaced (P3, #4)

**Goal**: An admin can remove an attached document outright.
**Independent test**: Open edit on a contractor with 3 documents → delete one → confirm → save → that column is null, its file is gone from storage, and the other two are untouched.

- [x] T038 [US6] Add a `remove_documents` rule to the update validation in `arab-contractors-union-api/app/Http/Controllers/Api/ContractorController.php` — `nullable|array`, with `remove_documents.*` constrained by `Rule::in()` over the 14 keys of the `$fileFields` map in `handleFileUploads()` (line 134), so an arbitrary column name can never be nulled
- [x] T039 [US6] Extend `handleFileUploads()` (line 133) to process `remove_documents[]` after the existing upload loop: for each named field, `Storage::disk('public')->delete($contractor->$field)` and set `$validated[$field] = null`. Skip any field that also has an uploaded file in the same request — a replace already deletes the old file at line 153 and must win over a stale remove flag
- [x] T040 [US6] Use an explicit opt-in list and **do not** reinstate "empty value means delete". TASK-01 #4 specifically added a guard that skips empty `VFileInput` arrays when building `FormData`, because Vuetify resets a cleared file input to `[]` and Laravel then rejected it with «يجب أن يكون حقل … ملفاً» on fields the user never touched. Treating empty as delete would re-break that fix
- [x] T041 [US6] Add a delete (🗑) control beside each already-uploaded document in `arab-contractors-union-front/resources/ts/pages/contractors/edit/[id].vue` — visible only where an existing file is present (the «مرفوع» badge branch), marking the field for removal locally rather than firing an immediate request
- [x] T042 [US6] Gate it behind a confirmation dialog, following the established pattern (`removeSpecialtyDialog` in the same file, `deleteDueDialog` in `dues/index.vue`) — deletion is irreversible once saved
- [x] T043 [US6] Append the marked fields as `remove_documents[]` entries in the edit form's `FormData` submit path, and give a marked-for-removal field clear visual state so it is distinguishable from both "unchanged" and "replaced" before save
- [x] T044 [US6] Add a feature test: mark one document for removal, assert the column is null, the file is gone from the `public` disk, and the other documents' paths are unchanged

**Checkpoint**: US6 is independently shippable.

---

## Phase 9: Polish & Cross-Cutting

- [x] T045 [P] Run `npm run typecheck` in `arab-contractors-union-front/` and confirm no new errors against the T001 baseline
- [x] T046 [P] Run `php artisan test` in `arab-contractors-union-api/` and confirm all green including the new US3/US5/US6 tests
- [ ] T047 [P] Run `npm run lint` in `arab-contractors-union-front/`
- [ ] T048 Consider extracting the shared `ConfirmDeleteDialog` component the cross-cutting notes have been recommending since TASK-01 — US6 adds the sixth one-off implementation of this dialog. Scope it as its own change rather than folding it into US6
- [ ] T049 Write the «Execution summary (2026-XX-XX)» block into TASK-16 in `TASK_PLAN.md` in the house format, recording what was actually found versus planned
- [ ] T050 Before pushing: confirm `VITE_API_BASE_URL` in `.env.production` is correct — it is baked in at build time and a push to `feature/arab-contractors-union` deploys straight to production. After the deploy reports green, verify the frontend actually updated (a front build failure rolls back to the old `dist/` while still reporting success)

---

## Dependencies

```
Phase 1 (Setup)
   ├─→ Phase 2 (Server limits) ──→ US5 (Phase 7)
   ├─→ US1 (Phase 3)   independent
   ├─→ US2 (Phase 4)   independent
   ├─→ US3 (Phase 5)   independent
   ├─→ US4 (Phase 6)   independent  ← T002 confirms its one assumption
   └─→ US6 (Phase 8)   independent
                            └─→ Phase 9 (Polish)
```

Within stories: T030→T031→T033/T034 (forms need the server-sourced ceiling). T038→T039→T041→T043 (backend contract before UI wiring). Everything else inside a story is sequential only where it touches the same file.

## Parallel opportunities

- **Across stories**: US1, US2, US3, US4 and US6 touch disjoint files and can run fully in parallel once Phase 1 is done. Only US5 is gated, on Phase 2.
- **US1**: T007, T008, T009 are three different files — all `[P]`.
- **US5**: T033 and T034 are the same edit applied to two different files — `[P]`.
- **Phase 9**: T045, T046, T047 are independent checks — `[P]`.
- **Sequencing caution**: US5 (T030) and US6 (T038/T039) both modify `ContractorController.php`, and US5 (T033/T034) and US6 (T041/T043) both modify the two contractor forms. Run those two stories in series, not in parallel, or expect conflicts.

## Suggested MVP

**US1 + US2.** Together they close both "the request never shows up in the panel" reports — the two issues with real data already queued and invisible behind them. US1 is three uncommented lines; US2 is one new page against an endpoint and test suite that already exist. Neither needs a migration, a backend change, or the server work.

Add **Phase 2** to that first push if the deploy window allows: it is config-only, carries no code risk, and immediately relieves the loudest complaint in the batch.
