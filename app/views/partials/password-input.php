<?php
/** Password field with a show/hide toggle. Vars: $name, $label, $errors, $autocomplete, ?$help */
$id = 'f_' . $name;
?>
<div class="mb-3">
    <label for="<?= e($id) ?>" class="form-label"><?= e($label) ?></label>
    <div class="input-group has-validation">
        <input type="password" class="form-control<?= invalid($errors, $name) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>"
               autocomplete="<?= e($autocomplete) ?>" required<?= isset($help) ? ' aria-describedby="' . e($id) . '_help"' : '' ?>>
        <button class="btn btn-outline-secondary" type="button" data-toggle-password="<?= e($id) ?>" aria-label="Show password">
            <i class="bi bi-eye" aria-hidden="true"></i>
        </button>
        <?= feedback($errors, $name) ?>
    </div>
    <?php if (isset($help)): ?><div id="<?= e($id) ?>_help" class="form-text"><?= e($help) ?></div><?php endif; ?>
</div>
