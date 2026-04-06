<h2>Edit Problem</h2>
<form method="POST" action="index.php?action=edit_problem" enctype="multipart/form-data">
  <input type="hidden" name="id" value="<?= $problem['id'] ?>">
  <?php if ($is_admin): ?>
    <h3>Title</h3>
    <input type="text" name="title" value="<?= htmlspecialchars($problem['title']) ?>" required>
  <?php else: ?>
    <b><?= htmlspecialchars($problem['title']) ?></b>
  <?php endif; ?>
  <h3>Statement</h3>
  <textarea rows="4" name="statement"><?= htmlspecialchars($problem['statement']) ?></textarea>
  <h3>Template</h3>
  <textarea name="template_text" style="white-space: nowrap" rows="4"><?= htmlspecialchars($problem['template']) ?></textarea>
  <p>Or upload as a file (.lean)</p>
  <input type="file" name="template_file" accept=".lean">
  <h3>Dependencies</h3>
  <select name="dependencies[]" multiple style="height: 100px;">
    <?php foreach ($other_problems as $op): ?>
      <option value="<?= $op['id'] ?>" <?= in_array($op['id'], $problem['deps_array'] ?: []) ? 'selected' : '' ?>>
        <?= htmlspecialchars($op['title']) ?> (ID: <?= $op['id'] ?>)
      </option>
    <?php endforeach; ?>
  </select>
  <input style="float: right" type="submit" value="Save Changes">
</form>
