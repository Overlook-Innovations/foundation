# overlook/foundation

The conventions Overlook Innovations applies to every application it builds.

Installed by the scaffolder into each new project, and kept current on projects
already built by Renovate. Public on purpose: a client handed one of these
repositories must be able to run `composer install` without a credential from
the studio.

## What belongs here, and what does not

Sorted by who owns the file. Getting this wrong is how the template this
replaced went stale.

| Tier | Contents | Mechanism |
|------|----------|-----------|
| A | Runtime behaviour — object storage disks, queue and session defaults | `FoundationServiceProvider`. Configuration set at boot, never a published file. |
| B | Repo furniture with no upstream — `renovate.json`, editor config, CI | Published stubs. Safe to copy wholesale, because nothing in Laravel owns them. |
| C | Edits to framework-owned files — `.env` additions | `overlook:install`, idempotently. Every entry is a debt; move it to Tier A when you can. |

**Never publish a file Laravel ships.** A published `config/filesystems.php`
freezes that file at whatever the framework shipped the day it was copied — the
same drift the template had, on a smaller surface and harder to notice, because
the repository still looks freshly generated.

## The storage contract

The studio's provisioner attaches Cloudflare R2 buckets to two disks by name,
`private` and `media`. Cloud replaces each entry wholesale at runtime from the
`LARAVEL_CLOUD_DISK_CONFIG` blob it hands the container, so the application only
has to declare the names — which `FoundationServiceProvider` does.

Rename either and buckets attach to a disk nothing writes to. The nightly
`Upstream` workflow checks both still resolve.

## Upstream drift

Applications are generated from whatever the Laravel installer ships that day,
deliberately unpinned. The cost of that is an upstream release breaking
provisioning without anything here changing, so `.github/workflows/upstream.yml`
runs nightly against every starter kit the studio offers.

A red build there on a Tuesday morning is the whole point. Without it the first
report comes from a client watching a provision fail.

## Handing a project to a client

Nothing to do. The package is public and carries no credentials, so a client
who takes over a repository keeps installing it like any other dependency.
