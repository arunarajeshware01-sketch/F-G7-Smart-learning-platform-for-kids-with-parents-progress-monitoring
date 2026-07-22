# Smart Learning Platform — Backend Setup Guide (XAMPP)

This package adds a working PHP + MySQL backend to your existing front-end
(the pages from your zip file, lightly wired up to call it) and matches the
SRS you shared.

## What you got

```
smart-learning/
├── frontend/              ← your HTML/CSS pages (login & register now call the real backend)
│   ├── index.html, login.html, register.html
│   ├── student-dashboard.html, parent-dashboard.html, admin-dashboard.html
│   ├── styles.css
│   └── js/api.js          ← shared helper that all pages use to talk to the backend
│
└── backend/
    ├── database.sql               ← import this to create the MySQL database
    ├── config/db.php              ← DB connection settings
    ├── includes/common.php        ← shared session/JSON helpers
    ├── uploads/videos/, uploads/materials/
    └── api/
        ├── register_student.php   (FR1)
        ├── register_parent.php    (FR2)
        ├── login.php               (FR3 — role-based: student/parent/admin)
        ├── logout.php
        ├── session.php             (used to guard dashboard pages)
        ├── student/
        │   ├── dashboard.php       (FR4 — subjects, lessons, quiz scores, progress, daily goal)
        │   ├── content.php         (lessons/videos/quizzes for a subject)
        │   ├── update_progress.php (log lesson completion / learning time)
        │   ├── quiz_get.php
        │   └── quiz_submit.php
        ├── parent/
        │   ├── link_child.php      (Link Child Account)
        │   ├── dashboard.php       (children + progress + quiz results + notifications)
        │   └── report.php          (daily / weekly / monthly report)
        └── admin/
            ├── students.php, parents.php, subjects.php  (manage users/subjects)
            ├── lessons.php, videos.php                  (upload study material & videos)
            ├── quizzes.php                              (create quizzes with questions)
            ├── notifications.php                        (send notifications)
            ├── reports.php                              (platform-wide report)
            └── backup.php                               (database backup)
```

## Step 1 — Install the files into XAMPP

1. Install/open **XAMPP** and start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Copy the whole `smart-learning` folder into your XAMPP `htdocs` directory, so you end up with:
   - Windows: `C:\xampp\htdocs\smart-learning\`
   - Linux/XAMPP: `/opt/lampp/htdocs/smart-learning/`
   - Mac (MAMP/XAMPP): `/Applications/XAMPP/htdocs/smart-learning/`

## Step 2 — Create the database

1. Open **phpMyAdmin**: go to `http://localhost/phpmyadmin`.
2. Click **Import** → **Choose File** → select `smart-learning/backend/database.sql` → **Go**.
3. This creates a database called `smart_learning` with all tables, the 4 fixed
   subjects (English, Mathematics, EVS, Basic Computer Knowledge), and one
   default admin login:
   - **Email:** `admin@smartlearning.com`
   - **Password:** `Admin@123`
   - *(Change this password after your first login — see "Adding an admin" below.)*

## Step 3 — Check the database credentials

Open `backend/config/db.php`. By default it uses XAMPP's defaults
(`root` user, empty password). If you've set a MySQL root password, update:

```php
define('DB_USER', 'root');
define('DB_PASS', 'your_password_here');
```

## Step 4 — Point the front-end at the backend

Open `frontend/js/api.js` and confirm this line matches where you placed the
project:

```js
const API_BASE = "http://localhost/smart-learning/backend/api";
```

## Step 5 — Run it

Visit: `http://localhost/smart-learning/frontend/index.html`

- **Register** a parent and a student (use the **same email** for "Parent email"
  on the student form and the parent's own login email — they'll auto-link).
- **Log in** on the matching tab (Student / Parent / Admin). Successful login
  redirects to the right dashboard; the dashboards now check your session and
  bounce you back to `login.html` if you're not logged in as the right role.
