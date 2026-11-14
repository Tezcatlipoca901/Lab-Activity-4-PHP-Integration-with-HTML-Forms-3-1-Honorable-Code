# Brewlist

A cozy, simple To-do List web app built with **PHP sessions**, HTML, CSS, and vanilla JavaScript. No database, no frameworks, no extra mumbo jumbo.

Built for **COMP016 – Web Development, Lab Activity 4: PHP Integration with HTML Forms**.

> **Group:** `HONORABLE_CODE`
> **Members:** ```
    DE GUZMAN, CYRUS KENT, MARAV	
    IGNACIO, RENZO MIGUEL, GALLARDO	
    LUCBAN, MA. PAMELA	
    PADILLA, MICHAEL JOSHUA, CAYANAN	
    RECELLA, RAM MATHEW, FERRER	
    REYES, MIKAELLA, FRANCISCO	
    ZULUETA, GIAN CARLO, BASCO```

---

## Features

### Core (CRUD with `$_SESSION`)
- **Create** a task with a name, description, optional due date, and category
- **Read** tasks in a list and open any task in the details panel
- **Update** a task through a pre-filled edit form
- **Delete** a task with a confirmation dialog
- **Mark as completed / not completed** from the details panel

### Organizing tasks
- **Views:** All tasks, Today, Overdue, This week, This month, This year, and No due date
- **Categories:** filter by category, and add, rename, or delete categories from the **Manage** dialog (deleting a category moves its tasks to *Others*)
- **Status tabs:** All, Active, and Completed, each with a live count
- **Search** by task name or description
- **Sort** by earliest first, latest first, name A–Z, or name Z–A

### UI/UX
- **Status pills and colors** (the same meaning everywhere):
  - Amber = Active
  - Green = Completed
  - Red = Overdue
- **Overdue** is only shown for unfinished tasks whose due date has passed
- Details-panel status button that is clearly clickable, with a hint
- **Success modal** after creating, updating, or deleting a task (auto-closes after about 3 seconds)
- Server-side validation with highlighted fields and error messages
- 4 themes: Latte, Frappé, Macchiato, and Mocha (saved in the browser)
- Responsive layout for desktop, tablet, and mobile (drawer sidebar and full-screen details panel)

---

## Tech stack

| Layer | Used |
|---|---|
| Backend | PHP 8+ (sessions, `$_POST`, `$_GET`, `$_SERVER`) |
| Markup | HTML5 (semantic) |
| Styling | CSS3 (external stylesheet, CSS variables for themes) |
| Interactivity | Vanilla JavaScript (external script) |
| Storage | `$_SESSION` only, with no database |

---

## Project structure

```
Brewlist/
├── template/
│   └── index.php      ← Main page: session, form handling + validation, rendering
├── design/
│   └── styles.css     ← External stylesheet (layout, themes, components, responsive)
├── script/
│   └── app.js         ← External script (dropdowns, theme, drawer, form helpers, success modal)
├── assets/
│   ├── icons/         ← PNG icons
│   └── fonts/         ← Inter font
└── README.md
```

---

## How to run

PHP files can't be opened by double-clicking. They need a local server.

### Option A – Laragon (recommended)
1. Copy the project folder into `C:\laragon\www\` (for example `C:\laragon\www\Brewlist`).
2. Open Laragon and click **Start All** (Apache is enough, and MySQL isn't needed).
3. Open `http://localhost/Brewlist/template/index.php` in your browser.

### Option B – XAMPP
1. Copy the project folder into `C:\xampp\htdocs\`.
2. Start **Apache** in the XAMPP Control Panel.
3. Open `http://localhost/Brewlist/template/index.php`.

### Option C – PHP built-in server
From the project folder, run:

```bash
php -S localhost:8000
```

Then open `http://localhost:8000/template/index.php`.

---

## How to use

1. **Add a task:** click **Add task**, fill in the form, and submit. A success message appears and the task shows in the list.
2. **View a task:** click any task to see its details on the right.
3. **Edit:** open a task, click **Edit task**, change the fields, then **Save changes**.
4. **Delete:** open a task, click **Delete task**, then confirm.
5. **Complete a task:** open it and click the status button (Not completed / Completed).
6. **Filter and find:** use the sidebar views and categories, the status tabs, the search box, and the sort menu.

> Tasks are stored in the PHP session, so they reset when the session ends (for example when the browser is closed or Apache restarts). This is expected for this lab, which doesn't use a database.

---

## Lab requirements checklist

| Requirement | Where |
|---|---|
| `session_start()` and CRUD inside `$_SESSION` | Top of `template/index.php` (`$_SESSION['tasks']`) |
| Self-submitting `method="POST"` form | Create / Edit panel in `template/index.php` |
| Read `$_POST`, trim, and validate | Create / Update handlers in `template/index.php` |
| Request handling with `$_SERVER["REQUEST_METHOD"]` | POST handling block in `template/index.php` |
| XSS protection with `htmlspecialchars()` | `e()` helper, used on every output |
| PHP loops rendering tasks | `foreach ($tasks as $task)` in `template/index.php` |
| Semantic HTML and external CSS | `template/index.php` and `design/styles.css` |
| External JavaScript for DOM events | `script/app.js` |

