# Deploying DGV Mining on Dokploy

CI/CD runs entirely inside Dokploy: Dokploy watches this GitHub repo, and on
every push to `main` it pulls the code, builds the `Dockerfile` on the server
and redeploys. There is no GitHub Actions workflow and no external registry.

```
git push main ──► Dokploy (Auto Deploy) ──► docker compose build + up
                                              │
                    Traefik (HTTPS, domain) ──► app (PHP 8.0 + Apache)
                                              ├─ volumes: uploads (3)
                                              └─ Dokploy MariaDB service ──► scheduled backups
```

| File | Purpose |
|---|---|
| `Dockerfile` | Image used both locally and in production |
| `docker-compose.yml` | **Local only** (your Mac): app + its own MariaDB, ports 80/8080/3307. **Never use on the server.** |
| `docker-compose.dev.yml` | **Dokploy development** environment (branch `develop`): app only, errors shown on screen |
| `docker-compose.prod.yml` | **Dokploy production** environment (branch `main`): app only, errors hidden |

> **Dokploy's default Compose Path is `./docker-compose.yml` (the local file).**
> Always change it: development → `./docker-compose.dev.yml`,
> production → `./docker-compose.prod.yml`. With the local file, the deploy
> fails on port 80 ("port is already allocated") and starts an extra,
> internet-exposed database on port 3307.
>
> In the development environment, don't set `APP_ENV` (or set it to
> `development`); `APP_ENV=production` there would hide errors.
| `docker/config/zz-docker.local.php` | Deployment config overrides, copied into the image (see below) |
| `docker/db-init.sh` | Local only: loads the dump into the local MariaDB |

### Running locally

The database dump is **not in git** because it contains client data. Get it from
the team (securely, not by email or chat), save it as `data/dgv_mining.sql`, then:

```bash
docker compose up -d --build
```

The first start imports the dump into the local MariaDB. To re-import after
replacing the file: `docker compose down -v && docker compose up -d`
(`-v` also clears the local upload volumes).
App: `http://localhost:8080/` or `http://localhost/dgv.mining.com/public/`.

### Application code is not modified

All production fixes live in deployment files. No file under `module/`,
`config/` or `public/` is edited in git:

