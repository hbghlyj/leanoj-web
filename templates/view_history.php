<h2>
  History: <a href="index.php?action=view_problem&id=<?= (int)$problem['id'] ?>"><?= htmlspecialchars($problem['title']) ?></a>
</h2>

<?php if ($revisions): ?>
  <table>
    <thead>
      <tr>
        <th style="text-align: center">ID</th>
        <th>Time (UTC)</th>
        <th>User</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($revisions as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= htmlspecialchars($r['time']) ?></td>
          <td><?= htmlspecialchars($r['username'] ?? 'Unknown') ?></td>
          <td>
            <a href="index.php?action=compare_revision&id=<?= (int)$problem['id'] ?>&rev1=<?= (int)$r['id'] ?>">[What Changed?]</a>
            <a href="index.php?action=compare_revision&id=<?= (int)$problem['id'] ?>&rev1=0&rev2=<?= (int)$r['id'] ?>">[Compare to Current]</a>
            
            <?php if ($user_id): ?>
              <form method="POST" action="index.php?action=rollback_revision" style="display:inline;" onsubmit="return confirm('Rollback problem to this version?');">
                <input type="hidden" name="rev_id" value="<?= (int)$r['id'] ?>">
                <input type="submit" value="Rollback" style="padding: 0 4px;">
              </form>
            <?php endif; ?>

            <?php if ($is_admin): ?>
              <form method="POST" action="index.php?action=delete_revision" style="display:inline;" onsubmit="return confirm('Permanently delete this revision?');">
                <input type="hidden" name="prob_id" value="<?= (int)$problem['id'] ?>">
                <input type="hidden" name="rev_id" value="<?= (int)$r['id'] ?>">
                <input type="submit" value="Delete" style="padding: 0 4px; color: red;">
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?>
  <p>No edit history found.</p>
<?php endif; ?>
