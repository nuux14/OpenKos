# MariaCloud Property Management — Architecture Proposal

**Status:** Implementation baseline — tenancy proof of concept pending  
**Product URL:** `property.mubarek.et`  
**Code foundation:** OpenKOS (Apache-2.0)  
**Working branch:** `mariacloud/property-management`

This document records the MariaCloud implementation baseline. The launch defaults have been applied to application configuration and new-install seed data. OpenKOS does not yet provide production-ready SaaS organization isolation; the tenancy design remains gated on a working proof of concept.

## 1. Product and tenancy model

MariaCloud Property Management must support multiple independent customer organizations. Each organization can own or manage multiple properties.

```
MariaCloud Platform
  ├── Organization A
  │    ├── Users and roles
  │    ├── Property A1
  │    ├── Property A2
  │    └── Property A3
  ├── Organization B
  │    ├── Users and roles
  │    ├── Property B1
  │    └── Property B2
  └── Platform Operations
       ├── Subscription and plan management
       ├── Tenant provisioning
       └── Platform-wide monitoring
```

A property is not a customer account. An organization is the security and commercial boundary; properties are resources managed inside that boundary.

### Organization roles

- Organization owner: controls organization profile, subscription, users and all its properties.
- Organization administrator: manages organization operations, subject to assigned permissions.
- Property manager/staff: access only assigned properties and allowed functions.
- Tenant/renter: accesses only their own tenant profile, leases, invoices, payments and maintenance requests.
- Platform administrator/support: operates the service through a separate, audited support capability; no silent unrestricted access to customer records.

A person may be associated with more than one organization. Organization membership and property assignments must therefore be explicit and must not be inferred from a global role alone.

## 2. Tenant isolation — proposed technical implementation

OpenKOS currently models multiple properties within one installation and associates staff users with properties. Its current README describes a self-hosted, single-business installation model; this is not equivalent to SaaS isolation between unrelated customer organizations.

Two viable approaches:

### Option A — Shared application and shared PostgreSQL database

Add an organization boundary to the domain model and enforce it in every query, authorization policy, background job, file access path, export, notification, and integration.

Advantages: simplest deployment and upgrades; efficient resource use; straightforward platform-wide reporting.

Risks: a missing organization filter can expose another customer's data. Requires comprehensive automated isolation tests and PostgreSQL row-level security as defence in depth.

### Option B — Shared application with a separate database per organization

Keep a central platform database for identities, organizations, subscriptions and provisioning, and place each organization's property-management records in its own PostgreSQL database.

Advantages: stronger database-level separation, simpler customer export/deletion boundaries, reduced impact of a tenant-scoped query mistake.

Risks: more complex connection switching, migrations, backups, queues, scheduled jobs, reporting and operational monitoring.

### Proposed direction

Use a **central control plane plus a separate PostgreSQL database per organization**. This preserves OpenKOS's single-business domain model inside each customer database.

The initial tenancy orchestration candidate is **spatie/laravel-multitenancy v4**, which supports the repository's Laravel 13 / PHP 8.3+ dependency range and provides database switching, tenant-aware queue support and tenant lifecycle commands. The dependency must be added with Composer and locked only after a local/container build and test run; no unverified lockfile changes should be committed.

Organization hostnames will follow `<organization-slug>.property.mubarek.et`; the root host `property.mubarek.et` is reserved for the platform landing and onboarding experience. The technical decision is recorded as proposed in ADR-014. It becomes accepted only after the database, session, queue, cache and storage isolation proof of concept passes.

## 3. Domain structure

- Organization: customer account, legal/business identity, plan, status and default preferences.
- OrganizationMembership: user, organization, role, status and invitation lifecycle.
- Property: belongs to exactly one organization.
- PropertyUser / PropertyMembership: property-specific access grants within an organization.
- Unit / UnitType / Rate: belongs to a property.
- Tenant: renter identity scoped to the organization; define an explicit model for a tenant renting across multiple properties.
- Lease: belongs to one property and one or more tenants; retains immutable currency and property lineage.
- Invoice / Payment / PaymentProof: must inherit the lease/property/organization security boundary.
- MaintenanceTicket / TenantDocument / Media: must be authorized through their owning property or tenant record.
- AuditLog: records actor, organization, property (where applicable), action, timestamp and safe metadata.

Enforce relationship integrity, not only UI filtering. A lease's unit, rates, tenant, invoice and payment references must all belong to the same organization/property context.

## 4. Property and financial configuration

A customer organization may manage properties in different locations and currencies.

- Organization defaults: legal name, contact details, locale, timezone, default currency and address format.
- Property overrides: country, region/state, city/district, address, timezone, default currency, tax settings and property-specific branding/contact information.
- Lease currency remains fixed at creation, as in OpenKOS's existing financial model.
- Rates and invoices must preserve their currency; do not silently convert amounts.
- Currency conversion, if added, must record exchange rate, source and effective timestamp.

### Confirmed launch defaults

