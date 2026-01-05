<?php
require_once '../includes/rules.php';
require_once '../includes/predefined_rules.php';

// Handle rule selection
if (isset($_POST['select_rules'])) {
    $game_id = $_POST['game_id'];
    $_SESSION['custom_rules'][$game_id] = $_POST['rules'] ?? [];
}
?>

<h3>Select Rules</h3>
<form method="POST">
    <label for="game">Choose Game:</label>
    <select name="game_id" id="game-select">
        <?php foreach ($predefined_rules as $game => $rules): ?>
            <option value="<?php echo $game; ?>"><?php echo ucfirst(str_replace("_"," ",$game)); ?></option>
        <?php endforeach; ?>
    </select>

    <div id="rules-container"></div>

    <button type="submit" name="select_rules">Save Rules</button>
</form>

<script>
const rules = <?php echo json_encode($predefined_rules); ?>;
const select = document.getElementById('game-select');
const container = document.getElementById('rules-container');

function renderRules(game) {
    container.innerHTML = '';
    rules[game].forEach(rule => {
        const label = document.createElement('label');
        label.innerHTML = `<input type="checkbox" name="rules[]" value="${rule}"> ${rule}`;
        container.appendChild(label);
        container.appendChild(document.createElement('br'));
    });
}
select.addEventListener('change', e => renderRules(e.target.value));
renderRules(select.value); // render first game by default
</script>

