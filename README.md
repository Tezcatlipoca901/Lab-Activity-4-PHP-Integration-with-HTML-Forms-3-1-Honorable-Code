# Brewlist

A cozy, simple To-do List web app built with **PHP**, **MySQL (PDO)**, HTML, CSS, and vanilla JavaScript. No frameworks, no extra mumbo jumbo.

Built for **COMP016 – Web Development**:
- **Lab Activity 4:** PHP Integration with HTML Forms (data kept in `$_SESSION`)
- **Lab Activity 5:** Integrating SQL Databases with PHP (data moved to MySQL with PDO prepared statements)

> **Group:** `HONORABLE_CODE`
> **Members:**
> - DE GUZMAN, CYRUS KENT, MARAV
> - IGNACIO, RENZO MIGUEL, GALLARDO
> - LUCBAN, MA. PAMELA
> - PADILLA, MICHAEL JOSHUA, CAYANAN
> - RECELLA, RAM MATHEW, FERRER
> - REYES, MIKAELLA, FRANCISCO
> - ZULUETA, GIAN CARLO, BASCO

---

## Features

### Core (CRUD with MySQL)
- **Create** a task with a name, description, optional due date, and category
- **Read** tasks in a list and open any task in the details panel
- **Update** a task through a pre-filled edit form
- **Delete** a task with a confirmation dialog
- **Mark as completed / not completed** from the details panel, or from the checkbox on a task card (asks for confirmation first)

### Organizing tasks
- **Views:** All tasks, Today, This week, This month, This year, and No due date
- **Categories:** filter by category, and add, rename, or delete categories from the **Manage** dialog (deleting a category moves its tasks to *Others*; *Others* can't be renamed or deleted)
- **Status tabs:** All, Active, Completed, and Overdue, each with a live count
- **Search** by task name or description
- **Sort** by earliest first, latest first, name A–Z, or name Z–A

### UI/UX
- **Status pills and colors** (the same meaning everywhere):
  - Amber = Active
  - Green = Completed
  - Red = Overdue
- **Overdue** is only shown for unfinished tasks whose due date has passed
- Details-panel status button that is clearly clickable, with a hint
- **Success modal** after creating, updating, deleting, or completing a task, and after adding, renaming, or deleting a category (auto-closes after about 3 seconds)
- Server-side validation with highlighted fields and error messages
- 4 themes: Latte, Frappé, Macchiato, and Mocha (saved in the browser)
- Responsive layout for desktop, tablet, and mobile (drawer sidebar and full-screen details panel)

---

## Tech stack

| Layer | Used |
|---|---|
| Backend | PHP 8+ (PDO, `$_POST`, `$_GET`, `$_SERVER`; sessions only for the success popups) |
| Markup | HTML5 (semantic) |
| Styling | CSS3 (external stylesheet, CSS variables for themes) |
| Interactivity | Vanilla JavaScript (external script) |
| Storage | MySQL / MariaDB (`task_db`) through PDO prepared statements |

---

## Project structure

```
Brewlist/
├── template/
│   ├── index.php      ← Main page: form handling + validation, MySQL queries, rendering
│   └── db.php         ← PDO connection to MySQL ($pdo)
├── design/
│   └── styles.css     ← External stylesheet (layout, themes, components, responsive)
├── script/
│   └── app.js         ← External script (dropdowns, theme, drawer, form helpers, success modal)
├── assets/
│   ├── icons/         ← PNG icons
│   └── fonts/         ← Inter font
├── brewlist.sql       ← Database schema + starting data (task_db)
└── README.md
```

---

## How to run

PHP files can't be opened by double-clicking. They need a local server.

### Option A – Laragon (recommended)
1. Copy the project folder into `C:\laragon\www\` (for example `C:\laragon\www\Brewlist`).
2. Open Laragon and click **Start All** (Apache **and MySQL** are both needed). Then import the database (see *Set up the database* below).
3. Open `http://localhost/Brewlist/template/index.php` in your browser.

### Option B – XAMPP
1. Copy the project folder into `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel, then import the database (see *Set up the database* below).
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

> Tasks and categories are saved in MySQL, so they stay after you close the browser or clear cookies. Only the one-time success popups use the PHP session.

---

## Lab requirements checklist

| Requirement | Where |
|---|---|
| `session_start()` (flash popups) and CRUD against MySQL | Top of `template/index.php` and `template/db.php` (`$pdo`) |
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
- The default categories (School, Personal, Work, Others) are inserted by `brewlist.sql`.

---

## Lab 5 – MySQL migration (in progress)

Refactoring the app from `$_SESSION` storage to MySQL via PDO.

### Done
- **Environment:** PHP 8.5 with `pdo_mysql`, MariaDB running, `root@localhost` with empty password.
- **Schema:** `brewlist.sql` imported into the `task_db` database (4 categories + sample tasks).
- **Order 2 – connection:** `template/db.php` created (PDO, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `ATTR_EMULATE_PREPARES => false`, `try/catch` showing `Database Connection Error: ...`). `require_once 'db.php';` added right after `session_start()` in `template/index.php`.
- **Order 3 – READ:** tasks and categories are loaded from MySQL, while task rows keep the existing template keys (`title`, `desc`, `due`, `cat`, `done`); selected and confirmation tasks use a fetch-one-by-id helper.
- **Order 4 – CREATE / UPDATE:** the `create` and `update` blocks save to MySQL with PDO prepared statements (`?` placeholders) instead of `$_SESSION`. All validation, the flash message, the redirect after success, and the re-render with errors are unchanged. An empty due date is saved as `NULL`, not `''`.
- **Order 5 – TOGGLE / DELETE:** toggle flips `done` with a prepared `UPDATE`, then reads the row back for the completed / reopened flash; delete reads the title for the flash, then runs `DELETE`. Both stay POST-only with the id cast to `(int)`.
- **Order 6 – CATEGORIES:** add, rename, and delete in the Manage dialog now use MySQL (see *CATEGORIES queries* below). The validation, the success popups, and the block on renaming or deleting *Others* are unchanged. No `$_SESSION['categories']` or `$_SESSION['tasks']` is used any more.

### Next
1. **Order 7 – merge into main**
2. **Order 8 – QA testing**

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

### CATEGORIES queries (Order 6)

```sql
-- add
INSERT INTO categories (name, color) VALUES (?, ?);

-- rename (tasks keep pointing to the same category_id, so they follow the new name)
UPDATE categories SET name = ? WHERE name = ?;

-- delete (one transaction: move the tasks to Others, then delete the category)
UPDATE tasks
SET category_id = (SELECT id FROM categories WHERE name = 'Others')
WHERE category_id = (SELECT id FROM categories WHERE name = ?);

DELETE FROM categories WHERE name = ?;
```

The delete uses `beginTransaction()` and `commit()`, and calls `rollBack()` if either statement fails, so a category is never deleted while its tasks are left pointing at it. The PDF's version (`SET category = 'Others'`) assumes the tasks table stores the category name; ours stores `category_id`, so the id is looked up instead.

### Verify CATEGORIES

1. **Add:** add a category in the Manage dialog, then run `SELECT * FROM task_db.categories;`. The new row should be last.
2. **Duplicate and length:** adding an existing name (any letter case) or a 21-character name shows an error and adds no row.
3. **Rename:** rename a category that has tasks. The `categories` row changes, and the tasks still show under the new name.
4. **Delete:** delete a category that has tasks. The row is gone and its tasks now show under *Others*.
5. **Others:** it has no rename or delete buttons.

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