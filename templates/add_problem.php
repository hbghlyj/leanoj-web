<h2>Add Problem</h2>
<form method="POST" action="index.php?action=add_problem" enctype="multipart/form-data">
  <h3>Title</h3>
  <input type="text" name="title" value="<?= htmlspecialchars($flash_input['title'] ?? '') ?>" required>
  <h3>Statement</h3>
  <textarea rows="4" name="statement"><?= htmlspecialchars($flash_input['statement'] ?? '') ?></textarea>
  <h3>Template</h3>
  <textarea name="template_text" style="white-space: nowrap" rows="4"><?= htmlspecialchars($flash_input['template_text'] ?? '') ?></textarea>
  <p>Or upload as a file (.lean):</p>
  <input type="file" name="template_file" accept=".lean">
  <h3>Dependencies</h3>
  <select name="dependencies[]" multiple style="height: 100px;">
    <?php foreach ($other_problems as $op): ?>
      <option value="<?= $op['id'] ?>" <?= in_array($op['id'], $flash_input['dependencies'] ?? []) ? 'selected' : '' ?>><?= htmlspecialchars($op['title']) ?> (ID: <?= $op['id'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <input style="float: right" type="submit" value="Add Problem">
</form>
