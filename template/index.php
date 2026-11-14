<?php
/* =====================================================================
   Brewlist – Lab Activity 4 (PHP + MySQL)
   Scope: CREATE / READ / UPDATE / DELETE tasks
   ===================================================================== */

session_start();                                   // keep flash messages across page reloads
require_once __DIR__ . '/db.php';                  // MySQL connection ($pdo)
date_default_timezone_set('Asia/Manila');

/* ---------- Configuration ---------- */
const DEFAULT_CATEGORY = 'Others';   // can't be renamed or deleted; tasks of a deleted category move here

// Colors offered in the "Manage categories" dialog.
$palette = ['#cba6f7', '#a6e3a1', '#b794f6', '#6c7086', '#f9e2af', '#89dceb', '#f5c2e7', '#b4befe'];

$categories = [];
$categoryStmt = $pdo->query('SELECT name, color FROM categories ORDER BY id');
foreach ($categoryStmt as $category) {
    $categories[$category['name']] = $category['color'];
}

$viewNames = ['all' => 'All tasks', 'today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'year' => 'This year', 'nodue' => 'No due date'];
$viewIcons = ['all' => 'all-tasks', 'today' => 'today', 'week' => 'week', 'month' => 'month', 'year' => 'year'];   // PNG icons from /assets
// No due date has no PNG, so it uses a small inline SVG icon (it takes the text color, so it can turn purple when active).
$viewSvgs  = [
    'nodue'   => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16M9.5 15h5"/>',
];
$sortNames = ['earliest' => 'Earliest first', 'latest' => 'Latest first', 'az' => 'Name A–Z', 'za' => 'Name Z–A'];

/* ---------- Helpers ---------- */

// Output escaping (XSS protection) – use on EVERYTHING echoed into HTML.
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatDate(string $date): string
{
    return $date !== '' ? date('M j, Y', strtotime($date)) : 'No due date';
}

function plural(int $n, string $word): string
{
    return $n . ' ' . $word . ($n === 1 ? '' : 's');
}

// Keep the array shape used by the existing filters and templates while reading from MySQL.
function fetchTasks(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT t.id, t.title, t.description AS `desc`, t.due_date AS due, '
        . 'c.name AS cat, t.done '
        . 'FROM tasks t '
        . 'JOIN categories c ON c.id = t.category_id '
        . 'ORDER BY t.id'
    );

    $tasks = [];
    foreach ($stmt as $task) {
        $task['id'] = (int) $task['id'];
        $task['due'] = $task['due'] ?? '';
        $task['done'] = (bool) $task['done'];
        $tasks[$task['id']] = $task;
    }

    return $tasks;
}

function fetchTask(PDO $pdo, int $taskId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT t.id, t.title, t.description AS `desc`, t.due_date AS due, '
        . 'c.name AS cat, t.done '
        . 'FROM tasks t '
        . 'JOIN categories c ON c.id = t.category_id '
        . 'WHERE t.id = :id'
    );
    $stmt->execute(['id' => $taskId]);
    $task = $stmt->fetch();

    if ($task === false) {
        return null;
    }

    $task['id'] = (int) $task['id'];
    $task['due'] = $task['due'] ?? '';
    $task['done'] = (bool) $task['done'];

    return $task;
}

// The little status pill: Overdue (red) / Active (amber) / Completed (green)
function statusPill(string $state): string
{
    $pills = [
        'overdue'   => ['Overdue',   '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16.2v.01"/>'],
        'active'    => ['Active',    '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="2.4"/>'],
        'completed' => ['Completed', '<path d="m5.5 12.5 4.2 4.2L18.5 8"/>'],
    ];
    [$label, $icon] = $pills[$state];
    return '<span class="pill pill-' . $state . '"><svg viewBox="0 0 24 24" aria-hidden="true">' . $icon . '</svg>' . $label . '</span>';
}

// Is an unfinished task past its due date?
function isOverdue(array $task): bool
{
    return !$task['done'] && $task['due'] !== '' && strtotime($task['due']) < strtotime('today');
}

// One of: completed | overdue | active  (drives the pills, colors and icons)
function taskState(array $task): string
{
    if ($task['done']) return 'completed';
    return isOverdue($task) ? 'overdue' : 'active';
}

// Is the task inside the chosen view (all / today / overdue / week / month / year / no due date)?
function inView(array $task, string $view): bool
{
    if ($view === 'all') return true;
    if ($view === 'nodue') return $task['due'] === '';
    if ($task['due'] === '') return false;

    $due   = strtotime($task['due']);
    $today = strtotime('today');

    switch ($view) {
        case 'today': return $due === $today;
        case 'week':  return $due >= strtotime('monday this week', $today) && $due <= strtotime('sunday this week', $today);
        case 'month': return date('Y-m', $due) === date('Y-m', $today);
        case 'year':  return date('Y', $due) === date('Y', $today);
    }
    return true;
}

// Build a link that keeps the current filters but changes the given params (null = remove).
function url(array $changes = []): string
{
    $keep   = ['view', 'cat', 'status', 'sort', 'q', 'task', 'new', 'edit', 'del', 'manage', 'rename', 'delcat'];
    $params = array_intersect_key($_GET, array_flip($keep));
    foreach ($changes as $key => $value) {
        if ($value === null || $value === '') unset($params[$key]);
        else $params[$key] = $value;
    }
    // drop values that are just the defaults, to keep URLs tidy
    if (($params['view'] ?? '') === 'all') unset($params['view']);
    if (($params['status'] ?? '') === 'all') unset($params['status']);
    if (($params['sort'] ?? '') === 'earliest') unset($params['sort']);

    return 'index.php' . ($params ? '?' . http_build_query($params) : '');
}

