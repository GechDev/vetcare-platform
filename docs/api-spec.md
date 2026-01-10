# VetCare Unified Operations Portal - API Specification

## 1) Scope
This document defines the LAN-only REST API exposed by Laravel for the Vue.js client. It covers authentication, RBAC, master data, rental assets, inventory/stock ledger, content publishing, patient visit reviews, import/export, deduplication, dashboards, and auditing.

- Base URL: `http://{onprem-host}/api/v1`
- Transport: HTTP on local network (HTTPS optional if local PKI is available)
- Data format: `application/json`
- File upload: `multipart/form-data`
- Timezone: facility-local (stored as UTC in DB)
- Currency: USD (`amount_cents` integers)

## 2) Roles and Access
Roles:
- `system_admin`
- `clinic_manager`
- `inventory_clerk`
- `technician_doctor`
- `content_editor`
- `content_approver`

Role behavior highlights:
- `system_admin`: full access, security/configuration, import/export, audits.
- `clinic_manager`: facility-scoped management, approvals, dashboards, merge confirmation.
- `inventory_clerk`: inventory receipts/issues/transfers/stocktakes.
- `technician_doctor`: rental checkout/return, service-order reservations, clinical operations.
- `content_editor`: create/edit drafts.
- `content_approver`: approve/reject/publish/rollback content.

## 3) Security Requirements (API)
- Auth: username/password, minimum 12 chars.
- Password storage: salted hash (Laravel Argon2id/Bcrypt).
- Session timeout: configurable; default 15 minutes inactivity.
- Login rate limit: 10 attempts / 10 minutes / workstation.
- CAPTCHA challenge required after 5 failed attempts.
- CSRF protection for state-changing requests when cookie auth is used.
- Parameterized queries only.
- Output encoding at client and server templates to reduce XSS risk.
- Sensitive-field masking for non-admin views (example owner phone).
- Audit retention: 7 years for auth, edits, exports, approvals, configs.

## 4) Common Conventions

### 4.1 Headers
- `Authorization: Bearer <token>` (or session cookie)
- `X-Workstation-Id: <stable-local-id>` for rate limiting and audit context
- `X-CSRF-TOKEN: <token>` for cookie-based auth
- `X-Request-Id: <uuid>` optional, echoed in response

### 4.2 Pagination
Request query:
- `page` (default `1`)
- `per_page` (default `20`, max `200`)

Response metadata:
```json
{
  "data": [],
  "meta": { "page": 1, "per_page": 20, "total": 120, "pages": 6 }
}
```

