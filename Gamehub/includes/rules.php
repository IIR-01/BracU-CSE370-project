<?php

if (!isset($_SESSION['custom_rules'])) {
    $_SESSION['custom_rules'] = [];
}

function addRule($game_id, $rule_text) {
    $rule_text = trim($rule_text);
    if (!empty($rule_text)) {
        if (!isset($_SESSION['custom_rules'][$game_id])) {
            $_SESSION['custom_rules'][$game_id] = [];
        }
        $_SESSION['custom_rules'][$game_id][] = $rule_text;
    }
}

function getRules($game_id = null) {
    if ($game_id !== null) {
        return $_SESSION['custom_rules'][$game_id] ?? [];
    }
    return $_SESSION['custom_rules'];
}


function clearRules($game_id = null) {
    if ($game_id !== null) {
        unset($_SESSION['custom_rules'][$game_id]);
    } else {
        $_SESSION['custom_rules'] = [];
    }
}
?>

