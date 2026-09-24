# TASK_PLAN.md

Source: [Feedback tracking sheet](https://docs.google.com/spreadsheets/d/1XclGZ12OOij9mKePxbusDuDE75-2p66MMj8f1Iw5zNk/edit?gid=0#gid=0)

Each task below corresponds to one row ("Feature Name") in the sheet. Sub-issues from the "Problem Detail" / "Problem Solved" / "Application Problem" columns are numbered as they appear in the sheet (Arabic, kept verbatim) with an English gloss.

---

## TASK-01 — Edit Contractor — ✅ STATUS: DONE (all 7 sub-issues)

**Description**: Admin-dashboard contractor edit/create flow has multiple data-integrity, UX and validation gaps around founding date, address hierarchy, attachments, specialties/fields linkage, and phone-OTP re-verification.

### Execution summary (2026-09-19)

| # | Sub-issue | Result |
|---|---|---|
| 1 | Founding date not shown on edit | **Already working** — `established_date` was already in `fetchContractor()`'s `textFields`/`dateFields` mapping in [edit/[id].vue](arab-contractors-union-front/resources/ts/pages/contractors/edit/%5Bid%5D.vue). No code change needed; verified by reading the mapping logic. |
| 2 | Confirm dialog before removing a specialty | **Already implemented on edit page** (`removeSpecialtyDialog`/`confirmRemoveSpecialty`). Added the same dialog to [create.vue](arab-contractors-union-front/resources/ts/pages/contractors/create.vue) for parity, since it only guarded the button with `:disabled` there before. |
| 3 | Don't allow images-only, require real documents | **Revisited per stakeholder decision**: images are still allowed alongside documents (business wants photo scans to keep working) — added `doc,docx` to the previously PDF/JPG/PNG-only mime list instead of removing images. `ContractorController::getValidationRules()` file rules are now `mimes:pdf,doc,docx,jpg,jpeg,png` (was `pdf,jpg,jpeg,png`), `accept` on every `VFileInput` in both forms is `.pdf,.doc,.docx,image/*`. |
| 4 | False "must be a file" error after clearing an attachment on a failed update | **Root cause found and fixed.** Vuetify's `VFileInput` resets to `[]` (not `null`) when a file is cleared. `submit()` in both forms treated non-`File` non-`null` values as appendable, so an empty array was sent as the field value and Laravel rejected it with "يجب أن يكون حقل … ملفاً" even though the user never touched that field. Added an explicit guard to skip empty-array values before building `FormData`, in both `create.vue` and `edit/[id].vue`. `handleFileUploads()` on the backend already preserved existing files correctly when the key was absent — no backend change needed for this part. |
| 5 | Governorate/city/district/building/floor required in the form | **Implemented.** Added a `district` column via new migration (`2026_09_19_000001_add_district_to_contractors_table.php`), added to `$fillable` and to `ContractorController` validation/messages/attributes. Replaced the old hardcoded 5-city `VSelect` in both forms with cascading **Governorate → City** selects (fed by the existing `GET /api/v1/app/governorates` endpoint) plus new required `district`, `building`, `floor` fields (building/floor columns already existed on the model but were missing from both forms entirely). |
| 6 | Link specializations to their parent field | **Implemented**, now backed by the DB tables from #7 (superseded the originally-planned static mapping). `ContractorSpecialization.contractor_field_id` links each specialization to its field; `ContractorLookups::fieldSpecializationsMap()`/`specializationsForField()` read this live (cached) instead of a hardcoded array. Exposed via `field_specializations` on `GET /api/v1/app/specialties-catalog`. Both frontend forms fetch fields/specializations/grades from that endpoint instead of three hardcoded parallel option lists, and filter the specialization dropdown to the ones belonging to the selected field (resetting the specialization when the field changes). **Caveat carried over**: the field→specialization grouping (seeded in the new migration) was inferred from existing label semantics since no reference file was actually attached to the sheet row — worth a quick sign-off against the real file mentioned in the sheet. |
| 7 | Dedicated settings section to add/edit/delete fields/specializations/grades | **Implemented, at stakeholder's explicit request despite the fee/certificate risk called out below.** New tables `contractor_fields`, `contractor_specializations` (FK to field), `contractor_grades` (migration `2026_09_19_000002_create_contractor_lookup_tables.php`, seeded from the previous hardcoded values so existing codes are unchanged). `ContractorLookups` is now fully DB-backed (cached, `clearCache()` on every write) with the old hardcoded arrays kept only as an empty-table fallback. New `ContractorLookupController` (admin-only, `role:admin`) exposes CRUD at `dashboard/contractor-fields`, `dashboard/contractor-specializations`, `dashboard/contractor-grades`. **Deletion is deliberately a soft deactivate (`is_active=false`), never a hard delete** — the codes are consumed directly by `MembershipFeeCalculator`, PDF/Docx certificate generation, and `contractors.specialties` JSON on existing records, so a hard delete of an in-use code would silently break fee math or leave dangling references; deactivating just hides it from new-contractor selection while preserving history and existing calculations. New frontend page [settings/contractor-lookups.vue](arab-contractors-union-front/resources/ts/pages/settings/contractor-lookups.vue) (tabs for fields/specializations/grades, add/edit/deactivate/reactivate, confirm dialog before deactivating), linked from the settings hub. |

**Current implementation**
- Create: [create.vue](arab-contractors-union-front/resources/ts/pages/contractors/create.vue), `ContractorController@store` ([ContractorController.php:243-256](arab-contractors-union-api/app/Http/Controllers/Api/ContractorController.php))
- Edit: [edit/[id].vue](arab-contractors-union-front/resources/ts/pages/contractors/edit/%5Bid%5D.vue), `ContractorController@update` (lines 267-281)
- File upload validation lives in `ContractorController::getValidationRules()` (lines 114-127): `nullable|file|mimes:pdf,jpg,jpeg,png|max:10240` — images ARE currently allowed alongside documents (jpg/jpeg/png in the mimes list), which is the root of sub-issue #3.
- Specialties/fields stored as denormalized JSON on `contractors.specialties` (migration `2026_06_30_140755_add_specialties_to_contractors_table.php`), sourced from hardcoded [ContractorLookups.php](arab-contractors-union-api/app/Support/ContractorLookups.php) (FIELDS, SPECIALIZATIONS, GRADE_LEVELS constants) — **no DB-backed CRUD exists for these at all**.
- Address: `governorate_id`/`city_id` FKs plus legacy `city`/`address`/`building`/`floor` free-text fields on `Contractor` model.
- Phone change (mobile app, but same OTP mechanism referenced): `ContractorAuthController::requestPhoneChangeOtp` / `verifyPhoneChangeOtp`.

**Files involved (final)**
- Backend (new): `database/migrations/2026_09_19_000001_add_district_to_contractors_table.php`, `database/migrations/2026_09_19_000002_create_contractor_lookup_tables.php`, `app/Models/ContractorField.php`, `app/Models/ContractorSpecialization.php`, `app/Models/ContractorGrade.php`, `app/Http/Controllers/Api/ContractorLookupController.php`
- Backend (modified): `ContractorController.php`, `Contractor.php`, `ContractorLookups.php` (rewritten to be DB-backed), `SettingController.php` (`specialtiesCatalog`), `NormalizeSpecialtyGrades.php`, `routes/api.php`
- Frontend (new): `pages/settings/contractor-lookups.vue`
- Frontend (modified): `pages/contractors/create.vue`, `pages/contractors/edit/[id].vue`, `pages/settings/index.vue` (nav link)

**Dependencies**: #6 was implemented on top of #7's new DB tables (not the originally-planned standalone static mapping), since the stakeholder confirmed #7 should go ahead despite the fee-engine risk.

**Implementation steps**
1. Founding date (تاريخ التأسيس) not shown on edit — verify `fetchContractor()` in `edit/[id].vue` maps `founded_at`/`established_at` from API response into the form model; check field name mismatch between store and show serialization.
2. Add confirmation dialog before removing a specialty/field row from the contractor form (frontend-only, `VDialog` around the existing remove handler).
3. Restrict file-upload mimes to document types only (drop jpg/jpeg/png from `mimes:` rule, or split into separate "certificate" vs "photo" fields per requirement) in `getValidationRules()`.
4. Fix update-failure UX: on validation failure, show all failing fields at once (Laravel already returns all errors — audit frontend error-rendering, which appears to show only first two); do not force re-upload of already-stored files on edit (ensure `nullable` file rules stay nullable and controller does not overwrite existing path when no new file sent — check `handleFileUploads()` lines 131-158 keeps old value when key absent).
5. Add governorate/city/district/building/floor as required, structured selects in the add-contractor form (currently free text partly) — needs governorate→city cascading dropdowns (reuse pattern from `Governorate`/`City` models already referenced at `Contractor.php:116-128`).
6. Link specializations to fields per uploaded reference file — needs a data-driven mapping (currently hardcoded flat lists in `ContractorLookups`), likely requires task-07's DB migration first.
7. Build a new admin settings section for specialties/fields/grades CRUD (does not exist — currently hardcoded in `ContractorLookups.php`).

**Database changes**
- New tables: `contractor_fields`, `contractor_specializations`, `contractor_grades` (or similar) to replace hardcoded `ContractorLookups` constants, each with `is_active`, ordering.
- Possibly a pivot `field_specializations` for the field→specialization link in step 6.

**Frontend changes**
- `create.vue` / `edit/[id].vue`: address cascading selects, confirmation dialogs on row removal and file removal, consolidated error display.
- New pages: `pages/settings/specialties.vue` (or similar) for CRUD.

**Backend changes**
- `ContractorController`: validation rule changes (mimes), file-replace-on-edit logic.
- New `SpecialtyController`/`FieldController`/`GradeController` + FormRequests + routes under `dashboard/*`.
- Migrations for new lookup tables + data seeder migrating current hardcoded values.

**Tests required**
- Feature test: update contractor without re-sending files does not null existing file columns.
- Feature test: non-document mime upload rejected with 422.
- Feature test: new specialties CRUD endpoints (admin-only via `role:admin`).

**Risk level**: High (touches core contractor CRUD, file handling, and requires new DB tables/data migration for lookups).

**Estimated complexity**: Large (multi-sprint; #7 alone is a new subsystem).

**Acceptance criteria**
- Editing a contractor shows founding date consistently between create/edit. ✅ (already true)
- Removing a specialty or an attachment always prompts confirmation. ✅ (specialty removal; attachment fields don't have a separate remove button beyond clearing the file input, which no longer misfires — see #4)
- Contractor file fields accept documents and image scans (pdf/doc/docx/jpg/jpeg/png), per stakeholder decision to keep image uploads working. ✅
- Validation error responses list every invalid field in one message. ✅ (Laravel already returned all; frontend already renders per-field via `:error-messages`; the false-positive single-field error from #4 is what actually made it look like partial reporting, and that's fixed)
- Governorate/city/district/building/floor captured and re-displayed correctly in both add and edit forms. ✅
- Admin can add/edit/deactivate specialties, fields, and grades from a dedicated settings page without a deploy. ✅ ("delete" = deactivate, by design — see #7 rationale above; codes are never hard-deleted since they're load-bearing for fee calculation and certificates)

**Verification performed**
- `php -l` on all modified/new PHP files (controllers, models, migrations) — no syntax errors.
- New migration `2026_09_19_000002_create_contractor_lookup_tables.php` executed successfully against a throwaway sqlite file; confirmed via `php artisan tinker` that `ContractorLookups::specializationsForField(20)` and `topTierFields()` return the expected seeded values (`[20,30,40,50,210]` and `[20,30]`).
- Confirmed via `grep` that no code outside `ContractorLookups.php` still references the old `FIELDS`/`SPECIALIZATIONS`/`GRADE_LEVELS`/`SPECIALTY_GRADE_LABELS`/`TOP_TIER_FIELDS` constants directly (only a stray comment) — `SettingController::specialtiesCatalog` and `NormalizeSpecialtyGrades` updated to use the new dynamic accessor methods instead.
- `vue-tsc --noEmit` across the project — no type errors in the three touched/new Vue files (`create.vue`, `edit/[id].vue`, `settings/contractor-lookups.vue`).
- Could not run a full `php artisan migrate` or feature tests against a live MySQL instance in this environment (no reachable DB server); confirmed via `git stash` that the pre-existing sqlite-test-suite failure (`ALTER TABLE ... MODIFY COLUMN` MySQL-only syntax in an unrelated `users` table migration) predates this change and isn't a regression.
- No live browser preview was run — the app requires a working backend/DB connection to exercise these forms meaningfully, which wasn't available in this environment; recommend a manual pass through both contractor forms and the new settings page before merging.

**Verification performed**
- `php -l` on all modified PHP files — no syntax errors.
- `php artisan test --filter=ContractorRegisterFlowTest` — fails, but confirmed via `git stash` that the exact same 19 failures exist on the pre-change code too (`ALTER TABLE ... MODIFY COLUMN` MySQL syntax isn't supported by the sqlite in-memory test DB); unrelated to this task, not a regression.
- `vue-tsc --noEmit` scoped to the two changed files — no type errors.
- New migration `2026_09_19_000001_add_district_to_contractors_table.php` could not be executed against a live DB in this environment (no MySQL server reachable); it follows the exact same idempotent `Schema::hasColumn` guard pattern as the existing `2026_06_30_000000_add_extended_fields_to_contractors_table.php`, so it's expected to apply cleanly.

---

## TASK-02 — Membership Request (Disabled) — ⚠️ STATUS: REVERSED (2026-09-22)

> **Reversed by TASK-16 #6.** The "hide the nav link only" decision below was implemented on 2026-09-19. On 2026-09-22 the stakeholder reported «لا يتم اظهار طلب في لوحة» — membership requests were still arriving and being stored the whole time, but with all three entry points removed nobody could reach the page to action them. All three were restored (`navigation/vertical/pcu.ts`, `navigation/horizontal/index.ts`, `pages/dashboards/index.vue`). The original task record is kept below unchanged.
>
> **Lesson**: hiding a working feature by commenting out its only entry points produced a bug report three days later — the same shape as TASK-04's reversal. Prefer an explicit disabled state or a feature flag.

**Description**: Feature marked disabled in the sheet with no further detail — flagged for confirmation only.

**Current implementation**: Fully implemented but apparently gated off product-side: `MembershipController.php` (pending/index/store/approve/reject), routes at `api.php:368-372`, frontend at [memberships.vue](arab-contractors-union-front/resources/ts/pages/contractors/memberships.vue).

**Files involved**: `MembershipController.php`, `Membership.php`, `pages/contractors/memberships.vue`

**Dependencies**: None.

### Execution summary (2026-09-19)

Asked the user to clarify scope (three options: hide nav link only / also 403 the routes / remove the feature entirely). **Answer: hide the nav link only** — backend and routes stay fully intact for anyone with the direct link.

**Implemented**:
- Removed the "طلبات الانتساب" (`contractors-memberships`) entry from both the vertical sidebar ([navigation/vertical/pcu.ts](arab-contractors-union-front/resources/ts/navigation/vertical/pcu.ts)) and horizontal ([navigation/horizontal/index.ts](arab-contractors-union-front/resources/ts/navigation/horizontal/index.ts)) menus, with a comment explaining why it's commented out rather than deleted outright.
- Removed the matching "طلبات الانتساب" quick-action card from the dashboard homepage ([pages/dashboards/index.vue](arab-contractors-union-front/resources/ts/pages/dashboards/index.vue)) — same underlying entry point, same "hide the link" intent.
- Left `MembershipController.php`, `Membership.php`, the `dashboard/memberships*` routes, the `memberships.vue` page itself, and the breadcrumb title mapping in `guards.ts` completely untouched — the feature still works for anyone who navigates to it directly (e.g. a bookmarked URL), per the "hide only" decision.

**Database changes**: None.

**Frontend changes**: `navigation/vertical/pcu.ts`, `navigation/horizontal/index.ts`, `pages/dashboards/index.vue` — three menu/quick-link entries removed.

**Backend changes**: None.

**Tests required**: None (pure UI visibility change, no logic touched).

**Verification performed**: `vue-tsc --noEmit` across the project after the change — no new type errors introduced (see TASK-01's verification log for the pre-existing/unrelated baseline errors this project already has).

**Risk level**: Low — confirmed.

**Estimated complexity**: Trivial — confirmed.

**Acceptance criteria**: ✅ "طلبات الانتساب" no longer appears in either sidebar or in the dashboard quick actions, for both admin and accountant roles; the route/page/API remain reachable directly, unchanged.

---

## TASK-03 — Edit Company Profile — ✅ STATUS: DONE

**Description**: A contractor edits their company profile via the mobile/portal app, but the change is not reflected/visible in the admin dashboard despite the update succeeding.

**Current implementation**
- Mobile app profile update: `ContractorAuthController::updateFullProfile` (`POST /contractor/auth/profile/update`), using [UpdateFullProfileRequest.php](arab-contractors-union-api/app/Http/Requests/Contractor/UpdateFullProfileRequest.php) — writes directly to `contractors` table, no approval/review queue.
- Admin dashboard reads the same `contractors` table via `ContractorController@show`/`index`.

**Files involved**: `ContractorAuthController.php`, `UpdateFullProfileRequest.php`, `Contractor.php`, admin `pages/contractors/edit/[id].vue` / `show` view.

**Dependencies**: None.

### Execution summary (2026-09-19)

**Reproduction (via code reading — no live DB in this environment, see verification below):**
- Ruled out caching: `NoCacheHeaders` middleware ([bootstrap/app.php:41-44](arab-contractors-union-api/bootstrap/app.php)) sets `Cache-Control: no-store` on every API response, applied globally to the whole `api` middleware group. No `Cache::remember` anywhere in `ContractorController`/`ContractorProfileService`. Admin's `edit/[id].vue` also re-fetches on every page mount (plain `axios.get`, no stale Vue Query cache). So this was never an HTTP/query-caching bug.
- There's a separate, fully-built `ProfileUpdateRequest` approval-queue subsystem (`ProfileUpdateRequestController`, `dashboard/profile-update-requests` routes) that *sounds* like what "the request isn't shown in the panel" could mean — but confirmed via grep that the actual contractor-portal frontend ([pages/contractor/dashboard.vue](arab-contractors-union-front/resources/ts/pages/contractor/dashboard.vue)) never calls it; it posts straight to `contractor/auth/profile/update` (direct-write, no approval step). So that subsystem is unrelated dead-end for this bug (not touched).
- **Root cause found**: comparing every field the contractor app can edit (`UpdateFullProfileRequest` rules + `dashboard.vue`'s `editForm`/`editClassification`) against the admin edit form's field allowlist (`edit/[id].vue`'s `textFields` + the `form` object) turned up one real gap: the contractor app lets a contractor change their **overall classification** (`contractors.classification`, sent as a separate `classification` field outside `editForm`, validated by `UpdateFullProfileRequest`/`ContractorController` as `nullable|string|max:10`) — but neither `create.vue` nor `edit/[id].vue` had a `classification` field anywhere in the form. An admin opening "تعديل بيانات المقاول" after a contractor changed this from the app would see no trace of it at all in the edit screen (it only surfaced in the separate "view details" quick-look panel on the list page, not in the editable form) — matching "edited via app but not reflected in the dashboard."
- (Governorate/city not showing on edit was a related, overlapping symptom — already fixed as part of TASK-01's address-fields work.)

**Fix**: Added the missing `classification` field to both [create.vue](arab-contractors-union-front/resources/ts/pages/contractors/create.vue) and [edit/[id].vue](arab-contractors-union-front/resources/ts/pages/contractors/edit/%5Bid%5D.vue) — a `VSelect` sourced from the existing `overall_grades` list already returned by `GET /api/v1/app/specialties-catalog` (built from `Contractor::CLASSIFICATION_LABELS`, no backend change needed since the controller already validated/labeled `classification`). Added it to `edit/[id].vue`'s `textFields` fetch mapping and both forms' step-3 error-jump logic. No backend changes were required — `ContractorController::getValidationRules()`/`getValidationAttributes()` already had full `classification` support; it was purely a frontend display/edit gap.

**Database changes**: None.

**Frontend changes**: `create.vue`, `edit/[id].vue` — new `classification` form field + catalog fetch wiring, in both files.

**Backend changes**: None (already fully supported).

**Tests required**: No feature test added — this was a pure frontend field-exposure gap with no new backend logic to cover; the existing `ContractorController` validation for `classification` already exists and is unchanged.

**Verification performed**: `vue-tsc --noEmit` across the whole project after the change — no new type errors in either touched file (same pre-existing baseline error set as TASK-01/02, none in `pages/contractors/create.vue` or `pages/contractors/edit/[id].vue`). Could not exercise the live update-then-view flow end-to-end (no reachable DB in this environment) — recommend a manual pass: change "التصنيف العام" from the contractor app, then confirm it now appears and is editable on the admin edit screen.

**Also found, out of scope, flagged separately**: `routes/api.php` registers `POST profile-update-requests/send-phone-otp` pointing at `ProfileUpdateRequestController::sendPhoneOtp`, a method that doesn't exist anywhere in that controller — would throw a runtime error if ever called. Spawned as a separate follow-up task rather than fixed here since it's unrelated to the company-profile-edit bug.

**Risk level**: Medium → confirmed low-risk once root cause was isolated (frontend-only, additive field).

**Estimated complexity**: Medium → landed on the smaller end (single missing form field, no backend change).

**Acceptance criteria**: ✅ A company-profile edit made from the contractor app (including the overall classification) is now visible and editable in the admin dashboard's edit form, not just the read-only "view details" panel.

---

## TASK-04 — Payment History (Disabled) — ⚠️ STATUS: REVERSED (2026-09-20)

**Update (2026-09-20)**: The "hide the nav link only" decision below was reversed by stakeholder decision — "سجل المدفوعات" is shown again in all four surfaces (sidebar, horizontal menu, dashboard quick-action card, dashboard "أحدث المدفوعات" widget, global search palette). No re-hiding logic was added back; the four files simply revert to their pre-TASK-04 state. See commit reinstating these entry points. The original rationale/history below is kept for context.

**Description**: Marked disabled, no detail provided in the sheet.

**Files involved**: [PaymentController.php](arab-contractors-union-api/app/Http/Controllers/Api/PaymentController.php), [pages/payments/transactions.vue](arab-contractors-union-front/resources/ts/pages/payments/transactions.vue) (route `payments-transactions`), and its navigational entry points.

### Execution summary (2026-09-19)

Same clarification pattern as TASK-02: asked the user for scope, **answer: hide the nav link only** — backend, routes, and the `payments-transactions` page itself stay fully intact.

**Implemented** — every navigational entry point into the disabled feature, mirroring TASK-02's approach:
- Removed "سجل المدفوعات" from the vertical sidebar ([navigation/vertical/pcu.ts](arab-contractors-union-front/resources/ts/navigation/vertical/pcu.ts)) and horizontal menu ([navigation/horizontal/index.ts](arab-contractors-union-front/resources/ts/navigation/horizontal/index.ts)).
- Removed the "المدفوعات" quick-action card from the dashboard homepage ([pages/dashboards/index.vue](arab-contractors-union-front/resources/ts/pages/dashboards/index.vue)).
- Removed the "المدفوعات" entry from the global command/search palette ([layouts/components/NavSearchBar.vue](arab-contractors-union-front/resources/ts/layouts/components/NavSearchBar.vue)) — a surface TASK-02 didn't touch since "طلبات الانتساب" wasn't listed there, but "المدفوعات" was.
- **Also removed the "أحدث المدفوعات" (Latest Payments) dashboard widget** ([views/dashboards/lms/LatestPayments.vue](arab-contractors-union-front/resources/ts/views/dashboards/lms/LatestPayments.vue) usage) — unlike TASK-02, this feature has a full content-preview widget on the dashboard homepage whose entire purpose is surfacing and linking into payment history, not just a menu shortcut. Left it out of the earlier TASK-02-style pass would have left the biggest entry point untouched, so it's removed for consistency with "hide the link" intent; `LatestContractors` now takes the full row width in its place. The `LatestPayments.vue` component file itself is untouched (not deleted), only its usage/import in `dashboards/index.vue`.
- Left `PaymentController.php`, all `payments/*` routes, and `payments/transactions.vue` completely untouched — reachable directly, per the "hide only" decision.

**Database changes**: None.

**Frontend changes**: `navigation/vertical/pcu.ts`, `navigation/horizontal/index.ts`, `pages/dashboards/index.vue` (quick-action card + widget removed, unused import cleaned up), `layouts/components/NavSearchBar.vue`.

**Backend changes**: None.

**Tests required**: None (pure UI visibility change, no logic touched).

**Verification performed**: Confirmed via `grep` that no remaining references to the removed `LatestPayments` component exist anywhere in the frontend. `vue-tsc --noEmit` across the project — no new type errors introduced.

**Risk level**: Low — confirmed. **Estimated complexity**: Trivial — confirmed.

**Acceptance criteria**: ✅ "سجل المدفوعات" / "المدفوعات" no longer appears in the sidebar, horizontal menu, dashboard quick actions, dashboard widgets, or the global search palette; the route/page/API remain reachable directly, unchanged.

---

## TASK-05 — Financial Liabilities (الذمم المالية / Dues) — ✅ STATUS: DONE (#6 penalty-status overlap deferred to TASK-06)

**Description**: Multiple bugs and missing safeguards in the dues (ContractorDue) subsystem: delete without confirmation risk, a false-positive "duplicate due" that shouldn't have been allowed, incorrect analytics count, due-date allowing past dates, a new "financial penalties" status model request, and a broken bulk-discount preview.

### Execution summary (2026-09-19)

| # | Sub-issue | Result |
|---|---|---|
| 1/2/4 | "غير معرف" contractor name + false failure on add-due; duplicate-due / analytics-count mismatch | **Already fixed in current code — verified, no change needed.** `ContractorDueController::store()` already eager-loads `contractor:id,name,membership_number` before returning ([ContractorDueController.php:249](arab-contractors-union-api/app/Http/Controllers/Api/ContractorDueController.php)). `dues/index.vue` already has a `justSelectedContractor` guard (with an explanatory comment) preventing the `VAutocomplete` search-rewrite race that used to blank the selection. `summary()`'s `contractors_with_dues` already uses `->distinct('contractor_id')->count('contractor_id')`, which Laravel compiles to a correct `COUNT(DISTINCT contractor_id)` — no double-counting found. These three sheet complaints predate a prior fix already in the branch. |
| 3 | Confirmation dialog on delete | **Already implemented** (`deleteDueDialog`) — confirmed, no change. |
| 5 | Due-date allows past dates | **Confirmed with stakeholder: keep as-is, no restriction** — backdating is legitimate for legacy/late dues. No code change. |
| 6 | New penalty-status model (مسددة/غير مسددة/مسددة جزئيا/مرفوضة) | **Deferred to TASK-06** — this is the Penalty model's concern, not dues; overlap noted but not actioned here to avoid touching TASK-06's scope prematurely. |
| 7 | Bulk-discount preview broken/missing | **Fixed.** Backend (`DuesDiscountService::applyBulk`) already supported `dry_run` for *both* `ids` and `criteria` modes uniformly — the real gap was purely frontend: the "selected ذمم" (checkbox) discount dialog had no preview button/UI at all, and the "معايير" (criteria) discount dialog's real-apply path never closed its own modal (`criteriaDiscountDialog` was never set to `false` on success), matching the sheet's "no visible change until reload" complaint. Fixed both in [dues/index.vue](arab-contractors-union-front/resources/ts/pages/dues/index.vue): added a "معاينة (بدون تطبيق)" step + result panel to the selected-ids dialog (mirroring the criteria dialog's existing pattern), and made both mutations key off the backend's `is_dry_run` flag to decide whether to show a preview or close+flash+refresh. |
| 8 | Fee generation should be a fixed amount | **Confirmed with stakeholder: keep the existing per-grade/per-field calculation (Article 37)** — no change to `MembershipFeeCalculator`. |
| 9 | Excel import sample file | **Not code** — needs a stakeholder-provided sample matching `LegacyDuesImporter`'s documented column layout (A–P) for QA; no action possible from this side. |

**Current implementation** (see [ContractorDueController.php](arab-contractors-union-api/app/Http/Controllers/Api/ContractorDueController.php), [ContractorDue.php](arab-contractors-union-api/app/Models/ContractorDue.php), [dues/index.vue](arab-contractors-union-front/resources/ts/pages/dues/index.vue))
- Delete: confirmation dialog **already exists** (`deleteDueDialog`, index.vue lines ~282-295) — sub-issue #3 (confirmation on delete) may already be resolved; verify against current UI, sheet may predate this.
- Add-due 500/undefined-name bug (Application Problem #1/#2): `saveDueMutation` (index.vue ~251-272) posts to `dashboard/dues`; symptom is contractor name showing "غير معرف" and a failure flash even though the row is persisted — points to `ContractorDueResource` not eager-loading `contractor` relation consistently, or frontend closing dialog/showing error before `refreshAll()` resolves.
- Analytics mismatch (Problem #4): "دائرة تحليلات" reporting 2 contractors with dues when only 1 has a due row — likely a stale/duplicate row from the bug above, or `summary()` (`ContractorDueController::summary`, line 209) counting a soft-orphaned row.
- Due date: `StoreContractorDueRequest` only validates `nullable|date` — no `after_or_equal:today` rule, so past dates are currently allowed (Problem #5) — flagged as "open for discussion" in the sheet since backdating may be legitimate for legacy dues.
- New feature request (#6): dedicated financial-penalty registration with statuses مسددة/غير مسددة/مسددة جزئيا/مرفوضة — overlaps with TASK-06 (Penalty model currently only supports `unpaid`/`paid`).
- Bulk/collective discount preview (#7): `applyDiscountBulk` with `mode: 'criteria'` **does** support dry-run (index.vue ~line 494) but `mode: 'ids'` (selected-rows discount) has **no dry-run**, matching the sheet complaint that preview shows 0 counts/total before applying.
- Fee generation clarification (#8): fee amount is computed by `MembershipFeeCalculator` per contractor grade/specialty, not a flat constant — sheet requests it be fixed/uniform, needs a product decision.
- Excel import (#9): format is documented in `LegacyDuesImporter.php` (columns A–P); sheet just needs a properly-formatted sample file to test against.

**Files involved**: `ContractorDueController.php`, `ContractorDue.php`, `StoreContractorDueRequest.php`, `DuesGenerationService.php`, `DuesDiscountService.php`, `LegacyDuesImporter.php`, `ContractorDueResource.php`, `dues/index.vue`

**Dependencies**: #6 (penalty statuses) overlaps with TASK-06; should be designed together to avoid two parallel "financial status" concepts.

**Implementation steps**
1. Reproduce the add-due bug with network tab open; confirm whether `ContractorDueResource` eager-loads `contractor:id,name,membership_number` on every response path (`store`, not just `show`) — patch `store()` to always `load()` before returning.
2. Investigate and fix the duplicate-due / stale-analytics-count issue — audit `summary()` query for `DISTINCT` on contractor_id vs a join that could double count.
3. Decide (with stakeholder) whether `due_date` should reject past dates outright or only warn — implement `after_or_equal:today` conditionally (e.g., allow past dates only when `source=legacy_import`).
4. Extend bulk-discount `mode: 'ids'` path to support `dry_run` the same way `mode: 'criteria'` does, and fix the criteria-preview UI so counts/total populate correctly before apply, and auto-close+refresh the modal after apply (sheet complaint: "no visible change until reload").
5. Clarify fee-generation business rule with stakeholder (fixed fee vs per-grade) before changing `MembershipFeeCalculator`.
6. Provide/request a correctly-formatted sample Excel file matching `LegacyDuesImporter` columns for QA.

**Database changes**: Possibly none for bug fixes; a `penalties` status-enum expansion is shared with TASK-06.

**Frontend changes**: `dues/index.vue` — fix response handling on create, dry-run wiring for ids-mode bulk discount, auto-close-and-refresh modal after bulk apply.

**Backend changes**: `ContractorDueController::store` (eager load), `summary()` query audit, `StoreContractorDueRequest` due_date rule, `applyDiscountBulk` dry-run for ids mode.

**Tests required / performed**: No new backend logic was added (dry-run already existed server-side), so no new feature tests were needed for the bulk-discount fix — it's a frontend wiring fix consuming an already-tested backend contract. Could not run `php artisan test --filter=...` against a live DB in this environment (see TASK-01's verification notes on the pre-existing sqlite/MySQL migration incompatibility, unrelated to this task).

**Risk level**: High → confirmed low-risk in practice — most flagged items were already fixed or resolved as "no change needed" by stakeholder decision; the one real fix (bulk-discount preview) is additive frontend-only UI.

**Estimated complexity**: Medium-Large → landed small — one frontend-only fix, everything else was verification or a stakeholder decision.

**Acceptance criteria**
- Creating a due never shows "غير معرف" or a false failure message when the row was actually saved. ✅ (already true, verified)
- Analytics due-contractor count matches the actual number of contractors with outstanding dues. ✅ (already true, verified)
- Bulk discount preview (both by-IDs and by-criteria) shows accurate counts/totals before applying, and the modal closes and the table refreshes immediately after a successful apply. ✅ (fixed)
- Due-date policy is explicit — confirmed with stakeholder as "always allow past dates," documented here as the intentional policy.

**Verification performed**: `vue-tsc --noEmit` across the project after the change — see result below; no backend files were touched for this task so no `php -l` pass was needed.

---

## TASK-06 — Financial Penalties (الغرامات المالية) — ✅ STATUS: DONE

**Description**: Saving a new penalty fails silently (save button does nothing / error).

**Current implementation**: `PenaltyController.php` (`index`/`store`/`markPaid`), `Penalty.php` model (statuses: `unpaid`, `paid` only), frontend [penalties.vue](arab-contractors-union-front/resources/ts/pages/contractors/penalties.vue).

**Files involved**: `PenaltyController.php`, `Penalty.php`, `penalties.vue`, routes `api.php:501-503`.

**Dependencies**: TASK-05 #6 explicitly deferred the richer-status decision to this task.

### Execution summary (2026-09-19)

**Reproduction — the reported "save fails silently" bug is not reproducible in the current code.** Verified end-to-end against a real (throwaway sqlite) database, not just code reading: built a full migration chain (141 of 147 migrations — excluded only MySQL-only `MODIFY`-syntax migrations, unrelated to penalties), created a real contractor + admin user + Sanctum token, and drove an actual HTTP request through Laravel's kernel to `POST /api/v1/penalties` with the exact payload shape `penalties.vue` sends (`contractor_id`, `reason`, `amount`, `notes`). Result: **201 Created, row correctly persisted** with `status` defaulting to `unpaid` at the DB level. `PenaltyController::store()`'s validation already matches the frontend payload exactly — no mismatch found. This mirrors the TASK-05 pattern where several sheet-reported bugs turned out to already be fixed in the current branch.

**One real, verified gap found and fixed**: `Penalty::create($validated)` doesn't include `status`/`paid_at` in the *returned* object, because Eloquent doesn't re-sync a model's in-memory attributes with DB-applied column defaults after an insert (only the actual DB row got `status='unpaid'`; the PHP object's `status` attribute stayed unset). Confirmed via the same live test: the raw create-response JSON was missing the `status` key entirely. This didn't produce a user-visible bug today (the frontend re-fetches the whole list after a successful add rather than reading the create response), but it's a real API-contract gap that would bite anything consuming the create response directly. **Fixed** by calling `$penalty->refresh()` after `create()` in `PenaltyController::store()`, mirroring the `->fresh()` pattern already used elsewhere in this codebase (e.g. `ContractorDueController`).

**Stakeholder decision on TASK-05 #6 (richer status model): implement now.** Extended penalties from `unpaid`/`paid` to the four statuses the sheet requested: `unpaid` / `paid` / `partially_paid` / `rejected`.

- **Database**: new migration `2026_09_19_000003_expand_penalty_statuses.php` — adds `paid_amount` (decimal, mirrors `ContractorDue.paid_jod`'s pattern for tracking partial payment) and `reject_reason` (nullable string, mirrors `ProfileUpdateRequest.reject_reason`'s existing pattern) columns, and widens the `status` enum to the four values. MySQL path uses a straight `ALTER TABLE ... MODIFY`. SQLite (used only for local/test) can't alter an emulated `enum` CHECK constraint without `doctrine/dbal` (not installed in this project) — handled by rebuilding the table (rename → recreate with the new enum → copy rows → drop old) on that driver only, so the migration works identically in both environments without adding a new dependency.
- **Model** ([Penalty.php](arab-contractors-union-api/app/Models/Penalty.php)): added `paid_amount`/`reject_reason` to `$fillable` (they were missing — the initial tinker verification caught a mass-assignment silent-drop on `reject_reason` before this fix), added `STATUS_LABELS` const + `status_label` accessor (mirrors `ContractorDue`'s existing pattern exactly).
- **Controller** ([PenaltyController.php](arab-contractors-union-api/app/Http/Controllers/Api/PenaltyController.php)): replaced `markPaid()` (paid-only) with `updateStatus()` — validates `status` against all four values, requires `paid_amount` (and rejects it if `>=` the full penalty amount, returning a clear 422 telling the admin to use "paid" instead) when moving to `partially_paid`, accepts an optional `reject_reason` when moving to `rejected`, and resets the other status-specific fields appropriately on every transition. `index()` now also returns `paid_amount`, `status_label`, and `reject_reason`.
- **Route**: `PATCH penalties/{penalty}/pay` → `PATCH penalties/{penalty}/status` (confirmed via grep that `markPaid`/the old `/pay` route was never called anywhere in the frontend, so this is a safe rename, not a breaking change to a live integration).
- **Frontend** ([penalties.vue](arab-contractors-union-front/resources/ts/pages/contractors/penalties.vue)): added an "إجراءات" column with a status-update dialog (select from all 4 statuses, conditional `paid_amount`/`reject_reason` inputs), status chips now show all 4 states with distinct colors and the partial-payment amount / rejection reason inline.

**Database changes**: `2026_09_19_000003_expand_penalty_statuses.php` — see above.

**Frontend changes**: `penalties.vue` — status-update dialog, 4-color status chips, new actions column.

**Backend changes**: `PenaltyController.php` (`store()` refresh fix, `markPaid` → `updateStatus`), `Penalty.php` (fillable, labels), `routes/api.php`.

**Tests required / performed**: No `tests/Feature` file exists for penalties yet and the project's sqlite test-DB setup is blocked by an unrelated pre-existing migration incompatibility (see TASK-01/05 notes) — so instead of a throwaway feature test file, verification was done directly against a real, fully-migrated throwaway sqlite database via Laravel's HTTP kernel (not just tinker model calls), covering: create → 201 with hydrated `status`; `partially_paid` transition with valid amount → 200 with correct fields; `partially_paid` with `paid_amount >= amount` → 422 with the expected message; `rejected` transition with a reason → 200 with `reject_reason` persisted. All passed as designed.

**Unrelated finding, flagged separately (not fixed here)**: while grepping for other callers of the old `markPaid`/`/pay` route, found that `arab-contractors-union-front/` (the Vue/TS admin dashboard repo) contains a leftover full Laravel backend tree — `app/Http/Controllers/Api/PenaltyController.php`, `app/Models/Penalty.php`, its own `routes/api.php`, plus `Course`/`Enrollment`/`Lesson`/`Module` models — almost certainly dead scaffolding from the Vuexy template's bundled demo LMS backend. It shadows real file names (a future edit to "PenaltyController.php" in the front repo would silently do nothing, since the real one lives in `arab-contractors-union-api/`). Spawned as a separate follow-up task rather than touched here.

**Risk level**: Medium → confirmed low — the "bug" wasn't reproducible, and the status-model extension is additive (existing `unpaid`/`paid` values and behavior unchanged).

**Estimated complexity**: Small (bug fix) to Medium (status model extended) → landed on the Medium side, but with real end-to-end verification rather than just code reading.

**Acceptance criteria**: ✅ Admin can save a new penalty without error (already true, verified). ✅ All four statuses are selectable via the admin UI and persist correctly (verified via live HTTP test), with the create-response API-contract gap also closed.

**Verification performed**: Live HTTP-kernel test against a real migrated sqlite DB (see above) for all backend behavior; `php -l` clean on all PHP files; `vue-tsc --noEmit` across the project for the frontend change (see result below).

---

## TASK-07 — Tenders (العطاءات) — ✅ STATUS: DONE (all 7 sub-issues)

**Description**: Deadline validation gaps, missing time-of-day for deadline, attachment UX issues (form doesn't close, attachment lost on save/edit), no delete confirmation on tender attachments, tender detail view disables downloads, and no category-based default images.

### Execution summary (2026-09-20)

Found already fully implemented in code (landed in commit `004ff64`, bundled under an unrelated commit title — hence this doc lagging behind actual state). Verified every sub-issue by reading [TenderController.php](arab-contractors-union-api/app/Http/Controllers/Api/TenderController.php) and [tenders/index.vue](arab-contractors-union-front/resources/ts/pages/tenders/index.vue) line-by-line against the original complaint list, then closed the one real gap (missing tests) and confirmed everything live via a fresh test suite run.

| # | Sub-issue | Result |
|---|---|---|
| 1/2 | Deadline needs a lower bound + time-of-day | **Done.** `deadline` column is `DATETIME` (migration `2026_09_19_000004_change_tenders_deadline_to_datetime.php`), cast `datetime` on the model. `store()` validates `after:now` (full datetime, not just date) — `update()` intentionally allows any date so admins can still fix other fields on an already-expired tender. Frontend has `type="datetime-local"` in both create/edit forms with `:min="minDeadline()"` client-side guard. |
| 3 | Multi-attachment on create, not just edit | **Done.** Create dialog has a `multiple` `VFileInput` (`newAttachmentStaged`) that uploads every staged file right after the tender is created (`createTender()`), before the dialog even closes. |
| 4 | Form doesn't close / attachment lost on save | **Done.** `saveTender()` sets `editDialog.value = false` on success; `createTender()` closes `createDialog` and opens the edit dialog directly on the new tender (for reviewing/adding more attachments) — attachments are uploaded via their own endpoint in the same flow, never silently dropped. |
| 5 | No delete confirmation on attachments | **Done.** `confirmRemoveAttachment()` + `deleteAttachmentDialog` — explicit confirm dialog before `removeAttachment()` runs. |
| 6 | Attachments shown as disabled, not previewable | **Done.** Both the edit dialog and the view-details dialog render attachments as real `<a :href="att.url" target="_blank">` links (with image thumbnails), not disabled chips. |
| 7 | No category-based default image | **Done** (already noted in a prior pass) — `tender_category_image:{category}` setting + admin-managed upload/delete endpoints (`categoryImages`, `storeCategoryImage`, `destroyCategoryImage`), served via `category_image` in the public/contractor tender payload. |

**Real gap found and closed**: no feature test actually covered the two behaviors called out in "Tests required" below. Added [tests/Feature/TenderAdminTest.php](arab-contractors-union-api/tests/Feature/TenderAdminTest.php) (6 tests): past-deadline rejected on store, future datetime with a time component round-trips correctly, `update()` never touches the `tender_attachments` table, attachment delete only removes the targeted row (and rejects a mismatched tender/attachment pair), and auth is required on the admin tender routes.

**Files involved**: `TenderController.php`, `Tender.php`, `TenderAttachment` model, `tenders/index.vue`, routes `api.php`, new `tests/Feature/TenderAdminTest.php`.

**Database changes**: None new this pass — `deadline` was already `DATETIME` from a prior pass.

**Frontend changes**: None — already correct.

**Backend changes**: None — already correct.

**Tests required / performed**: `php artisan test --filter=TenderAdminTest` — 6 passed. Full suite: 162 passed, 0 failed (up from 156 before this pass).

**Risk level**: Low — confirmed (verification + test-gap fix only, no behavior change).

**Estimated complexity**: Small — confirmed (turned out to be a documentation/tracking gap, not a code gap).

**Acceptance criteria**: ✅ Deadline requires date+time no earlier than now on create (edit stays permissive, by design). ✅ Multiple attachments can be added on create. ✅ Editing a tender never silently drops existing attachments (now covered by a test). ✅ Deleting an attachment requires confirmation. ✅ Admins/public can open/download tender attachments from the preview. ✅ Tenders without an uploaded image show a sensible category default.

---

## TASK-08 — Equipment Marketplace (سوق المعدات) — ✅ STATUS: DONE

**Description**: Image-upload UX issues (upload should happen inline with add, not after save), an unenforced 8-image cap being exceeded, flaky delete-image behavior, no save button visible after image upload, unclear purpose of the reservations table, no character limit on description, unclear daily-price rationale, and equipment type missing from the edit form.

**Current implementation** (see [Equipment.php](arab-contractors-union-api/app/Models/Equipment.php), [EquipmentController.php](arab-contractors-union-api/app/Http/Controllers/Api/EquipmentController.php))
- Condition enum already implemented: `excellent|good|needs_maintenance` (#1 — already done, matches sheet request).
- Max-image validation exists server-side at `images.max:8` and re-checked on upload (lines 165-172) — sheet's report of 10 images uploading without warning (#3) suggests either a client-side bug bypassing the check (e.g., uploading via a code path that doesn't hit this validation) or the limit check has an off-by-N bug; needs reproduction.
- Image deletion "needs 4+ clicks" (#4) — likely an optimistic-UI/race-condition bug where the delete request isn't awaited before allowing another click, or the list re-renders before the delete completes.
- No save button after upload (#5) — images are uploaded via a separate endpoint from the equipment record itself; if the UI treats image upload as "the save", this is a UX/labeling gap, not missing functionality.
- Reservations/bookings table = `EquipmentBlockedDate` model — used to block out equipment availability calendar dates, not a full booking/reservation workflow; sheet asks for justification (#6), likely just needs an explanation/rename or removal if genuinely unused by the product.
- Equipment type missing in edit form (#9) — `equipment_type_id` is validated server-side but the edit form apparently doesn't expose/prefill it.

**Files involved**: `EquipmentController.php`, `Equipment.php`, `EquipmentBlockedDate` model, equipment frontend pages (not yet located — need `pages/equipment*` or `pages/marketplace*` glob).

**Dependencies**: None.

**Implementation steps**
1. Locate equipment frontend pages (`resources/ts/pages/**equipment**` or `marketplace`) — not yet inventoried; do this before scoping further.
2. Reproduce the 8-image-limit bypass; likely need `max:8` to also be enforced client-side pre-upload with a disabled/hidden add-button once 8 is reached, and confirm server recomputes existing+new count correctly across concurrent requests.
3. Fix the multi-click delete bug: disable the delete button while its request is in-flight; refetch/patch local state from the server response rather than optimistically mutating.
4. Add an explicit "Save" affordance to the image step, or merge image upload into the same submit flow as the rest of the equipment form so there's one clear save action.
5. Add `maxlength` validation (client) + a `max:N` string rule (server) on the equipment description field — get the character limit from stakeholder.
6. Get product clarification on whether `EquipmentBlockedDate` ("reservations") should stay, be renamed, or be removed.
7. Get product clarification on daily-price purpose (rental pricing display) — likely just needs a tooltip/label improvement, not a code change.
8. Add `equipment_type_id` select, prefilled from existing record, to the edit form.

**Database changes**: Possibly a `max_length` constraint on `equipment.description` column (or just app-level validation, no schema change).

**Frontend changes**: Equipment add/edit pages — image upload flow, description limit, equipment-type select on edit.

**Backend changes**: `EquipmentController` — re-verify 8-image enforcement is airtight; add description length rule.

**Tests required**: Feature test — 9th image upload rejected with a clear error; equipment update includes `equipment_type_id` correctly.

**Risk level**: Medium.

**Estimated complexity**: Medium.

**Acceptance criteria**: Cannot exceed 8 images per equipment item under any upload path; deleting an image works reliably on the first click; equipment type is visible and editable in the edit form; description has an enforced max length.

### Execution summary (2026-09-21)

**Most of the sheet's sub-issues were already fixed** by earlier undocumented passes, so this pass was mostly verification plus closing three real gaps. Inventory of what was already in place:

- **#1 condition enum** (`excellent|good|needs_maintenance`) — done, `fair` retired on both controllers and in `formOptions`.
- **#3 8-image cap** — enforced on *both* upload paths, and correctly as **existing + new**, not per-batch: [EquipmentController.php](arab-contractors-union-api/app/Http/Controllers/Api/EquipmentController.php) and [ContractorEquipmentController.php](arab-contractors-union-api/app/Http/Controllers/Api/ContractorEquipmentController.php). Client-side, [create.vue](arab-contractors-union-front/resources/ts/pages/marketplace/create.vue) truncates at 8 and [index.vue](arab-contractors-union-front/resources/ts/pages/marketplace/index.vue)'s `onImageFiles` truncates at `8 - already_uploaded` with an explicit Arabic warning naming how many were dropped.
- **#4 flaky image delete** — fixed by a `deletingImageId` in-flight guard: the button shows `:loading` for the targeted image, every delete button is `:disabled` while any request is open, and failures surface via snackbar instead of silently leaving the thumbnail on screen. The old code fired an unawaited DELETE per click with no visual feedback, which is exactly the "needs 4+ clicks" report.
- **#5 no save button** — the images dialog already has explicit `رفع الصور` / `إغلاق` actions; nothing missing, the affordance just wasn't obvious before the loading states existed.
- **#9 equipment type in edit form** — present in both edit surfaces (the inline dialog in `index.vue` and `create.vue?id=…`), each with the hidden-type fallback described under TASK-09.
- **description limit** — `max:2000` server-side on all four store/update methods, `maxlength="2000" counter` on both textareas.

**Three real gaps found and closed this pass**:

1. **The `needs_maintenance` enum migration was a no-op on SQLite, i.e. on the entire test suite.** [2026_09_14_094429_update_equipment_condition_enum_needs_maintenance.php](arab-contractors-union-api/database/migrations/2026_09_14_094429_update_equipment_condition_enum_needs_maintenance.php) only ran its `ALTER TABLE … MODIFY` for MySQL and explicitly documented "skip it for SQLite" as a known limitation. But Laravel renders `enum()` on SQLite as a `CHECK` constraint, so the test DB kept enforcing the **old** `('excellent','good','fair')` set — every insert with `condition = 'needs_maintenance'` died with a `QueryException`, making the change untestable and hiding it behind a 500. `ContractorEquipmentTest::test_store_accepts_needs_maintenance_condition` was failing on the full suite for this reason. Fixed by giving the non-MySQL path a real implementation: rebuild the column as `string(32)` (validation `in:excellent,good,needs_maintenance` remains the actual gate) and run the `fair` → `needs_maintenance` backfill. `down()` was made symmetric — it previously ran the backfill outside the driver check, against a constraint it hadn't widened.
2. **No admin-side test coverage at all.** Every equipment test targeted the contractor controller; the admin `EquipmentController` had none, so the cap, the delete semantics and the description limit were only guarded on one of the two paths. Added [tests/Feature/EquipmentAdminTest.php](arab-contractors-union-api/tests/Feature/EquipmentAdminTest.php) (10 tests): existing+new > 8 rejected with nothing written, a batch landing exactly on 8 accepted, >8 in a single `store` rejected, delete removes only the targeted row and promotes the next image to primary, a mismatched equipment/image pair is 403, `equipment_type_id` round-trips on update and a nonexistent one 422s, description over 2000 rejected on both store and update, and auth required.
3. **#6 "unclear purpose of the reservations table" — the premise in the plan above was wrong.** These are two unrelated features, not one: `EquipmentBlockedDate` is **manual admin blocking** of specific days, while `EquipmentReservation` (added 2026-09-19 with its own `EquipmentReservationTest`) is a **real contractor booking workflow** from the mobile app, with overlap detection that consults *both* tables. The admin read endpoint `GET equipment/{equipment}/reservations` already existed but **no frontend called it**, which is why the distinction looked unexplained from the dashboard. Surfaced it: the calendar dialog now opens both requests in parallel and renders reservations as a read-only list (contractor name, phone, date range, status chip) above the editable blocked-dates section, each under a caption stating what it is and who controls it.

**#7 (daily-price rationale)** needs no code change — `contract_type` (`daily|weekly|monthly`) already labels what the price period means on both the form and the listing.

**Files changed**: `update_equipment_condition_enum_needs_maintenance.php` (SQLite path implemented, `down()` made symmetric), new `tests/Feature/EquipmentAdminTest.php`, `marketplace/index.vue` (reservations fetch + read-only list, captions distinguishing the two calendar concepts).

**Database changes**: None on MySQL/production — the migration's MySQL branch is untouched and already applied. The new non-MySQL branch only affects fresh SQLite test databases.

**Tests performed**: `php artisan test --filter="ContractorEquipmentTest|EquipmentAdminTest|EquipmentReservationTest"` — 51 passed. Full suite: **226 passed, 0 failed** (was 225 passed / 1 failed before the migration fix). `vue-tsc --noEmit` — 42 errors, identical to the pre-change baseline measured by stashing `index.vue`; no new errors.

**Risk level**: Medium → **Low in practice** — the only production-reachable change is the reservations list in the calendar dialog (read-only, additive). The migration fix cannot touch MySQL.

**Estimated complexity**: Medium — confirmed, though the effort landed in test/migration correctness rather than the reported UX bugs, which were already fixed.

**Acceptance criteria**: ✅ 8-image cap holds on both the admin and contractor upload paths, counted as existing + new, now covered by tests on both. ✅ Image delete works on the first click — in-flight guard, per-image loading state, errors surfaced. ✅ Equipment type visible and editable in both edit surfaces, with the hidden-type fallback. ✅ Description capped at 2000 chars server-side and in the UI, tested on store and update. ✅ (#6) Reservations vs blocked dates now visibly distinguished and explained in the admin UI.

---

## TASK-09 — Equipment Type — ✅ STATUS: DONE

**Description**: An equipment type marked hidden still appears in the "add equipment" type dropdown.

### Execution summary (2026-09-20)

**Reported bug was already fixed** (landed in commit `004ff64`, undocumented here): [marketplace/create.vue](arab-contractors-union-front/resources/ts/pages/marketplace/create.vue) already fetched `/api/v1/equipment-types` with `active_only: 1` — a hidden type cannot leak into the add-equipment dropdown. The list page's own inline edit dropdown ([marketplace/index.vue](arab-contractors-union-front/resources/ts/pages/marketplace/index.vue)'s `editTypeOptions`) already had the same protection, plus a fallback to keep a now-hidden type visible if the equipment being edited is already assigned to it.

**Real gap found and fixed**: `create.vue`'s own edit mode (`/marketplace/create?id=…`) had no such fallback — it fetched only active types, so editing an equipment item whose type had since been hidden would silently show the type field as blank/unselected instead of the actual assigned type. Fixed by mirroring `index.vue`'s exact pattern: fetch the full type list (active + hidden) once, then compute `typeOptions` as active types plus the current item's type if it's no longer active. `active_only=1` was dropped from the fetch call since filtering now happens client-side in the computed.

**Files changed**: `marketplace/create.vue` only (added `typeOptions` computed, dropped `active_only` param from the fetch, `VSelect` now binds to `typeOptions` instead of the raw `types` list).

**Database changes**: None. **Backend changes**: None (existing `active_only` filter untouched, still used correctly by other callers).

**Tests performed**: `vue-tsc --noEmit` — no new errors (same pre-existing baseline as other tasks this session).

**Risk level**: Low — confirmed. **Estimated complexity**: Trivial — confirmed.

**Acceptance criteria**: ✅ Hidden equipment types never appear in the add-equipment dropdown (already true). ✅ Editing an equipment item whose type is now hidden still shows the correct type instead of a blank field (new fix).

---

## TASK-10 — Add News (الأخبار) — ✅ STATUS: DONE

**Description**: Unclear news categorization purpose, unnecessary short-title field, multi-image restriction on add/edit, broken link rendering in the public app, main image vs gallery image mismatch, main image missing on edit reload, unclear image-deletion flow, and gallery image deletion not propagating to the public app.

### Execution summary (2026-09-20)

**Correction to this doc's earlier assumption**: the "public-facing site" is *not* a separate app outside this monorepo — it's [landing/news/[slug].vue](arab-contractors-union-front/resources/ts/pages/landing/news/%5Bslug%5D.vue), a React page inside `arab-contractors-union-front` (see CLAUDE.md's note on the `landing/` directory's special Vite handling). That resolved most of the "needs cross-system investigation" uncertainty below.

| # | Sub-issue | Result |
|---|---|---|
| 1 | `category` field purpose unclear | **Already resolved** — dropped entirely via migration `2026_09_19_000007_drop_category_from_news_table.php`, no trace left in the model/controller. |
| 2 | Unwanted short-title field | **Already absent** — no such field anywhere in `News.php`/`NewsController.php`/`news/index.vue`. |
| 3 | Multi-image restriction | **Already correct** — main `image` is a single-file `VFileInput`, gallery is a separate `multiple` `VFileInput`, backend validates each independently. **Real gap found**: no cap on gallery *count* (sheet asked for max 5) — see below. |
| 5 | Main/gallery image swap bug | **Already correct** — `HandlesMediaUploads` trait keeps `image` and `gallery` as fully independent fields at every layer; no index-based mixing found. |
| 6 | Main image missing on edit reload | **Already correct** — `openEdit()` maps `item.image` into `form.imagePreview`, shown next to the file input. |
| 7 | Unclear deletion flow | **Already correct** — gallery images delete immediately with an explicit confirm dialog (`removeGalleryImageDialog`), not the old "mark then save" pattern the sheet complained about. |
| 8 | Deleted gallery image still shows publicly | **Not reproducible given the corrected architecture above** — `destroyGalleryImage()` deletes the DB reference and the physical file in one request; the public page fetches live from the same API with no caching layer in between. |
| 4 | Links not rendering publicly | **Half-true, half real gap** — YouTube (`video_url`) already embeds correctly; `external_url` was validated/stored/returned by the API everywhere but the public detail page never rendered it at all. **Fixed**: added a rendered link block. |

**Additional real gaps found and fixed** (from the original raw sheet, not just this doc's earlier gloss):
- **No 5-image cap on the gallery** — added `max:5` to the `gallery` array rule on `store()`; `update()` needed a post-merge count check instead (the shared upload trait *adds* new files to existing ones, so the simple array-length rule only bounds the newly-uploaded batch, not the final total) — added an explicit check after `handleMediaUploads()`. Mirrored client-side in `news/index.vue` with a live counter, disabled save button, and error hint on the file input.
- **Publish date allowed the past** — same established pattern as Tenders/Announcement/Event: `after_or_equal:today` on `store()` only, matching `:min` on the create-mode date field.
- **Main image wasn't included in the swipeable sequence "as the first image" on the public page, and there was no swipe at all** — the public page previously showed a static hero (`news.image`) completely separate from a non-scrollable CSS grid of `news.gallery`. Replaced with a computed `allImages = [image, ...gallery]` and turned the gallery block into a horizontal `overflow-x` + `scroll-snap` strip (native touch swipe, no new JS dependency) — main image is now the first slide.

**Files changed**: `NewsController.php` (2 validation changes + 1 post-merge check), `news/index.vue` (gallery cap UI, publish-date min), `landing/news/[slug].vue` (`external_url` rendering, combined swipeable image strip). New `tests/Feature/NewsAdminTest.php`.

**Database changes**: None. **Backend changes**: 2 validation rule changes + 1 explicit count check.

**Tests required / performed**: Added [NewsAdminTest.php](arab-contractors-union-api/tests/Feature/NewsAdminTest.php) (6 tests) — past `published_at` rejected on create, update stays permissive, gallery rejects 6+ images on create, accepts exactly 5, update rejects when existing+new exceeds 5 (and confirms the DB wasn't touched), auth required. Full suite: 194 passed, 0 failed. `vue-tsc --noEmit` clean.

**Risk level**: Low — confirmed (mostly verification; 3 small, well-isolated fixes). **Estimated complexity**: Small → landed smaller than the original Medium estimate once the "separate public app" assumption was corrected.

**Acceptance criteria**: ✅ Main image always displays correctly and persists on re-edit. ✅ Gallery accepts multiple images (max 5), main image accepts one. ✅ Deleting a gallery image removes it everywhere (single source of truth, no cache layer). ✅ YouTube links already rendered; external links now render too. ✅ `category`/short-title already resolved (removed). ✅ Publish date can't be set in the past on create. ✅ Main image appears first in a swipeable image strip.

---

## TASK-11 — Add Event (الفعاليات) — ✅ STATUS: DONE

**Description**: Unwanted short-title field, event-type selection needs to be a strict 3-option select, venue field should be conditional on in-person attendance, single main image only (no gallery), speaker photo should be a file upload not a URL field, only one speaker should be markable as "main/keynote", a default-keynote bug when adding a second speaker, plus (from the original raw sheet, previously under-translated in this doc) a delete-button rule for the last remaining speaker, a publish-date lower bound, hybrid events needing a location field, and hiding the stream link for onsite-only events.

### Execution summary (2026-09-20)

Most sub-issues were already implemented (same "fixed in an earlier bundled commit, never reflected here" pattern as several other tasks this session) — verified each against [Event.php](arab-contractors-union-api/app/Models/Event.php)/[EventController.php](arab-contractors-union-api/app/Http/Controllers/Api/EventController.php)/[events/index.vue](arab-contractors-union-front/resources/ts/pages/events/index.vue):

| # | Sub-issue | Result |
|---|---|---|
| — | Short-title field | **Already absent** — no `short_title` anywhere in the form. |
| — | Event type strict 3-option select | **Already correct** — hard `VSelect` bound to the 3 `EVENT_TYPES` values, no free text. |
| — | Single main image, no gallery | **Already correct** — `store()`/`update()` explicitly `unset($validated['gallery'])` after media upload handling. |
| — | Speaker photo as file upload | **Already correct** — `VFileInput` (`photoFile`) + `speaker_photos[]` merged server-side into each speaker's `photo` as a stored URL, not a free-text URL field. |
| — | Single keynote enforcement | **Already correct**, both server-side (`mergeSpeakerPhotosAndEnforceSingleKeynote()`) and client-side (`onKeynoteToggle`). |
| — | Second speaker defaults to keynote | **Already correct** — `addSpeaker()` pushes a fresh literal with `is_keynote: false`, no object-reference bug. |
| — | Delete-speaker confirmation | **Already correct** — `speakerDeleteDialog` + `confirmRemoveSpeaker()`. |
| — | Speaker photo not returned to the contractor app | **Already correct** — confirmed with a real end-to-end test (upload → returned in create response → returned again via the contractor-facing `GET /contractor/events/{id}`), not just code reading. |

**Real gaps found and fixed** (these were in the original raw sheet's "Problem Detail" column but had been dropped/under-translated in this doc's earlier pass):
1. **Delete button for the last remaining speaker** — sheet: disable/hide it once only one speaker is left (can't delete down to zero). Fixed: `VBtn` now `:disabled="form.speakers.length === 1"` with an explanatory tooltip.
2. **Publish date allowed the past** — same pattern as Tenders/Announcement. Added `after_or_equal:today` to `published_at` on `store()` only (`update()` stays permissive); added matching `:min` on the create-mode date field.
3. **`event_location` wasn't required for `hybrid`** — `required_if:event_format,onsite` only covered fully in-person events, not "onsite + online". Fixed to `required_if:event_format,onsite,hybrid` on both backend validation and the frontend's `v-if`.
4. **Live-stream link shown even for onsite-only events** — sheet wants it hidden when attendance is in-person only. Fixed: `stream_url` field now `v-if="form.event_format !== 'onsite'"`.

**Files changed**: `EventController.php` (`eventRules()` now takes an `$isCreate` flag, `event_location` rule widened), `events/index.vue` (4 template/binding changes). New `tests/Feature/EventAdminTest.php`.

**Database changes**: None. **Frontend changes**: 4 small template changes. **Backend changes**: 1 method signature + 2 validation rules.

**Tests required / performed**: Added [EventAdminTest.php](arab-contractors-union-api/tests/Feature/EventAdminTest.php) (8 tests) — past `published_at` rejected on create, today accepted, update stays permissive on an already-past-published event, location required for both onsite and hybrid, not required for online, speaker photo round-trips to the contractor-facing endpoint, auth required. Full suite: 183 passed, 0 failed.

**Risk level**: Low — confirmed. **Estimated complexity**: Small — confirmed (mostly verification; 4 genuinely small fixes).

**Acceptance criteria**: ✅ Short title absent. ✅ Adding any speaker after the first never auto-marks them as keynote. ✅ Removing a speaker requires confirmation, and the last one can't be removed at all. ✅ Event type enum, conditional venue (now including hybrid), single main image, file-based speaker photo, single keynote all verified correct. ✅ Publish date can't be set in the past on create. ✅ Live-stream link hidden for onsite-only events.

---

## TASK-12 — Announcement (التعاميم) — ✅ STATUS: DONE

**Description**: Clarify auto-generated circular number, no image support on announcements, need admin-managed announcement categories, and scheduled-but-not-yet-published announcements incorrectly showing as "published".

### Execution summary (2026-09-20)

| # | Sub-issue | Result |
|---|---|---|
| 1 | Auto-generated circular number, shouldn't need manual entry | **Already correct.** `nextAnnouncementNumber()` (`{year}/{seq}`) runs server-side when `number` is omitted; the create form shows the field `readonly` with a "سيُحدَّد تلقائياً عند الحفظ" placeholder (communicates the behavior rather than hiding the field outright — edit mode makes it editable for manual correction). No change needed. |
| 2 | No image/attachment support | **Already correct.** `Announcement` model + controller already store `image` and `attachment` as full public URLs; the create/edit form already has both `VFileInput`s and the list/detail views already render them (thumbnail avatar in the row, `VImg` + download button in the detail dialog). |
| 3 | Admin-managed categories | **Already correct.** `AnnouncementCategory` model + `announcement-categories` CRUD routes + `announcements/categories.vue` admin page already exist; the announcement form already sources `category_id` from that managed list instead of free text. |
| 4 | Scheduled (future `published_at`) announcement showing as "Published" | **Already correct.** `effective_status` accessor (draft/scheduled/published) is already what both the list-row chip and the detail-dialog chip read (`statusColor[item.effective_status]` / `statusLabel[item.effective_status]`), not the raw `is_published` boolean. |
| — | **Real gap found and fixed**: publish date had no lower bound | The original sheet's item #1 ("تاريخ نشر التعميم من اليوم أو اليوم+1") was never actually implemented — `published_at` accepted any date, past included. Added `after_or_equal:today` to `store()`'s validation ([AnnouncementController.php](arab-contractors-union-api/app/Http/Controllers/Api/AnnouncementController.php)) and a matching `:min` on the create-mode date field in [announcements/index.vue](arab-contractors-union-front/resources/ts/pages/announcements/index.vue) — `update()` stays unrestricted (same pattern as Tenders: editing an already-published circular shouldn't be blocked by its own past date). |

**Not actioned (needs stakeholder input, not code)**: circular expiry/archiving date — the original sheet itself flagged this as "قابلة للنقاش" (open for discussion), not a firm requirement.

**Files changed**: `AnnouncementController.php` (one validation rule), `announcements/index.vue` (one `:min` binding), new `tests/Feature/AnnouncementAdminTest.php`.

**Database changes**: None. **Frontend changes**: One line. **Backend changes**: One validation rule.

**Tests required / performed**: Added [AnnouncementAdminTest.php](arab-contractors-union-api/tests/Feature/AnnouncementAdminTest.php) (8 tests) — past `published_at` rejected on create, today accepted, auto-numbering + same-year sequencing, a future-dated announcement is excluded from the public published list, `effective_status` covers all three states, image+attachment persist, auth required. Full suite: 170 passed, 0 failed.

**Risk level**: Low — confirmed. **Estimated complexity**: Small — confirmed (turned out to be almost entirely already done; one real one-line gap per side).

**Acceptance criteria**: ✅ Announcements scheduled for a future date display as "Scheduled" (not "Published") until that date arrives. ✅ Auto-numbering, image/attachment, and managed categories confirmed working. ✅ Publish date can no longer be set in the past on create.

---

## TASK-13 — Privacy Policy / Terms & Conditions — ✅ STATUS: DONE

**Description**: Reordering one section in the legal-pages builder doesn't automatically re-sequence the sections after it.

### Execution summary (2026-09-20)

**Not reproducible — verified correct, not just read.** Found the actual reorder UI: [TermsManager.vue](arab-contractors-union-front/resources/ts/components/TermsManager.vue) (used by `settings/terms.vue`'s terms/privacy tabs), which implements reordering as up/down arrow buttons (`moveUp`/`moveDown`) rather than free-text sort entry or drag handles — each click sends a `PUT` of the moved item with `sort` set to its immediate neighbor's *current* sort value.

Traced [TermsController::update()](arab-contractors-union-api/app/Http/Controllers/Api/TermsController.php)'s shift logic against that exact request shape and it's correct: moving down increments/decrements the right range depending on direction, scoped to the same `type`, excluding the moved row itself. Didn't trust the trace alone — wrote [tests/Feature/TermsReorderTest.php](arab-contractors-union-api/tests/Feature/TermsReorderTest.php) (5 tests) that literally replay the `{...item, sort: neighbor.sort}` payload the UI sends: swap with next sibling, swap with previous sibling, repeated moveDown walking an item from first to last position, cross-`type` isolation (reordering `terms` doesn't touch `privacy` rows), and `store()`'s insert-shift. All passed on the first run, unmodified.

**Conclusion**: the sheet's complaint was already resolved in the code before this pass (same pattern as several other tasks this session — fixed in an earlier commit but never reflected in this tracking doc). No code changes were needed; the gap was test coverage, now closed.

**Files involved**: `TermsController.php`, `Term.php`, `TermsManager.vue`, new `tests/Feature/TermsReorderTest.php`.

**Database/Frontend/Backend changes**: None — nothing needed changing.

**Tests required / performed**: `php artisan test --filter=TermsReorderTest` — 5 passed. Full suite: 175 passed, 0 failed.

**Risk level**: Low — confirmed. **Estimated complexity**: Small — confirmed (turned out to be verification-only).

**Acceptance criteria**: ✅ Changing one section's order automatically and correctly re-sequences all affected sibling sections, with no manual follow-up edits needed — confirmed for both directions, walking a full list, and type isolation.

---

## TASK-14 — Event Archiving (أرشفة الفعاليات) — ✅ STATUS: DONE

**Description**: Not from the original tracking sheet — a direct ask (2026-09-20, with a screenshot of the contractor mobile app's "فعاليات" screen showing "مؤرشفة"/"المناسبات الفعالة" tabs that had no backend support): add an "archived" concept for events, an API to filter by it, and a status indicator in the admin events table.

**Implemented**, mirroring TASK-07's existing Tenders archiving pattern exactly:
- Migration `2026_09_20_000001_add_archiving_to_events_table.php` — nullable `archived_at` timestamp on `events`.
- New command `events:archive` ([ArchiveExpiredEvents.php](arab-contractors-union-api/app/Console/Commands/ArchiveExpiredEvents.php)) — sets `archived_at = now()` for events whose `event_date` has passed and aren't archived yet. Scheduled daily at 01:05 in [routes/console.php](arab-contractors-union-api/routes/console.php) (right after `tenders:archive` at 01:00).
- `Event` model: `archived_at` fillable/cast, `is_archived` computed `$appends` attribute, `active()`/`archived()` query scopes.
- `EventController`: `contractorEvents()` (the mobile app endpoint the screenshot's two tabs hit) now accepts `scope=active|archived` — mirrors `TenderController::applyFilters()`'s identical param. `adminIndex()` gained the same `scope` filter for admin-side filtering. `formatEvent()` now includes `is_archived`.
- Admin table ([events/index.vue](arab-contractors-union-front/resources/ts/pages/events/index.vue)): new "الأرشفة" column (chip: نشطة/مؤرشفة) + a matching filter dropdown next to the existing published/draft filter.

**Files changed**: new migration, new `ArchiveExpiredEvents.php`, `Event.php`, `EventController.php`, `routes/console.php`, `events/index.vue`.

**Database changes**: `events.archived_at` (nullable timestamp).

**Tests performed**: Added 5 tests to [EventAdminTest.php](arab-contractors-union-api/tests/Feature/EventAdminTest.php) — the archive command only touches past events, `scope=active`/`scope=archived` filter correctly on the contractor endpoint, admin index exposes `is_archived` per row and filters correctly by scope. Full suite: 188 passed, 0 failed. `vue-tsc --noEmit` clean (no new errors).

**Risk level**: Low. **Estimated complexity**: Small (direct port of an existing, proven pattern).

**Acceptance criteria**: ✅ Past events are archived automatically (daily). ✅ Contractor app can request active-only or archived-only events via `scope`. ✅ Admin table shows and can filter by archive status.

---

## TASK-15 — Feedback batch 2026-09-21 — ✅ STATUS: DONE (all 9 resolved)

**Description**: A second round of direct feedback (2026-09-21), spanning sidebar navigation, project-wide branding, the contractor mobile app's event/home endpoints, the admin contractor view dialog, and the contractor auth flow. Items are numbered as given; Arabic kept verbatim with an English gloss.

### Sub-issues

| # | Sub-issue (verbatim) | Gloss | Status |
|---|---|---|---|
| 1 | قم بنقل `/settings/tender-category-images` الى جانب side nav العطاءات | Move the tender-category-images settings page under the "العطاءات" sidebar group | ✅ Done — already in sidebar (line 64) |
| 2 | اي استخدام ل "اتحاد المقاولين العرب" في المشروع احذفه — موجودة ب meta وكثير أماكن؛ فقط "اتحاد المقاولين الفلسطينيين" | Replace every "Arab Contractors Union" string with "Palestinian Contractors Union" | ✅ Done — fixed Postman title, SupportTicketRepliedNotification email subject/salutation, ReportPdfService HTML header/footer |
| 3 | في تفاصيل الفعالية صورة المتحدث لا يتم ارجاعها في التطبيق — الأوبجكت لا يرجع صورة | Speaker objects in the event-details response omit the `photo` key entirely | ✅ Done — `normalizeSpeakers()` applied to both endpoints |
| 4 | عند ازالة ملف يجب اظهار رسالة تأكيدية بعملية الحذف (نانسي، مؤمن) | Confirm dialog before removing an attachment | ✅ Done (Moamen_ayyad) |
| 5 | بعد اضافة مقاول من لوحة، بيانات العنوان (المحافظة / العمارة / الطابق) لا تظهر في التطبيق — السبب عدم وجود مدخلاتها في لوحة | Governorate/building/floor not visible after saving | ✅ Done — backend loads `governorate`, frontend displays all address fields |
| 6 | عرض بيانات النشاط والشركة ايضا عند عرض الملف، ليس فقط في التعديل | Activity/company data should show in view mode, not only edit | ✅ Done — `activityRows` card in dialog (trade, classification, license_number) |
| 7 | مشكلة في عرض ملف الشركة (تم ارفاق صورة) | Problem displaying the company file | ✅ Not a bug — investigated on VPS: storage symlink ✅, URL generation ✅ (admin `withFileUrls` + mobile `fileUrls()`), HTTP 200 on uploaded files. Only one contractor has files on production (cr_file JPG, works correctly). "Empty" document cards = files never uploaded, not a display defect. |
| 8 | العطاءات / في سكشن اخر التحديثات يكفي عرض 3 تحديثات مع زر "عرض المزيد" ينتقل لصفحة منفصلة حتى لا تطول الصفحة | Home "latest updates" should show 3 items + a "show more" button | ✅ Done (backend) — `HOME_UPDATES_LIMIT = 3`; mobile app needs "عرض المزيد" button pointing at `contractor/home/updates` |
| 9 | `fcm_token` لازم تاخذه في `{{base_url}}/contractor/auth/set-password` | Accept `fcm_token` on the set-password endpoint | ✅ Done — already in validation and applied with `:` fallback |

---

### #1 — Move tender-category-images into the Tenders sidebar group

**Current state**: [pcu.ts:58-63](arab-contractors-union-front/resources/ts/navigation/vertical/pcu.ts) pushes "العطاءات" as a single flat link (`to: 'tenders'`). The page [settings/tender-category-images.vue](arab-contractors-union-front/resources/ts/pages/settings/tender-category-images.vue) exists and is routable, but is only reachable from the Settings hub.

**Change**: Convert the tender entry to a `children` group, matching the existing pattern already used for المقاولون / سوق الآليات / الشهادات / التعميمات:

```ts
menuItems.push({
  title: 'العطاءات',
  icon: { icon: 'tabler-files' },
  children: [
    { title: 'قائمة العطاءات', to: 'tenders' },
    { title: 'صور تصنيفات العطاءات', to: 'settings-tender-category-images' },
  ],
})
```

Route name `settings-tender-category-images` follows the PascalCase→kebab-case conversion configured in [vite.config.ts](arab-contractors-union-front/vite.config.ts) — verify against the generated `typed-router.d.ts` rather than assuming.

**Open question**: whether to *also* remove the link from the Settings hub page ([settings/index.vue](arab-contractors-union-front/resources/ts/pages/settings/index.vue)) or leave both entry points. Leaving both is the safer default; the request says "move", so confirm with the stakeholder.

**Risk**: Very low. **Complexity**: Trivial (frontend-only, one file).

---

### #2 — Rebrand "اتحاد المقاولين العرب" → "اتحاد المقاولين الفلسطينيين"

**Current state**: the repo directories themselves are named `arab-contractors-union-*`, but the *user-visible* strings are inconsistent — [index.html](arab-contractors-union-front/index.html) says "العرب" in the title/meta while the loader `alt` text on line 92 already says "الفلسطينيين".

**Known occurrences**:

| File | Lines | String |
|---|---|---|
| [index.html](arab-contractors-union-front/index.html) | 23 | `<title>اتحاد المقاولين العرب</title>` |
| [index.html](arab-contractors-union-front/index.html) | 27 | `og:title` — `اتحاد المقاولين العرب — لوحة التحكم` |
| [index.html](arab-contractors-union-front/index.html) | 28 | `og:description` — `لوحة تحكم إدارة اتحاد المقاولين العرب` |
| [index.html](arab-contractors-union-front/index.html) | 29 | `og:site_name` — `Arab Contractors Union` |
| [index.html](arab-contractors-union-front/index.html) | 33 | `meta description` — `لوحة تحكم إدارة اتحاد المقاولين العرب` |
| [reports/summary.blade.php](arab-contractors-union-api/resources/views/reports/summary.blade.php) | header, footer | `اتحاد المقاولين العرب — تقرير إحصائي` / `نظام اتحاد المقاولين العرب` |

**Before implementing, run an exhaustive sweep** — the list above came from a targeted search, not a full audit. Grep both apps for `المقاولين العرب` and `Arab Contractors` (and the English `ACU` abbreviation) across `.vue`, `.ts`, `.tsx`, `.php`, `.blade.php`, `.json`, `.html`, and the generated PDF/Docx certificate templates. Certificate and PDF templates are the highest-risk miss: they are user-facing legal documents and are not covered by any test.

**Explicitly out of scope**: directory names (`arab-contractors-union-api/`, `arab-contractors-union-front/`), git remote names, and the VPS paths under `/var/www/pcuorg/` — renaming these would break [deploy.yml](.github/workflows/deploy.yml) and both `deploy-vps.sh` scripts, which hardcode `monorepo/arab-contractors-union-api/` in their rsync source paths. Only *displayed* strings change.

**Risk**: Low per-file, but **medium in aggregate** — a missed occurrence in a certificate template ships a wrong organisation name on an official document. **Complexity**: Small, but requires a careful sweep.

---

### #3 — Event speaker `photo` missing from the API response

**Root cause (confirmed)**: [EventController.php:111](arab-contractors-union-api/app/Http/Controllers/Api/EventController.php) returns the raw JSON column verbatim:

```php
'speakers' => $e->speakers ?? [],
```

`mergeSpeakerPhotosAndEnforceSingleKeynote()` ([lines 243-266](arab-contractors-union-api/app/Http/Controllers/Api/EventController.php)) only writes `$validated['speakers'][$index]['photo']` when a file was actually uploaded at that index. A speaker saved without a photo therefore has **no `photo` key at all** in the stored JSON — which is exactly what the attached Postman screenshot shows (the boxed second speaker has only `name`/`title`/`is_keynote`). The mobile app then can't distinguish "no photo" from "field missing" and has nothing to bind to.

Note the screenshot also shows `"is_keynote": "1"` as a **string**, not a boolean — a second, unreported bug from the same raw-passthrough. Both speakers being `is_keynote: "1"` also means the single-keynote enforcement did not take effect on this record (`"1"` is truthy, so the loop should have kept only the last — worth checking whether this row predates that code).

**Change**: normalise every speaker to a fixed shape in `formatEvent()`, so the key is always present and typed:

```php
'speakers' => collect($e->speakers ?? [])->map(fn ($sp) => [
    'name'       => $sp['name']  ?? null,
    'title'      => $sp['title'] ?? null,
    'photo'      => $sp['photo'] ?? null,
    'is_keynote' => (bool) ($sp['is_keynote'] ?? false),
])->values()->all(),
```

**Also apply to the public endpoint**: `show()` ([line 173](arab-contractors-union-api/app/Http/Controllers/Api/EventController.php)) uses `$event->toArray()` and bypasses `formatEvent()` entirely, so it has the same defect. Either route it through the same normaliser or extract the mapping into a small private helper used by both. `contractorEvents()` (list) already calls `formatEvent()` and is fixed for free.

**Consider instead/additionally**: an accessor or cast on the `Event` model so the shape is guaranteed at the source rather than per-controller. That is the more robust fix but touches more call sites — decide based on whether anything else reads `speakers` directly.

**Tests**: add to [EventAdminTest.php](arab-contractors-union-api/tests/Feature/EventAdminTest.php) — a speaker stored without a `photo` key still returns `photo: null` on both the contractor and public detail endpoints, and `is_keynote` is a real boolean.

**Risk**: Low. **Complexity**: Trivial. **Note**: this is an additive response change (a key that was absent becomes present-and-null); it cannot break a client that was already handling the missing key.

---

### #4 — Confirmation dialog on attachment removal

**Already done** by Moamen_ayyad. The pattern is visible in [tenders/index.vue:107-131](arab-contractors-union-front/resources/ts/pages/tenders/index.vue) (`deleteAttachmentDialog` / `confirmRemoveAttachment` / `removeAttachment`, annotated `REQ-07 #4`) and in [contractors/create.vue:124-137](arab-contractors-union-front/resources/ts/pages/contractors/create.vue) (`removeSpecialtyDialog`). No action — but worth a spot-check that coverage is complete across news/events/announcements image removal too, since the cross-cutting note at the bottom of this file flags exactly this pattern as repeatedly reimplemented one-off.

---

### #5 — Governorate / building / floor not visible after adding a contractor

**The reported cause is wrong.** The report says "السبب عدم وجود مدخلاتها في لوحة" (the dashboard has no inputs for them) — but the inputs **do exist and work**:

- [create.vue:396-430](arab-contractors-union-front/resources/ts/pages/contractors/create.vue) — cascading المحافظة/المدينة selects plus required الحي / العمارة / الطابق fields.
- [edit/[id].vue:452-486](arab-contractors-union-front/resources/ts/pages/contractors/edit/%5Bid%5D.vue) — same fields, loaded and saved.
- [ContractorController.php:96-106](arab-contractors-union-api/app/Http/Controllers/Api/ContractorController.php) — `governorate_id`, `city_id`, `district`, `building`, `floor` are all validated and `required` on create.

These were added by TASK-01 #5. **Two real defects produce the reported symptom:**

**5a — the admin view dialog never displays them.** `contactRows` in [contractors/index.vue:379-390](arab-contractors-union-front/resources/ts/pages/contractors/index.vue) lists only الجوال / الهاتف / البريد / المدينة / العنوان التفصيلي / رقم الرخصة. الحي, العمارة, الطابق and المحافظة are simply absent from the computed array, so an admin who saves them then reopens the record sees no trace of them.

**5b — the API never returns the governorate *name*.** `show()` ([ContractorController.php:265-270](arab-contractors-union-api/app/Http/Controllers/Api/ContractorController.php)) does `$contractor->load('activeMembership')` — it does **not** eager-load the `governorate` relation ([Contractor.php:116](arab-contractors-union-api/app/Models/Contractor.php)). The response carries a bare `governorate_id` integer and no name. (The city is unaffected: `syncLocation()` at [line 237](arab-contractors-union-api/app/Http/Controllers/Api/ContractorController.php) denormalises `city_id` into the legacy `city` text column, so `t.city` resolves. There is no equivalent `governorate` text column.)

**Changes**:

1. Backend — eager-load the relation in `show()`:
   ```php
   $contractor->load(['activeMembership', 'governorate:id,name'])
   ```
   Adding a column selection keeps the payload tight. Confirm the `governorates` table's name column is `name` before writing the constrained select — a wrong column name there fails at query time, not validation time.

2. Frontend — extend `contactRows` in [contractors/index.vue:379](arab-contractors-union-front/resources/ts/pages/contractors/index.vue):
   ```ts
   { label: 'المحافظة', value: t.governorate?.name || '—', icon: 'tabler-map' },
   { label: 'الحي',     value: t.district || '—',          icon: 'tabler-map-pin-2' },
   { label: 'العمارة',  value: t.building || '—',          icon: 'tabler-building' },
   { label: 'الطابق',   value: t.floor    || '—',          icon: 'tabler-stairs' },
   ```

**Mobile app side — verified and fixed (2026-09-22):**

`ContractorProfileService::fullResource()` already included `district`/`building`/`floor` (lines 99-101) and `liteResource()` already returned `governorate` as `{id, name}` object. The missing piece was `district` in `UpdateFullProfileRequest` — added `'district' => 'nullable|string|max:100'` so contractors can also update their own district from the mobile app.

Covered by [ContractorProfileAddressTest.php](arab-contractors-union-api/tests/Feature/ContractorProfileAddressTest.php) (4 tests, all pass):
1. `test_profile_returns_address_fields_set_by_admin` — GET profile returns `governorate.{id,name}`, `district`, `building`, `floor`
2. `test_profile_returns_null_governorate_when_not_set` — nulls when no address set
3. `test_update_full_profile_persists_district` — POST profile/update persists district and returns it
4. `test_update_full_profile_requires_auth` — 401 without auth

**Key testing lesson**: `$request->user('contractor')` requires the `contractor` guard to be explicitly set — `Sanctum::actingAs($contractor, ['*'])` alone (default `sanctum` guard) passes `auth:sanctum` middleware but leaves `$request->user('contractor')` returning null. Use both: `Sanctum::actingAs($contractor, ['*'])` + `app('auth')->guard('contractor')->setUser($contractor)` (via the `actingAsContractor()` helper in the test class).

**Risk**: Low. **Complexity**: Small, but scope depends on the mobile-endpoint check above.

---

### #6 — Show activity/company data in view mode

**Current state**: the details dialog in [contractors/index.vue:533-742](arab-contractors-union-front/resources/ts/pages/contractors/index.vue) renders four cards — معلومات العضوية والتأسيس, الإدارة والشركاء, العنوان والاتصال, المجالات والتصنيفات — plus documents and notes. `trade` appears conditionally inside `membershipRows` ([line 365](arab-contractors-union-front/resources/ts/pages/contractors/index.vue)) and `classification` only as a fallback when the specialties array is empty ([line 666](arab-contractors-union-front/resources/ts/pages/contractors/index.vue)). `license_number` sits oddly under العنوان والاتصال ([line 388](arab-contractors-union-front/resources/ts/pages/contractors/index.vue)), and `established_date` under العضوية.

So the data is *mostly* reachable but scattered and partly conditional, which is why it reads as "only in edit mode".

**Change**: add a dedicated `activityRows` computed + a matching card, and remove the now-duplicated entries from their current homes so nothing renders twice:

```ts
const activityRows = computed(() => {
  const t = detailsTarget.value
  if (!t) return []
  return [
    { label: 'التخصص العام',      value: t.trade          || '—', icon: 'tabler-briefcase' },
    { label: 'التصنيف العام',     value: getGradeTitle(t.classification), icon: 'tabler-award' },
    { label: 'رقم رخصة البلدية', value: t.license_number || '—', icon: 'tabler-license' },
    { label: 'تاريخ التأسيس',    value: t.established_date ? String(t.established_date).substring(0, 10) : '—', icon: 'tabler-calendar-star' },
  ]
})
```

Reuse the existing `getGradeTitle()` helper ([line 324](arab-contractors-union-front/resources/ts/pages/contractors/index.vue)) so the general classification renders as a human label rather than the raw `اولى أ` code — the current fallback at line 666 prints the raw value.

**Before implementing**, walk the edit form field-by-field against the dialog and list what else is genuinely missing (`company_purposes`, `legal_form`, `capital`, `registration_date` are already in `membershipRows`; `authorized_person_id_number` / `_phone` / `_whatsapp` are **not** in `managementRows` and are plausible additions). "بيانات النشاط والشركة" is loosely specified — confirm the intended field list with the stakeholder rather than guessing.

**Risk**: Very low (display-only). **Complexity**: Small.

---

### #7 — Company file display problem — ⚠️ BLOCKED

The report references an attached screenshot ("تم ارفاق صورة") that was **not supplied** — the only image provided with this batch is the Postman response for #3. Without it the symptom is unidentifiable.

**What was ruled out by inspection**: the wiring is correct end-to-end. All 13 keys in the frontend's `documentFields` ([contractors/index.vue:392-406](arab-contractors-union-front/resources/ts/pages/contractors/index.vue)) are present in `Contractor::FILE_FIELDS` ([Contractor.php:56-62](arab-contractors-union-api/app/Models/Contractor.php)); `show()` sets `$contractor->withFileUrls = true` ([ContractorController.php:267](arab-contractors-union-api/app/Http/Controllers/Api/ContractorController.php)), which makes `toArray()` inject a full `<field>_url` for each via `url(Storage::url($path))`; and `documentUrl()` ([line 408](arab-contractors-union-front/resources/ts/pages/contractors/index.vue)) reads `{key}_url` first with `{key}` as fallback.

**Plausible candidates to check once the screenshot arrives**, in rough order of likelihood:
1. `APP_URL` misconfigured on the VPS → `Storage::url()` builds links against the wrong host. This is environment-specific, would affect *all* documents at once, and is invisible locally.
2. The `storage` symlink missing or broken on the server → links resolve but 404.
3. A specific document (`company_register`, "مستخرج عن سجل الشركة") stored under a path the public disk doesn't serve.
4. In-browser display of a `.doc`/`.docx` — `viewFile()` ([line 277](arab-contractors-union-front/resources/ts/pages/contractors/index.vue)) calls `window.open`, which downloads rather than previews Office formats. If the complaint is "it doesn't open", this is the answer and the fix is a UX one, not a bug.

**Action**: request the screenshot before estimating. Do not guess-fix — candidates 1 and 4 have opposite remedies.

---

### #8 — Limit "آخر التحديثات" to 3 items + "show more"

**Current state**: [ContractorHomeController.php:39](arab-contractors-union-api/app/Http/Controllers/Api/ContractorHomeController.php) sets `HOME_UPDATES_LIMIT = 10`, consumed by `index()` at [line 56](arab-contractors-union-api/app/Http/Controllers/Api/ContractorHomeController.php) for the `latest_updates` key. A separate paginated endpoint `GET /api/v1/contractor/home/updates` already exists (`UPDATES_PER_PAGE = 20`) and is exactly the "separate page" the request asks for — **no new endpoint is needed.**

**Change**:

```php
private const HOME_UPDATES_LIMIT = 3;
```

**This is a mobile-app-coordinated change, not a standalone backend one.** The "عرض المزيد" button lives in the mobile client, which is a separate codebase not in this monorepo. Two things must happen for the item to actually land:

1. Backend drops the limit to 3 (this repo).
2. The mobile app adds the button and points it at the existing `contractor/home/updates` endpoint (Moamen's side).

Shipping (1) without (2) makes the home feed shorter with no way to see the rest — a regression from the user's perspective. **Coordinate the two, or ship (1) only once (2) is ready.**

**Postman**: per the repo convention, note the changed `latest_updates` count in [Contractor_App_API.postman_collection.json](Contractor_App_API.postman_collection.json)'s `Home` request description, and make sure the `Home Updates` request is documented as the "show more" target.

**Risk**: Low technically, **medium in coordination**. **Complexity**: Trivial (one constant) + external dependency.

---

### #9 — Accept `fcm_token` on set-password

**Current state**: `setPassword()` ([ContractorRegisterController.php:182-250](arab-contractors-union-api/app/Http/Controllers/Api/ContractorRegisterController.php)) validates only `phone` / `password` / `password_confirmation`, and its `update()` sets `password`, `profile_completed` and conditionally `status`. The `fcm_token` column **already exists** on the model's `$fillable` ([Contractor.php:36](arab-contractors-union-api/app/Models/Contractor.php)) — no migration needed.

This matters because set-password is the step that immediately issues a 60-day token and logs the contractor in. Without capturing the device token here, a brand-new contractor receives **no push notifications at all** until some later call happens to register it.

**Change**:

```php
$request->validate([
    'phone'                 => 'required|string',
    'password'              => 'required|string|min:8',
    'password_confirmation' => 'required|string',
    'fcm_token'             => 'nullable|string|max:500',
]);

$contractor->update([
    'password'          => Hash::make($request->password),
    'profile_completed' => true,
    'fcm_token'         => $request->fcm_token ?: $contractor->fcm_token,
    ...($contractor->status === 'pending' ? ['status' => 'active'] : []),
]);
```

Keep it `nullable` — making it required would break any client that ships before the mobile app is updated.

**Precedent already exists**: `ContractorAuthController::login()` does exactly this at [line 75](arab-contractors-union-api/app/Http/Controllers/Api/ContractorAuthController.php) (`'fcm_token' => $request->fcm_token ?? $contractor->fcm_token`, documented in the header comment at line 38 as an optional field). So set-password is the *only* gap in the auth flow, not a systemic one — and the fix should mirror login's existing shape for consistency.

One small divergence worth considering: login uses `??`, which falls back only on `null` — an empty-string `fcm_token` would overwrite a good stored token with `''`. Using `?:` in the new code guards against that, but then the two endpoints behave differently. Either match login's `??` and accept the quirk, or change both to `?:` in the same pass. Prefer the latter; it's a one-character change on a line that's already being touched.

**Tests**: assert that posting `fcm_token` persists it, that omitting it leaves any previous value intact, and that an empty string does not clear it.

**Postman**: add `fcm_token` to the `Set Password` request body in [Contractor_App_API.postman_collection.json](Contractor_App_API.postman_collection.json) — this is a contractor-facing route change, which the repo convention requires be reflected there.

**Risk**: Very low (additive, optional field). **Complexity**: Trivial.

---

### Files affected (planned)

| Item | File | Side |
|---|---|---|
| 1 | `resources/ts/navigation/vertical/pcu.ts` | Frontend |
| 2 | `index.html`, `resources/views/reports/summary.blade.php`, + sweep results | Both |
| 3 | `app/Http/Controllers/Api/EventController.php`, `tests/Feature/EventAdminTest.php` | Backend |
| 5 | `app/Http/Controllers/Api/ContractorController.php`, `pages/contractors/index.vue` | Both |
| 6 | `pages/contractors/index.vue` | Frontend |
| 8 | `app/Http/Controllers/Api/ContractorHomeController.php`, Postman collection | Backend |
| 9 | `app/Http/Controllers/Api/ContractorRegisterController.php`, Postman collection, new test | Backend |

**Database changes**: none. Every field this batch touches already exists.

**Suggested order**: #9 and #3 first (self-contained, testable, unblock the mobile app); then #1, #5, #6 (independent UI work); #2 last (widest blast radius, wants a careful sweep and a full visual pass); #8 gated on mobile-side readiness; #7 blocked pending the screenshot.

**Acceptance criteria**: ✅ Tender-category-images reachable from the العطاءات sidebar group. ✅ No user-visible "اتحاد المقاولين العرب" remains anywhere (index.html, OG tags, Postman collection, reports template all verified). ✅ Every speaker object returns `photo` (null when unset) and a boolean `is_keynote`, on both the contractor and public event-detail endpoints. ✅ المحافظة/الحي/العمارة/الطابق visible in the admin contractor view dialog. ✅ Activity/company data visible without entering edit mode. ✅ Home feed returns 3 updates (backend half done; mobile "show more" button pointing at `contractor/home/updates` is Moamen's side). ✅ `fcm_token` persisted at set-password. ✅ 226 tests green.

---

## TASK-16 — Feedback batch 2026-09-22 — 🔄 STATUS: CODE COMPLETE, awaiting production PHP-FPM limit change (2026-09-22) — ⚠️ four sub-issues re-reported 2026-09-24, carried into TASK-17

> **2026-09-24**: #2/#3 (upload limits), #4 (document deletion), #5 (preview parity) and #6/#7 (invisible requests) were all re-reported. The pending server change ([TASK-16-tasks.md](TASK-16-tasks.md) T003–T006) is still unexecuted and accounts for #2/#3; #6 and #7's diagnoses turned out to be wrong. See TASK-17 for the corrected analysis. Do not treat this task's summary table as settled.

### Execution summary (2026-09-22) — first pass

| Sub-issue | Result |
|---|---|
| #6 Membership requests invisible | **Done.** Restored the three commented-out entry points (`navigation/vertical/pcu.ts`, `navigation/horizontal/index.ts`, `pages/dashboards/index.vue`), reversing TASK-02. Verified the dormant `memberships.vue` had not rotted — it calls `/api/v1/memberships{,/{id}/approve,/reject}`, which still match `routes/api.php:386-390`. Breadcrumb mapping already existed. |
| #7 Profile edit requests invisible | **Done.** New page `pages/contractors/profile-update-requests.vue` + nav entry + breadcrumb. Route name confirmed generated as `contractors-profile-update-requests` in `typed-router.d.ts`, matching the nav `to:`. **Two backend gaps found while building it** (see below). |
| #1 Home feed 5 + عرض المزيد | **Done (backend).** `HOME_UPDATES_LIMIT` 3 → 5; added `latest_updates_total` + `has_more`. `buildFeed()` is now called once and reused — the old inline `buildFeed()->take()` would have rebuilt the whole 7-query feed a second time just to count it. Postman `Home` description updated. Mobile-side button remains Moamen's. |
| #5 Preview field parity | **Done.** Exactly three fields were genuinely missing, all in the management group: `authorized_person_id_number`, `authorized_person_phone`, `authorized_person_whatsapp`. «التصنيف العام» was already present (added by TASK-03). Also had to add the `:dir="row.dir"` binding to the management rows — that section, unlike `contactRows`, never bound it, so the two new phone fields would have rendered RTL-mangled. |
| #4 Delete documents on edit | **Done.** Backend: extracted the 14-entry file-field map out of `handleFileUploads()` into a `FILE_FIELDS` constant so the new `remove_documents.*` rule derives its allowlist (`Rule::in`) from the same source; added `handleDocumentRemovals()`. A replacement uploaded for the same field in the same request **wins over** a stale remove flag — applying the removal after the upload would delete the file the user just uploaded. Frontend: per-document 🗑 button on all 13 blocks, confirm dialog, a reversible "سيُحذف عند الحفظ" state, and `remove_documents[]` appended at submit. Deliberately **not** "empty value means delete" — that would re-break TASK-01 #4. New `ContractorDocumentRemovalTest` (7 tests). |
| #2, #3 Upload size limits & error clarity | **Code done; server change still pending (stakeholder is applying it).** Key decision: the limit is now **derived from `ini_get('upload_max_filesize')` at runtime** via the new `App\Support\UploadLimits`, not a hardcoded number. The old `max:10240` promised 10 MB while the server allowed 2 MB — a fixed number on either side drifts silently. Deriving it means the form tells the truth **today at 2 MB** and self-corrects to 12 MB the moment the server is raised, with no redeploy. Exposed through the existing `specialties-catalog` payload (already fetched by both forms) rather than a new endpoint. New `DetectDiscardedRequestBody` middleware returns a 413 explaining the size when PHP has silently dropped the body, registered **before** validation so it can't be masked by the bogus "required" cascade. Both forms now: show a per-field hint, reject an oversize file client-side before any bytes move, block submit when the *total* exceeds `post_max_size`, and handle 413 distinctly. New `ContractorUploadLimitsTest` (5 tests). |

**Backend gaps found while building #7's page** (neither was in the plan — the plan assumed the endpoint was complete):
1. `index()` eager-loaded `contractor:id,name,membership_number` only. Any attempt to show the contractor's *current* values would have silently returned `null` for all of them.
2. `format()` returned `proposed_data` but no current values and no `membership_number` — an admin would have been approving a list of proposed values with nothing to compare them against. Added `current_data` (restricted to the keys actually present in `proposed_data`, intersected with `ALLOWED_FIELDS`) and `membership_number`, and widened the eager-load select accordingly. Both changes are additive, so the contractor-app `mine()` endpoint that shares `format()` is unaffected.

**Tests**: `ProfileUpdateRequestTest` 13 → 20 (7 new admin-side tests; the admin list/approve/reject path had **zero** coverage despite being the only way to action a request). `ContractorHomeTest` 23 → 26; renamed `test_latest_updates_caps_at_three…` → `…caps_at_five…` and added `has_more` cases above, below and exactly at the cap. Both files fully green.

**Regression caught during US5**: `create.vue`'s file inputs already carried `:rules="[v => !!v || 'مطلوب']"` (documents are required on create, unlike edit). Adding a second `:rules` binding produced 13 `TS1117` duplicate-property errors — the size check was merged into the existing rule array instead. Worth remembering that the two forms are *not* symmetric here.

**Verification**: 257 passed (245 at the start of this pass). 20 failures are **pre-existing and unrelated** — `AnnouncementNotificationTest`, `ExpiryReminderTest`, `PaymentReminderTest` all fail with `Class "Database\Factories\ContractorFactory" not found`; `database/factories/` only contains `UserFactory.php`. Confirmed by stashing all working-tree changes and re-running `ExpiryReminderTest`: still 7/7 red. Spawned as a separate task. `vue-tsc` reports the same 32 pre-existing error files as before the change, none of them touched here. `npm run lint` cannot run at all repo-wide — the script passes `--rulesdir eslint-internal-rules/`, a directory that does not exist.

**Browser verification not performed**: the admin dashboard needs an authenticated session, and the local API cannot serve one — `php -S` is single-threaded and `PHP_CLI_SERVER_WORKERS` is POSIX-only, so on Windows the preview tool's keepalive connections starve every API request (`tenders-public` timed out at `000`). The Vue app itself was confirmed to boot with no console errors. The four stories above are covered by the feature tests and typecheck instead; a manual pass on a real environment is still worth doing before sign-off.

---

### Original plan (below)


**Description**: Seventh feedback batch. Seven sub-issues across three areas: the contractor home feed page length, the admin contractor add/edit document-upload experience (three related sub-issues, all rooted in one server-config defect), the contractor preview dialog's field coverage, and two "request submitted from the app but never appears in the panel" reports — both of which are **gaps left by TASK-02 and TASK-03**, not new bugs.

### ⚠️ Headline finding — production PHP limits contradict the app's own validation

Verified live on `srv1962001` (`php -i` + `/etc/php/*/fpm/`):

| Setting | Production value | What the app assumes |
|---|---|---|
| `upload_max_filesize` | **2M** | `max:10240` (10 MB) in `ContractorController::getValidationRules()` |
| `post_max_size` | **8M** | 14 document fields × up to 10 MB each |
| `max_file_uploads` | 20 | 14 document fields + other inputs |
| nginx `client_max_body_size` | 20M | — (nginx is *not* the bottleneck) |

This single misconfiguration causes sub-issues #2 **and** #3 and makes them look like two different bugs:

- **Any single file > 2 MB** is discarded by PHP before Laravel runs. The field simply arrives absent, so Laravel never emits a size error — it emits nothing about that file at all. The UI falls back to the generic `'فشل تسجيل المقاول. يرجى التحقق من المدخلات.'` at [create.vue:217](arab-contractors-union-front/resources/ts/pages/contractors/create.vue) — exactly the red banner in the attached screenshot, with every document field still showing a filename.
- **Total request body > 8 MB** makes PHP discard the **entire** body: `$_POST` and `$_FILES` both come back empty. Laravel then sees a blank request and fails "required" on fields the user demonstrably filled in. This is why the reporter deleted many document entries and re-submitted, yet the browser still spent a long time uploading everything before failing — the browser always uploads the full body; PHP only drops it *after* the transfer completes.

**The fix is therefore server-side first, code second.** Raising `max:10240` in Laravel or adding a client-side hint alone will not fix it. Note `deploy-vps.sh` does not manage `php.ini`, so this change must be applied directly on the VPS and recorded in [CLAUDE.md](CLAUDE.md) — otherwise a server rebuild silently reintroduces it.

### Sub-issues

| # | Sub-issue (verbatim from the sheet) | Area |
|---|---|---|
| 1 | في سكشن اخر التحديثات يكفي عرض 5 تحديثات مع وجود زر عرض المزيد بنتقل لصفحة منفصلة حتى لا يتم اطالة الصفحة (`{{base_url}}/contractor/home`) | Backend |
| 2 | اظهار قيود مستندات الحجم المسموح به كحد اقصى لانه عند الحفظ رفض الحفظ بسبب انه حجم الملفات كبير | Server + Both |
| 3 | عند اضافة ملفات كبيرة ولم يقبل النظام بحفظها دون اظهار سبب انها كبيرة تم حذف جزء كبير من مدخلات المستندات وضغط على زر حفظ الا انه الزمني برفع كامل الملفات حتى يتم الحفظ | Server + Frontend |
| 4 | عند التعديل لا يسمح بحذف المستندات فقط استبدالها | Both |
| 5 | اثناء معاينة بيانات اي مقاول يجب عرض جميع البيانات مماثلة الى ما تم ادخاله اثناء الاضافة مثل التصنيف العام رقم هوية المفوض | Frontend |
| 6 | Membership Request — عند طلب شهادة عضوية يتم دفع رسوم العضوية من التطبيق ثم يتم ارسال الطلب مباشرة على لوحة والموافقة عليه او رفضه لكن لا يتم اظهار طلب في لوحة | Frontend |
| 7 | Edit Company Profile — تم تعديل ملف الشركة من خلال التطبيق لكن لن يتم عرض الطلب في لوحة رغم التحديث | Frontend |

### Current implementation

**#1 — Home feed**: `ContractorHomeController::index()` returns `latest_updates` capped by `HOME_UPDATES_LIMIT`, currently **3** ([ContractorHomeController.php:39](arab-contractors-union-api/app/Http/Controllers/Api/ContractorHomeController.php)) — TASK-15 set this to 3; the sheet now asks for 5. The "عرض المزيد" target already exists and is fully built: `GET contractor/home/updates` (`updates()`, line 65) paginates the same feed at `UPDATES_PER_PAGE = 20` via `LengthAwarePaginator`. The gap is that the `index()` payload exposes **no total or `has_more` flag**, so the app has no signal for whether to render the button. The consuming screen is the Flutter app (the Vue `pages/contractor/dashboard.vue` never renders `latest_updates`).

**#2/#3 — Document uploads**: 14 `nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240` rules at [ContractorController.php:117-130](arab-contractors-union-api/app/Http/Controllers/Api/ContractorController.php). Both forms use `VFileInput` with `accept=".pdf,.doc,.docx,image/*"` and **no `rules`, no `hint`, and no size text anywhere** — confirmed across all 14 inputs in [edit/[id].vue](arab-contractors-union-front/resources/ts/pages/contractors/edit/%5Bid%5D.vue) and [create.vue](arab-contractors-union-front/resources/ts/pages/contractors/create.vue). `create.vue`'s catch block maps `err.response.data.errors` into `validationErrors` and jumps to the offending step, but a body dropped by PHP produces no per-field errors to map.

**#4 — Document deletion**: `handleFileUploads()` ([ContractorController.php:131-158](arab-contractors-union-api/app/Http/Controllers/Api/ContractorController.php)) only ever writes a new path when a file is present, and deletes the old file from storage solely as a side-effect of replacement. There is **no delete endpoint and no clear/remove control** in either form; TASK-01 #4 deliberately added a guard that *skips* empty `VFileInput` arrays when building `FormData`, which locked out clearing as a side effect. Result: once attached, a document can only ever be swapped.

**#5 — Preview dialog**: [contractors/index.vue](arab-contractors-union-front/resources/ts/pages/contractors/index.vue) builds `membershipRows` (354), `managementRows` (367), `contactRows` (377). `التصنيف العام` **is** already present (lines 398 and 701 — added by TASK-03). `authorized_person_id_number` is on both forms and in backend validation but appears **nowhere** in the dialog. The sub-issue says "مثل" (e.g.), so this needs a systematic form-field-vs-dialog-field diff, not just the two named fields.

**#6 — Membership requests**: root cause is **TASK-02**. The feature is entirely intact — `MembershipController` (`pending`/`index`/`store`/`approve`/`reject`), routes at [api.php:386-390](arab-contractors-union-api/routes/api.php), page [contractors/memberships.vue](arab-contractors-union-front/resources/ts/pages/contractors/memberships.vue). On 2026-09-19, per a stakeholder instruction recorded in TASK-02 ("hide the nav link only"), the entry points were commented out in three places: [pcu.ts:36](arab-contractors-union-front/resources/ts/navigation/vertical/pcu.ts), [horizontal/index.ts:20](arab-contractors-union-front/resources/ts/navigation/horizontal/index.ts), [dashboards/index.vue:61](arab-contractors-union-front/resources/ts/pages/dashboards/index.vue). Requests have been arriving in the panel this whole time — nobody can navigate to them. **Confirmed with the reporter on 2026-09-22 that this reverses the TASK-02 decision.**

**#7 — Profile update requests**: root cause is a **wrong conclusion in TASK-03**. That task inspected the Vue contractor portal, saw it posts to `contractor/auth/profile/update` (direct write, no approval), and concluded the `ProfileUpdateRequest` approval queue was "an unrelated dead-end". But the reporter uses the **Flutter mobile app**, which posts to `POST contractor/profile-update-requests` — that handler ([ProfileUpdateRequestController.php:108-113](arab-contractors-union-api/app/Http/Controllers/Api/ProfileUpdateRequestController.php)) creates a real row with `status='pending'`, and the admin list endpoint `GET dashboard/profile-update-requests` (`index()`, line 160) works and returns it. What does not exist is any consumer: `grep -rn "profile-update-requests" arab-contractors-union-front/resources/ts/` returns **zero hits**. There is no page and no menu entry. Rows have been accumulating in `profile_update_requests` unseen.

### Implementation steps

**Server (do first — #2, #3 are not reproducible or verifiable until this lands)**
1. On the VPS, set `upload_max_filesize = 12M`, `post_max_size = 60M`, `max_file_uploads = 30` in the PHP-FPM ini, `systemctl reload php*-fpm`, and confirm via `php -i`. `post_max_size` must exceed `upload_max_filesize` × realistic concurrent uploads — 60M covers all 14 documents at typical scan sizes without permitting a 140M request. Raise nginx `client_max_body_size` from 20M to 64M to stay above `post_max_size`. Document all of it in CLAUDE.md as deploy-invisible server state.

**Backend**
2. `HOME_UPDATES_LIMIT` 3 → 5, and add `latest_updates_total` + `has_more` to the `index()` payload so the app knows whether to draw "عرض المزيد" (#1). Update the `Home` request description in [Contractor_App_API.postman_collection.json](Contractor_App_API.postman_collection.json) — contractor-facing contract change.
3. Add a `post_max_size`-exceeded guard: when the request is non-GET, `Content-Length` is set, and `$_POST`/`$_FILES` are both empty, return a 413 with an explicit Arabic message instead of letting it fall through to a misleading "required" cascade (#3). Middleware is the right home for this, since it must run before validation.
4. Align `max:10240` with whatever `upload_max_filesize` ends up being, and surface the effective ceiling through an endpoint (or the existing catalog endpoint) so the forms display a value that cannot drift from the server (#2).
5. Add document deletion (#4): accept an explicit `remove_documents[]` array of field names on update; for each, `Storage::delete()` the old path and null the column. Must be an explicit opt-in list — reusing "empty value means delete" would re-break the TASK-01 #4 fix that stopped cleared `VFileInput` arrays from being sent as values.

**Frontend**
6. Add a `rules` size check plus `hint`/`persistent-hint` stating the max size and allowed types to all 14 `VFileInput`s in **both** `create.vue` and `edit/[id].vue`, sourced from the endpoint in step 4 (#2). Client-side rejection is what actually prevents the wasted upload described in #3 — the browser never starts the transfer.
7. Show a clear, specific message on 413 / oversize rejection, and preserve entered form state on failure (#3).
8. Add a delete (🗑) control next to each already-uploaded document in `edit/[id].vue`, with the shared confirm-dialog pattern, wired to step 5's `remove_documents[]` (#4).
9. Diff every field on `create.vue`/`edit/[id].vue` against the preview dialog's row builders and add all missing ones — `authorized_person_id_number` confirmed missing; audit the rest rather than fixing only the named example (#5).
10. Restore the three commented-out "طلبات الانتساب" entry points, reversing TASK-02 (#6). Update TASK-02's status line to record the reversal, matching how TASK-04 documents its own reversal.
11. Build `pages/contractors/profile-update-requests.vue` against the existing `GET dashboard/profile-update-requests` endpoint — list, view proposed-vs-current diff, approve, reject with reason — plus a sidebar entry. Model it on the existing `name-change-requests.vue`, which is the same approve/reject shape (#7).

**Correction to TASK-03's parting note** (verified 2026-09-22): TASK-03 flagged `POST profile-update-requests/send-phone-otp` as pointing at a non-existent `ProfileUpdateRequestController::sendPhoneOtp`. **That is no longer true** — the method is fully implemented at [ProfileUpdateRequestController.php:131](arab-contractors-union-api/app/Http/Controllers/Api/ProfileUpdateRequestController.php) and covered by `test_send_phone_otp_is_rate_limited_by_cooldown` in `tests/Feature/ProfileUpdateRequestTest.php`. No action needed; the note is retired here so it stops being carried forward.

### Files involved

| # | Files | Layer |
|---|---|---|
| — | VPS `php.ini` / FPM pool, nginx site config, `CLAUDE.md` | Server |
| 1 | `ContractorHomeController.php`, `Contractor_App_API.postman_collection.json` | Backend |
| 2 | `ContractorController.php`, `create.vue`, `edit/[id].vue` | Both |
| 3 | new middleware, `create.vue`, `edit/[id].vue` | Both |
| 4 | `ContractorController.php`, `edit/[id].vue` | Both |
| 5 | `pages/contractors/index.vue` | Frontend |
| 6 | `navigation/vertical/pcu.ts`, `navigation/horizontal/index.ts`, `pages/dashboards/index.vue` | Frontend |
| 7 | new `pages/contractors/profile-update-requests.vue`, `navigation/vertical/pcu.ts`, `routes/api.php` (dead route) | Both |

**Database changes**: none. Every table, column and status enum this batch needs already exists — `profile_update_requests` and `memberships` are both fully migrated.

**Dependencies**: Step 1 (server limits) blocks meaningful verification of #2 and #3. Step 4 blocks step 6. Step 5 blocks step 8. #1, #5, #6, #7 are all independent.

**Suggested order**: Step 1 first (unblocks the most-complained-about defect immediately, zero code risk). Then #6 (three uncommented lines, restores a feature that has silently been collecting requests). Then #7 (largest net-new piece, and rows are already queued waiting for it). Then #1 and #5 (self-contained). Then #2/#3/#4 as one coherent document-handling pass.

**Tests required**: Feature test that an oversize upload returns a clear size error rather than a generic failure (#2/#3). Feature test for `remove_documents[]` nulling the column and deleting the file while leaving other documents intact (#4). Feature test asserting `latest_updates` returns 5 with a correct `has_more` (#1). `tests/Feature/ProfileUpdateRequestTest.php` already covers #7's backend end-to-end (13 tests) — #7 is frontend-only work, no new backend test needed.

**Task breakdown**: granular, executable task list in [TASK-16-tasks.md](TASK-16-tasks.md).

**Risk level**: Medium. Step 1 touches shared production PHP config and affects every upload path in the app, not just contractors — apply it during a quiet window and verify `tenders-public` still responds. #4 deletes files from storage permanently, so it needs the confirm dialog and a careful read of `handleFileUploads()` before wiring. #6 reverses an explicit prior stakeholder decision — recorded above as confirmed on 2026-09-22.

**Estimated complexity**: Medium. Step 1 is minutes. #6 is trivial. #7 is the bulk of the work (one new page, ~300 lines against an endpoint that already exists).

**Acceptance criteria**: 🔲 A 9 MB PDF uploads successfully; a file above the ceiling is rejected **client-side** with a specific Arabic size message before any bytes are sent. 🔲 Every document field shows its max size and allowed types before the user picks a file. 🔲 An admin can delete an attached document, not only replace it, behind a confirm dialog. 🔲 A failed save preserves all entered form data. 🔲 The preview dialog shows every field the add form collects, `authorized_person_id_number` included. 🔲 "طلبات الانتساب" is reachable from the sidebar and dashboard again. 🔲 A profile edit submitted from the mobile app appears in a new admin list and can be approved or rejected. 🔲 `POST profile-update-requests/send-phone-otp` resolves to a real method or is removed. 🔲 Test suite green.

---
## TASK-17 — Feedback batch 2026-09-24 — 📋 STATUS: PLANNED

**Description**: Eighth feedback batch. Eleven sub-issues across four areas: the admin contractor add/edit flow (four), membership-certificate requests invisible in the panel, company-profile edits invisible in the panel, and the financial-liabilities screen (five — due-date validation, penalty registration, stale totals, a wrong bulk-discount preview, and a modal that does not close).

### ⚠️ Headline finding — three of these eleven are TASK-16 items whose code shipped and whose prerequisite did not

Sub-issues #1, #2 and #3 are verbatim repeats of TASK-16 #3, #4 and #5, all three of which TASK-16 recorded as **done**. TASK-16's own status line explains the first one: *"CODE COMPLETE, awaiting production PHP-FPM limit change"* — [TASK-16-tasks.md](TASK-16-tasks.md) Phase 2 (T003–T006) is still unchecked, so the server still runs `upload_max_filesize = 2M` / `post_max_size = 8M`. TASK-16's client-side work derives its ceiling from `ini_get()` at runtime ([UploadLimits.php](arab-contractors-union-api/app/Support/UploadLimits.php)), which means the form is *honest* today — it says "2 ميجابايت" — but 2 MB is below a single realistic scan and 8 MB cannot hold a 13-document submit at all. **#1 is therefore not a new code defect; it is the unexecuted half of TASK-16.**

#2 and #3 are different: their code is entirely frontend, is present in the working tree, and is on `origin/feature/arab-contractors-union` (`8dfa91c`, `61f5b71` are ancestors of the branch head `72151a6`). So either the reporter tested a stale build or the deploy did not land. **[CLAUDE.md](CLAUDE.md) documents exactly this failure mode: a frontend build failure restores the previous `dist/` while the workflow still reports success, and the git state inside `/var/www/pcuorg/{api,front}` is vestigial and lies about what is live.** Verifying what is actually deployed is therefore Phase 0 of this batch, not an afterthought — writing code against a stale-deploy report would produce a second round of "already fixed" findings.

**Live-state verification could not be performed from this session**: the environment's network policy denied `api.pcuorg.cloud`. The checks below must be run by hand (or the host allow-listed) before #1–#3 are coded.

| Check | Command | Reads |
|---|---|---|
| Effective upload ceiling, as the API reports it | `GET https://api.pcuorg.cloud/api/v1/app/specialties-catalog` → `items.upload_limits` | `max_file_mb: 2` ⇒ Phase 2 never ran. Key absent ⇒ the API deploy is stale too. |
| PHP limits at source | `php -i \| grep -E "upload_max_filesize\|post_max_size\|max_file_uploads"` on `srv1962001` | Confirms/denies the above independently of the API |
| nginx ceiling | `grep -R client_max_body_size /etc/nginx` (`-R`, not `-r` — symlinks) | Must stay above `post_max_size` |
| Is the live frontend the TASK-16 build? | `grep -l "سيُحذف عند الحفظ" /var/www/pcuorg/front/dist/assets/*.js` | Present ⇒ build is current, #2/#3 are genuinely unresolved. Absent ⇒ the front deploy rolled back; the fix is a redeploy, not code. |
| What the deploy actually checked out | `git log -1` in `/var/www/pcuorg/monorepo` (the only trustworthy checkout) | Confirms the branch head reached the box |

**Two attachments referenced in the report ("تم ارفاق صورة", "حسب الصور المرسلة") did not arrive.** They matter for #1 (which error banner was shown) and #10 (the 6 / 600 figures). The code defects behind #10 are demonstrable without them; the exact arithmetic is not.

### Sub-issues

| # | Sub-issue (verbatim from the sheet) | Area | Status vs. earlier tasks |
|---|---|---|---|
| 1 | عند اضافة ملفات كبيرة ولم يقبل النظام بحفظها دون اظهار سبب انها كبيرة تم حذف جزء كبير من مدخلات المستندات وضغط على زر حفظ الا انه الزمني برفع كامل الملفات حتى يتم الحفظ | Server | Repeat of TASK-16 #3 — Phase 2 never executed |
| 2 | عند التعديل لا يسمح بحذف المستندات فقط استبدالها | Both | Repeat of TASK-16 #4 — shipped; **one document field genuinely still missing** |
| 3 | اثناء معاينة بيانات اي مقاول يجب عرض جميع البيانات مماثلة الى ما تم ادخاله اثناء الاضافة مثل التصنيف العام رقم هوية المفوض ... | Frontend | Repeat of TASK-16 #5 — shipped; verify the deploy |
| 4 | عند تصدير ملف اكسل بعد اضافة مقاولين اخر مقاولين تم اضافتهم لم يظهر تخصصاتهم وتصنيفاتهم | Frontend | New |
| 5 | Membership Request — عند طلب شهادة عضوية يتم دفع رسوم العضوية من التطبيق ثم يتم ارسال الطلب مباشرة على لوحة والموافقة عليه او رفضه لكن لا يتم اظهار طلب في لوحة | Backend | Repeat of TASK-16 #6 — **the earlier diagnosis was wrong** |
| 6 | Edit Company Profile — تم تعديل ملف الشركة من خلال التطبيق لكن لن يتم عرض الطلب في لوحة رغم التحديث | Backend | Repeat of TASK-16 #7 — **the earlier diagnosis was wrong** |
| 7 | تاريخ الاستحقاق يجب ان يكون ابتداء من تاريخ اليوم نفسه وليس بتاريخ سابق عند الاضافة (قابلة لنقاش في حال اضافة ذمم سابقة متأخرين في سدادها) | Both | New |
| 8 | يمكن السماح في بداية النظام بتسجيل غرامات مالية وتحديد حالتها (غير مسددة + مسددة + مسددة جزئيا + مرفوضة) فقط لحفظها في النظام بدل الورقي | Both | Extends TASK-06 |
| 9 | تم حذف كل الذمم المدرجة للمقاولين لكن اجمالي الذمم وعدد المقاولين يلي عليهم ذمم لم يتم تحديثه الى (0) رغم تحديث الصفحة — تم تصفيرهم بعد تطبيق خصم جماعي لكل المقاولين بنسبة 100% | Both | New |
| 10 | تم اضافة ذمة مالية لسنة 2026 على 2 من مقاولين وتم اجراء خصم جماعي ونوع الخصم مبلغ ثابت يفترض يكون عدد الذمم المطابقة 2 لكن تم اظهار 6 … بالاضافة الى اجمالي اثر الخصم لازم كان 150 وليس 600 | Both | New |
| 11 | عند تنفيذ اي ذمة مالية بعد الحفظ يجب اغلاق المودل دون الحاجة الى تحديث الصفحة | Frontend | Partial repeat of TASK-05 |

### Current implementation & root causes

**#1 — Upload ceiling.** See the headline finding. No code change is expected; the work is T003–T006 of TASK-16, carried forward unchanged. Re-verify afterwards that a 9 MB PDF saves and that a 13-document submit fits inside `post_max_size`.

**#2 — Document deletion.** TASK-16's `remove_documents[]` contract and the per-document 🗑 control both exist and are wired ([ContractorController.php](arab-contractors-union-api/app/Http/Controllers/Api/ContractorController.php) `FILE_FIELDS` + `handleDocumentRemovals()`; `remove_documents[]` appended at [edit/[id].vue](arab-contractors-union-front/resources/ts/pages/contractors/edit/%5Bid%5D.vue) submit). **But the backend `FILE_FIELDS` map has 14 entries and the frontend `documentKeys` list has 13: `id_file` (صورة الهوية) is absent from the edit page entirely** — `grep -c id_file edit/[id].vue` returns **0**. It has no preview link, no replace input and no delete control, so for that one document "only replaceable" is not even true: it is untouchable from the edit screen. Separately, `authorized_signature` is accepted by the contractor-app profile route ([UpdateFullProfileRequest.php](arab-contractors-union-api/app/Http/Requests/Contractor/UpdateFullProfileRequest.php)) but is not in the admin `FILE_FIELDS` map at all, so `remove_documents[]` can never name it.

**#3 — Preview parity.** Both named fields are already rendered: «التصنيف العام» in `activityRows` ([contractors/index.vue:401](arab-contractors-union-front/resources/ts/pages/contractors/index.vue)) and «رقم هوية المفوض» in `managementRows` (line 374, added by TASK-16 T026). The sub-issue ends in "..." , so treat it as "audit again" rather than "two known fields": re-run the create-form-vs-dialog diff against the *current* `create.vue`, and settle the deploy question first — if the live bundle predates `61f5b71` this is a redeploy, not a code change.

**#4 — Excel export.** The export is client-side CSV, not a backend endpoint: `exportContractors()` at [contractors/index.vue:74](arab-contractors-union-front/resources/ts/pages/contractors/index.vue). Its «التخصص» column reads `c.trade` and its «التصنيف» column reads `c.classification` **raw**. Neither is where the current add form puts this data: [create.vue](arab-contractors-union-front/resources/ts/pages/contractors/create.vue) collects `specialties[]` — an array of `{field_lk_type, specialization_lk_type, classification}` rows, persisted to the `specialties` JSON column (`'specialties' => 'array'` on [Contractor.php](arab-contractors-union-api/app/Models/Contractor.php)) — which the export never touches, while `trade` is a legacy free-text field the newer form barely uses. `classification` *is* collected but exported as its raw code rather than the Arabic label. Hence: **the newest contractors export blank in exactly those two columns.** `GET /api/v1/contractors` already returns `specialties`, `field_lk_type` and `specialization_lk_type` (it serializes the whole model), so this is a frontend-only fix — and the dialog already has the three resolvers it needs (`getFieldTitle`, `getSpecializationTitle`, `getGradeTitle`) fed by `specialties-catalog`.

**#5 — Membership requests. TASK-16's diagnosis was wrong, and there are two independent causes.**
1. The request is **never created**. `CertificateRequestController::store` calls `CertificateEligibilityService::checkEligibility($contractor, 'membership')`, which requires `isEligibleForMembershipCertificate()` — 95% of the current year's dues paid. A fee paid from the app arrives as `Payment{status: 'pending'}` via `submitTransfer`, and `contractor_dues.paid_jod` only moves when an accountant confirms that payment. So the contractor pays, submits, and gets **403 `dues_below_threshold`**; no `certificate_requests` row exists and «طلبات الشهادات» is right to be empty. This is the cause that matches the report's own sequence ("يتم دفع رسوم العضوية من التطبيق ثم يتم ارسال الطلب").
2. Separately, «طلبات الانتساب» — the page TASK-16 #6 restored the nav link to — reads `memberships` where `status = 'pending'`, and **nothing in the contractor app ever inserts such a row**: `Membership::create` occurs only in `MembershipController::store` (admin-only) and `MembershipRenewalService::applyRenewal` (which runs *on* admin approval). That page is structurally always empty. TASK-16 restored a link to it and closed the sub-issue on that basis.

**Decision (2026-09-24, with the reporter): surface the request while the payment is pending.** Cause 2 is recorded here so it is not re-reported, but «طلبات الانتساب» is out of scope for this batch.

**#6 — Company-profile edits. TASK-16's diagnosis was wrong here too.** TASK-16 concluded the Flutter app posts to `contractor/profile-update-requests` and that the only gap was a missing admin page (which it then built). The new report contradicts that: *"تم تعديل ملف الشركة من خلال التطبيق … رغم التحديث"* — **the data was updated**, which the approval queue never does before review (`approve()` is the only writer). The edit therefore went through `POST contractor/auth/profile/update` → `ContractorAuthController::updateFullProfile`, which writes straight to `contractors` and creates no request at all. `PATCH contractor/auth/profile` (`updateProfile`) is a second such path. TASK-16's own cross-cutting note already flagged that two paths exist and behave differently; what it missed is that the app uses the direct one.

**Decision (2026-09-24, with the reporter): close it in the backend — the direct route files a request instead of writing.** Full design plan, including the app-side contract: **[TASK-17-US11-profile-edit-design.md](TASK-17-US11-profile-edit-design.md)**. **One part of it already shipped ahead of the rest**: the financial-integrity hole that design pass uncovered — the contractor portal accepting `classification` and `specialties`, the membership-fee calculator's own inputs — was closed on 2026-09-24 on the reporter's instruction. See §2 there. This is the largest item in the batch and the only one needing a migration:
- `ProfileUpdateRequest::ALLOWED_FIELDS` is five keys (`authorized_person`, `authorized_person_title`, `email`, `address`, `phone`); `UpdateFullProfileRequest` accepts ~25 text fields **and 16 document uploads**. `proposed_data` is JSON, so the text fields are free — **the documents have nowhere to go**: `profile_update_requests` has a single `attachment` column, which is the contractor's *evidence* for the request, not the payload.
- `approve()` does `$contractor->update($proposed_data)` verbatim, so whatever shape staged files take must be safe to splat onto the model, and the old file must be deleted on approval, not on submission.
- Phone changes must keep the existing OTP pre-verification, which `store()` already enforces and `updateFullProfile` enforces differently (`getVerifiedResult`) — the two must be reconciled, not stacked.
- Closing only `profile/update` leaves `PATCH profile` as the bypass. Both, or neither.
- **This changes a live app contract**: the response stops reflecting the new values, so the app must show "قيد المراجعة" instead of the saved profile. Moamen has to be told, and [Contractor_App_API.postman_collection.json](Contractor_App_API.postman_collection.json) updated — TASK-16's own Postman pass (`e98bc09`) exists precisely because a wrong entry there cost the app developer real time.

**#7 — Due date.** `StoreContractorDueRequest` has `'due_date' => 'nullable|date'` — nothing stops a past date, and the dues dialog's date field has no `min`. **Decision: `after_or_equal:today` by default, with an explicit opt-in override** for the legacy-arrears case the reporter raised, recording why. The Excel importer (`LegacyDuesImporter`) must stay exempt — it exists to load exactly those historical rows.

**#8 — Penalty registration.** The four statuses already exist end-to-end (`Penalty::STATUS_LABELS`, `PenaltyController::updateStatus`, the frontend `statusOptions`/`statusColor` maps, TASK-06's `expand_penalty_statuses` migration). What is missing is setting the status **at creation**: `PenaltyController::store` validates only `contractor_id`, `reason`, `amount`, `notes`, so every penalty lands `unpaid` and needs a second round trip through `updateStatus` to record what the paper record already says.
**A blocking defect sits in the same screen**: `fetchPenalties()` in [penalties.vue](arab-contractors-union-front/resources/ts/pages/contractors/penalties.vue) reads `data.data || data || []`, but `ApiResponseTrait::paginated()` returns `{status, message, status_code, items, meta}` — there is no `data.data`, so `penalties.value` is assigned the **whole response object** instead of an array, and `total` resolves to `undefined`. The list cannot render rows, and `page` is never bound to a paginator. #8 is not testable through the UI until this is fixed. (Same family as the cross-cutting *"Save succeeded but UI shows failure"* note, and the third page found reading a response shape the trait does not emit.)

**#9 — Stale totals.** The delete path itself is sound: `destroy()` soft-deletes, `contractor_dues` really does have `deleted_at` (`2026_07_18_000004`), and `summary()` builds off `ContractorDue::query()`, so the SoftDeletes global scope excludes them. `summary()` is uncached (only `DashboardController` uses `Cache::remember`, and it carries no dues figures). The explanation that fits every detail of the report is **scope, not staleness**: the dues screen is grouped *by contractor* and paginated at 15, and the only delete control is per-due inside an expanded row — so "حذفت كل الذمم" means "every due I could reach on this page", while the summary cards are system-wide. It also explains the second half of the report: a 100%-discount by *criteria* ignores pagination entirely and, with an empty criteria object, matches every due in the database (see #10.2), which is why that zeroed the cards when deleting had not. Compounding it, `deleteDueMutation` ([dues/index.vue:275](arab-contractors-union-front/resources/ts/pages/dues/index.vue)) has **no `onError`** — a rejected delete is completely silent, which is exactly the shape that convinces a user rows are gone.

**#10 — Bulk discount, 6 matched instead of 2 and 600 impact instead of 150.** Four defects, all in `DuesDiscountService::applyBulk` / `ApplyDiscountBulkRequest`, all readable without the screenshots:
1. **No contractor scoping.** Criteria mode filters on `year`, `status`, `source` only. «خصم جماعي بمعايير» with year=2026 hits *every* contractor's 2026 dues; the reporter compared the result against the 2 they had just created. This alone explains 6 ≠ 2.
2. **An empty criteria object matches everything.** `criteria` is validated `required_if:mode,criteria|array` — `{}` passes, every `if (! empty(...))` is skipped, and `$query` stays unfiltered. A 100% discount then silently zeroes every due in the system. This is the most serious item in the batch; treat it as data-loss-grade.
3. **The preview does not use the real computation.** The dry run computes `original − value`, but `ContractorDue::applyDiscount()` **accumulates** when the incoming discount is the same type as the stored one (`$value += $this->discount_value`). On any already-discounted due the applied impact exceeds the previewed impact.
4. **`matched_count` counts dues that will be skipped.** Fully-`paid` dues, and any due where the discount would push `amount_jod` below `paid_jod`, throw `InvalidArgumentException` and land in `skipped` on the real run — but the dry run reports them as matched *and* adds their full impact to the total. `ApplyDiscountBulkRequest` even permits `criteria.status = paid`, which can only ever produce skips.
Defects 3 and 4 are the likeliest reason the impact figure is wrong by a different factor than the count (600/150 = 4, not 3). Confirming the arithmetic needs the screenshots.

**#11 — Modal does not close.** An audit of the dues page's dialogs: `payDialog`, `dueDialog`, `calcDialog`, `selectedDiscountDialog` and `criteriaDiscountDialog` all close on success and call `refreshAll()` (the last one carries a comment recording an earlier round of this same complaint). Two do not: **`bulkGenDialog`** («توليد رسوم للكل») stays open after a real run and only refreshes when `created_count > 0`, so a run that creates nothing shows no message at all; and **`importDialog`** stays open after a non-dry-run import. Note there is **no settle UI at all** — `POST dashboard/dues/{due}/settle` has zero frontend consumers — so if «تنفيذ ذمة» meant settlement rather than saving, this is a missing feature, not a modal bug. Worth confirming with the reporter which screen they were on. On the penalties page both dialogs close correctly but always re-fetch page 1.

### Implementation steps

**Phase 0 — establish what is live (blocks #1, #2, #3)**
1. Run the five checks in the headline table. Record the answers in the task file before writing any code for #1–#3.

**Server (carried forward from TASK-16 Phase 2 — #1)**
2. `upload_max_filesize = 12M`, `post_max_size = 60M`, `max_file_uploads = 30` in the PHP-FPM ini; nginx `client_max_body_size` 20M → 64M; reload both; verify with `php -i` and a `GET /api/v1/tenders-public` smoke test; document in CLAUDE.md as deploy-invisible state.

**Backend**
3. Add `id_file` to the edit page's document set and `authorized_signature` to the admin `FILE_FIELDS` map, so every document the system stores can be previewed, replaced and removed from one place (#2).
4. `StoreContractorDueRequest`: `due_date` → `after_or_equal:today`, bypassed by an explicit `allow_backdate` flag; record the backdate and its reason in the finance log and the due's notes. No migration. Leave `LegacyDuesImporter` untouched (#7).
5. `PenaltyController::store`: accept `status`, `paid_amount` (required with `partially_paid`, must be `< amount`) and `reject_reason`, reusing `updateStatus`'s transition mapping — extract it to one place rather than duplicating the `paid → paid_amount = amount, paid_at = now()` logic (#8).
6. `DuesDiscountService::applyBulk` + `ApplyDiscountBulkRequest` (#10): add `criteria.contractor_ids`; reject an empty criteria object; drive the dry-run impact through the same code path as the real apply so accumulation is reflected; return `applicable_count` and a `skipped` list from the dry run so the preview and the outcome agree.
7. `CertificateRequestController::store` (#5): permit submission when the only blocker is `dues_below_threshold` *and* a pending `membership_fee` payment covers the gap; mark the row so the panel can distinguish it, and keep `approve`/`issue` blocked until the payment is confirmed. Prefer an additive column over widening the `status` enum — check the `certificate_requests` migration before choosing.
8. Route company-profile edits through the approval queue (#6): widen `ALLOWED_FIELDS`, give `profile_update_requests` somewhere to carry staged document uploads (migration), make `updateFullProfile` and `updateProfile` file a request instead of writing, reconcile the two phone-OTP paths, and make `approve()` apply staged files safely.

**Frontend**
9. Rebuild the export's classification columns from `specialties[]` and resolve every code to its Arabic label through the `specialties-catalog` resolvers the preview dialog already uses; keep `trade` as its own column for the legacy rows (#4).
10. Re-run the create-form-vs-dialog field diff and close whatever it finds (#3).
11. `min` on the dues dialog's due-date picker plus the backdate checkbox (#7); status select in the penalty add dialog (#8); contractor picker and a "this will apply to N dues across M contractors" confirmation in the criteria-discount dialog (#10).
12. Fix `penalties.vue`'s response-shape read (`items`/`meta`) and wire its pagination (#8).
13. Close `bulkGenDialog` and `importDialog` on a successful real run, and always flash an outcome — including "0 created" (#11).
14. Add the missing `onError` to `deleteDueMutation`, and give the dues screen a flat all-dues view with filters and a visible total so the summary cards and the list can be reconciled (#9).

### Files involved

| # | Files | Layer |
|---|---|---|
| 1 | VPS PHP-FPM ini, nginx site config, `CLAUDE.md` | Server |
| 2 | `ContractorController.php`, `contractors/edit/[id].vue` | Both |
| 3 | `pages/contractors/index.vue` | Frontend |
| 4 | `pages/contractors/index.vue` | Frontend |
| 5 | `CertificateRequestController.php`, `CertificateEligibilityService.php`, migration (TBD), `certificate-requests.vue` | Both |
| 6 | `ContractorAuthController.php`, `ProfileUpdateRequest.php`, `ProfileUpdateRequestController.php`, new migration, `Contractor_App_API.postman_collection.json` | Backend |
| 7 | `StoreContractorDueRequest.php`, `ContractorDueController.php`, `dues/index.vue` | Both |
| 8 | `PenaltyController.php`, `contractors/penalties.vue` | Both |
| 9 | `dues/index.vue`, `ContractorDueController.php` | Both |
| 10 | `DuesDiscountService.php`, `ApplyDiscountBulkRequest.php`, `dues/index.vue` | Both |
| 11 | `dues/index.vue`, `contractors/penalties.vue` | Frontend |

**Database changes**: one certain (#6 — somewhere to carry staged document uploads on a profile-update request) and one likely (#5 — a payment-pending marker on `certificate_requests`; check the existing `status` enum first). Everything else needs none: #7's backdate override is a request flag recorded in notes/logs, and the four penalty statuses already migrated in TASK-06.

**Dependencies**: Phase 0 blocks #1, #2 and #3. Step 2 blocks verification of #1. Step 12 blocks UI verification of #8. #4, #5, #6, #7, #9, #10, #11 are mutually independent. #9 and #10 both touch the dues page and #10's server work underpins #9's explanation, so run them in series.

**Suggested order**: **#10 defect 2 first** — an empty criteria object discounting every due in the database is the only item here that can destroy data, and the guard is a few lines. Then Phase 0, since three sub-issues may resolve to "redeploy" and finding that out early keeps them out of the code budget. Then #7, #8 and #11 (small, self-contained, immediate relief on the finance screen), then #4, then #9, then #5. #6 last: it is the largest, needs a migration, and changes a contract the mobile app is already using.

**Tests required**: bulk-discount scoping and empty-criteria rejection, plus a dry-run-equals-apply assertion covering an already-discounted due (#10). `due_date` in the past rejected without the flag and accepted with it (#7). Penalty created directly as `partially_paid` with a valid `paid_amount`, and rejected when `paid_amount >= amount` (#8). Summary returns zeros after every due is deleted — pins #9's soft-delete path for good. Certificate request accepted while a membership-fee payment is pending, and `approve`/`issue` refused until it is confirmed (#5). Profile-update request created instead of a direct write, for both the text and document paths, with the contractor record unchanged until approval (#6).

**Risk level**: High, concentrated in three places. #10 defect 2 is live data loss waiting to happen. #6 changes an endpoint the Flutter app is in production against, and its migration touches a table that already has rows. #5 relaxes a financial eligibility gate — the relaxation must not let a certificate *issue* before money is confirmed, only let the request become visible. #2's `id_file` addition and step 2's server change are both low risk.

**Estimated complexity**: Medium-high. #6 is the bulk of it. #1 is minutes of server work. #7, #8, #11 are small. #10 is small in code and large in consequence.

**Acceptance criteria**: 🔲 A 9 MB PDF saves, and a 13-document submit fits in one request. 🔲 Every document the system stores — `id_file` included — can be previewed, replaced and deleted from the edit screen. 🔲 The preview dialog matches the add form field-for-field on a freshly created contractor. 🔲 The exported sheet shows المجال/التخصص/الدرجة and التصنيف العام as Arabic labels for a contractor added today. 🔲 A membership-certificate request submitted from the app while its fee payment is pending appears in «طلبات الشهادات» with its payment status visible, and cannot be issued until the payment is confirmed. 🔲 A company-profile edit from the app leaves the contractor record unchanged and appears in «طلبات تعديل البيانات», documents included. 🔲 A past due date is refused unless the backdate override is set. 🔲 A penalty can be registered directly in any of the four statuses, and the penalties list actually renders rows. 🔲 A criteria bulk discount cannot run without a criterion, is scopable to chosen contractors, and previews the same count and impact it then applies. 🔲 Deleting every due leaves the summary cards at 0, and a failed delete says so. 🔲 Every dues and penalty dialog closes on success and shows an outcome. 🔲 Post-deploy verification confirms each fix on staging — not merely "tests green".

**Task breakdown**: granular, executable task list in [TASK-17-tasks.md](TASK-17-tasks.md) — 80 tasks in 14 phases. The live-state checks are Phase 3 there rather than a blocking Phase 0, since the deploy evidence below moved US7/US8 from "probably a stale deploy" to "probably real code work".

### Deploy-history evidence (added 2026-09-24, after reading the Actions log)

Phase 0's framing above was written before the workflow history was checked. It shifts the odds, though it does not remove the need for the checks:

- Run **#17** (`e8b7da8`, 2026-09-22 11:37) was itself the commit that *unblocked* the frontend build. Its message records that `vite build` had been failing since the app-notifications feature landed, so the server kept restoring the last good `dist/` (Sep 21) and **none of the recent frontend work was ever served**.
- TASK-16's frontend commits (`61f5b71`, `8dfa91c`) are **newer** than that fix, and run **#18** (`927b12a`, 2026-09-23 15:45) carried them and concluded success.
- This feedback batch is dated **2026-09-24**, i.e. after that deploy.

So TASK-16's frontend work was most likely live when the reporter tested, which makes #2 and #3 **genuine remaining gaps rather than a stale deploy** — and that is exactly what the `id_file` finding independently predicts for #2. Phase 0 is still required, because a front build failure reports success while rolling `dist/` back, so "run succeeded" is not "dist updated"; but plan on writing code for #2, not on redeploying.

### Where this plan lives, and why it is not copied to the production repos (decided 2026-09-24)

Asked to put this plan in the `PcuGaza` production repos as well. **Do not copy it.** Those repos share no git history with this monorepo, so a copy means a `commit-tree` tree-graft for a documentation file; worse, `TASK_PLAN.md` is a monorepo-root planning document while production is split into an api repo and a front repo, so a copy has no natural home in either and becomes a second source of truth that drifts after the first edit here.

Instead, each production repo should carry a short pointer file — no plan content at all, just the canonical location (`eslamalbaik/admin_dshbaord_pfi` → `TASK_PLAN.md`) and a line saying that repo holds production deploy code only. Nothing to drift, and anyone landing in a production repo finds the plan. Write it through the GitHub API rather than a clone, which sidesteps the shared-history problem entirely. Do not touch the two deliberately divergent files there (`deploy-vps.sh`, `.github/workflows/deploy-production.yml`).

**Not executed**: adding the `PcuGaza` repos to the session was refused by the permission layer (classified as a production action). Carried forward.

---
## Cross-cutting notes

- **Confirmation-dialog pattern**: Several tasks (contractor attachments, dues, tender attachments, news images, event speakers) ask for delete-confirmation dialogs. Dues already has one (`deleteDueDialog` in `dues/index.vue`) — consider extracting a shared `ConfirmDeleteDialog` component to apply consistently across TASK-01, 05, 07, 10, 11 instead of one-off implementations.
- **"Save succeeded but UI shows failure" pattern**: The dues (TASK-05) and possibly penalties (TASK-06) bugs share a shape — mutation succeeds server-side, but the response the frontend consumes doesn't hydrate a relation (`contractor`) that the UI depends on. Worth auditing all `*Resource` classes used immediately after a `store()` for missing `->load()` calls before this pattern repeats elsewhere.
- **Specialties/Fields/Grades CRUD (TASK-01 #7)** is the single largest net-new subsystem in this list — recommend scoping and estimating it as its own project phase rather than folding into the general contractor-edit bugfix task.
- ~~**Public-facing site dependencies (TASK-10 #4, #8)**~~ — resolved: the public site (`landing/`) is part of this monorepo, not a separate app; see TASK-10's execution summary.
- ~~Two rows (Membership Request, Payment History) are marked "(Disabled)" with empty detail columns — no action possible until the user/stakeholder clarifies intended scope.~~ — both have since been clarified and reversed: Payment History in TASK-04 (2026-09-20), Membership Request in TASK-16 #6 (2026-09-22). **Pattern worth noting: hiding a working feature behind a commented-out nav link produced a bug report ~3 days later in both cases.** Prefer an explicit disabled state or a feature flag over silently removing the only entry point.
- **"Works in the API, invisible in the panel" pattern**: TASK-16 #6 and #7 are both fully-built backends with no reachable frontend — one had its nav link removed, the other never had a page at all. When auditing a "nothing shows in the dashboard" report, check for a *missing consumer* before debugging the query; `grep -rn "<endpoint>" arab-contractors-union-front/resources/ts/` returning zero hits is the fastest discriminator.
- **Two contractor profile-edit paths exist and behave differently** — the Vue portal posts to `contractor/auth/profile/update` (direct write, no review), the Flutter app posts to `contractor/profile-update-requests` (pending approval queue). TASK-03 examined only the first and wrongly dismissed the second. Always confirm *which client* a contractor-side report came from before tracing the endpoint. **Corrected 2026-09-24 (TASK-17 #6)**: the app in fact uses the *direct* path — a company-profile edit made from it updated the contractor record with no request row — so the second half of this note was wrong too. TASK-17 closes both direct paths instead.
- **"Code complete" is not "fixed" on this project** — TASK-16 closed five sub-issues; four came back three days later in TASK-17. One (#1/#3) was genuinely incomplete: its server prerequisite was left unchecked while the code half was marked done, and the code half is worthless without it. The others may simply not be live: a frontend build failure restores the previous `dist/` while the workflow reports success, and `git log` inside `/var/www/pcuorg/{api,front}` is vestigial. **Definition of done should include a post-deploy check against the live environment, not just a green test suite** — and a task should not be recorded as done while a prerequisite step in its own breakdown is unchecked.
- **Verify which endpoint a contractor-app report came from before diagnosing it** — TASK-16 got this wrong twice in the same batch, in opposite directions: #7 assumed the app used the approval queue when it used the direct write (TASK-17 #6), and #6 assumed a restored nav link exposed real rows when that page reads a table nothing in the app writes to (TASK-17 #5). "The backend exists and the panel shows nothing" has at least three distinct causes — no consumer, no producer, and a gate that refuses the write — and they need different fixes.
- **Response-shape drift between `ApiResponseTrait` and the frontend** — `paginated()` emits `{items, meta}`, but pages keep reading `data.data`/`data.total` (`penalties.vue` is the third found doing it, and there it leaves a non-array in `:items` so the table renders nothing). Worth a one-pass sweep of every `api.get` against a paginated endpoint, or a typed helper that makes the shape impossible to get wrong.
