# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## HMA Workspace — Project Context

Portal internal perusahaan (Hana Tekindo). **Laravel 12 + Livewire 3/Volt + Flux UI Free + Tailwind v4 (CSS-first, no `tailwind.config.js`)**. Auth via Fortify (2FA + email verification). DB lokal: MySQL `hma_workspace`. Cache & queue: **Redis (predis)**. Served by Laravel Herd → `https://hma-workspace.test`.

### Commands

- **Dev (semua sekaligus):** `composer run dev` → concurrently menjalankan `serve` + `queue:listen` + `pail` (logs) + `vite`. Herd juga menyajikan aplikasi di `https://hma-workspace.test`; jangan menjalankan server HTTP tambahan.
- **Setup baru:** `composer run setup` memasang dependency, membuat `.env`, generate key, migrate, install npm, dan build asset; gunakan hanya dengan izin eksplisit untuk aksi database.
- **Frontend only:** `npm run dev` (Vite 7) / `npm run build`. Entry: `resources/css/app.css` + `resources/js/app.js`.
- **Test:** `php artisan test --compact` (Pest 4). Filter: `--filter=namaTest` atau path file. Test pakai SQLite `:memory:`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, Pulse/Telescope off (lihat `phpunit.xml`).
- **Format (wajib sebelum finalisasi):** `vendor/bin/pint --dirty --format agent`.
- **Queue:** restart `queue:work` tiap ubah Job/dependency (lihat memory queue-worker-restart).
- **Artisan khusus project files:**
  - `php artisan projectfiles:reconcile {project} [--dry-run]` — sinkronkan mapping file DB ↔ BEPM.
  - `php artisan projectfiles:backfill-sizes {project?|--all} [--dry-run]`
  - `php artisan projectfiles:backfill-folders {project?|--all} [--dry-run]`
  - `php artisan project-files:cleanup-multipart` (juga terjadwal `daily()` di `routes/console.php`)
  - `php artisan permissions:sync-super-admin`

### Arsitektur data hybrid (paling penting)

- DB lokal HANYA: RBAC (users/roles/permissions/departments), tasks/task_assignments, knowledge (`support_*`), notifications/push_subscriptions, activity_log, virtual folder files (`project_folders`/`project_folder_files`/`project_file_sizes`).
- **Projects, Companies, Izin, DAR, Cash Advance TIDAK di DB lokal** — dari API eksternal (`config/services.php`):
  - **BEPM** (projects/companies/users): `API_PROJECT` = `bepm.hanatekindo.com/api/v2/`, bearer `TOKEN_KEY`.
  - **DARBE** (izin/DAR): `API_IZIN` = `darbe.hanatekindo.com`.
- `project_folders.project_id` sengaja **tanpa FK** — merujuk project BEPM eksternal. File MinIO: key flat + ULID immutable, lokasi/ukuran di DB (lihat memory hma-folder-virtual-architecture).
- `DatabaseSeeder` menarik users dari BEPM API → butuh token + jaringan saat seeding.

### Services layer (`app/Services/`) — JANGAN panggil `Http` langsung di komponen

- **Cache/Writer pairs** (singleton di `AppServiceProvider::register()`): `ProjectCache`/`ProjectWriter`, `IzinCache`/`IzinWriter`, `DarCache`/`DarWriter`, `CaCache`/`CaWriter`. Cache berbasis **tag**; Writer memanggil `flush*()` pada Cache setelah mutasi.
- Concerns: `MakesExternalRequests` → `externalRead()` (timeout 3s, **retry 5xx** karena GET idempotent) vs `externalWrite()` (15s, **JANGAN retry 5xx** — POST/PUT non-idempotent). `CachesFlexibly::rememberFlexible()` → SWR (`Cache::flexible`) + simpan `:last_good` forever, kembalikan last-good saat API down.
- Resolusi di Volt: `app(ProjectWriter::class)` atau method injection di `mount()`.
- Cash Advance memakai `CaCache`/`CaWriter` dan composer PDF terkait; Pengajuan Barang masih dummy melalui `PengajuanBarangDummy`.
- Lain: `ProjectFileStorage`, `SpdPdfComposer` (FPDF/FPDI), `PermissionCache`, `DarNotifier`, `NotificationService`, `RemoteImageFetcher`.

### Struktur folder

- **Volt single-file components** → `resources/views/livewire/**` (mostly class-based: `new class extends Component`; contoh `project/project-show.blade.php`). UI baru = Volt.
- **Class-based Livewire** (`app/Livewire/`): `Events/EventShow`, `Settings/*`, `Forms/*` (ActivityForm, FilesForm, SpectechForm), `Actions/Logout`.
- `app/Jobs/` (queued `ShouldQueue`): register/delete user ke API PM & Izin, `SendWhatsappJob`, `DeleteProjectFilesJob`, `RegisterProjectDocJob`.
- `app/Notifications/` — webpush + database channel. `app/Http/Controllers/` tipis (upload file project + SPD PDF). Middleware alias `mobile` = `EnsureMobileDevice`.
- `app/Helpers/helpers.php` (autoloaded): `formatFileSize`, `fileExtMeta`, `isImageFile`, `project_storage_year`, `project_doc_keywords`, `colorFromSeed`, `getErrorMessages`.

