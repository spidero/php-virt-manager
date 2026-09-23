<?php

// users with global roles; each role includes the permissions of the lower ones

const ROLES = ['viewer' => 1, 'operator' => 2, 'admin' => 3];

// permission => minimal role
const PERMISSIONS = [
    'view'    => 'viewer',   // read everything
    'operate' => 'operator', // power, snapshots, console, create/edit/clone VM
    'admin'   => 'admin',    // delete VM, storage/network management, schedules, users
];

const USERNAME_PATTERN = '/^[A-Za-z0-9._@-]{1,64}$/';
const PASSWORD_MIN_LENGTH = 8;

function user_find($id) {
    return db_query('SELECT * FROM users WHERE id = ?', [$id])->fetch() ?: null;
}

function user_find_by_name($username) {
    return db_query('SELECT * FROM users WHERE username = ?', [$username])->fetch() ?: null;
}

function user_list() {
    return db_query('SELECT * FROM users ORDER BY username COLLATE NOCASE')->fetchAll();
}

function user_count_admins() {
    return (int)db_query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
}

function user_create($username, $password_hash, $role) {
    db_query('INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, ?, ?)',
        [$username, $password_hash, $role, time()]);
    return (int)db()->lastInsertId();
}

function user_update($id, array $fields) {
    $allowed = ['password_hash', 'role', 'lang', 'last_login'];
    $set = [];
    $params = [];
    foreach ($fields as $name => $value) {
        if (in_array($name, $allowed, true)) {
            $set[] = $name.' = ?';
            $params[] = $value;
        }
    }
    if ($set) {
        $params[] = $id;
        db_query('UPDATE users SET '.implode(', ', $set).' WHERE id = ?', $params);
    }
}

function user_delete($id) {
    db_query('DELETE FROM users WHERE id = ?', [$id]);
}

// first run: the admin account from config.php becomes the first user
function users_bootstrap($username, $password_hash) {
    if ($password_hash !== '' && (int)db_query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        user_create($username, $password_hash, 'admin');
    }
}

// null when valid, otherwise an error message
function user_validate_password($password) {
    return mb_strlen($password) < PASSWORD_MIN_LENGTH
        ? t('Password must have at least %d characters.', PASSWORD_MIN_LENGTH)
        : null;
}

function role_allows($role, $permission) {
    $needed = PERMISSIONS[$permission] ?? 'admin';
    return (ROLES[$role] ?? 0) >= ROLES[$needed];
}

// user of the current session, reloaded on every request so role changes apply at once
function current_user() {
    static $user = false;
    if ($user === false) {
        $user = !empty($_SESSION['user_id']) ? user_find((int)$_SESSION['user_id']) : null;
    }
    return $user;
}

function can($permission) {
    $user = current_user();
    return $user !== null && role_allows($user['role'], $permission);
}

// stops the request when the current user lacks the permission
function require_permission($permission, $back_url = 'index.php') {
    if (!can($permission)) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $back_url === null) {
            http_response_code(403);
        }
        flash_set('danger', t('You do not have permission for this action.'));
        redirect($back_url ?? 'index.php');
    }
}

// permission flags for templates
function permission_flags() {
    $flags = [];
    foreach (array_keys(PERMISSIONS) as $permission) {
        $flags[$permission] = can($permission);
    }
    return $flags;
}
