# myself

Complete PHP MySQL + React social media starter for Hostinger/XAMPP.

## Setup

1. Create/import the database using `database.sql`.
2. Edit database credentials in `api/config/db.php`.
3. Install frontend packages:
   ```bash
   npm install
   ```
4. Development:
   ```bash
   npm run dev
   ```
5. Production build:
   ```bash
   npm run build
   ```

For XAMPP local frontend development, run `npm run dev` and open the Vite URL. After `npm run build`, the production frontend is in `dist`, so XAMPP can serve `http://localhost/app/dist`.

## Defaults

- App/site/database name: `myself`
- Timezone: `Asia/Kolkata`
- Admin email: `admin@myself.local`
- Admin password: `admin123`

## SEO and compliance

- Public SSR post URLs: `http://localhost/app/post/{slug}`
- Public SSR profile URLs: `http://localhost/app/user/{username}`
- Sitemap index: `http://localhost/app/sitemap.xml`
- Posts sitemap: `http://localhost/app/sitemap-posts.xml`
- Profiles sitemap: `http://localhost/app/sitemap-profiles.xml`
- Robots: `http://localhost/app/robots.txt`
- Apply `migrations_seo_18_plus.sql` to an existing `myself` database created before this update.
- Apply `migrations_social_friends_chat.sql` for unique 10-digit IDs, friends, message media, chat coin rules, and notification upgrades.
- Apply `migrations_ui_messaging_fixes.sql` to randomize old numeric IDs and enable the latest chat/navbar UI rules.
- Apply `migrations_secure_social_wallet_admin.sql` for reactions, withdrawals, admin metrics, badges, safer user metadata, and richer chat records.
- Apply `migrations_personal_gifs.sql` for per-user private GIF galleries in chat.
- Apply `migrations_comment_like_chat_delete.sql` for comment likes, comment replies display, and chat message selection/delete.
- Apply `migrations_online_audio_comments_live.sql` for `mysocialmedia`, live comments, audio post media, heartbeat, online status, and last seen support.
- Apply `migrations_stories_module.sql` for the complete 18+ Stories module, multi-admin roles, publishing workflow, engagement, moderation, and unique views.
- Apply `migrations_audio_calls.sql` for secure one-to-one voice call logs.

## Audio calls

Audio calls use WebRTC peer-to-peer audio with the free Google STUN server and
an authenticated Socket.io signaling service. Start the signaling service
alongside Apache:

```powershell
npm.cmd run call-server
```

The default local signaling URL is `http://localhost:3001`. Set
`VITE_CALL_SIGNALING_URL` before `npm.cmd run build` when deploying it
elsewhere. TURN is optional; see `signaling-server/README.md` for its opt-in
variables. Production microphone access requires HTTPS and the signaling server
must be exposed over WSS. Shared hosting must support a persistent Node.js
process; otherwise deploy only the signaling service on a Node-capable host.

## Voice and video rooms

- Apply `migrations_rooms_system.sql` for the full room database module.
- Rooms list: `http://localhost/app/dist/rooms`
- Create room: `http://localhost/app/dist/rooms/create`
- Realtime room events run on the same Socket.io server:
  ```powershell
  npm.cmd run call-server
  ```
- Browser-uploaded room videos are saved under `uploads/rooms/videos`.
- Locked room passwords are stored with PHP `password_hash`; validation is server-side only.

## Stories and admin

- Stories SPA: `http://localhost/app/dist/stories`
- Crawlable Stories listing: `http://localhost/app/stories`
- Crawlable Story detail: `http://localhost/app/stories/{slug}`
- Admin login: `http://localhost/app/admin/login`
- Admin registration: `http://localhost/app/admin/register` (Super Admin only)
- Admin portal is separate from public user login/registration. Legacy `/app/dist/admin/...` links redirect to the clean admin portal.

## Friend finder discovery

- Apply `migrations_friend_finder_discovery.sql` for gender, approximate location, discovery preferences, and transparent demo-profile fields.
- Seed 50 female and 50 male clearly labeled Demo / AI profiles:
  ```bash
  C:\xampp\php\php.exe seed_demo_users.php
  ```
- Nearby API: `/app/api/nearby-users.php`
- Filtered search API: `/app/api/search-users.php`
- Location update API: `/app/api/update-location.php`
- Exact latitude/longitude values are used only for server-side ranking and are never returned in discovery cards.
- Default Super Admin: `admin@myself.local` / `admin123` (change this immediately outside local development)

## Hostinger

Upload the built `dist` contents as the public site files, keep the `api` folder beside it, import `database.sql`, then update `api/config/db.php` with Hostinger database credentials.
