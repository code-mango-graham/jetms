# JETMS User Manual

Complete walkthrough of every screen in JETMS (JET Montessori School of Ramon, Incorporated) — every CRUD screen, every process, with real screenshots captured from the running app. Organized by role: **Admin**, **Teacher**, **Student**, plus the standalone **Attendance Kiosk**.

---

## Logging In

**Where:** `index.html` — the app's front door, reachable without a session.

![Login page](docs/manual/login.png)

1. Pick your role at the top: **Admin**, **Teacher**, or **Student**.
2. Enter your username (admin: a chosen username; teacher: their email; student: their LRN) and password.
3. Click **Log in**.

Wrong role/username/password combinations are rejected with an error — the app checks the account exists in that role's table, is not archived/deactivated, and the password matches. On success you land on `mainpage.php`, which is guarded server-side: no session, no access, full stop (hitting the URL directly without logging in redirects back here).

**Default passwords must be replaced.** New accounts start with the password `admin`. The first time someone logs in with it, a *Change Password* window opens that cannot be closed until a new password is set; nothing else in the system works until then. New passwords need at least 8 characters with both letters and numbers, cannot be a common password (`password`, `12345678`...) and cannot equal the username. (On a local test machine this can be switched off with `SEC_FORCE_PASSWORD_CHANGE` in `config.local.php`.)

**Too many wrong passwords** temporarily lock the account and the network address that was guessing; wait a few minutes and try again. Every attempt (good or bad) is recorded under Settings > Security Logs. You are logged out automatically after 2 hours without activity.

---

## School Calendar

**Where:** top navigation bar → **School Calendar**. Available to every logged-in role — Admin, Teacher, and Student all see the same calendar.

![School Calendar](docs/manual/calendar_admin.png)

A month-grid view with Prev/Next/Today navigation. Events are color-coded by type (see the legend at the top: Holiday, Exam, Meeting, Deadline, Other), and days with events show them as small chips directly in the grid.

Click any day to see everything scheduled on it:

![Day detail modal](docs/manual/calendar_day_modal.png)

**Only Admin can create, edit, or delete events** — the Edit/Delete icons and the **Add Event** button only appear for an Admin session. Teachers and Students get the exact same calendar, read-only, including the day-detail popup.

**Adding an event:**
1. Click **Add Event** (top-right) or click a specific day, then **Add Event on This Day**.
2. Give it a title, a start date, and optionally an end date for multi-day events (e.g. a semester break) — leave End Date blank for a single-day event.
3. Pick a type and, optionally, a description.
4. Save — it immediately shows up in the grid and in anyone's Upcoming Events list (including the Admin Dashboard's widget).

---

# ADMIN PANEL

## Dashboard

**Where:** left sidebar → **Dashboard** (also the default landing screen after login).

![Dashboard](docs/manual/dashboard.png)

An at-a-glance overview, all pulled live from real data — nothing here is manually maintained:

- Four stat tiles: students enrolled in the active school year, total collected, outstanding balance (tuition minus payments, active year), and today's attendance count (distinct students scanned today).
- **Recent Payments**, **Recent Enrollments**, and **Recent Announcements** — the latest few of each, so you don't have to go hunting for what just happened.
- **Upcoming Events** — the next few items from the School Calendar (see below), with a **View Full Calendar** shortcut.

---

## Students (CRUD)

**Where:** left sidebar → **Students**.

![Students list](docs/manual/students_list.png)

