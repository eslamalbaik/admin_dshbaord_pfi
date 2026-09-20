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

## TASK-02 — Membership Request (Disabled) — ✅ STATUS: DONE

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

## TASK-07 — Tenders (العطاءات)

**Description**: Deadline validation gaps, missing time-of-day for deadline, attachment UX issues (form doesn't close, attachment lost on save/edit), no delete confirmation on tender attachments, tender detail view disables downloads, and no category-based default images.

**Current implementation** (see [TenderController.php](arab-contractors-union-api/app/Http/Controllers/Api/TenderController.php), [Tender.php](arab-contractors-union-api/app/Models/Tender.php), [tenders/index.vue](arab-contractors-union-front/resources/ts/pages/tenders/index.vue))
- Deadline: `deadline` cast to `date`, validated `nullable|date` with no lower-bound — allows a deadline before "yesterday relative to add-date" per the sheet's complaint (#1), and stores no time component (#2) — column is `date`, not `datetime`.
- Multiple attachments: model already supports this via `TenderAttachment` hasMany + upload endpoint (lines 336-356) — sheet complaint #3 may be about the UI not exposing multi-file selection correctly, or about the *create* form specifically vs. edit.
- Form-close-after-save: create dialog does close (`index.vue` line ~376) per current code — sheet issue #4 may be stale, or specific to the edit flow / to the attachment being lost when combined with save.
- Attachment deletion confirmation: sheet wants a confirm dialog (#5); current code does immediate delete with just a loading spinner, no confirm.
- Tender preview/download (#6): dashboard preview shows attachments as disabled (`disabled` file chips) instead of clickable download/preview links.
- Default category image (#7): `CATEGORIES` constant defines categories but no per-category image mapping exists.

**Files involved**: `TenderController.php`, `Tender.php`, `TenderAttachment` model, `tenders/index.vue`, routes `api.php` (~159-160 public, ~487-489 admin).

**Dependencies**: None blocking; #7 is a separate content feature (needs image asset management).

**Implementation steps**
1. Change deadline column to `datetime` (migration) and add time-of-day picker to the tender form; add validation `after_or_equal:today` (or `after:now` once combined datetime) on submission date.
2. Verify multi-attachment upload in the create flow specifically (not just edit) — add multiple-file input if create dialog currently only supports one.
3. Fix attachment loss on save/edit — likely the staged-attachment array isn't included in the same submit payload as the tender fields, or is cleared on dialog re-open; trace `newAttachmentStaged` handling around save.
4. Add a confirmation `VDialog` before `removeAttachment()` executes.
5. In tender preview, replace disabled file chips with functional download/open-in-new-tab links (attachments are presumably stored under `storage/app/public/tenders` and already public-accessible).
6. Add a `category_images` mapping (settings table or config) so tenders without a custom image fall back to a category default; allow admin to manage per-category images from a settings page.

**Database changes**: `deadline` column type change to `datetime`; possibly new `tender_category_images` table for step 6.

**Frontend changes**: `tenders/index.vue` — datetime picker, multi-attachment fix, attachment delete confirmation, enabled download links.

**Backend changes**: `TenderController` validation (`after_or_equal:today`), migration for datetime column, optional new controller for category images.

**Tests required**: Feature test — deadline in the past rejected; attachment persists through an update request that doesn't touch attachments.

**Risk level**: Medium.

**Estimated complexity**: Medium.

**Acceptance criteria**: Deadline requires date+time no earlier than now; multiple attachments can be added on create; editing a tender never silently drops existing attachments; deleting an attachment requires confirmation; admins/public can open/download tender attachments from the preview; tenders without an uploaded image show a sensible category default.

---

## TASK-08 — Equipment Marketplace (سوق المعدات)

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

---

## TASK-09 — Equipment Type

**Description**: An equipment type marked hidden still appears in the "add equipment" type dropdown.

**Current implementation**: `EquipmentType.is_active` flag exists; `EquipmentTypeController::index` supports `active_only=1` filtering (lines 17-19) — but this filter is opt-in via query param, so if the equipment-add form doesn't pass `active_only=1` when fetching the dropdown options, hidden types leak through.

**Files involved**: `EquipmentTypeController.php`, `EquipmentType.php`, equipment add/edit form's type-select data fetch.

**Dependencies**: Should be verified alongside TASK-08's equipment-type-in-edit-form work.

**Implementation steps**
1. Confirm the equipment-add/edit form's fetch call for type options — add `?active_only=1` if missing.

**Database changes**: None.

**Frontend changes**: One query-param fix in the equipment form's type-fetch call.

**Backend changes**: None (filter already exists) unless it's found to be buggy on inspection.

**Tests required**: Manual/QA check — hide a type, confirm it disappears from the add-equipment dropdown.

**Risk level**: Low.

**Estimated complexity**: Trivial.

**Acceptance criteria**: Hidden equipment types never appear in the equipment-add type dropdown.

---

## TASK-10 — Add News (الأخبار)

**Description**: Unclear news categorization purpose, unnecessary short-title field, multi-image restriction on add/edit, broken link rendering in the public app, main image vs gallery image mismatch, main image missing on edit reload, unclear image-deletion flow, and gallery image deletion not propagating to the public app.

**Current implementation** (see [News.php](arab-contractors-union-api/app/Models/News.php), [NewsController.php](arab-contractors-union-api/app/Http/Controllers/Api/NewsController.php), [news/index.vue](arab-contractors-union-front/resources/ts/pages/news/index.vue))
- `category` field currently hardcoded/forced to `'news'` server-side — sheet questions why a category exists at all since tenders are already a separate content type (#1); likely dead/vestigial field, candidate for removal or repurposing.
- Short title (#2) exists in the form; sheet wants it removed in favor of just the main title.
- Multi-image restriction (#3): gallery already supports multiple images per the model (`gallery` array field) — sheet's complaint likely means the *main* image field incorrectly allows multiple, or the UI doesn't make clear only the main image is single.
- Links not rendering in public app (#4) — need to check how `body`/`content` HTML or a dedicated `links` field is stored and whether the public-facing frontend (separate app, out of this monorepo) sanitizes/strips them; may be an API-shape gap (`links` not included in the public resource) rather than a dashboard bug.
- Main/gallery image swap bug (#5): both are uploaded together but the wrong one displays as "main" in the public app — check `NewsResource`/public serialization for which array index or field name is used as the hero image.
- Main image not shown when reopening edit (#6): edit form fetch likely doesn't map the `image` response field back into the file/preview input.
- Unclear deletion flow (#7): UX-only — needs a clearer icon/label/confirmation, not a functional bug on its own (ties into #8 below where it's used for gallery).
- Deleted gallery image still shows in public app (#8) — `NewsController` gallery-delete (lines 146-162) does delete the physical file and update DB; if the public app still shows it, that's on the separate public frontend possibly caching the old `gallery` array or serving a stale CDN copy — needs cross-checking with whoever owns the public site, since it's outside this monorepo.

**Files involved**: `News.php`, `NewsController.php`, `news/index.vue`.

**Dependencies**: #4 and #8 depend on inspecting the separate public-facing site, which is not part of this monorepo — flag as needing access/coordination.

**Implementation steps**
1. Confirm with stakeholder whether `category` should be removed entirely from news (since tenders already split out) — if yes, drop from form + hide/remove column.
2. Remove short-title field from create/edit forms and DB usage if confirmed unnecessary.
3. Ensure only the gallery input allows `multiple`; main image input stays single-file.
4. Investigate `links` handling: check whether the field exists on `News` model/migration and whether it's included in the resource served to the public frontend.
5. Fix main-vs-gallery hero-image bug in `NewsResource` / whatever serializer the public site consumes — ensure `image` is always the designated main image.
6. Fix edit form to prefill main image preview from the fetched record.
7. Improve delete-image affordance with clear icon + confirmation dialog (shared pattern with tender/other-attachment confirmations).
8. Trace gallery-image deletion end-to-end to public site (verify no caching layer, CDN, or separate table holds a stale copy).

**Database changes**: Possibly drop `category`/short-title columns if confirmed obsolete (only after stakeholder sign-off — don't drop data blindly).

**Frontend changes**: `news/index.vue` — form field cleanup, main-image prefill fix, delete confirmation, multiple-only-on-gallery fix.

**Backend changes**: `NewsController`/`News.php`/resource — hero-image serialization fix, links field investigation.

**Tests required**: Feature test — creating news with both main + gallery images: assert `image` field in public resource matches the uploaded main image, not a gallery image.

**Risk level**: Medium.

**Estimated complexity**: Medium (several small bugs, one cross-system investigation for #4/#8).

**Acceptance criteria**: Main image always displays correctly and persists visibly on re-edit; gallery accepts multiple images, main image accepts one; deleting a gallery image removes it everywhere including the public app; links added to a news item render correctly on the public site; category/short-title resolved per stakeholder decision.

---

## TASK-11 — Add Event (الفعاليات)

**Description**: Unwanted short-title field, event-type selection needs to be a strict 3-option select, venue field should be conditional on in-person attendance, single main image only (no gallery), speaker photo should be a file upload not a URL field, only one speaker should be markable as "main/keynote", and a default-keynote bug when adding a second speaker.

**Current implementation** (see [Event.php](arab-contractors-union-api/app/Models/Event.php), [EventController.php](arab-contractors-union-api/app/Http/Controllers/Api/EventController.php), [events/index.vue](arab-contractors-union-front/resources/ts/pages/events/index.vue))
- `EVENT_TYPES` enum (`international|institutional|local`) already exists (#2 — already implemented, verify frontend renders it as a hard select not free text).
- `event_format` (onsite/online/hybrid) with venue conditionally required via `required_if:event_format,onsite` already implemented (#3 — appears done).
- Main image is already a single file field (#4 — appears done); sheet may be describing a state before this was built, needs verification against current UI.
- Speaker photo: `mergeSpeakerPhotosAndEnforceSingleKeynote()` already handles file-based speaker photo upload (#5 — appears the URL-field complaint predates the current file-upload implementation; verify frontend uses `VFileInput`, not a text URL field, per the earlier exploration which found `photoFile` input at index.vue line ~507).
- Keynote enforcement: `EventController` already enforces only one `is_keynote=true` at a time (lines 238-248) — "only last true wins" logic exists (#6 — appears solved).
- Second-speaker default (#7): `addSpeaker()` pushes `is_keynote:false` by default (index.vue line 89) — sheet's complaint about a second speaker defaulting to keynote may be a regression or edge case (e.g., cloning the first speaker's object by reference instead of a fresh object) — needs reproduction.
- Delete-speaker confirmation (#8): not confirmed present — needs a `VDialog` guard like other delete flows in this codebase.

**Files involved**: `Event.php`, `EventController.php`, `events/index.vue`.

**Dependencies**: None.

**Implementation steps**
1. Verify current behavior against each sheet item live in the dashboard — several items (#2, #3, #4, #5, #6) appear to already be implemented based on code inspection; this task may be largely a verification/regression pass rather than new work.
2. Remove short-title field if still present in the form (#1).
3. Reproduce #7: check `addSpeaker()` for object-reference bugs (e.g., `{...speakers[0]}` spread vs. a fresh literal) that could carry over `is_keynote: true` from a previous speaker.
4. Add confirmation dialog before removing a speaker (#8).

**Database changes**: None expected.

**Frontend changes**: `events/index.vue` — remove short-title, fix speaker-add default bug, add delete-speaker confirmation.

**Backend changes**: None expected unless verification in step 1 surfaces a real gap.

**Tests required**: Component/manual test — add 2nd, 3rd speaker, confirm none default to keynote unless explicitly toggled.

**Risk level**: Low (mostly verification + one small bug).

**Estimated complexity**: Small.

**Acceptance criteria**: Short title removed; adding any speaker after the first never auto-marks them as keynote; removing a speaker requires confirmation; all previously-implemented constraints (event type enum, conditional venue, single main image, file-based speaker photo, single keynote) verified still correct in the live UI.

---

## TASK-12 — Announcement (التعاميم)

**Description**: Clarify auto-generated circular number, no image support on announcements, need admin-managed announcement categories, and scheduled-but-not-yet-published announcements incorrectly showing as "published".

**Current implementation** (see [Announcement.php](arab-contractors-union-api/app/Models/Announcement.php), [AnnouncementController.php](arab-contractors-union-api/app/Http/Controllers/Api/AnnouncementController.php), [announcements/index.vue](arab-contractors-union-front/resources/ts/pages/announcements/index.vue))
- Auto-numbering already implemented via `nextAnnouncementNumber()` (`{year}/{seq}` format) (#1 — already done, sheet may just need this explained to the reporter, not fixed).
- Image field already exists (`image`, stored as full public URL) (#2 — appears already implemented; sheet may predate this or refer to a different image use-case, e.g., no gallery).
- Category CRUD already exists via `announcement categories` routes (`api.php:333-336`) (#3 — appears already implemented).
- Scheduled-status bug (#4): `effective_status` accessor (`Announcement.php` line 37) computes `scheduled` when `is_published=true` but `published_at` is future — need to verify this computed field is actually what's rendered in the list/detail UI, versus the raw `is_published` boolean being shown instead.

**Files involved**: `Announcement.php`, `AnnouncementController.php`, `announcements/index.vue`.

**Dependencies**: None.

**Implementation steps**
1. Verify #1–#3 against the live dashboard — likely already resolved by existing code; close as "already implemented" after a quick confirmation pass, or clarify to the reporter via the number-format/category-management screen.
2. For #4: audit `announcements/index.vue`'s status-badge rendering — ensure it reads `effective_status` (draft/scheduled/published) rather than the raw `is_published` flag, so a future-dated announcement shows "Scheduled" not "Published" until `published_at` passes.

**Database changes**: None expected.

**Frontend changes**: Fix status badge to use `effective_status`.

**Backend changes**: None expected (accessor already computes correctly) unless list/index endpoint omits `effective_status` from its resource — verify and add if missing.

**Tests required**: Feature test — announcement with future `published_at` and `is_published=true` returns `effective_status = 'scheduled'` from the list endpoint.

**Risk level**: Low.

**Estimated complexity**: Small.

**Acceptance criteria**: Announcements scheduled for a future date display as "Scheduled" (not "Published") in the dashboard until that date arrives; auto-numbering, image, and category features confirmed working as already implemented.

---

## TASK-13 — Privacy Policy / Terms & Conditions

**Description**: Reordering one section in the legal-pages builder doesn't automatically re-sequence the sections after it.

**Current implementation**: `Term.php` (`sort` int field), [TermsController.php](arab-contractors-union-api/app/Http/Controllers/Api/TermsController.php) `update()` (lines 77-107) already contains auto-adjust logic for moving a section up or down (lines 85-98) — the sheet's complaint suggests either a specific edge case isn't handled (e.g., moving to the very first or last position, or moving across `type` boundaries between `terms` and `privacy`), or a regression.

**Files involved**: `TermsController.php`, `Term.php`, legal-pages frontend page (not yet located).

**Dependencies**: None.

**Implementation steps**
1. Reproduce with the exact scenario from the sheet (which section, which direction) to isolate which branch of the reorder logic (lines 87-91 vs 92-98) is failing.
2. Add a boundary/edge-case fix (e.g., off-by-one at first/last position, or filtering the adjacent-sections query by `type` to avoid cross-type interference).

**Database changes**: None expected.

**Frontend changes**: None expected unless the reorder UI sends stale sibling data.

**Backend changes**: `TermsController::update` reorder branch — targeted fix once reproduced.

**Tests required**: Feature test — reorder a middle section, assert all siblings' `sort` values shift correctly in both directions.

**Risk level**: Low.

**Estimated complexity**: Small.

**Acceptance criteria**: Changing one section's order automatically and correctly re-sequences all affected sibling sections, with no manual follow-up edits needed.

---

## Cross-cutting notes

- **Confirmation-dialog pattern**: Several tasks (contractor attachments, dues, tender attachments, news images, event speakers) ask for delete-confirmation dialogs. Dues already has one (`deleteDueDialog` in `dues/index.vue`) — consider extracting a shared `ConfirmDeleteDialog` component to apply consistently across TASK-01, 05, 07, 10, 11 instead of one-off implementations.
- **"Save succeeded but UI shows failure" pattern**: The dues (TASK-05) and possibly penalties (TASK-06) bugs share a shape — mutation succeeds server-side, but the response the frontend consumes doesn't hydrate a relation (`contractor`) that the UI depends on. Worth auditing all `*Resource` classes used immediately after a `store()` for missing `->load()` calls before this pattern repeats elsewhere.
- **Specialties/Fields/Grades CRUD (TASK-01 #7)** is the single largest net-new subsystem in this list — recommend scoping and estimating it as its own project phase rather than folding into the general contractor-edit bugfix task.
- **Public-facing site dependencies (TASK-10 #4, #8)**: some news issues may live in a separate public frontend not present in this monorepo — confirm repo/ownership before starting investigation.
- Two rows (Membership Request, Payment History) are marked "(Disabled)" with empty detail columns — no action possible until the user/stakeholder clarifies intended scope.