/* ---------- Filters from the query string (GET) ---------- */
$view      = in_array($_GET['view'] ?? 'all', array_keys($viewNames), true) ? ($_GET['view'] ?? 'all') : 'all';
$catFilter = isset($_GET['cat']) && isset($categories[$_GET['cat']]) ? $_GET['cat'] : null;
$status    = in_array($_GET['status'] ?? 'all', ['all', 'active', 'completed', 'overdue'], true) ? ($_GET['status'] ?? 'all') : 'all';
$sort      = isset($sortNames[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'earliest';
$search    = trim($_GET['q'] ?? '');

/* =====================================================================
   CREATE  –  handle the self-submitting form (method="POST", action=create)
   UPDATE  –  handle the edit form  (method="POST", action=update)
   DELETE  –  handle the delete form (method="POST", action=delete)
   TOGGLE  –  toggle done/not-done  (method="POST", action=toggle)
   ===================================================================== */
$errors = [];
$old    = ['title' => '', 'desc' => '', 'due' => '', 'cat' => DEFAULT_CATEGORY];

// "Manage categories" dialog: validation errors + values to re-fill after a failed submit.
$catErrors = [];
$catOld    = ['name' => '', 'color' => '#f9e2af', 'rename' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? 'create');

    /* ---- TOGGLE ---- */
    if ($action === 'toggle') {
        $tid      = (int) ($_POST['task_id'] ?? 0);
        $fromList = ($_POST['from'] ?? '') === 'list';     // came from the checkbox on a task card (after the confirm popup)
        if (isset($_SESSION['tasks'][$tid])) {
            $_SESSION['tasks'][$tid]['done'] = !$_SESSION['tasks'][$tid]['done'];
            if ($fromList) {
                $_SESSION['flash'] = ['type' => $_SESSION['tasks'][$tid]['done'] ? 'completed' : 'reopened', 'title' => $_SESSION['tasks'][$tid]['title']];
            }
        }
        // From the list: stay on the list. From the details panel: keep showing that task.
        header('Location: ' . ($fromList
            ? url(['new' => null, 'edit' => null, 'del' => null, 'manage' => null, 'rename' => null, 'delcat' => null])
                : url(['task' => $tid, 'new' => null, 'edit' => null, 'del' => null, 'manage' => null, 'rename' => null, 'delcat' => null])));
        exit();
    }

    /* ---- DELETE ---- */
    if ($action === 'delete') {
        $tid = (int) ($_POST['task_id'] ?? 0);
        if (isset($_SESSION['tasks'][$tid])) {
            $_SESSION['flash'] = ['type' => 'deleted', 'title' => $_SESSION['tasks'][$tid]['title']];   // shown once in the success modal
            unset($_SESSION['tasks'][$tid]);
        }
        header('Location: ' . url(['task' => null, 'new' => null, 'edit' => null, 'del' => null, 'manage' => null, 'rename' => null, 'delcat' => null]));
        exit();
    }

    /* ---- CATEGORIES: add / rename / delete (Manage categories dialog) ---- */
    if (in_array($action, ['cat_add', 'cat_rename', 'cat_delete'], true)) {
        $orig = $action === 'cat_add' ? '' : (string) ($_POST['cat_orig'] ?? '');
        $back = ['manage' => 1, 'rename' => null, 'delcat' => null];   // where to go after success

        if ($action === 'cat_delete') {
            if ($orig !== DEFAULT_CATEGORY && isset($_SESSION['categories'][$orig])) {
                foreach ($_SESSION['tasks'] as &$t) {                    // move its tasks to "Others"
                    if ($t['cat'] === $orig) $t['cat'] = DEFAULT_CATEGORY;
                }
                unset($t);
                unset($_SESSION['categories'][$orig]);
                $_SESSION['flash'] = ['type' => 'cat_deleted', 'title' => $orig];  // success popup
                $back = ['manage' => null, 'rename' => null, 'delcat' => null];    // close Manage dialog so flash is visible
                if (($_GET['cat'] ?? '') === $orig) $back['cat'] = null;  // don't keep filtering by a deleted category
            }
            header('Location: ' . url($back));
            exit();
        }

        $name  = trim($_POST['cat_name'] ?? '');
        $color = trim($_POST['cat_color'] ?? '');
        $key   = $action === 'cat_add' ? 'add' : 'rename';
        $msg   = '';

        if ($name === '') {
            $msg = 'Category name is required.';
        } elseif (mb_strlen($name) > 20) {
            $msg = 'Category name cannot exceed 20 characters.';
        } else {
            foreach ($_SESSION['categories'] as $existing => $c) {
                if ((string) $existing !== $orig && mb_strtolower((string) $existing) === mb_strtolower($name)) {
                    $msg = 'That category already exists.';
                }
            }
        }
        if ($msg === '' && $action === 'cat_add' && !in_array($color, $palette, true)) {
            $msg = 'Please pick a color.';
        }
        if ($msg === '' && $action === 'cat_rename' && ($orig === DEFAULT_CATEGORY || !isset($_SESSION['categories'][$orig]))) {
            $msg = 'That category can\'t be renamed.';
        }

        if ($msg === '') {
            if ($action === 'cat_add') {
                $_SESSION['categories'][$name] = $color;
                $_SESSION['flash'] = ['type' => 'cat_added', 'title' => $name];        // success popup, like "Task created"
                $back = ['manage' => null, 'rename' => null, 'delcat' => null];       // close the Manage dialog
            } else {
                $renamed = [];                                            // rebuild so the order is kept
                foreach ($_SESSION['categories'] as $k => $c) {
                    $renamed[(string) $k === $orig ? $name : $k] = $c;
                }
                $_SESSION['categories'] = $renamed;
                foreach ($_SESSION['tasks'] as &$t) {
                    if ($t['cat'] === $orig) $t['cat'] = $name;
                }
                unset($t);
                $_SESSION['flash'] = ['type' => 'cat_updated', 'title' => $name];     // success popup
                $back = ['manage' => null, 'rename' => null, 'delcat' => null];       // close the Manage dialog
                if (($_GET['cat'] ?? '') === $orig) $back['cat'] = $name;             // keep filtering by the renamed category
            }
            header('Location: ' . url($back));
            exit();
        }

        // Validation failed: keep the dialog open and show the message.
        $catErrors[$key] = $msg;
        if ($action === 'cat_add') {
            $catOld['name']  = $name;
            if (in_array($color, $palette, true)) $catOld['color'] = $color;
        } else {
            $catOld['rename'] = $name;
            $_GET['rename']   = $orig;
        }
        $_GET['manage'] = '1';
    }

    /* ---- UPDATE ---- */
    if ($action === 'update') {
        $tid = (int) ($_POST['task_id'] ?? 0);
        $old['title'] = trim($_POST['task_name'] ?? '');
        $old['desc']  = trim($_POST['task_description'] ?? '');
        $old['due']   = trim($_POST['due_date'] ?? '');
        $old['cat']   = trim($_POST['category'] ?? '');

        if ($old['title'] === '') {
            $errors['title'] = 'Task name is required.';
        } elseif (mb_strlen($old['title']) < 3) {
            $errors['title'] = 'Task name must be at least 3 characters long.';
        } elseif (mb_strlen($old['title']) > 80) {
            $errors['title'] = 'Task name cannot exceed 80 characters.';
        }

        if ($old['desc'] === '') {
            $errors['desc'] = 'Description is required.';
        } elseif (mb_strlen($old['desc']) > 255) {
            $errors['desc'] = 'Description cannot exceed 255 characters.';
        }

        if ($old['due'] !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $old['due']);
            if (!$d || $d->format('Y-m-d') !== $old['due']) {
                $errors['due'] = 'Please enter a valid due date.';
            }
        }

        if (!isset($categories[$old['cat']])) {
            $errors['cat'] = 'Please choose a valid category.';
        }

        if (!$errors && fetchTask($pdo, $tid) !== null) {
            // Save the changes to MySQL (prepared statement; the category name is turned into its id)
            $stmt = $pdo->prepare(
                'UPDATE tasks '
                . 'SET title = ?, description = ?, due_date = ?, '
                . 'category_id = (SELECT id FROM categories WHERE name = ?) '
                . 'WHERE id = ?'
            );
            $stmt->execute([
                $old['title'],
                $old['desc'],
                $old['due'] !== '' ? $old['due'] : null,   // empty due date is saved as NULL, not ''
                $old['cat'],
                $tid,
            ]);
            $_SESSION['flash'] = ['type' => 'updated', 'title' => $old['title']];
            header('Location: ' . url(['task' => $tid, 'new' => null, 'edit' => null, 'del' => null, 'manage' => null, 'rename' => null, 'delcat' => null]));
            exit();
        }
        // Fall through to re-render the edit form with errors
        // (keep ?edit=1 in the URL so the right panel shows the form)
        $_GET['task'] = $tid;
        $_GET['edit'] = '1';
    }

    /* ---- CREATE ---- */
    if ($action === 'create') {
        // 1. Extraction with null-coalescing fallback  2. Trimming extra spaces
        $old['title'] = trim($_POST['task_name'] ?? '');
        $old['desc']  = trim($_POST['task_description'] ?? '');
        $old['due']   = trim($_POST['due_date'] ?? '');
        $old['cat']   = trim($_POST['category'] ?? '');

        // 3. Server-side validation (collect messages in $errors)
        $errors = [];

        if ($old['title'] === '') {
            $errors['title'] = 'Task name is required.';
        } elseif (mb_strlen($old['title']) < 3) {
            $errors['title'] = 'Task name must be at least 3 characters long.';
        } elseif (mb_strlen($old['title']) > 80) {
            $errors['title'] = 'Task name cannot exceed 80 characters.';
        }

        if ($old['desc'] === '') {
            $errors['desc'] = 'Description is required.';
        } elseif (mb_strlen($old['desc']) > 255) {
            $errors['desc'] = 'Description cannot exceed 255 characters.';
        }

        if ($old['due'] !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $old['due']);
            if (!$d || $d->format('Y-m-d') !== $old['due']) {
                $errors['due'] = 'Please enter a valid due date.';
            }
        }

        if (!isset($categories[$old['cat']])) {
            $errors['cat'] = 'Please choose a valid category.';
        }

        // 4. Save in MySQL, then redirect (Post/Redirect/Get) so a refresh doesn't resubmit
        if (!$errors) {
            $stmt = $pdo->prepare(
                'INSERT INTO tasks (title, description, due_date, category_id) '
                . 'VALUES (?, ?, ?, (SELECT id FROM categories WHERE name = ?))'
            );
            $stmt->execute([
                $old['title'],                              // stored raw; escaped with e() when displayed
                $old['desc'],
                $old['due'] !== '' ? $old['due'] : null,    // empty due date is saved as NULL, not ''
                $old['cat'],
            ]);
            $_SESSION['flash'] = ['type' => 'created', 'title' => $old['title']];
            header('Location: ' . url(['task' => null, 'new' => null, 'edit' => null, 'del' => null, 'manage' => null, 'rename' => null, 'delcat' => null]));   // back to the task list, not the details/edit panel
            exit();
        }
    }
}

