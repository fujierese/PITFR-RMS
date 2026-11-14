# Automatic deployment to GoDaddy (cPanel)

Every push to `main` deploys to the GoDaddy server once **PITFR CI** passes:

1. `.github/workflows/deploy.yml` builds `vendor/` (Composer, no dev packages) and `public/build/` (Vite) on GitHub. The shared host has no Node.js, and Composer is unreliable there.
2. It uploads them over SSH as `~/pitfr-release.tgz`.
3. On the server it runs `git merge --ff-only` to the tested commit, then `deploy.sh`. That puts the site in maintenance mode, swaps in `vendor/` and `public/build/`, runs `migrate --force`, rebuilds the caches and brings the site back up.

You can also start a deploy by hand: **Actions → Deploy to GoDaddy → Run workflow**.

Until the secrets below are set, the workflow skips itself with a warning instead of failing.

## One-time setup

### 1. Enable SSH on the hosting plan
GoDaddy → My Products → Web Hosting → **Manage** → Settings → **Server** → turn on **SSH Access**. Note the **SSH username** and **server IP/hostname** shown there.

### 2. Authorize the deploy key
Use a dedicated key that has no passphrase and is only used for deploys.
- cPanel → **SSH Access** → **Manage SSH Keys** → **Import Key**. Paste the public key (`.pub`), give it a name, then click **Authorize** next to it.

### 3. Connect the live folder to GitHub (run once over SSH)
Back up first. Then, inside the folder the site runs from (often `~/public_html`):

```bash
cp -r ~/public_html ~/backup-$(date +%F)
cd ~/public_html
git init
git remote add origin https://github.com/fujierese/PITFR-RMS.git
git fetch origin
git checkout -B main
git reset --hard origin/main
git branch -u origin/main
```

`reset --hard` only replaces tracked files. The live `.env`, uploaded files in `storage/`, `vendor/` and `public/build/` are untracked and stay as they are. Any edits made on the server to tracked files are replaced by the GitHub version.

If the repository becomes private, the server needs read access. Add a read-only **Deploy key** in the repo settings, using a key generated on the server.

Check which PHP the command line uses:

```bash
php -v
```

If it is older than 8.2, find the 8.2 binary (usually `/opt/cpanel/ea-php82/root/usr/bin/php`) and set it as `GODADDY_PHP_BIN` below.

### 4. Add the GitHub secrets
Repository → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**:

| Secret | Value |
|---|---|
| `GODADDY_SSH_HOST` | Server IP or hostname from step 1 |
| `GODADDY_SSH_USER` | cPanel SSH username |
| `GODADDY_SSH_KEY` | The **private** deploy key: the full file, including the `BEGIN`/`END` lines |
| `GODADDY_APP_PATH` | Absolute path of the app, e.g. `/home/USERNAME/public_html` |
| `GODADDY_SSH_PORT` | *(optional)* defaults to `22` |
| `GODADDY_PHP_BIN` | *(optional)* PHP 8.2+ binary path, defaults to `php` |
| `GODADDY_KNOWN_HOSTS` | *(optional, recommended)* output of `ssh-keyscan HOST` taken from a trusted network. Pins the server's key instead of trusting it on first connect. |

### 5. First deploy
Run **Actions → Deploy to GoDaddy → Run workflow**, or push to `main`.

## Manual deploy (no GitHub Actions)

```bash
cd ~/public_html
git pull --ff-only
./deploy.sh
```

Without an archive, `deploy.sh` runs `composer install` if Composer is available. `public/build/` is not in git, so asset changes need the GitHub workflow or a manual upload.

## Notes
- **Database backups:** deploys run `php artisan migrate --force` automatically. Take a database backup in cPanel (phpMyAdmin export or **Backup**) before pushing changes that include migrations.
- **Failed deploy:** the site comes back out of maintenance mode automatically. If `git merge --ff-only` fails, someone changed tracked files on the server or the histories diverged. Inspect them with `git status` over SSH.
- **Rollback:** on the server, run `git reset --hard <previous-sha>` and then `./deploy.sh`. Or revert the commit on GitHub and let it redeploy.
