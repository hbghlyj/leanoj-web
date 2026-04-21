<h2>
  Submission #<?= (int)$submission['id'] ?>
  <?php if ($is_admin || ($user_id && $submission['user'] == $user_id)): ?>
    <a href="index.php?action=view_problem&id=<?= (int)$submission['problem'] ?>&edit_submission=<?= (int)$submission['id'] ?>">
      <button type="button">Edit</button>
    </a>
    <form method="POST" action="index.php?action=delete_submission" style="display:inline;" onsubmit="return confirm('Delete submission?');">
      <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
      <input type="submit" value="Delete">
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
    </tr>
  </tbody>
</table>
<div class="code-container">
  <button class="copy-button" type="button" onclick="copyCode(this)">Copy</button>
  <pre><?= htmlspecialchars($submission['source']) ?></pre>
</div>