/* =====================================================================
   READ  –  decide what the right panel shows, then build the list
   ===================================================================== */
$selectedId = isset($_GET['task']) ? (int) $_GET['task'] : 0;
$selected   = fetchTask($pdo, $selectedId);
$showEdit   = $selected !== null && isset($_GET['edit']) && $_GET['edit'] === '1';
$showDel    = $selected !== null && isset($_GET['del'])  && $_GET['del']  === '1';
$showDetail = $selected !== null && !isset($_GET['new']) && !$showEdit && $_SERVER['REQUEST_METHOD'] !== 'POST';

// Checkbox on a task card: ?confirm=ID opens the "Mark as completed?" popup (not kept by url(), so it goes away on any other link).
$confirmId   = isset($_GET['confirm']) ? (int) $_GET['confirm'] : 0;
$confirmTask = fetchTask($pdo, $confirmId);
$showConfirm = $confirmTask !== null && !$showDel && $_SERVER['REQUEST_METHOD'] !== 'POST';

// If we're returning from a failed UPDATE, pre-fill the form with the submitted values
if ($errors && isset($_GET['edit'])) {
    $showEdit   = true;
    $showDetail = false;
}

// If we're returning from a failed UPDATE without errors, $old is already populated above.
// Prefill $old from the selected task if we just opened the edit form (GET request).
if ($showEdit && !$errors) {
    $old = [
        'title' => $selected['title'],
        'desc'  => $selected['desc'],
        'due'   => $selected['due'],
        'cat'   => $selected['cat'],
    ];
}

$allTasks = fetchTasks($pdo);

// Success message left by CREATE / UPDATE / DELETE. Read it once, then remove it so a refresh doesn't show it again.
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// One-shot flag: skip the Manage dialog's open animation after a category delete (the dialog stays open).
$noAnim = !empty($_SESSION['no_anim']);
unset($_SESSION['no_anim']);

$flashText = [
    'created'     => ['Task created',      'was added to your list.'],
    'updated'     => ['Task updated',      'was updated successfully.'],
    'deleted'     => ['Task deleted',      'was removed from your list.'],
    'cat_added'   => ['Category added',    'was added to your categories.'],
    'cat_updated' => ['Category updated',  'was updated successfully.'],
    'cat_deleted' => ['Category deleted',  'was removed from your categories.'],
    'completed'   => ['Task completed',    'was marked as completed.'],
    'reopened'    => ['Task reopened',     'was moved back to your active tasks.'],
];

