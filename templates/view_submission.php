<h2>
  Submission #<?= (int)$submission['id'] ?>
  <?php if ($is_admin): ?>
    <form method="POST" action="index.php?action=rejudge" style="display:inline;">
      <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
      <input type="submit" value="Rejudge">
    </form>
  <?php endif; ?>
</h2>
<table>
  <thead>
    <tr>
      <th style="text-align: center">#</th>
      <th>User</th>
      <th>Problem</th>
      <th>Time (UTC)</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>
        <a href="index.php?action=view_submission&id=<?= (int)$submission['id'] ?>">
          <?= (int)$submission['id'] ?>
        </a>
      </td>
      <td style="max-width: 120px">
        <?= htmlspecialchars($submission['username']) ?>
      </td>
      <td style="max-width: 280px">
        <a href="index.php?action=view_problem&id=<?= (int)$submission['problem'] ?>">
          <?= htmlspecialchars($submission['title']) ?>
        </a>
      </td>
      <td><?= $submission['time'] ?: "Long time ago" ?></td>
      <td class="status-cell">
        <span class="status-<?= str_replace(' ', '-', strtolower($submission['status'])) ?>">
          <?= htmlspecialchars($submission['status']) ?>
        </span>
        <a href="index.php?action=status_info">ⓘ</a>
      </td>
    </tr>
  </tbody>
</table>
<?php if ($show_source): ?>
  <div class="code-container">
    <button class="copy-button" type="button" onclick="copyCode(this)">Copy</button>
    <pre><?= htmlspecialchars($submission['source']) ?></pre>
  </div>
<?php else: ?>
  <p>You have to solve the problem first to view the source code.</p>
<?php endif; ?>
