<h2>History for Local File: <?= htmlspecialchars(str_replace('/var/www/leanoj/local_files/', '', $lf['path'])) ?></h2>
<p><strong>Creator:</strong> <?= htmlspecialchars($lf['creator_name'] ?? "Admin") ?></p>

<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Snapshot Date</th>
      <th>Contributor</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($revisions as $rev): ?>
      <tr>
        <td><?= $rev['id'] ?></td>
        <td><?= $rev['time'] ?></td>
        <td><?= htmlspecialchars($rev['username'] ?? "Anonymous") ?></td>
        <td>
          <?php if ($can_edit): ?>
            <form method="POST" action="index.php?action=rollback_local_file" style="display:inline;" onsubmit="return confirm('Roll back to this version? This will overwrite the file and create a new revision.');">
              <input type="hidden" name="rev_id" value="<?= (int)$rev['id'] ?>">
              <input type="submit" value="Rollback">
            </form>
          <?php endif; ?>
          <?php if (($_SESSION['username'] ?? '') === 'admin'): ?>
            <form method="POST" action="index.php?action=delete_local_file_revision" style="display:inline;" onsubmit="return confirm('Permanently delete this snapshot?');">
              <input type="hidden" name="rev_id" value="<?= (int)$rev['id'] ?>">
              <input type="hidden" name="fid" value="<?= (int)$lf['id'] ?>">
              <input type="submit" value="Delete">
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<hr>
<p><a href="index.php?action=view_local_files">[Back to File List]</a></p>
