<h2>Edit Local File: <?= htmlspecialchars(str_replace('/var/www/leanoj/local_files/', '', $lf['path'])) ?></h2>
<p>You can modify the path or the content. Every save will create a new revision in the history.</p>

<form method="POST" action="index.php?action=edit_local_file">
  <input type="hidden" name="id" value="<?= (int) $lf['id'] ?>">

  <div style="margin-bottom: 10px;">
    <label>Relative Path (from safe directory):</label>
    <input type="text" name="path"
      value="<?= htmlspecialchars(str_replace('/var/www/leanoj/local_files/', '', $lf['path'])) ?>" required
      style="width: 100%;">
  </div>



  <div style="margin-bottom: 10px;">
    <label>Content (.lean code):</label>
    <textarea name="content" rows="30" style="width: 100%; font-family: monospace;"
      required><?= htmlspecialchars($content) ?></textarea>
  </div>

  <input type="submit" value="Update File and Save Revision">
</form>

<hr>
<p><a href="index.php?action=view_local_file_history&id=<?= $lf['id'] ?>">[View Version History]</a></p>