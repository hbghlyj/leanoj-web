<h3>
  Submissions
  <?php if ($for_problem): ?>
    for
    <a href="index.php?action=view_problem&id=<?= (int)$problem['id'] ?>">
      <?= htmlspecialchars($problem['title']) ?>
    </a>
  <?php endif; ?>
</h3>
<?php if ($submissions): ?>
  <table>
    <thead>
      <tr>
        <th style="text-align: center">#</th>
        <th>User</th>
        <?php if (!$for_problem): ?><th>Problem</th><?php endif; ?>
        <th>Time (UTC)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($submissions as $s): ?>
        <tr>
          <td>
            <a href="index.php?action=view_submission&id=<?= (int)$s['id'] ?>">
              <?= (int)$s['id'] ?>
            </a>
          </td>
          <td style="max-width: <?= $for_problem ? "300px" : "120px" ?>">
            <?= htmlspecialchars($s['username']) ?>
          </td>
          <?php if (!$for_problem): ?>
            <td style="max-width: 280px">
              <a href="index.php?action=view_problem&id=<?= $s['problem']?>">
                <?= htmlspecialchars($s['title']) ?>
              </a>
            </td>
          <?php endif; ?>
          <td><?= $s['time'] ?? "Long time ago" ?></td>
          <?php if ($is_admin || ($user_id && $s['user'] == $user_id)): ?>
            <td style="border-left: none;">
              <form method="POST" action="index.php?action=delete_submission" style="display:inline;" onsubmit="return confirm('Delete submission?');">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <input type="submit" value="x" style="padding: 0 4px; color: red;">
              </form>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="index.php?action=view_submissions&id=<?= $problem['id'] ?>&page=<?= $page - 1 ?>">&#9664; prev.</a>
    <?php endif; ?>
    <span>Page <?= $page ?> of <?= $total_pages ?></span>
    <?php if ($page < $total_pages): ?>
      <a href="index.php?action=view_submissions&id=<?= $problem['id'] ?>&page=<?= $page + 1 ?>">next &#9654;</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <p>None yet.</p>
<?php endif; ?>
