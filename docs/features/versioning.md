# Versioning

## Built-in Versioning

DOM ORM can automatically commit your XML data file to a Git or Mercurial repository after
every write. This gives you a full audit trail and an off-site backup without any extra
infrastructure.
DOM ORM supports [Git](https://git-scm.com/) and [Mercurial](https://www.mercurial-scm.org/) out of the box.

### Prerequisites

- **Git:** `git` must be on the system `PATH`. Run `git --version` to verify.
- **Mercurial:** `hg` must be on the system `PATH`. Run `hg --version` to verify.
- The storage directory (configured under `flysystem.config.location`) must already be inside an initialised repository (`git init` / `hg init`). DOM ORM does **not** create the repo for you.
- For push to work, a remote must be configured and any required credentials (SSH key or credential helper) must be available to the PHP process.

### Configuration
In your `config/dom-orm.php` file, there are three settings for this feature:
```php
<?php return [
  'dom-orm' => [
    'versioning' => true,
    'version_control' => 'git',
    'version_control_push' => 'manual', 
  ];
```

| Option | Values | Description |
|--------|--------|-------------|
| `versioning` | `true` / `false` | Master switch. Defaults to `false` — no VCS activity until explicitly enabled. |
| `version_control` | `git` / `hg` | Which VCS binary to use. Defaults to `'git'`. |
| `version_control_push` | `manual` | Commit locally on every write, but **never push** automatically. Use a cron job or CI to push on your own schedule. |
| | `on_persist` | Commit **and** push on every write. Convenient for low-traffic projects where blocking on a network call is acceptable. |

> **Tip:** Start with `version_control_push: 'manual'` and add a cron-driven push once you
> have verified that commits are being created correctly.

### How it works

1. After DOM ORM writes the XML file, `VcsService::commit()` is called automatically.
2. It stages all changes in the storage directory (`add -A` / `addremove`).
3. It creates a commit whose message names the calling class and method
4. If `version_control_push` is `on_persist`, it pushes to the configured remote.

Failures are **non-fatal** — a PHP `E_USER_WARNING` is emitted and the application continues
normally. The XML write that already succeeded is never rolled back.

### Automatic push via cron job

When `version_control_push` is set to `'manual'`, you control when pushes happen. A cron job
is the simplest way to push on a schedule.

#### Every N minutes

Open the crontab editor:

```bash
crontab -e
```

Push every 15 minutes (replace paths to match your setup):

```cron
*/15 * * * * cd /var/www/my-project/storage && git push >> /var/log/dom-orm-push.log 2>&1
```

#### Mercurial

Replace `git push` with `hg push`:

```cron
*/15 * * * * cd /var/www/my-project/storage && hg push >> /var/log/dom-orm-push.log 2>&1
```

#### Quick cron reference

| Field | Meaning | Example |
|-------|---------|---------|
| `*/15 * * * *` | Every 15 minutes | — |
| `*/30 * * * *` | Every 30 minutes | — |
| `0 */2 * * *` | Every 2 hours | on the hour |
| `0 */6 * * *` | Every 6 hours | on the hour |
| `0 3 * * *` | Once a day | 03:00 |

> **Security note:** Avoid embedding credentials in cron commands or remote URLs. Use SSH keys
> or a Git credential helper so secrets are never stored in plain text in the crontab.