- **Admin** logs in with `admin@smartlearning.com` / `Admin@123`.

## Important note vs. the original SRS

FR1 (student registration) didn't originally collect a student email, but FR3
says every role logs in with **email + password**. To make login actually
work for students, a **"Student login email"** field was added to the
registration form and to the `students` table. Everything else follows the
SRS as written.

## How the pieces fit together

- Every backend endpoint returns JSON: `{ "success": true/false, "message": "...", ...data }`.
- Login uses a PHP **session** (a cookie), so once logged in, subsequent
  requests (e.g. `student/dashboard.php`) know who you are — no token needed.
- `frontend/js/api.js` provides three helpers used across pages:
  - `apiPost(endpoint, data)` — JSON POST (login, register, submit quiz, etc.)
  - `apiGet(endpoint)` — JSON GET (dashboards, reports, session check)
  - `apiUpload(endpoint, formData)` — file upload (admin video upload)
  - `guardPage(role)` — put at the top of a dashboard's script to require login
  - `logout()` — clears the session

### Example: pulling live data into a dashboard

The dashboards you sent were static demo pages. The backend is fully ready to
feed them real data — you just need to call the right endpoint and drop the
result into the page. Example for the student dashboard:

```html
<script>
guardPage('student').then(async () => {
  const res = await apiGet('student/dashboard.php');
  if (res.success) {
    console.log(res.student, res.subjects, res.recent_scores, res.total_minutes);
    // e.g. document.querySelector('#studentName').textContent = res.student.name;
  }
});
</script>
```

Do the same pattern for `parent/dashboard.php` and any admin endpoint — call
it, then write the returned fields into the existing page elements (or new
ones) with plain JavaScript.

### Example: admin creating a quiz

```js
await apiPost('admin/quizzes.php', {
  action: 'create',
  subject_id: 2,            // Mathematics
  class_level: 'Class 3',
  title: 'Addition & Subtraction',
  questions: [
    { question_text: '5 + 3 = ?', option_a: '6', option_b: '7', option_c: '8', option_d: '9', correct_option: 'C' }
  ]
});
```

### Example: admin uploading a video

```js
const form = new FormData();
form.append('subject_id', 1);
form.append('class_level', 'Class 2');
form.append('title', 'The Alphabet Song');
form.append('video', fileInputElement.files[0]);
await apiUpload('admin/videos.php', form);
```

## Database backup (Admin Module)

`backend/api/admin/backup.php` runs `mysqldump` for you. It defaults to the
standard Windows XAMPP path:

```php
define('MYSQLDUMP_PATH', 'C:\\xampp\\mysql\\bin\\mysqldump.exe');
```

If you're on macOS/Linux XAMPP, open that file and swap in the Linux path
that's commented out just below it (e.g. `/opt/lampp/bin/mysqldump`).
Backups are saved to `backend/backups/`.

## Security notes for going beyond a demo

- Passwords are hashed with PHP's `password_hash()` (bcrypt) — never stored in plain text.
- All queries use PDO **prepared statements**, so they're protected from SQL injection.
- Sessions gate every endpoint via `require_login()`.
- Before any real deployment: enable HTTPS, restrict `Access-Control-Allow-Origin`
  in `includes/common.php` to your actual domain (it's `*` for local development),
  and consider rate-limiting login attempts.

## Troubleshooting

| Problem | Fix |
|---|---|
| "Database connection failed" | Make sure MySQL is running in XAMPP and `database.sql` was imported. |
| Login/register does nothing | Open browser DevTools → Console/Network tab; check `API_BASE` in `js/api.js` matches your folder name. |
| CORS errors | Make sure you're opening pages via `http://localhost/...` (not double-clicking the HTML file directly) so cookies/session work. |
| Video upload fails | Check `backend/uploads/videos/` folder exists and is writable; check PHP's `upload_max_filesize` in `php.ini` if the video is large. |