| Problem in the app | Deployment-level fix |
|---|---|
| `config/autoload/local.php` builds the DSN with `hostname=`, which PDO ignores, so the DB can't be reached in another container | `zz-docker.local.php` (loaded last by Laminas) sets the same DSN with `host=` |
| `public/index.php` forces `display_errors=1`, and modules set `display_exceptions=true`: visitors see stack traces, paths and SQL | Apache `php_admin_flag display_errors ${PHP_DISPLAY_ERRORS}` (which `ini_set` can't override) plus `display_exceptions=false` in `zz-docker.local.php` when `APP_ENV=production` |
| Controllers' `init()` keeps running after redirecting to login, which prints warnings and breaks the redirect | `output_buffering=4096` in the image's PHP settings (same as XAMPP/MAMP) |
| Code needs PHP 8.0, but `composer.json` says `^7.3` and lists setup-only packages that block PHP 8 | Adjusted only inside the Docker build; `composer.json` in git is unchanged |

The only non-deployment file touched is `.env` (local config, not code): the
line `/*CREATE USER TO SSO*/` became `# CREATE USER TO SSO`, because Docker
Compose can't parse it. The app ignored that line before and still ignores it.

---

## 1. Database (Dokploy-managed MariaDB)

1. In the Dokploy project: **Create Service → Database → MariaDB**, image `mariadb:10.11`.
   - Database name: `dheyma_mining_24`
   - Set a user and a strong password (these become `DB_USER` / `DB_PASS`).
2. Deploy it, and note the **Internal Host** shown on the database page.
   That's the `DB_HOST` value for the app.
3. Import the full dump once (from the Dokploy server's shell):
   ```bash
   docker exec -i $(docker ps -qf name=<db-app-name>) \
     mariadb -u<DB_USER> -p<DB_PASS> dheyma_mining_24 < dheyma_mining_24_full.sql
   ```
   Or temporarily enable the external port, import with SQLyog, then disable it again.
   Don't use `docker/db-init.sh` here: it is a local-only helper that skips errors.

## 2. App (Compose service)

1. **Create Service → Compose**
   - Provider: **GitHub**. Install the Dokploy GitHub App on the `athangict` org once.
   - Repository `dgv_mining`, branch `main`
   - Compose path: `./docker-compose.prod.yml` (production) or
     `./docker-compose.dev.yml` (development, branch `develop`)
   - **Auto Deploy: ON**. This is the CI/CD: every push to `main` redeploys.
2. **Environment** tab: paste the values below. Never commit them.
   ```env
   DB_HOST=<internal host from step 1>
   DB_PORT=3306
   DB_NAME=dheyma_mining_24
   DB_USER=<db user>
   DB_PASS=<db password>

   APP_NAME=MONAL
   ERP_TAG=ERP
   APL_CLIENT=BTN
   INITIAL_DEFAULT_PASSWORD=<strong value>
   # STATIC_SALT / LEGACY_STATIC_SALT: keep EXACTLY the values production uses
   # today, or existing passwords stop working.

   SSO_ENABLED=true
   SSO_CLIENT_ID=dgv_mining
   SSO_CLIENT_SECRET=<rotated secret>
   SSO_REDIRECT_URI=https://<domain>/auth/callback
   SSO_OPENID_CONFIGURATION_URI=...
   SSO_AUTHORIZATION_ENDPOINT=...
   SSO_TOKEN_ENDPOINT=...
   SSO_USERINFO_ENDPOINT=...
   SSO_JWKS_URI=...
   SSO_LOGOUT_URI=...
   SSO_VALID_ISSUERS=...
   SSO_LOGOUT_ENDPOINT=...
   SSO_POST_LOGOUT_REDIRECT_URI=...
   SSO_USER_CREATE_ENDPOINT=...
   SSO_USER_API_KEY=<rotated key>
   SSO_USER_API_KEY_HEADER=...
   SSO_ALLOW_CLIENT_CREDENTIALS_FOR_USER_CREATE=false
   ```
   `APP_ENV=production` and `PHP_DISPLAY_ERRORS=Off` are fixed in the compose
   file. They hide PHP errors and stack traces from visitors; errors go to the
   container log instead.
   The deploy fails fast if `DB_HOST`, `DB_USER`, `DB_PASS` or
   `INITIAL_DEFAULT_PASSWORD` is missing.
3. **Domains** tab: add the domain → service `app`, port `80`, HTTPS with Let's Encrypt.
4. Register `https://<domain>/auth/callback` as a redirect URI for the
   `dgv_mining` client on sso.athang.com.
5. **Deploy.** The container reports `healthy` once `/auth/login` answers
   (that check exercises PHP, the config and the database).

## 3. Uploaded files (local disk, no S3)

The app keeps files on disk. In production they live in three Docker volumes
that survive every redeploy:

| Volume | Path in container | Contents |
|---|---|---|
| `uploads_private` | `/var/www/data/uploads` | Documents (pdf, docx, xlsx, dwg, …) |
| `uploads_public` | `/var/www/public/upload` | Public images |
| `uploads_photos` | `/var/www/public/uploads` | Employee photos |

Dokploy prefixes the names with the compose app name (e.g. `<app>_uploads_private`).
List them on the server with `docker volume ls | grep uploads`.

**Moving the client's existing files in** (once, from the server shell):
```bash
docker run --rm -v <app>_uploads_private:/dst -v /path/to/old/data/uploads:/src:ro \
  alpine sh -c 'cp -a /src/. /dst/ && chown -R 33:33 /dst'
# repeat for public/upload -> <app>_uploads_public and public/uploads -> <app>_uploads_photos
```
(`33` is `www-data` in the image.)

## 4. Backups (keep at least one copy off this server)

The app doesn't use S3. A backup *destination*, though, should be on a
different machine than the app: if the server dies, backups stored on it go
with it.

1. **Destination:** Dokploy → Settings → **S3 Destinations**. "S3" is only the
   storage protocol here; AWS isn't required. Cheap options: Backblaze B2,
   Cloudflare R2, Hetzner Object Storage, or MinIO on another client machine.
2. **Database:** MariaDB service → **Backups** → daily schedule to that destination.
3. **Files:** Compose service → **Volume Backups** for the three `uploads_*`
   volumes, on the same schedule as the DB so files and DB rows stay consistent.
   If the client's Dokploy version has no volume backups, use a nightly
   `restic`/`rsync` cron job on the server that copies those volumes to
   another server or a Hetzner Storage Box.
4. **Test a restore** on a staging copy before go-live, and periodically after.

## 5. Day-to-day

- **Deploy:** merge/push to `main`. Dokploy builds and redeploys automatically.
  Watch progress in the service's **Deployments** tab.
- **Manual deploy:** press **Deploy** in Dokploy.
- **Roll back:** `git revert <bad commit>` and push. Dokploy redeploys the
  previous code. Uploads and the database are not touched by deploys.
- **Logs:** service → **Logs** (PHP errors are logged there in production).

## Not production-ready yet / follow-ups

- **Full database dump** from the client is still needed (the repo dump only has the `sys_*` tables).
- **`.env` is tracked in git.** Remove it from the repo (`git rm --cached .env`)
  and rotate `SSO_CLIENT_SECRET` and `SSO_USER_API_KEY`.
- **No `composer.lock`:** each build resolves dependency versions fresh, so two
  deploys can get different library versions. Commit a lock file for
  repeatable builds.
- **For the development team** (code issues found during deployment; worked
  around at deployment level, not changed):
  - `config/autoload/local.php`: DSN uses `hostname=` (should be `host=`).
  - Controllers' `init()` doesn't `return` after redirecting to login.
  - `public/index.php` hard-codes `display_errors=1`.
  - `composer.json` says `"php": "^7.3"`, but the code needs PHP 8.0.
  - `module/Accounts/view/accounts/party-adjustment/getsalesmemo.phtml` has a
    PHP syntax error (line 42).
  - The repo's `vendor/` folder is incomplete (the image rebuilds it).