### Validation rules
- **Task name:** required, 3–80 characters
- **Description:** required, up to 255 characters
- **Due date:** optional, must be a valid date
- **Category:** must be an existing category
- **Category name:** required, up to 20 characters, and must be unique

---

## Notes
- Output is escaped everywhere, so `<script>` typed into a task is shown as plain text.
- After a successful POST the page redirects (Post/Redirect/Get), so refreshing doesn't resubmit the form.
- The default category **Others** can't be renamed or deleted.
- Default categories (School, Personal, Work, Others) are defined at the top of `template/index.php`.

---

## Lab 5 – MySQL migration (in progress)

Refactoring the app from `$_SESSION` storage to MySQL via PDO.

### Done
- **Environment:** PHP 8.5 with `pdo_mysql`, MariaDB running, `root@localhost` with empty password.
- **Schema:** `brewlist.sql` imported into the `task_db` database (4 categories + sample tasks).
- **Order 2 – connection:** `template/db.php` created (PDO, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `ATTR_EMULATE_PREPARES => false`, `try/catch` showing `Database Connection Error: ...`). `require_once 'db.php';` added right after `session_start()` in `template/index.php`.
- Verified: PDO connects, and a wrong password shows a readable error.

### Done
- **Order 3 – READ:** tasks and categories are loaded from MySQL, while task rows keep the existing template keys (`title`, `desc`, `due`, `cat`, `done`); selected and confirmation tasks use a fetch-one-by-id helper.
- **Order 4 – CREATE / UPDATE:** the `create` and `update` blocks now save to MySQL with PDO prepared statements (`?` placeholders) instead of `$_SESSION`. All validation, the flash message, the redirect after success, and the re-render with errors are unchanged. An empty due date is saved as `NULL`, not `''`. Verified in the `tasks` table (see *Verify CREATE / UPDATE* below).

### Next
Remaining orders:
1. **Order 5 – TOGGLE / DELETE**
2. **Order 6 – CATEGORIES**
3. **Order 7 – merge into main**
4. **Order 8 – QA testing**

> Until these are done, toggle (mark completed), delete, and the category actions still use `$_SESSION` and do not change the database.

### Column mapping (`brewlist.sql` vs. the Lab 5 PDF)

| Lab 5 PDF | `brewlist.sql` |
|---|---|
| `task_name` | `title` |
| `task_description` | `description` |
| `category` (string) | `category_id` (FK → `categories.id`) |
| `status` `'pending'`/`'completed'` | `done` `0`/`1` |
| `categories(name, color)` | `categories(id, name, color, is_default, created_at)` |

Order 3 read query that keeps the existing array keys (`title`, `desc`, `due`, `cat`, `done`):

```sql
SELECT t.id, t.title, t.description AS `desc`, t.due_date AS due, c.name AS cat, t.done
FROM tasks t
JOIN categories c ON c.id = t.category_id
```

### CREATE / UPDATE queries (Order 4)

```sql
-- create
INSERT INTO tasks (title, description, due_date, category_id)
VALUES (?, ?, ?, (SELECT id FROM categories WHERE name = ?));

-- update
UPDATE tasks
SET title = ?, description = ?, due_date = ?,
    category_id = (SELECT id FROM categories WHERE name = ?)
WHERE id = ?;
```

The `?` placeholders are filled in by PHP when the form is submitted (the values are sent separately from the query, which prevents SQL injection). The form's category name is turned into `category_id` by the subquery.

### Verify CREATE / UPDATE

1. Create a task in the app, then run:
   ```sql
   SELECT t.id, t.title, t.description, t.due_date, c.name AS category, t.done
   FROM task_db.tasks t JOIN task_db.categories c ON c.id = t.category_id
   ORDER BY t.id;
   ```
   The new task should appear as the last row.
2. Edit that task in the app and run the query again: the same row should show the new values and a new `updated_at`.
3. Create or edit a task with the due date left empty: `due_date` should be `NULL`.
4. Submit invalid data (for example a 2-character name): the form shows the errors and no row is added or changed.

### Set up the database

Import the schema once (the file creates `task_db`, its tables, the default categories, and sample tasks):

```bash
mysql -u root -p < brewlist.sql
```

Or import `brewlist.sql` with HeidiSQL or phpMyAdmin. To start over, run `DROP DATABASE task_db;` and import again. Connection settings are in `template/db.php` (database `task_db`, user `root`). Don't commit a real password in that file.

### Run locally

```bash
php -S 127.0.0.1:8000
# open http://127.0.0.1:8000/template/index.php
```
