# Automatic deployment on Plesk (every push to GitHub `main`)

Today someone presses **Pull** in Plesk after every push. Plesk can do it by itself: GitHub calls a Plesk
webhook on every push, Plesk pulls `main` into the site folder and then runs `deploy/deploy.sh --plesk`
(packages, the migrations listed in `deploy/migrations.txt`, caches, checks). One-time setup, about 10 minutes.

## 1. Plesk: switch the repository to automatic deployment

Plesk > **Websites & Domains** > xeasy.vnrseeds.co.in > **Git** > the Xeasy-Staging repository > **Repository Settings**

| Setting | Value |
|---|---|
| Deployment mode | **Automatic** |
| Server path | the site folder (as now, e.g. `/xeasy.vnrseeds.co.in`) |
| Branch | `main` |
| Enable additional deployment actions | **on** |

Additional deployment actions (one line):

```
bash deploy/deploy.sh --plesk >> storage/logs/deploy.log 2>&1
```

Save. Plesk now shows a **Webhook URL** on the repository card (`https://<server>:8443/modules/git/public/web-hook.php?uuid=...`).
Treat it as a secret: anyone who has it can trigger a deploy (only of what is already on GitHub).

## 2. GitHub: call the webhook on every push

GitHub > `mycaaa/Xeasy-Staging` > **Settings** > **Webhooks** > **Add webhook**

| Field | Value |
|---|---|
| Payload URL | the Plesk webhook URL from step 1 |
| Content type | `application/json` |
| SSL verification | enabled (if Plesk on 8443 has a valid certificate; otherwise use the site's own certificate or fix the panel certificate first) |
| Which events | **Just the push event** |
| Active | on |

GitHub sends a test ping at once; the webhook list shows a green tick when Plesk answered.

The repository is private, so Plesk must be able to read it: if the repository was added with an SSH URL, Plesk shows
its public key on the Git page; add it under GitHub > Settings > **Deploy keys** (read-only, *Allow write access* off).
(If Pull already works today, this is already done.)

## 3. Firewall

GitHub must reach the Plesk panel port (8443) from the internet. If 8443 is closed, allow GitHub's webhook addresses
(`https://api.github.com/meta`, list "hooks") or ask for a webhook on port 443.

## 4. Check it

1. Push any small change to `main`.
2. GitHub > Webhooks > Recent deliveries: response 200.
3. Plesk > Git: the repository shows the new commit and "Deployed".
4. On the server: `tail -n 40 storage/logs/deploy.log` ends with `Done.`

## What the deploy script does under Plesk (`--plesk`)

- no `git pull` (Plesk has already placed the files) and no questions;
- finds PHP in `/opt/plesk/php/<version>/bin/php` and Composer (`composer` or Plesk's `composer.phar`);
- treats the subscription user (the user PHP runs as on Plesk) as the web user for `storage/` permissions;
- two pushes close together: the second deploy waits for the first (lock file), they never run at the same time;
- maintenance mode during the update, back up on any error (the error is in `deploy.log`);
- only the migrations listed in `deploy/migrations.txt`, each with `--path`; nothing else in the database.

To pin the PHP version: `PHP=/opt/plesk/php/8.3/bin/php bash deploy/deploy.sh --plesk ...` in the action line.
Reloading PHP-FPM needs root on Plesk; if the log says "reload ... by hand", use Plesk > PHP Settings > *Apply* or
leave it (Plesk's OPcache revalidates changed files within a few seconds by default).

## Production later

Same setup on the live subscription, with **Deployment mode: Manual** until a release is approved, then
*Deploy now* in Plesk. Never point the live site at a branch that receives test pushes.

## PHP upload limits (bill photos)

Plesk's PHP defaults (post_max_size 8M, upload_max_filesize 2M) are too small for several phone photos in one
claim; above them PHP drops the whole form and the claim fails ("page expired" / "server error"). The web screen
now shrinks photos before upload, but PDFs and the app send files as they are. Plesk > xeasy > **PHP Settings**:

| Setting | Value |
|---|---|
| upload_max_filesize | 20M |
| post_max_size | 60M |
| max_file_uploads | 20 |
| memory_limit | 512M |

and, if nginx is in front (Plesk > Apache & nginx Settings > Additional nginx directives): `client_max_body_size 60m;`
