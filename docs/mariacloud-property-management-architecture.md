# MariaCloud Property Management — Architecture Proposal

**Status:** Draft proposal for review  
**Product URL:** `property.mubarek.et`  
**Code foundation:** OpenKOS (Apache-2.0)  
**Working branch:** `mariacloud/property-management`

This document records the proposed product architecture before application code is changed. It is intentionally a design proposal, not a claim that OpenKOS already supports SaaS organization isolation.

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

## 2. Tenant isolation — decision required before production

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

Use a **central control plane plus a separate PostgreSQL database per organization** as the preferred production target if the operational budget supports it. It aligns better with OpenKOS's current single-business domain assumptions and reduces the amount of invasive row-level tenant scoping required.

Before committing to this, build a proof of concept for organization provisioning, request context, queue context, migrations and backup/restore. If that proves too operationally expensive, evaluate Option A with mandatory PostgreSQL row-level security; do not ship a shared database with application filters alone.

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

Initial regional defaults may include Ethiopia (ETB and Africa/Addis_Ababa), but these must be configurable rather than hard-coded. English should be available initially; Somali and Amharic should be planned as localization options.

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

1. Complete the tenancy proof of concept and record the database-isolation decision.
2. Build organization provisioning, membership and organization context.
3. Adapt property, user, role, settings and audit boundaries.
4. Add comprehensive isolation tests before broad feature customization.
5. Apply MariaCloud branding and localization.
6. Add configurable regional, currency and financial settings.
7. Implement MariaCloud File, Sign and Mail integrations as separate modules.
8. Deploy to staging at `property.mubarek.et`, validate backup/restore and complete acceptance tests.
9. Release to production only after security and operational gates pass.

## 10. Scope boundary

This proposal does not authorize changing MariaCloud's existing cloud installation, reusing its database, or deploying customer data. The working branch is isolated from `main`. Application changes should be made incrementally, tested, and reviewed before any production deployment.