A DataTable of every student on record, with search, sort, and pagination built in. Each row has:
- A **profile** button (opens the student's full read-only profile in a new tab)
- An **edit** button (pencil icon — opens the same form as Add, pre-filled)

### Add a Student

Click **Add**:

![Add Student modal](docs/manual/students_add.png)

- Upload a 2x2 photo (or use **Open Camera** to capture one live) — auto-cropped and compressed.
- Fill in the student's personal info, parent/contact info, and address (the address fields cascade: pick Province → City/Municipality → Barangay, each dropdown populated from the previous choice).
- **CP No.** and **Contact Number** are restricted to exactly 11 digits — non-digit characters are stripped as you type, and the form won't submit with fewer or more than 11.
- Click **Save**. A duplicate LRN is rejected with a clear error.

Editing works the same way — the same modal opens pre-filled, and **Save** updates instead of inserting.

### View Full Profile

Click the profile icon on any row to open a dedicated, read-only page in a new tab:

![Student full profile](docs/manual/student_full_profile.png)

Shows everything on file for that student — personal data, address, parent/contact info — in one place, useful for printing or a quick look without opening the edit modal.

---

## Enrollment

**Where:** left sidebar → **Enrollment**.

![Enrollment list](docs/manual/enrollment_list.png)

Lists every enrollment record for every school year, with each row's status badge (Enrolled / Dropped / Transferred / Completed) and balance.

### Add Enrollment

Click **Add Enrollment**:

![Add Enrollment modal](docs/manual/enrollment_add.png)

1. Pick the student and the level — the section list and subject list both populate based on the level chosen.
2. For levels with a fixed curriculum, every subject is auto-assigned; for levels that allow subject selection (Senior High), pick individually.
3. Set the tuition fee, and optionally record a downpayment right there (mode, amount, reference number).
4. Save — this creates the enrollment record for the **active school year only** (there's always exactly one active year at a time).

Subject and section **names are frozen at enrollment time** — renaming a subject or section later never rewrites a student's already-recorded enrollment history.

### Enrollment Detail — the real hub of this page

Click the eye icon on any row:

![Enrollment detail modal](docs/manual/enrollment_detail.png)

Three stacked sections in one scrollable modal:

1. **Current Enrollment** — school year, level, section, status, subject list, and three action buttons:
   - **Drop / Transfer** — relabels the enrollment as dropped/transferred and preserves all history (payments, subjects, everything stays on record).
   - **Edit** — corrects the current year's enrollment for a student who is still enrolled: the **tuition**, the **section** (another section of the same level: a mid-year move), and, for levels with subject selection (Senior High), the **subjects** ticked. A short **reason** is required and is recorded in the audit log. Limits: tuition cannot go below what the student has already paid; once grades exist in the current section, the section can no longer be changed, and a subject with grades cannot be removed (Drop/Transfer or keep it). Fixed-curriculum levels cannot pick subjects: they always get every subject of the level.
   - **Cancel Enrollment** — only allowed if **zero payments** have been recorded against it; this is for undoing a mistaken enrollment, not a real withdrawal. If payments exist, cancel is blocked and Drop/Transfer is the only option.
   - **View Full Profile** — jumps to the student's full profile page (same as the Students list).
2. **Payment History** — running Tuition / Paid / Balance summary, an **Add Payment** button (date, amount, mode, reference number — reference number is always shown regardless of mode), and the full payment table for this enrollment.
3. **Enrollment History** — every enrollment this student has ever had, across every school year, with status and remarks — so you can see the whole story (e.g. enrolled → dropped → re-enrolled) at a glance.

### Rules that protect grades and balances

- **Cancelling** an enrollment is refused once the student has any grades in that year and section; use *Drop* or *Transfer* instead, which keep the record.
- **Reactivating** only works for an enrollment of the **active** school year (last year's dropped students must be enrolled again).
- Grades can only be entered for students who belong to that class, and a score cannot exceed its maximum.
- Amounts (tuition, payments) and dates are validated: real calendar dates only, and an upper limit on amounts.
- **A student can never pay more than the tuition.** Payments (new or edited) and the downpayment on the enrollment form are refused when the total would exceed the tuition, and the message says how much room is left.
- **New subjects reach students already enrolled.** When you add a subject to a fixed-curriculum level (Settings > Levels), every student of that level enrolled in the current school year gets it automatically (the confirmation message says how many). For Senior High, tick the subject in the student's **Edit** window.
- **Archived offices and positions** still appear (marked "(archived)") when you edit a teacher who has them, so the teacher can be saved without changing them.

### Reactivate

If a student was dropped/transferred/cancelled in error, reactivating restores an `enrolled` status — surfaced from the same detail view once a record is in a non-active state.

---

## Payments

**Where:** left sidebar → **Payments**. A school-wide, read-only ledger — separate from the per-enrollment payment history above.

![Payments ledger](docs/manual/payments_ledger.png)

- **TOTAL COLLECTED** tile — sums whatever's currently filtered/shown.
- Three combinable filters: **School Year**, **From Date**, **To Date**. Set any combination and click **Apply Filters**; **Clear** resets everything.
- The **Transactions** tab is the full row-by-row ledger: Date, Student, LRN, Level/Section, School Year, Amount, Mode, Reference #, and who recorded it.

Click the **Totals per Date** tab to switch to a per-day rollup:

![Payments totals per date](docs/manual/payments_daily.png)

One row per calendar date — how many payments came in and their combined total — respecting the same filters. Useful for "how much did we collect today/this week" without manually adding rows.

New payments are recorded from the Enrollment detail modal above, against a specific enrollment — but existing ones can be corrected right here. Click the pencil icon on any row:

![Edit Payment modal](docs/manual/payments_edit.png)

- The same Date / Amount / Mode / Reference # fields as recording a payment, pre-filled.
- A **Reason for Edit** field — **required**. Every edit is logged (old values, new values, who, when, and why) to an internal audit trail, so there's always a record of what changed and why, even though the visible row just shows the corrected values.

---

## Attendance

**Where:** left sidebar → **Attendance**. This is a **lookup** screen, not the scanning terminal (that's the separate Kiosk — see below).

![Attendance lookup](docs/manual/attendance_lookup.png)

1. Pick a student from the searchable dropdown.
2. Their attendance log loads below — Date, Time, Type (Time In / Time Out), and Source (`manual` today, ready for `rfid` once a reader is wired in).

The **Open Gate Kiosk** button at top-right jumps to the standalone scanning page in a new tab.

---

## Announcements (CRUD)

**Where:** left sidebar → **Announcements**. Facebook-style feed, admin-authored, visible (in read-only form) to Teachers and Students too.

![Admin announcements feed](docs/manual/announcements_admin.png)

Each post shows the poster's current photo and name, how long ago it was posted (with the exact timestamp on hover), an audience badge, and — for the admin who's logged in — Edit/Delete controls.

### New Post

Click **New Post**:

![New announcement modal](docs/manual/announcements_add.png)

- Title, content (multi-line supported), and an **Audience** selector: All / Teachers Only / Students Only.
- Attach any number of photos — each gets its own delete control once uploaded, and clicking any photo in the feed opens a lightbox instead of a new tab.
- Posting immediately makes the item count toward the unread badge (a red pill on the Announcements nav item) for every Teacher/Student it's targeted at, until they open the feed.

---

## Settings

**Where:** left sidebar → **Admin Settings**. A single hub for every reference/structural table in the app.

![Settings menu](docs/manual/settings_menu.png)

Each item on the left loads its own management screen into the same content area — all follow the same List → Add/Edit modal → Delete pattern described once here and not repeated per-section below unless something's different.

### Users

![Users list](docs/manual/settings_users.png)

A unified view across all three account tables (Admin/Teacher/Student logins). Note: **you don't usually need to create accounts here** — adding a Teacher or Student (above) automatically creates their login account with a default password of `admin`. Everyone created this way is asked to choose their own password at first login. This screen is for the exceptions: extra admin accounts, or manually creating an account for someone whose record already exists but has no login yet.

![Add Account modal](docs/manual/settings_users_add.png)

### Offices

![Offices list](docs/manual/settings_offices.png)

Departments teachers can be assigned to (e.g. "Senior High Department"). Simple name + description CRUD.

![Add Office modal](docs/manual/settings_offices_add.png)

### Positions

![Positions list](docs/manual/settings_positions.png)

Job titles teachers can hold (e.g. "Principal", "Subject Teacher"). Same simple CRUD pattern.

![Add Position modal](docs/manual/settings_positions_add.png)

### Teachers

![Teachers list](docs/manual/settings_teachers.png)

Teacher records — separate from their login account (which gets auto-created, as noted under Users above).

![Add Teacher modal](docs/manual/settings_teachers_add.png)

**Phone Number** is restricted to exactly 11 digits, same rule and same live input-stripping as the Student form.

### School Year

![School Year list](docs/manual/settings_schoolyear.png)

There's always exactly **one active school year** (names must look like `2026-2027`, and you cannot activate an earlier year than the current one or delete a year that already has enrollments or classes) — activating a new one automatically flips every still-`enrolled` record from the previous active year to `completed`, so history stays accurate without manual cleanup.

**Activating a year is a protected action.** Clicking the green check asks you to re-enter your **admin password**, and warns that the change affects the whole school year:

![Activate school year](docs/manual/activate_year_dialog.png)

A full database backup is taken automatically *before* anything changes; if that backup fails, the activation is cancelled. Wrong password: nothing happens (and the attempt is logged).

![Add School Year modal](docs/manual/settings_schoolyear_add.png)

### Levels (and their Sections/Subjects)

![Levels list](docs/manual/settings_levels.png)

Each level (Kinder 1 through Grade 12) has an order (controls sort/progression), a flag for whether students can pick their own subjects (Senior High) vs. a fixed curriculum (everything else), and counts of its sections/subjects.

Click the diagram icon on any level row to manage its Sections and Subjects:

![Manage Sections & Subjects modal](docs/manual/settings_levels_manage.png)

Two independent lists in one modal — add a section name, or a subject name + optional code, each with its own delete control. These are what populate the dropdowns everywhere else in the app (Enrollment, Assignments, etc.).

### Assignments

![Assignments list](docs/manual/settings_assignments.png)

This is where a **Teacher** gets connected to a **Subject** + **Section** for the active school year — the prerequisite for that class showing up anywhere (Teacher's My Classes, Student's My Subjects/My Grades).

![Add Assignment modal](docs/manual/settings_assignments_add.png)

1. Pick a Level — Section and Subject dropdowns populate from it.
2. Pick the Section, Subject, and Teacher.
3. Save. The app blocks assigning the same subject+section twice in one school year (you'd edit the existing assignment instead, to change who teaches it).

Rules the system enforces: only assignments of the **active** school year can be edited; once a class has grades its subject and section can no longer be changed (you may still change the teacher); re-adding an assignment you previously deleted simply brings the old one back, with its grades.

Deleting an assignment here doesn't delete any grades already recorded — it's a soft-remove that just stops it appearing as active.

### Backup & Restore

![Backup and Restore](docs/manual/backups_page.png)

A list of every backup file on the server, newest first, with its type: **Weekly (automatic)** every Sunday at 2:00 AM (the newest 12, about 3 months, are kept), **Manual**, **Before school-year activation**, and **Before a restore**. Each row has a download button and a restore button.

- **Backup Now** asks for your admin password, then saves a full copy of the database:

![Backup confirmation](docs/manual/backup_confirm_dialog.png)

- **Restore** (the red arrow) is the most serious action in the system. It asks you to type `RESTORE` **and** enter your password, and warns that the change affects the whole school year:

![Restore confirmation](docs/manual/restore_confirm_dialog.png)

  The database goes back **exactly** as it was when that backup was made — everything entered since (enrollments, payments, grades, students, accounts) is lost. A safety backup of the current data is saved first, and if the restore fails it is loaded back automatically. You are logged out afterwards. Do it outside school hours. The Security Logs are never rolled back by a restore.

- After 5 wrong passwords within 15 minutes these confirmations lock for 15 minutes.
- Backups don't include uploaded photos — copy the `assets/img` folders separately. Download backups regularly and keep a copy off the server.

### Security Logs

Two tabs, visible to Admin only:

**Change Log** — every create, edit, archive and delete in the system: when, who (name and role), what, and from which IP address. Click the eye icon on a row for the details — for an edit, the exact fields that changed with their before and after values (passwords are never recorded). Payment and grade edits show the reason that was given. Also recorded: backups, restores, school-year activations, password resets and changes, wrong-password confirmation attempts, and any time a teacher or student tries an admin-only action (`denied`).

![Change Log](docs/manual/security_logs_audit.png)

**Login Records** — every login, logout and **failed** login (the username that was tried, IP address and browser). The reason for a failure ("wrong password" vs "no such account") is visible here only; the person logging in always just sees "Invalid username or password".

![Login Records](docs/manual/security_logs_login.png)

Use the date filters to narrow the list (the newest 5,000 entries are shown). The logs have no edit or delete option.

---

# TEACHER'S PANEL

## My Classes (and the Gradebook)

**Where:** left sidebar → **My Classes**.

![My Classes list](docs/manual/teacher_my_classes.png)

Every class this teacher is assigned to (see Assignments above), with a live student count pulled from actual enrollment data. Click **Manage** on any row to open its gradebook.

![Gradebook](docs/manual/teacher_gradebook.png)

- **Quarter tabs** (Q1–Q4) — everything below is scoped to whichever quarter is selected.
- **Components** — the quizzes/activities/exams for this quarter, shown as chips (each labeled with its max score). Click **Add Component**:

![Add Component modal](docs/manual/teacher_add_component.png)

  Pick a type (Quiz / Activity / Exam), give it a title, a max score, and optionally a date. It immediately appears as a chip and opens a new score-entry column in the grid for every enrolled student.

- **The grid** — one row per enrolled student (pulled live from actual enrollment + subject records, no separate roster to maintain), one column per component, plus a **Final Grade** column at the end.
  - Type a score directly into any cell — it saves automatically when you click away (on blur). A score outside the component's max is rejected with an inline error.
  - **Final Grade is typed in manually**, per student, per quarter — it is **not** auto-averaged from the component scores above. Those scores are reference material; the Final Grade is your own official entry.
  - **Editing an already-recorded score or Final Grade** (not the first time entering it — changing a value that's already there) pops up a required reason prompt before it saves:

![Reason for edit prompt](docs/manual/gradebook_edit_reason.png)

    Cancel and the cell reverts to its previous value; confirm with a reason and it saves, with the old value, new value, reason, and who made the change all logged internally.
  - Removing a component (the × on its chip) hides it and its scores without deleting the underlying data — a safety net, not a hard delete.
  - The roster includes every student who ever took this class, not just currently-enrolled ones — a small **Completed**/**Dropped**/**Transferred** badge next to a name means that student's own enrollment has since ended, but their scores for this class stay visible and editable here.

## Announcements

**Where:** left sidebar → **Announcements**. Same feed a Teacher sees as everyone else, filtered to posts targeted at "All" or "Teachers" — read-only (no New Post button, since only Admin authors these).

![Teacher announcements view](docs/manual/teacher_announcements.png)

---

# STUDENT'S PANEL

## My Subjects

**Where:** left sidebar → **My subjects**.

![My Subjects](docs/manual/student_my_subjects.png)

One card per subject the student has ever been enrolled in — **not just the current year**. Each shows a status badge (green **Current** for the active enrollment, or **Completed** / **Dropped** / **Transferred** for past ones), the section, school year, and either the assigned teacher's name or **"Teacher not yet assigned"** if Admin hasn't set up that Assignment yet (see Settings → Assignments above — until that happens, there's simply nothing to grade). Subjects with a teacher get a **View Grades** shortcut straight into the next screen, pre-selected. Current-year subjects are listed first, then past years newest-first.

## My Grades

**Where:** left sidebar → **My Grades**.

![My Grades](docs/manual/student_my_grades.png)

1. Pick a subject from the dropdown (only subjects with an assigned teacher/class appear here). Since a student can have the same subject across multiple years, each option is labeled with its school year and status, e.g. "Mathematics (MATH1) — S.Y. 2027-2028 [completed]".
2. One panel per quarter that has any activity — a **Final Grade** badge (green with the number once released, gray "Not yet released" until then), and a table of every quiz/activity/exam for that quarter with its score, or "Not yet recorded" if the teacher hasn't graded it yet.

Everything here is read-only and scoped to the logged-in student's own account — verified that a crafted request for someone else's class is rejected server-side, not just hidden in the UI.

**Grades from a completed, dropped, or transferred school year stay fully accessible** — activating a new school year (which auto-completes everyone still enrolled in the old one) does not lock a student out of their own history. This applies on the Teacher side too: a class's gradebook roster still shows students from years the class was actually taught, each tagged with their status, instead of the roster going empty once that year ends.

## Payment History

**Where:** left sidebar → **Payment History**.

![Payment History](docs/manual/student_payment_history.png)

The same style of table as Admin's Payments ledger, but scoped to only this student's own payments.

## Announcements

**Where:** left sidebar → **Announcements**. Read-only feed filtered to "All" or "Students" posts.

![Student announcements view](docs/manual/student_announcements.png)

---

# Attendance Kiosk (standalone, no login required)

**Where:** `attendance_kiosk.html` — a completely separate page, reachable without any session, meant to run on a gate-side tablet/terminal.

![Attendance kiosk](docs/manual/attendance_kiosk.png)

Built for scanning a student's LRN in and out at the gate:
- Enter/scan the LRN.
- The system automatically figures out whether this is a Time In or Time Out — it toggles based on that student's most recent scan **today**.
- No admin/teacher/student login is needed or checked here by design — it's meant to sit at a physical gate, not behind the app's normal auth wall. To stop outsiders using it over the internet, set the school's public IP in `KIOSK_ALLOWED_IPS` (`config.local.php`). Scans are limited to 30 per minute per device, and failed scans are written to the audit log.
- `source` is recorded as `manual` for now; the same log structure is ready for an RFID reader to feed it directly later.

---

## How it all connects (quick reference)

1. **Admin** creates Students and Teachers (Settings), sets up Levels/Sections/Subjects (Settings), and activates a School Year.
2. **Admin** enrolls a student (Enrollment) — subjects get assigned based on the level's curriculum rules, tuition is set, payments can start.
3. **Admin** assigns a Teacher to teach a Subject in a Section (Settings → Assignments) — this is what turns an enrolled subject into an actual "class" with a gradebook.
4. **Teacher** adds quiz/activity/exam components and enters scores + Final Grades per quarter (My Classes).
5. **Student** sees their subjects (My Subjects), their grades (My Grades), and their payment history — all read-only, all automatically scoped to their own account.
6. **Admin** posts Announcements anytime, visible to whichever audience is picked, with unread badges for Teachers/Students until they check the feed.
7. Attendance happens two ways: the no-login **Kiosk** for gate scans, and the in-app **Attendance** lookup for reviewing a specific student's history.
