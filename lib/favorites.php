<?php

// machines starred by a user: shown in the menu, per connection

function favorite_names($user_id, $conn) {
    return db_query('SELECT name FROM favorites WHERE user_id = ? AND conn = ? ORDER BY name COLLATE NOCASE',
        [$user_id, $conn])->fetchAll(PDO::FETCH_COLUMN);
}

// stars or unstars a machine; returns the new state
function favorite_toggle($user_id, $conn, $name) {
    if (db_query('DELETE FROM favorites WHERE user_id = ? AND conn = ? AND name = ?', [$user_id, $conn, $name])->rowCount()) {
        return false;
    }
    db_query('INSERT INTO favorites (user_id, conn, name) VALUES (?, ?, ?)', [$user_id, $conn, $name]);
    return true;
}

// favorites of the current user that exist on the connection, with their state (menu)
function favorite_menu_items($con, $user_id, $conn) {
    $names = favorite_names($user_id, $conn);
    if (!$names) {
        return [];
    }
    $existing = libvirt_list_domains($con) ?: [];
    $items = [];
    foreach ($names as $name) {
        if (!in_array($name, $existing, true)) {
            continue;
        }
        $res = libvirt_domain_lookup_by_name($con, $name);
        $info = $res ? libvirt_domain_get_info($res) : ['state' => -1];
        $items[] = ['name' => $name] + domain_state($info['state']);
    }
    return $items;
}