### 4.3 Error Format
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "One or more fields are invalid.",
    "details": [
      { "field": "password", "rule": "min:12", "message": "Password must be at least 12 characters." }
    ]
  },
  "request_id": "2f0c3f1e-8f5c-4b57-9f90-c8b0ec4f8d19"
}
```

### 4.4 Idempotency
For import and selected POST writes:
- `Idempotency-Key: <uuid>` supported.
- If same key + same actor + same endpoint is replayed, return first result.

### 4.5 Data Validation Rules
Common validation constraints across all entities:
- **Usernames**: alphanumeric + underscore, 3-50 chars, case-insensitive matching
- **Passwords**: minimum 12 characters (uppercase, lowercase, digit, symbol required)
- **Emails**: RFC 5322 format; case-insensitive matching
- **Phone numbers**: E.164 format with country code (e.g., +1-612-555-0100)
- **Currency amounts**: positive integer cents; range 0 to 9,999,999,999 (max USD 99,999,999.99)
- **Dates**: ISO 8601 format (YYYY-MM-DD)
- **DateTimes**: ISO 8601 with timezone (e.g., 2026-03-15T14:30:00Z or 2026-03-15T14:30:00-05:00)
- **QR/Barcode**: alphanumeric + symbols; max 255 chars; unique per facility
- **External keys**: alphanumeric + underscore/dash; max 128 chars; unique per entity type
- **Text fields**: UTF-8 encoded; max specified length; no null bytes

### 4.6 Response Status Codes
| Code | Meaning | Example Scenario |
|------|---------|------------------|
| 200 | Success | GET, PATCH with valid data |
| 201 | Created | POST new entity |
| 204 | No Content | DELETE, logout |
| 400 | Bad Request | Malformed JSON, missing required fields |
| 401 | Unauthorized | Invalid token, expired session |
| 403 | Forbidden | Insufficient permissions, facility scope mismatch |
| 404 | Not Found | Entity does not exist |
| 409 | Conflict | Optimistic lock failure (ETag mismatch), duplicate external_key |
| 410 | Gone | Entity soft-deleted |
| 422 | Unprocessable Entity | Validation failed (see error details) |
| 429 | Too Many Requests | Rate limit exceeded (login attempts, API quota) |
| 500 | Internal Server Error | Unexpected backend error |
| 503 | Service Unavailable | Database maintenance, temporary outage |

## 5) Authentication and Session APIs

### POST `/auth/login`
Authenticate local user.

Request:
```json
{
  "username": "j.smith",
  "password": "<SECRET>",
  "captcha_token": "optional-until-required"
}
```

Response `200`:
```json
{
  "token": "jwt-or-sanctum-token",
  "expires_in_seconds": 900,
  "user": {
    "id": 12,
    "username": "j.smith",
    "display_name": "John Smith",
    "roles": ["clinic_manager"],
    "facility_ids": [1, 2]
  },
  "security": { "captcha_required": false }
}
```

Possible errors: `401`, `429`.

### POST `/auth/logout`
Invalidate current token/session. Returns `204`.

### GET `/auth/me`
Returns user profile, effective permissions, facility scope.

### POST `/auth/password/change`
Change own password with policy validation.

## 6) Master Data and Unified Model APIs

### Entities
- Facilities, departments, providers, services, service pricing, business hours, addresses.
- Unified canonical schema with source metadata.

### GET `/master/{entity}`
List entity records with filters (`facility_id`, `updated_since`, `status`).

### POST `/master/{entity}`
Create or upsert record (supports `external_key` idempotent behavior).

### PATCH `/master/{entity}/{id}`
Partial update with version check via `If-Match` (ETag/version).

### GET `/master/{entity}/{id}/versions`
Returns who/when/what history and reversible snapshots.

### POST `/master/{entity}/{id}/revert`
Revert to prior version (manager/admin only).

## 8) Inventory and Stock Ledger APIs

### Data Model (key fields)
- `sku`, `barcode`, `lot_number`
- `description`, `unit_of_measure` (each, box, case, pallet)
- `category`, `subcategory`
- `facility_id`, `storeroom_id`
- `current_quantity`, `on_hand`, `available_to_promise` (ATP)
- `unit_cost_cents`, `reorder_level`, `safety_stock_days`
- `expiration_date`, `last_received_date`, `last_issued_date`

### Stock Movement Types
All inventory writes post immutable ledger entries:
- **inbound**: Receipt from supplier or transfer-in
- **outbound**: Issue to department or transfer-out
- **transfer**: Inter-storeroom movement
- **adjustment**: Stocktake variance or correction
- **reservation**: Reserved for pending order (reversible)

### GET `/inventory/items`
List inventory by facility and storeroom. Filters:
- `facility_id`, `storeroom_id` (required)
- `category`, `status` (active, low-stock, discontinued)
- `updated_since`, `expires_before`

### POST `/inventory/receive`
Create inbound movement. Captures:
- PO reference, supplier, received_date, received_by
- Line items: sku, quantity, unit_cost_cents, lot_number, expiration_date
- Validations: sku exists, facility match, quantity > 0

### POST `/inventory/issue`
Create outbound movement. Captures:
- Department/cost center, issued_date, issued_by
- Line items: sku, quantity (debits ATP)
- Validations: ATP >= requested quantity, no expired lots

### POST `/inventory/stocktake`
Create adjustment based on physical count. Captures:
- Storeroom, counted_date, counted_by, manager_approver_id
- Line items: sku, expected_qty, actual_qty, variance_reason
- Validation: variance > 5% requires manager approval and mandatory reason
- Post-approval: variance entries written to audit and ledger

### GET `/inventory/ledger`
Query immutable stock ledger. Filters:
- `facility_id`, `storeroom_id`, `sku`, `date_range`
- Returns: date, movement_type, quantity, unit_cost, balance_after, posted_by

## 9) Content Publishing and Approval Workflow APIs

### Data Model (key fields)
- `id`, `type` (announcement, carousel, alert)
- `title`, `body` (markdown supported)
- `media_urls` (images, videos)
- `publish_from_date`, `publish_until_date`
- `target_audience` (facility_ids[], department_ids[], role_ids[], tag_names[])
- `status` (draft, pending_approval, approved, published, archived)
- `created_by`, `approved_by`, `published_by`
- `version`, `etag` (for optimistic lock)

### Workflow States
- **Draft**: Content editor creates/edits
- **Pending Approval**: Editor submits for review
- **Approved**: Content approver signs off
- **Published**: Auto-scheduled or manual publication
- **Archived**: End-of-life, retained for audit

### GET `/content`
List content filtered by:
- `status`, `type`, `facility_id`
- `created_by`, `approved_by`
- Returns: paginated list with version/etag for each item

### POST `/content`
Create new content draft.
- Validations: title required (3-255 chars), body max 10,000 chars
- Returns: 201 with location header

### PATCH `/content/{id}`
Update draft or pending content. Must include `If-Match: <etag>`.
- Only drafts can be edited by creator
- Editing pending content is forbidden

### POST `/content/{id}/submit`
Transition draft → pending_approval. Requires content_editor+ role.
- Validations: required fields populated, media uploaded

### POST `/content/{id}/approve`
Approve and transition pending → approved. Requires content_approver role.
- Returns approval metadata: approver_id, approved_at

### POST `/content/{id}/publish`
Publish approved content (manual). Requires content_approver role.
- Validations: must be in approved state
- Updates published_at, published_by

### POST `/content/{id}/revert`
Revert published content to draft. Audit-logged.
- Requires system_admin or original_creator + content_approver

### GET `/content/{id}/approvals`
Return approval history and sign-offs for audit trail.

## 10) Patient Visit Reviews and Feedback APIs

### Data Model (key fields)
- `visit_id`, `facility_id`, `patient_id_hash` (PII masking)
- `visit_date`, `provider_id`
- `service_type`, `rating` (1-5)
- `review_text`, `follow_up_needed`
- `reviewed_by`, `reviewed_at`
- `admin_response`, `admin_response_by`, `response_date`
- `status` (submitted, manager_review, responded, closed, appeal)

### GET `/reviews`
Query reviews filtered by facility, date range, rating, status.
- Clinic managers see facility-scoped reviews
- System admins see all reviews

### POST `/reviews/{id}/respond`
Management response to review. Captures:
- response_text (max 1000 chars)
- follow_up_actions (optional)
- Validations: status must be manager_review

### POST `/reviews/{id}/appeal`
Patient appeal workflow. Requires identity verification.
- Flags for escalation to clinic manager

### GET `/reviews/dashboard`
Aggregated metrics: avg rating, review count, response rate, trends by provider.

## 11) Import, Export, and Deduplication APIs

### POST `/import/{entity}`
Bulk upsert records via CSV or JSON.
- Required: external_key column for idempotent matching
- Row-level validation; individual row failures do not abort entire import
- Returns: import_id, total_rows, success_count, failure_count, warnings
- Audit logged with file hash and summary

### POST `/import/{import_id}/validate`
Pre-import validation without committing.
- Returns preview of changes: created count, updated count, errors

### GET `/import/{import_id}/results`
Detailed results including per-row error logs.

### POST `/export/{entity}`
Export filtered records as CSV or JSON.
- Filters: facility_id, date_range, status, etc.
- Request: format (csv, json), fields (optional column subset)
- Response: file download with checksum metadata
- Audit logged with count and actor

### POST `/dedup/rules`
Define deduplication matching rules (admin only).
- Rule format: entity type + field matching criteria
- Example: match providers by (last_name, first_name, dob_month_year)
- Returns: potential duplicates for manual review

### POST `/dedup/merge`
Merge two entities (e.g., duplicate patient records).
- Captures: primary_id, secondary_id, merge_by_user
- Post-merge: secondary entity soft-deleted, references updated, versioned
- Fully audit-logged with reversible snapshots

## 12) Audit and Compliance APIs

### GET `/audits`
Query immutable audit log.
- Filters: entity_id, entity_type, action (create, update, delete, export, login, approve), date_range, actor
- Returns: timestamp, actor (user_id, workstation_id), entity changes (before/after snapshots), reason
- Pagination: max 500 per request
- Retention: 7 years per policy

### GET `/audits/export`
Export audit report in compliance-ready format (CSV, PDF).
- Filters: entity_type, date_range, specific users
- Returns: detailed log with all fields, signatures

### GET `/audits/dashboard`
Audit metrics: recent activity, login trends, export summary, approval counts.

## 13) Health and System APIs

### GET `/health`
Service health check. Returns `200` with service metadata.

Response:
```json
{
  "service": "vetcare-backend",
  "status": "healthy",
  "version": "1.0.0",
  "database": "connected",
  "cache": "operational",
  "timestamp": "2026-03-15T14:30:00Z"
}
```

### GET `/config/public`
Return non-sensitive application configuration (roles, features, constants).
- Client uses this to initialize UI state and feature flags
- `status`: `available|rented|maintenance|deactivated|overdue`
- Pricing: `daily_rate_cents`, `weekly_rate_cents`, `deposit_cents`

### GET `/rentals/assets`
Search/filter asset ledger.

Filters:
- `q` (name/code)
- `status`
- `facility_id`
- `category`
- `scan_code` (QR/barcode direct lookup)

### POST `/rentals/assets`
Create asset. Default deposit:
- `max(20% of replacement_cost, $50.00)`

### PATCH `/rentals/assets/{id}`
Update asset metadata/status.

### POST `/rentals/checkouts`
Checkout with conflict prevention.

Request:
```json
{
  "asset_id": 101,
  "renter_type": "department",
  "renter_id": 9,
  "checked_out_at": "2026-03-30T10:00:00Z",
  "expected_return_at": "2026-03-31T10:00:00Z",
  "pricing_mode": "daily",
  "deposit_cents": 12000,
  "fee_terms": "Standard infusion pump rental terms"
}
```

Behavior:
- Blocks double booking for overlapping open rental.
- Transitions asset to `rented`.
- Auto transition to `overdue` at `expected_return_at + 2h`.

### POST `/rentals/checkouts/{id}/return`
Return asset and close rental transaction.

### GET `/rentals/checkouts/{id}`
Includes countdown/overdue indicators for UI.

### Ownership and Facility Transfer (Q1)
Assets are facility-owned by default and can only be moved via transfer workflow.

#### POST `/rentals/assets/{id}/transfer`
Creates an approval-required facility transfer request.

Request:
```json
{
  "to_facility_id": 3,
  "requested_effective_at": "2026-04-02T09:00:00Z",
  "reason": "Temporary cross-site demand"
}
```

Behavior:
- Only manager/admin can request transfer.
- Transfer blocked when asset has an open checkout or maintenance task.
- On approval, closes previous ownership window and opens new ownership window.
- Emits audit event with before/after facility scope.

#### POST `/rentals/transfers/{id}/approve`
Manager/admin approval endpoint; returns final ownership state.

## 8) Inventory and Stock Ledger APIs

### Core Concepts
- Multi-storeroom per facility.
- Immutable stock ledger entries:
  - `inbound`
  - `outbound`
  - `transfer_out`
  - `transfer_in`
  - `adjustment`
- ATP (available-to-promise) derived from ledger + reservations.
- Safety stock default: 14 days average usage.

### GET `/inventory/items`
List SKUs with on-hand, reserved, ATP, safety-stock, low-stock flag.

### POST `/inventory/receipts`
Receive stock into storeroom.

### POST `/inventory/issues`
Issue stock to department/service order.

### POST `/inventory/transfers`
Create inter-store transfer (out + in ledger pair).

### POST `/inventory/stocktakes`
Submit count session header.

### POST `/inventory/stocktakes/{id}/lines`
Submit counted lines with variance checks.

Variance rules:
- If absolute variance > 5%, requires manager approval and reason.

### POST `/inventory/stocktakes/{id}/approve-variance`
Manager approval workflow.

### PUT `/inventory/reservation-strategy`
Facility-level setting per service order flow:
- `reserve_on_order_create`
- `deduct_on_order_close`

### Reservation Strategy Conflict Handling (Q5)
Mixed strategies are supported per service, but ATP is always computed from a unified reservation projection.

#### POST `/inventory/service-orders/{id}/reserve`
Creates reservation events according to the configured service strategy.

Request:
```json
{
  "service_id": 2201,
  "storeroom_id": 14,
  "lines": [
    { "item_id": 901, "qty": 2.0, "uom": "ea" }
  ]
}
```

Behavior:
- If strategy is `reserve_on_order_create`, creates `reserved` ledger projections immediately.
- If strategy is `deduct_on_order_close`, records planned demand without reducing on-hand.
- ATP endpoint treats both reservation types consistently to avoid false availability.

#### POST `/inventory/service-orders/{id}/close`
Finalizes consumption:
- Converts planned demand to outbound movement for `deduct_on_order_close`.
- Reconciles reserved quantities for `reserve_on_order_create`.

## 9) Content Publishing APIs

### Content Types
- `announcement`
- `homepage_carousel`

### Workflow States
- `draft`
- `pending_approval`
- `approved`
- `published`
- `rejected`
- `archived`

### POST `/content/items`
Create draft content item.

### PATCH `/content/items/{id}`
Edit draft or create new version for published content.

### POST `/content/items/{id}/submit-approval`
Route item to approvers.

### POST `/content/items/{id}/approve`
Approve and optionally publish now/schedule.

### POST `/content/items/{id}/reject`
Reject with comment.

### POST `/content/items/{id}/rollback`
Rollback to prior version (keeps audit chain).

Targeting fields:
- `facility_ids[]`
- `department_ids[]`
- `role_codes[]`
- `tags[]`

### GET `/content/items/{id}/versions`
Version history and diffs metadata.

## 10) Patient Visit Feedback APIs

### POST `/reviews`
Create owner review for completed order.

Request:
```json
{
  "visit_order_id": 88021,
  "rating": 4,
  "tags": ["friendly_staff", "wait_time"],
  "text": "Great technician support.",
  "images": ["multipart-file-1", "multipart-file-2"]
}
```

Rules:
- Up to 5 images.
- Local disk storage + checksum integrity validation.
- Order-level uniqueness (one review per order unless reopened by manager).

### POST `/reviews/{id}/responses`
Manager/provider response.

### POST `/reviews/{id}/appeal`
Trigger appeal workflow for abusive/contested content.

### POST `/reviews/{id}/hide`
Hide from operational screens while preserving audit trail.

### GET `/reviews`
Filter by facility/provider/date/rating/tag/status.

## 11) Analytics and Dashboard APIs

### GET `/analytics/reviews/summary`
By clinic/provider/date range.

Metrics:
- `average_score`
- `negative_review_rate` (rating <= 2)
- `median_response_time_minutes`

### GET `/analytics/inventory/low-stock`
Low-stock items by storeroom/facility.

### GET `/analytics/rentals/overdue`
Overdue rentals and aging buckets.

Note: snapshot tables in MySQL can be updated by scheduled local jobs.

## 12) Import/Export and Dedup APIs

### POST `/imports/{entity}`
CSV upload with row-level validation and idempotent upsert by `external_key`.

Response includes per-row status:
```json
{
  "import_id": "imp_20260330_001",
  "summary": { "inserted": 34, "updated": 12, "failed": 3 },
  "rows": [
    { "line": 14, "status": "failed", "errors": ["provider_name is required"] }
  ]
}
```

### GET `/imports/{import_id}`
Retrieve import report and rollback links.

### Import Conflict Detection (Q9)
Each import row must include `entity`, `external_key`, and optional `source_version`.

#### POST `/imports/{entity}/validate`
Dry-run validation and conflict preview before commit.

Response includes:
- `would_insert`
- `would_update`
- `conflicts` (version mismatch or concurrent changes)

#### POST `/imports/{entity}/commit`
Executes import job using optimistic concurrency.

Rules:
- If `source_version` mismatches current row version, row marked `conflict` and skipped.
- Import-level mode:
  - `continue_on_conflict=true` (default): commit non-conflicting rows.
  - `continue_on_conflict=false`: abort entire batch on first conflict.

#### POST `/imports/conflicts/{conflict_id}/resolve`
Manual resolution by manager/admin with action:
- `overwrite_with_import`
- `keep_existing`
- `merge_fields` (field-level selection)

### POST `/imports/{import_id}/rollback`
Revert import transaction set.

### GET `/exports/{entity}`
CSV export with scope/audit logging.

### POST `/dedup/scan`
Run local dedup/entity-resolution detection:
- URL normalization
- announcement similarity via SimHash/MinHash
- key-field matching for providers/services

### POST `/dedup/merge/{candidate_group_id}`
Manager-confirmed merge with conflict resolution rules and provenance retention.

## 13) Audit APIs

### GET `/audit/logs`
Query by actor/action/entity/date/workstation.

Audited events include:
- login/logout/failures
- create/update/delete/revert
- approvals/rejections/publish/rollback
- imports/exports
- security and configuration changes

### GET `/audit/logs/{id}`
Detailed event with before/after payload references.

### Audit Scalability and Query APIs (Q15)
Audit storage is partitioned by month and facility to keep query paths bounded.

#### GET `/audit/partitions`
Admin-only endpoint returning active and archived partitions.

#### POST `/audit/archive`
Archives closed partitions older than configured threshold (default 18 months) while preserving 7-year retention.

Request:
```json
{
  "before_month": "2024-09",
  "compress": true
}
```

#### POST `/audit/reindex`
Rebuilds selective indexes for a partition after restore or large import.

## 14) Reference Status Codes
- `200 OK` read/update success
- `201 Created` resource created
- `204 No Content` delete/logout success
- `400 Bad Request` malformed request
- `401 Unauthorized` auth failure
- `403 Forbidden` permission denied
- `404 Not Found`
- `409 Conflict` double-booking/version conflict/idempotency mismatch
- `422 Unprocessable Entity` validation errors
- `429 Too Many Requests` throttling/captcha requirement
- `500 Internal Server Error`

## 15) Non-Functional API Requirements
- Must operate fully offline on local LAN.
- P95 API latency target: < 400 ms for standard list/create operations on 50 concurrent users.
- File integrity checks on upload and periodic verification.
- All mutations emit audit events in same transaction boundary where feasible.
- Backups and restore procedures must preserve data + audit + version history.
- Import conflict visibility and row-level recoverability are mandatory for bulk jobs.
- Audit queries over 7-year retention must support partition-pruned execution plans.

## 16) Minimal Table Contracts for Priority Questions

The following schema contracts are normative for API compatibility and correspond to Q1, Q5, Q9, Q14, and Q15.

### 16.1 `rental_asset_ownership_history` (Q1)
- `id` (pk)
- `asset_id` (fk rentals.assets.id)
- `facility_id` (fk facilities.id)
- `effective_from_utc` (datetime)
- `effective_to_utc` (datetime nullable)
- `transfer_request_id` (fk rentals.asset_transfers.id nullable)
- `created_by_user_id`
- Unique active-owner constraint: one open ownership row per asset.

### 16.2 `inventory_reservation_events` (Q5)
- `id` (pk)
- `service_order_id` (fk)
- `service_id` (fk services.id)
- `item_id` (fk inventory_items.id)
- `storeroom_id` (fk storerooms.id)
- `event_type` (`reserve|plan|consume|release`)
- `qty` decimal(12,3)
- `strategy` (`reserve_on_order_create|deduct_on_order_close`)
- `created_at_utc`, `created_by_user_id`

### 16.3 `import_conflicts` (Q9)
- `id` (pk)
- `import_job_id` (fk import_jobs.id)
- `entity`
- `external_key`
- `row_number`
- `db_version`
- `source_version`
- `status` (`open|resolved|ignored`)
- `resolution_action` nullable
- `resolution_payload_json` nullable
- `resolved_by_user_id` nullable
- `resolved_at_utc` nullable

### 16.4 `review_moderation_cases` (Q14)
- `id` (pk)
- `review_id` (fk reviews.id)
- `reason_category` (`abusive_language|harassment|privacy|spam|other`)
- `policy_version`
- `status` (`open|upheld|rejected|escalated|closed`)
- `requested_by_user_id`
- `assigned_to_user_id` nullable
- `decision_by_user_id` nullable
- `decision_note` nullable
- `created_at_utc`, `decided_at_utc` nullable

### 16.5 `audit_event_partitions` (Q15)
- `id` (pk)
- `partition_key` (e.g., `2026_03_facility_2`)
- `month_utc`
- `facility_id`
- `storage_tier` (`hot|warm|archive`)
- `row_count`
- `bytes_size`
- `sealed_at_utc` nullable
- `archived_at_utc` nullable