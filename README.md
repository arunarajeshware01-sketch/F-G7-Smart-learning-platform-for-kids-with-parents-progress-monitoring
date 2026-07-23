# Smart Learning

A learning platform for Class 1–5 students, with three dashboards:
**Student**, **Parent**, and **Admin**. Built with a PHP + MySQL backend
and a plain HTML/CSS/JS frontend (no framework, no build step).

## Project structure

```
smart-learning/
├── frontend/
│   ├── index.html, login.html, register.html
│   ├── student-dashboard.html
│   ├── parent-dashboard.html
│   ├── admin-dashboard.html
│   ├── styles.css              (shared styles across all pages)
│   ├── css/
│   │   ├── student-dashboard.css
│   │   ├── parent-dashboard.css
│   │   └── admin-dashboard.css
│   └── js/
│       ├── api.js              (shared fetch helpers, session guard)
│       ├── student-dashboard.js
│       ├── parent-dashboard.js
│       └── admin-dashboard.js
├── backend/
│   ├── api/
│   │   ├── login.php, logout.php, session.php
│   │   ├── register_student.php, register_parent.php
│   │   ├── classes.php               (public: live class list)
│   │   ├── student/                  (student-facing endpoints)
│   │   ├── parent/                   (parent-facing endpoints)
│   │   └── admin/                    (admin-only endpoints)
│   ├── includes/common.php           (DB include, session/auth helpers, JSON response helper)
│   ├── config/db.php                 (DB connection settings)
│   ├── database.sql                  (full schema — run this to set up a fresh DB)
│   ├── add_classes_table.sql         (one-time migration if your DB predates the classes feature)
│   ├── add_daily_activity_table.sql  (one-time migration for the parent activity chart)
│   ├── fix_stale_progress.sql        (one-time repair for stale "completed" counts)
│   └── uploads/                      (videos/ and materials/ — files uploaded via admin)
├── GIT_WORKFLOW.md                   (how our 5-person team uses git for this submission)
└── README.md                         (this file)
```

## Local setup (XAMPP)

1. Copy this whole `smart-learning` folder into `C:\xampp\htdocs\`
   (or your OS's equivalent `htdocs` path).
2. Start **Apache** and **MySQL** from the XAMPP control panel.
3. Open `http://localhost/phpmyadmin`, create a database named
   `smart_learning`, and import `backend/database.sql`.
4. Open `http://localhost/smart-learning/frontend/index.html` in your
   browser.
5. Register a student/parent account, or log in as admin (see your
   team's shared admin credentials / whatever you seeded in the DB).

If you pull changes that include a new `add_*.sql` file in `backend/`,
run that file once in phpMyAdmin's SQL tab — it's a migration for
existing databases so you don't have to recreate yours from scratch.

## Team workflow

See [`GIT_WORKFLOW.md`](./GIT_WORKFLOW.md) for exact git commands and
our task split, so all 5 of us can pull/push without stepping on each
other's files.
