# MariaCloud technical implementation plan

**Branch:** `mariacloud/property-management`  
**Launch profile:** Ethiopia / ETB / English, Somali and Amharic / Docker  
**Status:** Phase 0 configuration and image ownership updates committed; tenancy runtime proof of concept not yet implemented.

## 1. Confirmed technical baseline

- Laravel 13.7, PHP 8.3+ according to Composer constraints, React 19, Inertia 3 and TypeScript.
- PostgreSQL is the documented production database.
- Production runs as Docker services: web, queue and scheduler, using FrankenPHP.
- The MariaCloud product name is already configured in the application name and settings seed.
- Application locale configuration already lists `en`, `so` and `am`.
- Settings seed already establishes country `ET`, currency `ETB`, and timezone `Africa/Addis_Ababa`.
- The global Laravel timezone now reads `APP_TIMEZONE`, defaulting to `Africa/Addis_Ababa`.
- Production Compose and image publishing workflows now reference `ghcr.io/nuux14/openkos`, not the upstream `ghcr.io/senatroxx/openkos` image.

## 2. Tenancy implementation target

Use a central control-plane database and one PostgreSQL database per organization. The current OpenKOS business database remains intact within each organization. Each organization can manage multiple properties.

The control plane must not store property business records. It stores organization identity, verified hostnames, generated database identifier, lifecycle state, subscription status and provisioning audit information.

The tenant database stores the existing OpenKOS user accounts, roles, properties, renters, leases, invoices, payments, settings and audit data. Authentication is independent per organization in the first release; cross-organization SSO is not included.

## 3. Package integration

Candidate: `spatie/laravel-multitenancy` v4.3.1, compatible with Laravel 13 and PHP 8.3+ according to the package metadata checked on 2026-11-12.

Do not hand-edit `composer.lock`. On a working checkout/container:

1. Add the package with Composer.
2. Publish its configuration and landlord migration.
3. Inspect generated config and migrations against the exact installed package version.
4. Configure explicit `landlord` and `tenant` PostgreSQL connections. Tenant connection database must start as null.
5. Configure the organization model to use the landlord connection and existing OpenKOS business models to use the tenant connection.
6. Configure database switching, tenant identification and tenant-aware queues.
7. Commit both `composer.json` and the generated `composer.lock`.

Package references:
- https://spatie.be/docs/laravel-multitenancy/v4/installation/base-installation
- https://spatie.be/docs/laravel-multitenancy/v4/installation/using-multiple-databases

## 4. Safe implementation sequence

### Phase A — tenancy foundation

1. Add package dependency and generated config.
2. Create landlord organization model/table with lifecycle states: provisioning, active, suspended, failed and deprovisioning.
3. Add organization domain table with unique normalized hostnames and verification state.
4. Add hostname resolver that looks up the organization on the landlord connection before switching databases.
5. Fail closed for unknown, unverified, suspended or incomplete organizations.
6. Add explicit tenant route middleware. Keep marketing/onboarding routes on the landlord connection.
7. Add a controlled database provisioning service. Database names and credentials are generated server-side; never accept raw identifiers from user input.
8. Add idempotent tenant migration and seeding commands.

### Phase B — context isolation

1. Ensure database connection is reset after every request and job.
2. Configure tenant-aware jobs and test worker reuse across two organizations.
3. Prefix cache keys and rate-limit keys by organization.
4. Isolate session cookies and host scope; test that a session from one organization cannot authenticate on another.
5. Isolate private file storage by organization and authorize every download against the active organization.
6. Make scheduled tasks iterate organizations explicitly and switch/forget context safely.

### Phase C — test gates

Create at least two synthetic organizations, each with distinct users, properties and records. Tests must prove:

- Organization A cannot resolve Organization B's host, user, property, lease, invoice, payment or file.
- Unknown and suspended hostnames never fall back to a default customer database.
- Tenant context is cleared after exceptions and after queued jobs.
- A long-lived queue worker can process jobs for A then B without leaking context.
- Cache and session keys do not collide across organizations.
- Provisioning retries are idempotent and partial failures leave the organization inactive.
- Tenant migrations can be run for all active organizations and report failures per organization.
- Backups and restores for one organization do not overwrite another organization's data.

Do not mark the hosted tenancy foundation complete until these tests pass in CI against PostgreSQL, not only SQLite.

## 5. Docker and release requirements

- Build the fork image from this repository and publish to `ghcr.io/nuux14/openkos`.
- Use immutable image tags for staging and production; avoid deploying `latest` for a release.
- Keep landlord and tenant database credentials in secrets, not in source control.
- Restrict database network access to application containers and provisioning service.
- Set `APP_ENV=production`, `APP_DEBUG=false`, secure cookies, HTTPS and trusted proxies.
- Run web, queue and scheduler with identical tenant configuration and shared private file storage.
- Verify migration, backup, restore and disaster recovery before onboarding real customers.

## 6. Ethiopia launch defaults

| Setting | Default |
| --- | --- |
| Country | Ethiopia (`ET`) |
| Currency | ETB |
| Timezone | Africa/Addis_Ababa |
| Default locale | English (`en`) |
| Supported locales | English (`en`), Somali (`so`), Amharic (`am`) |
| Deployment | Docker / FrankenPHP / PostgreSQL |
| Platform hostname | `property.mubarek.et` |
| Organization hostname | `<organization-slug>.property.mubarek.et` |

Currency, timezone and locale are defaults and must remain configurable. A property may override its organization's defaults where the business requires it. Lease currency remains immutable after creation.

## 7. Current completion status

| Work item | Status |
| --- | --- |
| Architecture proposal and ADR-014 | Documented; proposed pending proof of concept |
| Ethiopia country/currency/timezone seed defaults | Already present in repository |
| English/Somali/Amharic locale configuration | Already present in repository |
| Configurable global timezone default | Committed |
| Fork-specific production image references | Committed |
| Tenancy package and landlord schema | Not yet installed |
| Hostname resolution and database switching | Not yet implemented |
| Queue/cache/session/file isolation | Not yet implemented |
| PostgreSQL isolation test suite | Not yet implemented |
| Production deployment | Not started |

This plan distinguishes committed work from proposed work. No production database, domain DNS, customer records or live deployment has been changed.
