# Deploying Lab Ordering

The app ships as one Docker image that runs as three containers (web, queue worker, scheduler) next to
MySQL and Redis. `docker-compose.yml` wires them together. Anything that runs Docker Compose works: a
single VM is enough to start.

## What runs

| Service     | What it does                                                                 |
|-------------|------------------------------------------------------------------------------|
| `app`       | nginx + PHP-FPM on port 8080. Runs migrations on start when `RUN_MIGRATIONS=true`. |
| `queue`     | Sends emails, SMS and WhatsApp messages (`queue:work`).                     |
| `scheduler` | Expires unpaid orders, sends reminders, prunes idle guest carts (`schedule:work`). |
| `mysql`     | MySQL 8, data in the `mysql` volume.                                         |
| `redis`     | Cache, sessions and the queue, data in the `redis` volume.                   |

Private files (lab reports, prescriptions) live in the `storage` volume, shared by `app`, `queue` and
`scheduler`.

## First deploy

1. Install Docker with the Compose plugin, and copy the repository to the server (for example
   `/srv/lab-ordering`).
2. Create the settings file:
   ```sh
   cp .env.production.example .env
   docker compose build
   docker compose run --rm --no-deps app php artisan key:generate --show
   ```
   Paste the key into `APP_KEY` in `.env`, then fill in `APP_URL`, the database passwords, mail, and the
   payment, SMS and WhatsApp keys you use. Keep `.env` out of version control.
3. Start everything:
   ```sh
   docker compose up -d
   docker compose ps          # app should become "healthy"
   ```
4. Create the first admin: register on the site, then
   ```sh
   docker compose exec app runuser -u www-data -- php artisan user:role you@example.com admin
   ```
5. Sign in, open **Admin → Settings** and set the currency, timezone, invoice details, privacy contact
   and notification channels. Then add service areas or branches, tests and packages.

## HTTPS

The container speaks plain HTTP on port 8080. Put HTTPS in front of it: a load balancer, or a reverse
proxy such as Caddy, Traefik or nginx with Let's Encrypt. Keep `TRUSTED_PROXIES=*` (or the proxy's IP) so
the app knows requests came over HTTPS; it then sends HSTS and secure cookies
(`SESSION_SECURE_COOKIE=true`).

## Payment webhooks

In the payment provider's dashboard, send events to:

- Stripe: `https://your-domain/webhooks/payments/stripe`
  (`checkout.session.completed`, `checkout.session.async_payment_succeeded`, `refund.updated`,
  `charge.refund.updated`)
- Razorpay: `https://your-domain/webhooks/payments/razorpay`
  (`payment_link.paid`, `refund.processed`, `refund.failed`)

Put the signing secret in `STRIPE_WEBHOOK_SECRET` or `RAZORPAY_WEBHOOK_SECRET`.

## Updating

```sh
git pull
docker compose build
docker compose up -d        # migrations run when the new app container starts
```

Queue workers restart with the new code because their containers are replaced.

## Backups

`docker/backup.sh` writes a compressed database dump and an archive of the private files to
`./backups` and deletes ones older than 14 days (`BACKUP_KEEP_DAYS`). Run it nightly from cron:

```cron
15 2 * * * cd /srv/lab-ordering && ./docker/backup.sh >> /var/log/lab-ordering-backup.log 2>&1
```

Copy `./backups` somewhere else as well (object storage or another server).

### Restore

```sh
gunzip -c backups/db-YYYYMMDD-HHMMSS.sql.gz \
  | docker compose exec -T mysql sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
docker compose exec -T app tar -xzf - -C /var/www/html/storage < backups/files-YYYYMMDD-HHMMSS.tar.gz
docker compose exec app chown -R www-data:www-data /var/www/html/storage/app
```

Test a restore on a spare machine now and then.

## Monitoring

- `GET /up` returns 200 when the app is running. Point your uptime monitor at it.
- Logs from all containers: `docker compose logs -f app queue scheduler`.
- **Admin → Dashboard** warns about missing settings and failed messages; **Admin → Notifications →
  History** lists every message sent and lets admins resend failed ones.

## Search engines

`/sitemap.xml` lists the home page, the catalogue, published tests and packages, the FAQ and the
written pages. `/robots.txt` keeps account, admin, cart and checkout pages out and points to the
sitemap. Submit the sitemap in Google Search Console once the site is live.
