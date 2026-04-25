# InMessage — Project Architecture

**Owner:** Sanusi Emmanuel (GitHub: vikcy112)  
**Live domain:** https://app.inmessage.xyz  
**VPS:** 72.62.5.152 (Hostinger KVM 2, Ubuntu 24.04, HestiaCP)

---

## Repository Structure

| Repo | URL | Purpose |
|------|-----|---------|
| `inmessage-backend` | https://github.com/vikcy112/inmessage-backend | Laravel 10 REST API |
| `inmessage-app` | https://github.com/vikcy112/inmessage-app | Flutter app (iOS + Android) |

> Note: These repos were migrated from `awaitANTHONY/dating-backend` and `awaitANTHONY/inmessage-app` in April 2026. Full commit history preserved.

---

## Backend (Laravel)

- **Framework:** Laravel 10 / PHP 8.x
- **Database:** MySQL (on VPS)
- **Storage:** Local disk + VPS filesystem
- **Auth:** Laravel Sanctum (token-based)
- **VPS path:** `/home/eisasemmanuel/web/app.inmessage.xyz/public_html/`

### Deploy to VPS
```bash
ssh root@72.62.5.152
cd /home/eisasemmanuel/web/app.inmessage.xyz/public_html
git pull origin main
php artisan cache:clear
php artisan config:clear
php artisan route:clear
# Only if there are new migrations:
php artisan migrate --force
```

### SSH Access
- Key: `~/.ssh/id_ed25519` (registered in Hostinger panel)
- User: `root`
- Host: `72.62.5.152`
- Default port: `22`

---

## Flutter App

- **Framework:** Flutter (Dart)
- **State management:** GetX
- **API base URL:** `https://app.inmessage.xyz` (in `lib/global/connection/client/dio_endpoints.dart`)
- **Local path:** `/Users/sanusi/Documents/Flutter/ Apps/inmessage-app/`

### Push app updates
```bash
cd "/Users/sanusi/Documents/Flutter/ Apps/inmessage-app"
git add -A
git commit -m "your message"
git push origin main
# Then build and submit to App Store / Play Store
```

---

## Key Settings (controlled from DB — no app update needed)

These values live in the `settings` table and are read by the API:

| Setting key | Default | Description |
|-------------|---------|-------------|
| `match_expiry_hours_free` | 48 | Hours until a free user's match expires |
| `match_expiry_hours_premium` | 168 | Hours until a premium user's match expires |
| `match_free_visible_count` | 3 | How many matches free users can see |
| `match_revive_coin_cost` | 5 | Coins to revive an expired match |

To change a setting live (no downtime, no app update):
```bash
ssh root@72.62.5.152
cd /home/eisasemmanuel/web/app.inmessage.xyz/public_html
php artisan tinker --execute="DB::table('settings')->where('name','match_revive_coin_cost')->update(['value'=>'5']);"
php artisan cache:clear
```

---

## Activity Tabs — API Endpoints

| Tab | Endpoint | Notes |
|-----|----------|-------|
| Likes | `GET /api/v1/user-interactions` | Returns received likes |
| Matches | `GET /api/v1/matches` | Returns `{active, expired, limits}` |
| Views | `GET /api/v1/profile/visitors` | Profile visitors |
| Connect | `GET /api/v1/direct-connect/received` | Direct connect requests |

---

## Known History / Bug Fixes

- **Apr 2026**: Fixed Matches tab empty — Flutter expected `{active, expired, limits}` format, backend returned flat array. Fixed in `UserInteractionController::getMatches()`.
- **Apr 2026**: Fixed Likes/Views 500 crash — 106 fake profiles were hard-deleted, leaving orphan interaction records. Added null guards in `UserInteraction`, `UserMatch`, `ProfileController`.
- **Apr 2026**: Added `POST /api/v1/matches/{id}/revive` endpoint.
- **Apr 2026**: Fixed "Get More Coins" button opening Earn Coins page instead of Buy Coins sheet (Flutter fix, requires app update).
- **Apr 2026**: Migrated repos from developer account (`awaitANTHONY`) to owner account (`vikcy112`).

---

## For Future Developers / AI Agents

1. **Never hard-delete users** — always soft-delete or the interaction records will orphan and crash endpoints.
2. **Settings are cached forever** — always run `php artisan cache:clear` after changing the `settings` table.
3. **Backend-only changes** deploy via `git push` + `git pull` on VPS — no app store submission needed.
4. **Flutter changes** always require a new app build and store submission.
5. The `awaitANTHONY` GitHub account belonged to the original developer — **do not push there**. All work goes to `vikcy112`.
