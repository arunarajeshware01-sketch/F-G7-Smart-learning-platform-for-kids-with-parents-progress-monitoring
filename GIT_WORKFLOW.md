# Git workflow — Smart Learning (5-person team)

This is the exact set of commands each of the 5 team members should use.
Follow it in order every time you sit down to work, and every time you're
done working. It's designed to avoid conflicts when 5 people touch the
same project.

---

## 0. One-time setup (only the first time, per person)

If your teammate already created the GitHub repo and added you as a
collaborator:

```bash
git clone https://github.com/YOUR-TEAM/smart-learning.git
cd smart-learning
```

If you're the one creating the repo for the first time:

```bash
cd smart-learning
git init
git add .
git commit -m "Initial project setup"
git branch -M main
git remote add origin https://github.com/YOUR-TEAM/smart-learning.git
git push -u origin main
```

Then go to GitHub → Settings → Collaborators → add your 4 teammates by
their GitHub username/email so they can push.

Tell everyone their **name and email** should be set once on their machine:

```bash
git config --global user.name "Your Name"
git config --global user.email "your@email.com"
```

---

## 1. Every time you start working

**Always pull the latest changes first**, before you touch any file. This
is the #1 rule — skipping this is what causes conflicts.

```bash
git checkout main
git pull origin main
```

Then create your own branch for whatever you're about to work on. Never
work directly on `main` — it keeps everyone's changes isolated until
they're ready.

```bash
git checkout -b yourname-feature-name
```

Example: `git checkout -b priya-notifications-panel`

---

## 2. While you're working

Save your work normally. When you're ready to commit:

```bash
git add .
git commit -m "Short description of what you changed"
```

Examples of good commit messages:
- `"Add manage-quizzes tab to admin dashboard"`
- `"Fix progress bar showing wrong completed count"`
- `"Add classes.php backend endpoint"`

---

## 3. Push your branch and open a Pull Request

```bash
git push -u origin yourname-feature-name
```

Then go to GitHub → you'll see a banner "Compare & pull request" → click
it → add a short description → **Create pull request**.

Ask one teammate to review it (or just merge it yourself if your group
doesn't require review). Once merged on GitHub, delete the branch there
(GitHub shows a "Delete branch" button after merging).

---

## 4. Sync up again before your next session

```bash
git checkout main
git pull origin main
git branch -d yourname-feature-name   # delete your old local branch, it's merged now
```

Then start again from Step 1 for your next task.

---

## If two people edited the same file (merge conflict)

`git pull` or merging a Pull Request will sometimes say there's a
**conflict**. This is normal with a 5-person team — don't panic.

1. Open the file(s) git lists as conflicted. You'll see markers like:
   ```
   <<<<<<< HEAD
   your version
   =======
   their version
   >>>>>>> branch-name
   ```
2. Manually edit the file to keep the correct combined version, and
   delete the `<<<<<<<`, `=======`, `>>>>>>>` lines.
3. Save the file, then:
   ```bash
   git add .
   git commit -m "Resolve merge conflict"
   git push
   ```

**How this project avoids most conflicts:** the admin/student/parent
dashboards each have their JS split into separate files
(`js/admin-dashboard.js`, `js/student-dashboard.js`,
`js/parent-dashboard.js`) and CSS split similarly (`css/*.css`) instead
of one giant HTML file. If you're working on the admin panel and a
teammate is working on the student panel, you're touching completely
different files, so conflicts should be rare. Conflicts mostly happen
when two people edit the exact same file/function at the same time —
so it helps to briefly tell your team in your group chat which file
you're about to work on.

---

## Quick command cheat-sheet

| What you want to do              | Command                                  |
|-----------------------------------|-------------------------------------------|
| Get everyone's latest changes     | `git pull origin main`                    |
| Start a new task                  | `git checkout -b yourname-task`           |
| See what changed                  | `git status`                              |
| Save your changes                 | `git add .` then `git commit -m "..."`    |
| Upload your branch                | `git push -u origin yourname-task`        |
| Switch back to main                | `git checkout main`                       |
| See commit history                 | `git log --oneline --graph --all`         |

---

## Suggested task split for 5 people (avoids file overlap)

| Person | Area                                            | Main files touched |
|--------|--------------------------------------------------|---------------------|
| 1      | Student dashboard                                | `frontend/student-dashboard.html`, `js/student-dashboard.js`, `css/student-dashboard.css`, `backend/api/student/*` |
| 2      | Parent dashboard                                 | `frontend/parent-dashboard.html`, `js/parent-dashboard.js`, `css/parent-dashboard.css`, `backend/api/parent/*` |
| 3      | Admin dashboard — students/parents/subjects/classes | `frontend/admin-dashboard.html`, `js/admin-dashboard.js`, `backend/api/admin/students.php`, `parents.php`, `subjects.php`, `classes.php` |
| 4      | Admin dashboard — content & quizzes              | same admin files, `backend/api/admin/videos.php`, `lessons.php`, `quizzes.php`, `notifications.php` |
| 5      | Auth, landing page, database & submission report | `frontend/index.html`, `login.html`, `register.html`, `backend/database.sql`, `backend/includes/`, writing the project report/README |

If two people (like 3 & 4) both need `admin-dashboard.html`, agree in
your group chat on who edits it first, push quickly, and the other
pulls before starting — that avoids most conflicts on the shared file.
