# ADR-014: MariaCloud Hosted Edition Uses a Database per Organization

**Status:** Proposed — pending technical proof of concept  
**Date:** 2026-11-12

## Context

The upstream OpenKOS application is deliberately single-business per installation (ADR-001). MariaCloud Property Management is a hosted service that must operate one application platform for multiple independent customer organizations. Each organization may manage multiple properties.

The word "tenant" is already used by OpenKOS to mean a renter/occupant. The hosted platform must not overload that domain concept. In this ADR, **organization** means a MariaCloud customer account; **renter** continues to mean the person leasing a unit or property.

Two storage models were considered:

- Shared PostgreSQL schema with organization IDs and mandatory row-level scoping across all business data.
- A central control-plane database plus a separate PostgreSQL database for each customer organization.

A shared schema would require invasive changes to existing policies, global owner-role assumptions, settings, model scopes, imports/exports, jobs, media access and every new feature. One missed filter could disclose another customer's records.

## Decision

For the MariaCloud hosted edition, use a **shared application deployment with a central control-plane database and one PostgreSQL database per customer organization**.

The tenant database retains OpenKOS's single-business domain model. Multiple properties, units, leases and renters remain supported within that organization database.

Use spatie/laravel-multitenancy v4 as the initial tenancy orchestration candidate. It supports Laravel 12/13 and PHP 8.3+, database-per-tenant switching, tenant-aware queues and tenant lifecycle commands. The exact package release must be locked by Composer and verified in CI before merge.

### Control plane

The central database stores only platform-level records:

- Organization identity, display name, unique slug and verified domains.
- Generated tenant database name and provisioning status.
- Subscription plan/status and service lifecycle state.
- Platform-level provisioning and operational audit records.
- Optional central platform operator accounts.

Do not put leases, invoices, payments, renter profiles, property documents or other customer business records in the control plane.

### Organization database

Each organization database contains its own OpenKOS business schema, including users, roles, properties, units, renters, leases, invoices, payments, settings, audit records and tenant-owned media metadata.

A customer may manage any number of properties inside this database. The organization is the isolation boundary; a property is a business resource inside that boundary.

### Tenant identification

- The base host property.mubarek.et is reserved for marketing, registration and account discovery.
- Each organization receives a unique host such as <organization-slug>.property.mubarek.et.
- A verified custom domain may be supported later.
- Resolve the host against the central organization registry before switching to the organization's database.
- Unknown, suspended, provisioning or inactive organizations must fail closed; never fall back to another organization's database.

### Authentication boundary

For the first hosted release, authentication and authorization remain inside each organization database. The same email address may exist in multiple organizations, but accounts, roles and sessions are independent. Cross-organization single sign-on is out of scope until a separate identity architecture is designed.

## Implementation requirements

1. Configure explicit landlord and tenant PostgreSQL connections. The tenant connection must not have a default database selected.
2. Use a dedicated organization model on the landlord connection and configure the package's SwitchTenantDatabaseTask.
3. Add tenant identification by verified domain and protect tenant routes with NeedsTenant followed by EnsureValidTenantSession.
4. Keep queue infrastructure central. Jobs must carry tenant context explicitly and restore/clear it after success and failure. Test worker reuse across different organizations.
5. Isolate cache keys, sessions, rate limits, filesystem paths and generated URLs by organization. Do not rely on the database switch alone.
6. Store files in private organization-specific storage prefixes/volumes. Authorize every download through the organization context.
7. Provision databases using generated identifiers and restricted PostgreSQL roles. Never accept raw database names or connection credentials from customer input.
8. Maintain a central migration path for control-plane schema and run tenant migrations against every active organization database.
9. Add lifecycle states (provisioning, active, suspended, failed, deprovisioning) and make provisioning retryable and idempotent.
10. Test backup, restore, suspension, export and deletion for one organization without affecting another.

## Ethiopia launch defaults

- Country code: ET
- Default currency: ETB
- Default display timezone: Africa/Addis_Ababa
- Default locale: en
- Supported locales: English (en), Somali (so), Amharic (am)
- Deployment target: Docker / FrankenPHP / PostgreSQL

These are defaults, not hard-coded restrictions. Currency, timezone, locale and address details may be overridden by an organization or property where appropriate. Lease currency remains immutable after creation.

## Consequences

- Existing OpenKOS property and rental workflows can remain single-business within each organization database.
- The application must correctly select and isolate the organization database on every HTTP request, queued job, scheduled task, CLI operation and file access.
- Cross-organization reporting must use explicit, audited control-plane aggregation rather than joining customer databases casually.
- Operating cost and migration complexity increase with organization count. Database provisioning, connection limits, migration orchestration and backup automation are first-class platform services.
- No customer data may be onboarded until the proof of concept demonstrates database switching, session isolation, queue isolation, file isolation and tenant lifecycle recovery.

## Revisit trigger

Reconsider this decision if the number of organizations, database connection overhead or operational burden makes a shared schema with PostgreSQL row-level security materially safer or more sustainable. Such a change requires a new ADR and a tested migration plan.