// Sidebar counts: views respect the category filter, categories respect the view.
$viewCounts = [];
foreach ($viewNames as $key => $label) {
    $viewCounts[$key] = count(array_filter($allTasks, fn($t) => inView($t, $key) && (!$catFilter || $t['cat'] === $catFilter)));
}
$catCounts = [];
foreach ($categories as $name => $color) {
    $catCounts[$name] = count(array_filter($allTasks, fn($t) => $t['cat'] === $name && inView($t, $view)));
}

// Apply view, category and search filters first...
$base = array_filter($allTasks, function ($t) use ($view, $catFilter, $search) {
    if (!inView($t, $view)) return false;
    if ($catFilter && $t['cat'] !== $catFilter) return false;
    if ($search !== '' && stripos($t['title'] . ' ' . $t['desc'], $search) === false) return false;
    return true;
});

// ...so the All / Active / Completed tabs can show how many tasks each one holds.
$statusCounts = [
    'all'       => count($base),
    'active'    => count(array_filter($base, fn($t) => !$t['done'])),
    'completed' => count(array_filter($base, fn($t) => $t['done'])),
    'overdue'   => count(array_filter($base, 'isOverdue')),
];

// Then apply the status tab.
$tasks = array_filter($base, function ($t) use ($status) {
    if ($status === 'active') return !$t['done'];
    if ($status === 'completed') return $t['done'];
    if ($status === 'overdue') return isOverdue($t);
    return true;
});

// Sort.
usort($tasks, function ($a, $b) use ($sort) {
    switch ($sort) {
        case 'az':     return strcasecmp($a['title'], $b['title']);
        case 'za':     return strcasecmp($b['title'], $a['title']);
        case 'latest': return [$a['due'] === '', $b['due']] <=> [$b['due'] === '', $a['due']];
        default:       return [$a['due'] === '', $a['due']] <=> [$b['due'] === '', $b['due']];   // earliest first
    }
});

$pageTitle = $viewNames[$view] . ($catFilter ? ' · ' . $catFilter : '');
$hasFilters = $view !== 'all' || $catFilter || $status !== 'all' || $search !== '';

// Small helper so the form can mark a field as invalid.
$bad = fn(string $field): string => isset($errors[$field]) ? ' bad' : '';

// Mobile: the right panel is a full-screen sheet, only shown when there is something to show in it.
$panelOpen = $selected !== null || isset($_GET['new']) || $errors;