### Otorisasi / RBAC

- `Role->level` (hierarki) + permission many-to-many (`role_permission`) + `departments` (punya `parent`/`code`).
- `User`: `hasPermission()`, `hasRole()`, `hasAnyPermission()`, `viewScopeFor($module)` → prioritas `all` > `department` > `own` (default `own`), `canAccessProject($project)`, `isInDepartment($code)`.
- **Super-admin bypass** di dua tempat: `User::hasPermission()` dan `Gate::before` (permissions di-cache 30 menit per user). Gates eksplisit: `viewPulse`, `viewActivityLog`.
- Policies untuk model `Support*` (knowledge). Cek permission di dalam action **sebelum** menulis (pola: guard → `Toaster::warning` → return).
- Fortify views custom di `FortifyServiceProvider` → semua `livewire/auth/*`. 419 → redirect `login` dengan flash (`bootstrap/app.php`).

### Database lokal (kunci)

- **Jangan menjalankan aksi yang menyentuh database** (migrate, seed, test, `config:cache`, tinker write, atau perubahan data) tanpa izin eksplisit pengguna.
- `roles` (slug, name, level, department_id, is_system, can_approve) · `permissions` · `role_permission` · `departments`.
- `tasks` (priority/status enum, assigned_by/updated_by) + `task_assignments` (status pending/accepted/rejected, unique [task_id,user_id]).
- `support_*` (announcements/articles/policies/documentation) · `notifications` · `push_subscriptions` · `activity_log` (spatie) · pulse tables.

### Konvensi & pola berulang

- Ikuti sibling files dan struktur Laravel yang sudah ada; jangan menambah dependency tanpa persetujuan.
- Untuk pertanyaan Laravel/Livewire/Flux/Tailwind, gunakan Laravel Boost `search-docs` terlebih dahulu; gunakan `list-artisan-commands` sebelum memanggil Artisan dengan opsi yang belum diverifikasi.
- Flux UI Free dulu, fallback Blade. Dark mode `dark:` (Flux pakai `.dark` class variant). Font Poppins; `--color-accent: red-800`; `zinc` di-remap ke `slate` di `app.css`.
- Flash message: **`Masmerise\Toaster\Toaster`** (`Toaster::success/error/warning`).
- Activity logging via concern `LogsModelActivity` (override `activityLogName/Label/Except`) — spatie/laravel-activitylog + UI.
- Volt attributes yang dipakai: `#[Lazy]`, `#[On]`, `#[Computed]`.
- Bahasa domain Indonesia: "izin" = cuti/perizinan, "perusahaan" = company, "spektek/spectech" = spesifikasi teknis, "SPD" = surat perjalanan dinas, "DAR" = daily activity report.

### File uploads (project docs → MinIO)

- **Direct multipart ke MinIO** via Uppy (`resources/js/project-file-uploader.js`, `@uppy/aws-s3`): control-plane endpoints `projects/{project}/files/uploads` initiate/sign/complete/abort (`ProjectFileUploadController`, Form Requests di `app/Http/Requests/ProjectFiles/`). Fallback proxy chunk: `project-files/upload-chunk`.
- Path storage pakai `project_storage_year($project)` (start_date → contract_date → created_at). Signed HEAD ditolak proxy publik MinIO → hindari Flysystem `move/exists` (memory minio-public-proxy-head-403).
- Excel: `resources/js/excel-import.js` + SheetJS (`xlsx`). Rich text: CKEditor 5.

### Testing

- Pest 4, feature-heavy (`tests/Feature/`). Project punya suite "Wave A–E" + banyak test Spectech/Dar/Izin/ProjectFiles. External API dimock via `Http::fake()`.
- Setiap perubahan wajib bertes; jalankan subset via `--filter` dulu.

### Gotchas

- Tailwind v4 CSS-first — **tidak ada `tailwind.config.js`**; theme di `@theme` dalam `app.css`.
- `trustProxies(at: '*')` untuk nginx; di production `URL::forceRootUrl/forceScheme(https)` (`AppServiceProvider::boot`).
- Redis wajib untuk cache tag (`Cache::tags`) & flexible cache — driver `array`/`file` tidak mendukung tags di runtime (test pakai `array`, jadi service di-mock).
- `AGENTS.md` & `GEMINI.md` = salinan blok boost-guidelines untuk tool lain; jangan bingung dengan konteks project ini.