- Country: Ethiopia (country code ET)
- Currency: ETB (Ethiopian Birr), with USD retained as an additional supported currency
- Display timezone: Africa/Addis_Ababa
- Default language: English
- Planned language options: Somali and Amharic

These defaults have been applied to the settings seeder and locale configuration. The locale options are currently scaffolding: Somali and Amharic translation catalogs still need to be authored and reviewed. Do not represent the interface as fully translated until those catalogs and language-switching tests are complete. Property-level country, timezone and currency overrides remain supported by design.

## 5. MariaCloud identity and integrations

Integrations should be implemented behind explicit service contracts and organization-scoped configuration.

### MariaCloud File

- Store property documents, tenant documents, lease attachments and maintenance evidence in organization-scoped folders.
- Keep the application's authorization authoritative; possession of a file URL must not bypass access checks.
- Support private files, expiring access links, upload limits, malware scanning hooks and audit events.
- Define retention, deletion and backup behavior.

### MariaCloud Sign

- Generate lease and agreement documents from approved templates.
- Submit documents for signing through MariaCloud Sign.
- Store signing status, signer identity, timestamps and final signed artifact reference.
- Verify callbacks/webhooks and make processing idempotent.
- Never mark a lease as executed merely because a signing request was sent.

### MariaCloud Mail

- Send invitations, rent reminders, receipts, overdue notices, maintenance updates and signing notifications.
- Support organization-specific sender identity only after domain verification.
- Queue messages and record delivery outcomes without exposing sensitive message contents in logs.

Integrations must be optional and failure-tolerant: property management core workflows should remain usable if a connected MariaCloud service is temporarily unavailable.

## 6. Branding and product identity

- Product name: **MariaCloud Property Management**.
- Public URL: `https://property.mubarek.et`.
- Replace OpenKOS names, logos, icons, colors, page titles, emails, PDF headers and default seed data in customer-facing surfaces.
- Preserve third-party copyright and Apache-2.0 notices where required. Do not imply that MariaCloud owns the original OpenKOS project.
- Keep upstream attribution in an appropriate legal/about location.

## 7. Deployment and environments

The selected launch deployment is Docker using the repository's FrankenPHP application image, with PostgreSQL as the database engine. The current upstream Compose file references an upstream OpenKOS image; MariaCloud must build and publish its own image from this fork before production deployment. Never deploy the upstream image as the MariaCloud product.

- Do not deploy into or reuse MariaCloud's existing Nextcloud/MySQL database.
- Use a dedicated PostgreSQL service and dedicated application storage.
- Test the documented Docker/FrankenPHP deployment first; do not assume a Plesk PHP-FPM deployment is supported.
- Separate development, staging and production environments, secrets, databases, object storage and email credentials.
- Require HTTPS, secure cookies, production debug disabled, trusted-proxy configuration, queue workers, scheduler, monitoring and tested backups.
- Use private object storage for customer documents before considering horizontal scaling.
- Confirm supported PHP and PostgreSQL versions against the exact dependency lockfile and deployment image; repository docs and Composer constraints currently differ on the PHP minimum.

## 8. Security and release gates

No customer production data should be introduced until all of the following pass:

1. Cross-organization access tests for every major resource and endpoint.
2. Cross-property staff access tests, including nested routes and exports.
3. Tenant portal tests for lease, invoice, payment, document and maintenance ownership.
4. Queue/job isolation tests proving organization context is restored and cleared.
5. File/media isolation tests, including guessed IDs and direct download URLs.
6. Payment tests for overpayment, duplicate callbacks, authorization and audit trails.
7. Organization provisioning, suspension, export, backup and restore tests.
8. Two-factor authentication for privileged accounts and audited support access.
9. Dependency, secret, upload and production-configuration checks.
10. A staging acceptance test using synthetic data only.

## 9. Delivery sequence

1. Complete the tenancy proof of concept using the proposed Spatie package and record the accepted database-isolation decision.
2. Build organization provisioning, membership and organization context.
3. Adapt property, user, role, settings and audit boundaries.
4. Add comprehensive isolation tests before broad feature customization.
5. Complete MariaCloud branding and deliver reviewed English, Somali and Amharic translation catalogs.
6. Add configurable regional, currency and financial settings.
7. Implement MariaCloud File, Sign and Mail integrations as separate modules.
8. Deploy to staging at `property.mubarek.et`, validate backup/restore and complete acceptance tests.
9. Release to production only after security and operational gates pass.

## 10. Current implementation status

- Application locale configuration now lists English, Somali and Amharic.
- New-install settings seed Ethiopia, ETB, USD, Africa/Addis_Ababa and MariaCloud product defaults.
- The database-per-organization ADR is proposed, not accepted; the tenancy package has not yet been installed or wired into request handling.
- Somali and Amharic translations are not complete.
- Docker production deployment must use a MariaCloud image built from this fork; the existing Compose reference still points at the upstream OpenKOS image.

## 11. Scope boundary

This proposal does not authorize changing MariaCloud's existing cloud installation, reusing its database, or deploying customer data. The working branch is isolated from `main`. Application changes should be made incrementally, tested, and reviewed before any production deployment.