// "Manage categories" dialog state (?manage=1, plus ?rename=Name / ?delcat=Name for the inline row actions).
$getStr     = fn(string $k): string => is_string($_GET[$k] ?? null) ? $_GET[$k] : '';
$showManage = $getStr('manage') === '1';
$renameName = ($showManage && $getStr('rename') !== DEFAULT_CATEGORY && isset($categories[$getStr('rename')])) ? $getStr('rename') : null;
$delCatName = ($showManage && $getStr('delcat') !== DEFAULT_CATEGORY && isset($categories[$getStr('delcat')])) ? $getStr('delcat') : null;
$manageUrl  = url(['manage' => 1, 'rename' => null, 'delcat' => null]);
$closeManage = url(['manage' => null, 'rename' => null, 'delcat' => null]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Brewlist</title>
  <link rel="icon" href="../assets/icons/brewlist.png">
  <link rel="stylesheet" href="../design/styles.css">
  <script src="../script/app.js" defer></script>
</head>
<body data-theme="mocha"<?= $panelOpen ? ' class="panel-open"' : '' ?>>

<header class="topbar">
  <!-- Mobile only: opens the sidebar as a drawer -->
  <button type="button" class="menu-btn" id="menuBtn" aria-label="Open menu" aria-expanded="false" aria-controls="sidebar">
    <svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
  </button>
  <a class="brand" href="index.php">
    <img src="../assets/icons/brewlist.png" alt="" class="brand-logo">
    <span class="brand-name">Brewlist</span>
  </a>
  <span class="brand-sep"></span>
  <span class="tagline">A cozy place to get things done.</span>

  <div class="theme-wrap">
    <button type="button" class="theme-btn" id="themeBtn" aria-haspopup="true" aria-expanded="false">
      <img src="../assets/icons/theme.png" alt="">
      <span id="themeLabel">Mocha</span>
      <svg class="chev" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <ul class="menu" id="themeMenu" hidden>
      <li><button type="button" data-theme="latte">Latte</button></li>
      <li><button type="button" data-theme="frappe">Frappé</button></li>
      <li><button type="button" data-theme="macchiato">Macchiato</button></li>
      <li><button type="button" data-theme="mocha">Mocha</button></li>
    </ul>
  </div>

  <!-- Search (GET form: ?q=...) keeps the other filters -->
  <form class="search-form" method="GET" action="index.php" role="search">
    <?php foreach (['view' => $view === 'all' ? '' : $view, 'cat' => $catFilter, 'status' => $status === 'all' ? '' : $status, 'sort' => $sort === 'earliest' ? '' : $sort] as $k => $v): ?>
      <?php if ($v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endif; ?>
    <?php endforeach; ?>
    <label class="sr-only" for="q">Search tasks</label>
    <svg class="search-ic" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
    <input class="search" id="q" name="q" type="search" placeholder="Search tasks..." value="<?= e($search) ?>" autocomplete="off">
    <?php if ($search !== ''): ?>
      <a class="search-clear" href="<?= e(url(['q' => null])) ?>" aria-label="Clear search" title="Clear search">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </a>
    <?php endif; ?>
  </form>
</header>

<div class="scrim" id="scrim" hidden></div>

<div class="app">
  <!-- ================= Sidebar (a slide-in drawer on mobile) ================= -->
  <aside class="sidebar" id="sidebar">
    <!-- Mobile only: drawer header with a close button -->
    <div class="drawer-head">
      <span class="drawer-brand">
        <img src="../assets/icons/brewlist.png" alt="" class="brand-logo">
        <span class="brand-name">Brewlist</span>
      </span>
      <button type="button" class="icon-btn" id="drawerClose" aria-label="Close menu">
        <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </button>
    </div>

    <nav class="views">
      <?php foreach ($viewNames as $key => $label): $active = $view === $key; ?>
        <a class="nav-item<?= $active ? ' active' : '' ?>" href="<?= e(url(['view' => $key])) ?>">
          <?php if (isset($viewIcons[$key])): ?>
            <img src="../assets/icons/<?= $viewIcons[$key] . ($active ? '-active' : '') ?>.png" alt="">
          <?php else: ?>
            <svg class="nav-ic" viewBox="0 0 24 24" aria-hidden="true"><?= $viewSvgs[$key] ?></svg>
          <?php endif; ?>
          <span><?= e($label) ?></span>
          <b class="count"><?= $viewCounts[$key] ?></b>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="side-divider"></div>

    <div class="cat-head">
      <span class="eyebrow-sm">Categories</span>
      <a class="manage-btn" href="<?= e($manageUrl) ?>">
        <svg viewBox="0 0 24 24"><path d="M4 8h10M18 8h2M4 16h2M10 16h10"/><circle cx="16" cy="8" r="2"/><circle cx="8" cy="16" r="2"/></svg>Manage
      </a>
    </div>
    <?php if ($view !== 'all'): ?>
      <p class="cat-note">Filtering within <?= e($viewNames[$view]) ?>.</p>
    <?php endif; ?>
    <nav class="cats">
      <?php foreach ($categories as $name => $color): $active = $catFilter === $name; ?>
        <a class="nav-item cat-item<?= $active ? ' active' : '' ?>" href="<?= e(url(['cat' => $active ? null : $name])) ?>">
          <i class="dot" style="--c:<?= e($color) ?>"></i>
          <span><?= e($name) ?></span>
          <b class="count"><?= $catCounts[$name] ?></b>
        </a>
      <?php endforeach; ?>
    </nav>
  </aside>

  <!-- ================= Task list (READ) ================= -->
  <main class="main">
    <div class="main-head">
      <div>
        <div class="eyebrow">My tasks</div>
        <h1><?= e($pageTitle) ?></h1>
        <p class="sub"><?= plural(count($tasks), 'task') ?> in this view</p>
      </div>
      <a class="btn btn-primary btn-add" href="<?= e(url(['task' => null, 'new' => 1])) ?>">
        <svg viewBox="0 0 24 24" class="i"><path d="M12 5v14M5 12h14"/></svg>Add task
      </a>
    </div>

    <div class="toolbar">
      <div class="tabs" role="tablist">
        <?php
        // Each tab has its own icon + color so Active and Completed are easy to tell apart.
        $tabs = [
            'all'       => ['All',       '<path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/>'],
            'active'    => ['Active',    '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2.6"/>'],
            'completed' => ['Completed', '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.8 2.8L16 9.8"/>'],
            'overdue'   => ['Overdue',   '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16.2v.01"/>'],
        ];
        foreach ($tabs as $key => [$label, $icon]): ?>
          <a class="tab tab-<?= $key ?><?= $status === $key ? ' active' : '' ?>" role="tab" href="<?= e(url(['status' => $key])) ?>">
            <svg class="tab-ic" viewBox="0 0 24 24" aria-hidden="true"><?= $icon ?></svg>
            <span><?= $label ?></span>
            <b class="tab-count<?= $key === 'overdue' && $statusCounts[$key] > 0 ? ' tab-count-red' : '' ?>"><?= $statusCounts[$key] ?></b>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="sort">
        <span class="sort-label">Sort by</span>
        <div class="sort-wrap">
          <button type="button" class="sort-btn" id="sortBtn" aria-haspopup="true" aria-expanded="false">
            <span><?= e($sortNames[$sort]) ?></span>
            <svg class="chev" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
          </button>
          <ul class="menu menu-right" id="sortMenu" hidden>
            <?php foreach ($sortNames as $key => $label): ?>
              <li><a class="<?= $sort === $key ? 'on' : '' ?>" href="<?= e(url(['sort' => $key])) ?>"><?= e($label) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>

    <?php if ($tasks): ?>
      <ul class="task-list">
        <?php foreach ($tasks as $task): // dynamic rendering with a PHP loop ?>
          <?php $color = $categories[$task['cat']] ?? '#6c7086'; $state = taskState($task); ?>
          <li>
            <a class="task<?= $task['done'] ? ' done' : '' ?><?= $state === 'overdue' ? ' overdue' : '' ?><?= ($showDetail && $selectedId === $task['id']) ? ' selected' : '' ?>"
               href="<?= e(url(['task' => $task['id'], 'new' => null, 'edit' => null, 'del' => null, 'manage' => null, 'rename' => null, 'delcat' => null])) ?>">
              <span class="check-static state-<?= $state ?>" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="2.5" y="2.5" width="19" height="19" rx="6"/><?php if ($task['done']): ?><path d="m7.5 12.5 3 3 6-6.5"/><?php endif; ?></svg>
              </span>
              <div class="task-body">
                <div class="task-top">
                  <div class="task-title"><?= e($task['title']) ?></div>
                  <div class="task-meta">
                    <?= statusPill($state) ?>
                    <span class="chip<?= $task['cat'] === DEFAULT_CATEGORY ? ' plain' : '' ?>" style="--c:<?= e($color) ?>"><?= e($task['cat']) ?></span>
                    <span class="date<?= $state === 'overdue' ? ' date-overdue' : '' ?>"><img src="../assets/icons/calendar.png" alt=""><?= e(formatDate($task['due'])) ?></span>
                  </div>
                </div>
                <div class="task-desc"><?= e($task['desc']) ?></div>
              </div>
            </a>
            <!-- Checkbox hit area: a separate link on top of the card (links can't be nested), opens the confirm popup -->
            <?php $checkLabel = ($task['done'] ? 'Mark as not completed: ' : 'Mark as completed: ') . $task['title']; ?>
            <a class="check-link" href="<?= e(url(['confirm' => $task['id'], 'del' => null, 'manage' => null, 'rename' => null, 'delcat' => null])) ?>" aria-label="<?= e($checkLabel) ?>" title="<?= e($task['done'] ? 'Mark as not completed' : 'Mark as completed') ?>"></a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php elseif (!$allTasks): ?>
      <div class="empty">
        <img src="../assets/icons/no-task.png" alt="" class="empty-ic">
        <h3>No tasks yet</h3>
        <p>Use the form to create your first task.</p>
      </div>
    <?php else: ?>
      <div class="empty">
        <img src="../assets/icons/no-task.png" alt="" class="empty-ic">
        <h3>No tasks match your filters</h3>
        <p>Try another status or clear your search to see more tasks.</p>
        <?php if ($hasFilters): ?>
          <a class="btn btn-ghost" href="index.php"><img src="../assets/icons/clear-filters.png" alt="">Clear filters</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>

  <!-- ================= Right panel ================= -->
  <aside class="panel" id="panel">

    <!-- Mobile only: closes the sheet and goes back to the list -->
    <a class="panel-close" href="<?= e(url(['task' => null, 'new' => null, 'edit' => null, 'del' => null, 'manage' => null, 'rename' => null, 'delcat' => null])) ?>" aria-label="Back to tasks">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>Back to tasks
    </a>

    <div class="panel-card">

    <?php if ($showEdit): // ---------- EDIT: pre-filled task form ---------- ?>
      <?php $color = $categories[$selected['cat']] ?? '#6c7086'; ?>
      <div class="panel-ic panel-ic-edit"><img src="../assets/icons/edit.png" alt="" style="width:18px;height:18px;"></div>
      <h2>Edit task</h2>
      <p class="panel-sub">Make changes without losing the original task.</p>

      <form class="form" id="taskForm" method="POST" novalidate>
        <input type="hidden" name="action"  value="update">
        <input type="hidden" name="task_id" value="<?= e($selectedId) ?>">

        <?php if ($errors): ?>
          <div class="banner" role="alert">
            <img src="../assets/icons/warning.png" alt="">
            <div><strong>Check the highlighted fields</strong><span>Fix the errors below before saving changes.</span></div>
          </div>
        <?php endif; ?>

        <div class="field<?= $bad('title') ?>">
          <label for="task_name"><span>Task name <i class="req">*</i></span></label>
          <input class="input" type="text" id="task_name" name="task_name" maxlength="80"
                 placeholder="What needs to be done?" value="<?= e($old['title']) ?>" autocomplete="off">
          <?php if (isset($errors['title'])): ?><p class="err"><img src="../assets/icons/warning.png" alt=""><?= e($errors['title']) ?></p><?php endif; ?>
        </div>

        <div class="field<?= $bad('desc') ?>">
          <label for="task_description"><span>Description <i class="req">*</i></span><span class="counter" id="counter"><?= mb_strlen($old['desc']) ?>/255</span></label>
          <textarea class="textarea" id="task_description" name="task_description" maxlength="255"
                    placeholder="Add a little context..."><?= e($old['desc']) ?></textarea>
          <?php if (isset($errors['desc'])): ?><p class="err"><img src="../assets/icons/warning.png" alt=""><?= e($errors['desc']) ?></p><?php endif; ?>
        </div>

        <div class="row2">
          <div class="field<?= $bad('due') ?>">
            <label for="due_date"><span>Due date <i class="opt">(optional)</i></span></label>
            <div class="date-input">
              <input type="date" id="due_date" name="due_date" value="<?= e($old['due']) ?>">
              <img src="../assets/icons/calendar.png" alt="">
            </div>
            <?php if (isset($errors['due'])): ?><p class="err"><img src="../assets/icons/warning.png" alt=""><?= e($errors['due']) ?></p><?php endif; ?>
          </div>
          <div class="field<?= $bad('cat') ?>">
            <label for="category"><span>Category <i class="req">*</i></span></label>
            <div class="select-wrap" style="margin-top:0">
              <select class="select" id="category" name="category" style="font-size:15px">
                <?php foreach ($categories as $name => $catColor): ?>
                  <option value="<?= e($name) ?>"<?= $old['cat'] === $name ? ' selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
              </select>
              <svg class="chev" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </div>
            <?php if (isset($errors['cat'])): ?><p class="err"><img src="../assets/icons/warning.png" alt=""><?= e($errors['cat']) ?></p><?php endif; ?>
          </div>
        </div>

        <div class="form-actions">
          <a class="btn btn-cancel" href="<?= e(url(['edit' => null, 'new' => null, 'manage' => null, 'rename' => null, 'delcat' => null])) ?>">Cancel</a>
          <button type="submit" name="submit" class="btn btn-primary">
            <img class="d-only" src="../assets/icons/edit-dark.png" alt="" style="width:15px;height:15px;">
            <svg class="i m-only" viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>Save changes
          </button>
        </div>
      </form>

    <?php elseif ($showDetail): // ---------- READ: task details ---------- ?>
      <?php $color = $categories[$selected['cat']] ?? '#6c7086'; $state = taskState($selected); ?>
      <div class="detail-head">
        <div>
          <div class="p-eyebrow">Task details</div>
          <h2><?= e($selected['title']) ?></h2>
        </div>
        <span class="status-ring state-<?= $state ?><?= $selected['done'] ? ' on' : '' ?>" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
        </span>
      </div>
      <div class="detail-meta">
        <?= statusPill($state) ?>
        <span class="chip" style="--c:<?= e($color) ?>"><?= e($selected['cat']) ?></span>
        <span class="date<?= $state === 'overdue' ? ' date-overdue' : '' ?>"><img src="../assets/icons/calendar.png" alt=""><?= e(formatDate($selected['due'])) ?></span>
      </div>
      <div class="sec-title">Description</div>
      <p class="detail-desc"><?= e($selected['desc']) ?></p>
      <div class="sec-title">Status</div>

      <!-- Toggle done/not-done -->
      <form method="POST" style="margin-top:12px;margin-bottom:0">
        <input type="hidden" name="action"  value="toggle">
        <input type="hidden" name="task_id" value="<?= e($selectedId) ?>">
        <?php foreach (['view' => $view === 'all' ? '' : $view, 'cat' => $catFilter, 'status' => $status === 'all' ? '' : $status, 'sort' => $sort === 'earliest' ? '' : $sort, 'task' => $selectedId, 'q' => $search] as $k => $v): ?>
          <?php if ($v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endif; ?>
        <?php endforeach; ?>
        <?php $nextLabel = $selected['done'] ? 'Mark as not completed' : 'Mark as completed'; ?>
        <button type="submit" class="status-box status-box-btn status-<?= $state ?>" title="<?= e($nextLabel) ?>">
          <span class="status-dot"></span>
          <span class="status-label"><?= $selected['done'] ? 'Completed' : 'Not completed' ?></span>
          <span class="status-action">
            <span class="status-action-text"><?= e($nextLabel) ?></span>
            <svg class="status-swap" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 15 5 5 5-5M7 9l5-5 5 5"/></svg>
          </span>
        </button>
        <p class="status-hint">Click to switch between Completed and Not completed.</p>
      </form>

      <!-- Edit / Delete action buttons -->
      <div class="panel-actions">
        <a class="btn btn-outline btn-edit" href="<?= e(url(['edit' => 1, 'new' => null, 'del' => null, 'manage' => null, 'rename' => null, 'delcat' => null])) ?>">
          <img src="../assets/icons/edit.png" alt="" class="ed d-only">
          <svg class="i m-only" viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16v4zM13.5 6.5l4 4"/></svg>Edit task
        </a>
        <a class="btn btn-danger btn-del" href="<?= e(url(['del' => 1, 'new' => null, 'edit' => null, 'manage' => null, 'rename' => null, 'delcat' => null])) ?>">
          <img src="../assets/icons/delete-red.png" alt="" style="width:15px;height:15px;">Delete task
        </a>
      </div>

    <?php else: // ---------- CREATE: self-submitting form ---------- ?>
      <div class="panel-ic"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></div>
      <h2>Create task</h2>
      <p class="panel-sub">Capture what needs your attention next.</p>

      <form class="form" id="taskForm" method="POST" novalidate>
        <input type="hidden" name="action" value="create">

        <?php if ($errors): ?>
          <div class="banner" role="alert">
            <img src="../assets/icons/warning.png" alt="">
            <div><strong>Check the highlighted fields</strong><span>Fix the errors below before saving this task.</span></div>
          </div>
        <?php endif; ?>

        <div class="field<?= $bad('title') ?>">
          <label for="task_name"><span>Task name <i class="req">*</i></span></label>
          <input class="input" type="text" id="task_name" name="task_name" maxlength="80"
                 placeholder="What needs to be done?" value="<?= e($old['title']) ?>" autocomplete="off">
          <?php if (isset($errors['title'])): ?><p class="err"><img src="../assets/icons/warning.png" alt=""><?= e($errors['title']) ?></p><?php endif; ?>
        </div>

        <div class="field<?= $bad('desc') ?>">
          <label for="task_description"><span>Description <i class="req">*</i></span><span class="counter" id="counter"><?= mb_strlen($old['desc']) ?>/255</span></label>
          <textarea class="textarea" id="task_description" name="task_description" maxlength="255"
                    placeholder="Add a little context..."><?= e($old['desc']) ?></textarea>
          <?php if (isset($errors['desc'])): ?><p class="err"><img src="../assets/icons/warning.png" alt=""><?= e($errors['desc']) ?></p><?php endif; ?>
        </div>

        <div class="row2">
          <div class="field<?= $bad('due') ?>">
            <label for="due_date"><span>Due date <i class="opt">(optional)</i></span></label>
            <div class="date-input">
              <input type="date" id="due_date" name="due_date" value="<?= e($old['due']) ?>">
              <img src="../assets/icons/calendar.png" alt="">
            </div>
            <?php if (isset($errors['due'])): ?><p class="err"><img src="../assets/icons/warning.png" alt=""><?= e($errors['due']) ?></p><?php endif; ?>
          </div>
          <div class="field<?= $bad('cat') ?>">
            <label for="category"><span>Category <i class="req">*</i></span></label>
            <div class="select-wrap" style="margin-top:0">
              <select class="select" id="category" name="category" style="font-size:15px">
                <?php foreach ($categories as $name => $catColor): ?>
                  <option value="<?= e($name) ?>"<?= $old['cat'] === $name ? ' selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
              </select>
              <svg class="chev" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </div>
            <?php if (isset($errors['cat'])): ?><p class="err"><img src="../assets/icons/warning.png" alt=""><?= e($errors['cat']) ?></p><?php endif; ?>
          </div>
        </div>

        <div class="form-actions">
          <a class="btn btn-cancel" href="<?= e(url(['task' => null, 'new' => null])) ?>">Cancel</a>
          <button type="submit" name="submit" class="btn btn-primary">
            <svg viewBox="0 0 24 24" class="i"><path d="M12 5v14M5 12h14"/></svg>Add task
          </button>
        </div>
      </form>
    <?php endif; ?>
    </div><!-- /.panel-card -->
  </aside>
</div>

<?php if ($showDel && $selected): // ---- DELETE CONFIRMATION DIALOG ---- ?>
<div class="overlay" id="delOverlay" role="dialog" aria-modal="true" aria-labelledby="delTitle">
  <div class="dialog">
    <div class="dialog-icon">
      <img src="../assets/icons/delete-red.png" alt="" style="width:20px;height:20px;">
    </div>
    <h2 id="delTitle">Delete this task?</h2>
    <p>This will remove <strong><?= e($selected['title']) ?></strong> from your task list. This action cannot be undone.</p>
    <div class="dialog-actions">
      <a class="btn btn-outline" id="delCancel" href="<?= e(url(['del' => null, 'manage' => null, 'rename' => null, 'delcat' => null])) ?>">Cancel</a>
      <form method="POST" style="margin:0">
        <input type="hidden" name="action"  value="delete">
        <input type="hidden" name="task_id" value="<?= e($selectedId) ?>">
        <?php foreach (['view' => $view === 'all' ? '' : $view, 'cat' => $catFilter, 'status' => $status === 'all' ? '' : $status, 'sort' => $sort === 'earliest' ? '' : $sort, 'q' => $search] as $k => $v): ?>
          <?php if ($v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endif; ?>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-danger">
          <img src="../assets/icons/delete-red.png" alt="" style="width:15px;height:15px;">Delete task
        </button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($showConfirm && !$showManage): // ---- CONFIRM: mark as completed / not completed (task card checkbox) ---- ?>
<?php $toDone = !$confirmTask['done']; ?>
<div class="overlay" id="confirmOverlay" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
  <div class="dialog">
    <div class="dialog-icon <?= $toDone ? 'dialog-icon-ok' : 'dialog-icon-warn' ?>">
      <?php if ($toDone): ?>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5.5 12.5 4.2 4.2L18.5 8"/></svg>
      <?php else: ?>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 14 4 9l5-5M4 9h11a5 5 0 0 1 0 10h-3"/></svg>
      <?php endif; ?>
    </div>
    <h2 id="confirmTitle"><?= $toDone ? 'Mark as completed?' : 'Mark as not completed?' ?></h2>
    <p>
      <strong><?= e($confirmTask['title']) ?></strong>
      <?= $toDone ? 'will be marked as completed. You can change this anytime.' : 'will be moved back to your active tasks.' ?>
    </p>
    <div class="dialog-actions">
      <a class="btn btn-outline" id="confirmCancel" href="<?= e(url()) ?>">Cancel</a>
      <form method="POST" style="margin:0">
        <input type="hidden" name="action"  value="toggle">
        <input type="hidden" name="task_id" value="<?= e($confirmId) ?>">
        <input type="hidden" name="from"    value="list">
        <button type="submit" class="btn btn-primary" id="confirmOk"><?= $toDone ? 'Mark as completed' : 'Mark as not completed' ?></button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($flash && isset($flashText[$flash['type']])): // ---- SUCCESS MODAL (created / updated / deleted) ---- ?>
<div class="overlay" id="flashOverlay" role="alertdialog" aria-modal="true" aria-labelledby="flashTitle">
  <div class="dialog dialog-success">
    <div class="dialog-icon dialog-icon-ok">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5.5 12.5 4.2 4.2L18.5 8"/></svg>
    </div>
    <h2 id="flashTitle"><?= e($flashText[$flash['type']][0]) ?></h2>
    <p><strong><?= e($flash['title']) ?></strong> <?= e($flashText[$flash['type']][1]) ?></p>
    <div class="dialog-actions">
      <a class="btn btn-primary" id="flashDone" href="<?= e(url(['manage' => null, 'rename' => null, 'delcat' => null])) ?>">Done</a>
    </div>
    <div class="flash-bar" aria-hidden="true"><span></span></div>
  </div>
</div>
<?php endif; ?>

<?php if ($showManage): // ---- MANAGE CATEGORIES DIALOG ---- ?>
<div class="overlay<?= ($noAnim || $renameName || $delCatName || $catErrors) ? ' no-anim' : '' ?>" id="manageOverlay" role="dialog" aria-modal="true" aria-labelledby="manageTitle">
  <div class="modal">
    <div class="modal-head">
      <div>
        <div class="p-eyebrow">Organize your way</div>
        <h2 id="manageTitle">Manage categories</h2>
        <p>Create, rename, or remove task categories.</p>
      </div>
      <a class="icon-btn" id="manageClose" href="<?= e($closeManage) ?>" aria-label="Close">
        <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </a>
    </div>

    <!-- New category -->
    <form class="modal-sec" method="POST" novalidate>
      <input type="hidden" name="action" value="cat_add">
      <label class="lbl" for="cat_name">New category</label>
      <div class="cat-add">
        <input class="input<?= isset($catErrors['add']) ? ' bad' : '' ?>" type="text" id="cat_name" name="cat_name"
               maxlength="20" placeholder="e.g. Health" value="<?= e($catOld['name']) ?>" autocomplete="off">
        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 24 24" class="i"><path d="M12 5v14M5 12h14"/></svg>Add
        </button>
      </div>
      <?php if (isset($catErrors['add'])): ?><p class="err"><img src="../assets/icons/warning.png" alt=""><?= e($catErrors['add']) ?></p><?php endif; ?>
      <div class="color-row">
        <span class="color-lbl">Color</span>
        <div class="swatches" role="radiogroup" aria-label="Category color">
          <?php foreach ($palette as $hex): $on = $catOld['color'] === $hex; ?>
            <label class="swatch<?= $on ? ' on' : '' ?>" style="--c:<?= e($hex) ?>">
              <input class="sr-only" type="radio" name="cat_color" value="<?= e($hex) ?>"<?= $on ? ' checked' : '' ?>>
              <svg viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </form>

    <!-- Existing categories -->
    <div class="cat-list">
      <?php foreach ($categories as $name => $color):
        $name      = (string) $name;
        $isDefault = $name === DEFAULT_CATEGORY;
        $used      = count(array_filter($allTasks, fn($t) => $t['cat'] === $name));
      ?>
        <?php if ($renameName === $name): // inline rename ?>
          <form class="cat-row" method="POST" novalidate>
            <input type="hidden" name="action" value="cat_rename">
            <input type="hidden" name="cat_orig" value="<?= e($name) ?>">
            <i class="dot" style="--c:<?= e($color) ?>"></i>
            <div class="info">
              <input class="rename-input<?= isset($catErrors['rename']) ? ' bad' : '' ?>" type="text" name="cat_name" maxlength="20"
                     value="<?= e(isset($catErrors['rename']) ? $catOld['rename'] : $name) ?>" autocomplete="off" aria-label="Category name">
              <?php if (isset($catErrors['rename'])): ?><p class="err"><?= e($catErrors['rename']) ?></p><?php endif; ?>
            </div>
            <div class="acts">
              <button type="submit" aria-label="Save name"><svg viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg></button>
              <a href="<?= e(url(['rename' => null, 'delcat' => null])) ?>" aria-label="Cancel"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></a>
            </div>
          </form>
        <?php elseif ($delCatName === $name): // delete confirmation ?>
          <div class="confirm-row">
            <span>Delete <b><?= e($name) ?></b>?<?= $used ? ' Its ' . e(plural($used, 'task')) . ' will move to ' . e(DEFAULT_CATEGORY) . '.' : '' ?></span>
            <div class="btns">
              <a class="btn btn-outline" href="<?= e(url(['delcat' => null])) ?>">Cancel</a>
              <form method="POST" style="margin:0">
                <input type="hidden" name="action" value="cat_delete">
                <input type="hidden" name="cat_orig" value="<?= e($name) ?>">
                <button type="submit" class="btn btn-danger">Delete</button>
              </form>
            </div>
          </div>
        <?php else: // normal row ?>
          <div class="cat-row">
            <i class="dot" style="--c:<?= e($color) ?>"></i>
            <div class="info">
              <div class="nm"><?= e($name) ?></div>
              <div class="ct"><?= e(plural($used, 'task')) ?><?= $isDefault ? ' · Default' : '' ?></div>
            </div>
            <?php if (!$isDefault): ?>
              <div class="acts">
                <a href="<?= e(url(['rename' => $name, 'delcat' => null])) ?>" aria-label="Rename <?= e($name) ?>">
                  <svg viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16v4zM13.5 6.5l4 4"/></svg>
                </a>
                <a href="<?= e(url(['delcat' => $name, 'rename' => null])) ?>" aria-label="Delete <?= e($name) ?>">
                  <svg viewBox="0 0 24 24"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>
                </a>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

</body>
</html>