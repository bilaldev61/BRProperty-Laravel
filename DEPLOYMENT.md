# Deploying to Hostinger (shared hosting)

Shared hosting has **PHP, Composer, Git and MySQL, but no Node**. So the compiled
CSS/JS is committed to the repo (`public/build`) and built on your machine, never
on the server.

---

## 1. Add your SSH key to Hostinger

You already have a key at `~/.ssh/id_ed25519` — the same one that pushes to
GitHub. Reuse it.

```bash
cat ~/.ssh/id_ed25519.pub
```

In **hPanel → Advanced → SSH Access**: switch SSH on, note the **IP, port and
username** (the port is usually `65002`, the username `uXXXXXXXXX`), then paste
the public key into *SSH Keys → Import SSH Key*.

Save yourself typing it every time — in `~/.ssh/config` on your machine:

```
Host hostinger
    HostName YOUR_SERVER_IP
    User uXXXXXXXXX
    Port 65002
    IdentityFile ~/.ssh/id_ed25519
```

Then `ssh hostinger` gets you in.

### Let the server pull from GitHub

The server needs its own key to reach GitHub:

```bash
ssh hostinger
ssh-keygen -t ed25519 -C "hostinger-deploy" -f ~/.ssh/id_ed25519 -N ""
cat ~/.ssh/id_ed25519.pub
```

Add that output to GitHub as a **deploy key**: repo → Settings → Deploy keys →
Add deploy key. Read-only is enough. Then confirm:

```bash
ssh -T git@github.com     # "Hi <your-github-username>!"
```

---

## 2. Create the database

**hPanel → Databases → MySQL Databases.** Create a database and a user, give the
user all privileges, and keep the three values — Hostinger prefixes them, so they
look like `u123456789_estate`.

---

## 3. Clone the app

Laravel must **not** be cloned straight into `public_html`: that would expose
`.env`, `storage/` and every source file to the open web. Clone it alongside, and
point the web root at `public/`.

```bash
ssh hostinger
cd ~/domains/yourdomain.com

git clone git@github.com:<your-github-username>/brproperty.git app
cd app

composer install --no-dev --optimize-autoloader

cp .env.hostinger.example .env
nano .env                    # fill in APP_URL and the three DB values
php artisan key:generate

php artisan migrate --force
php artisan db:seed --force  # demo listings; skip if you are adding real ones
php artisan storage:link

chmod -R 775 storage bootstrap/cache
```

### Point `public_html` at `public/`

```bash
cd ~/domains/yourdomain.com
rm -rf public_html
ln -s app/public public_html
```

If Hostinger refuses to serve through the symlink, use the fallback instead:

```bash
cd ~/domains/yourdomain.com
mkdir -p public_html
cp -r app/public/* app/public/.htaccess public_html/
```

and edit `public_html/index.php`, changing the two `__DIR__.'/../'` paths to
`__DIR__.'/../app/'`. The symlink is cleaner — try it first.

---

## 4. Every deploy after that

On **your machine**, whenever you touch anything under `resources/`:

```bash
npm run build
git add public/build
git commit -m "Rebuild assets"
git push
```

On the **server**:

```bash
ssh hostinger
cd ~/domains/yourdomain.com/app
./deploy.sh
```

`deploy.sh` pulls, installs, migrates, re-links storage and rebuilds the caches.

> **Never run `migrate:fresh` on the server.** It drops every table. `deploy.sh`
> uses `migrate --force`, which only applies new migrations.

---

## Automatic deploys (GitHub Actions)

Every push to `main` runs `.github/workflows/deploy.yml`:

1. **Tests** - the full PHPUnit suite on PHP 8.4, plus a check that the
   committed `public/build` matches a fresh `npm run build`.
2. **Deploy** - only if tests pass: one SSH connection to the server, which
   runs `git pull` and `./deploy.sh`.
3. **Check** - fails the run if the site does not answer HTTP 200.

Watch runs under the repo's **Actions** tab. To redeploy without a new commit,
open the workflow there and press **Run workflow**.

**The deploy key.** GitHub holds a private key (`DEPLOY_SSH_KEY` secret) whose
public half sits in the server's `~/.ssh/authorized_keys` behind a `restrict`
and a forced `command=`. Whatever a connection asks for, that key can only
pull and deploy - no shell, no file access. To revoke it, delete the line
ending `github-actions-deploy@estate.brealstate.example` from that file.

Still run `npm run build` and commit `public/build` before pushing front-end
changes - the server has no Node, and the workflow will refuse to deploy a
stale build.

---

## 5. Before you call it live

- [ ] `APP_DEBUG=false` and `APP_ENV=production` in the server `.env`
- [ ] **Change the admin password** — `DEMO_LOGIN_PASSWORD` in `.env`, and the
      real user's password: `php artisan tinker` →
      `App\Models\User::first()->update(['password' => bcrypt('...')]);`
- [ ] Set `DEMO_LOGIN=false` if the site stops being a demo
- [ ] Turn on **SSL** in hPanel → Security → SSL, and make `APP_URL` `https://`
- [ ] Replace the placeholder figures, bio and licence in **Settings → Business**
- [ ] Submit `https://yourdomain.com/sitemap.xml` in Google Search Console
- [ ] Check `https://yourdomain.com/storage/branding/...` loads — if not, the
      `storage:link` symlink did not take
- [ ] **AI features (optional)** — set `OPENROUTER_API_KEY` (or `ANTHROPIC_API_KEY`)
      in `.env` to switch on the reply drafts and the public chat assistant. Leave
      both blank and neither appears. Keep credit on the OpenRouter key: when it
      runs dry, visitors get your phone number instead of an answer. `AI_CHAT_ENABLED=false` keeps the drafts but hides the assistant;
      `AI_CHAT_MODEL_ENABLED=false` keeps the assistant but serves every reply from
      the free rule layers (listing facts, searches) and hands the rest to your
      phone number — the fastest way to stop spend without removing the key;
      `AI_CHAT_DAILY_LIMIT` caps how many visitor messages it answers per day.
- [ ] **Email** — create a mailbox in hPanel → Emails (e.g. `noreply@yourdomain.com`),
      then set `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.hostinger.com`, `MAIL_PORT=465`,
      `MAIL_SCHEME=smtps`, `MAIL_USERNAME`/`MAIL_FROM_ADDRESS` to that address and
      `MAIL_PASSWORD` to its password, then `php artisan config:cache`. Until then
      `MAIL_MAILER=log` sends nothing, and the enquiry confirmation email is skipped.
- [ ] **Scheduler cron** — hPanel → Advanced → Cron Jobs, every minute:
      `cd ~/domains/yourdomain.com/app && /opt/alt/php84/usr/bin/php artisan schedule:run >> /dev/null 2>&1`
      (adjust the path to where you cloned the app). Use the full PHP 8.4 path:
      the plain `php` on Hostinger's command line can be older than the site's. It deletes anonymous
      assistant chats after 90 days.

---

## If something breaks

| Symptom | Cause |
|---|---|
| 500, blank page | `storage/` not writable → `chmod -R 775 storage bootstrap/cache` |
| Site loads unstyled | `public/build` missing → run `npm run build` locally and commit it |
| "No application encryption key" | `php artisan key:generate` on the server |
| Uploaded images 404 | `php artisan storage:link` |
| Changes to `.env` do nothing | `php artisan config:clear && php artisan config:cache` |
| Directory listing instead of the site | `public_html` is not pointing at `app/public` |
