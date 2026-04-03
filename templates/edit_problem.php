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
  <textarea rows="4" name="statement" required><?= htmlspecialchars($problem['statement']) ?></textarea>
  <h3>Template</h3>
  <textarea name="template_text" style="white-space: nowrap" rows="4"><?= htmlspecialchars($problem['template']) ?></textarea>
  <p>Or upload as a file (.lean)</p>
  <input type="file" name="template_file" accept=".lean">
  <?php if ($is_admin): ?>
    <h3>Local Reference File</h3>
    <select name="answer">
      <option value="">None</option>
      <?php foreach ($local_files as $lf): ?>
        <option value="<?= $lf['id'] ?>" <?= ($problem['answer'] == $lf['id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars(str_replace('/var/www/leanoj/local_files/', '', $lf['path'])) ?>
        </option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>
  <input style="float: right" type="submit" value="Save Changes">
</form>
