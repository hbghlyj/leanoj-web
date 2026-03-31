<h2>
  <?= htmlspecialchars($problem['title']) ?>
  <?php if ($is_admin): ?>
    <span class="admin-link">
      <a href="index.php?action=edit_problem&id=<?= (int)$problem['id'] ?>">[Edit]</a>
    </span>
    <?php if ($problem['contest']): ?>
      <form method="POST" action="index.php?action=toggle_archive" style="display:inline;">
        <input type="hidden" name="id" value="<?= (int)$problem['id'] ?>">
        <input type="submit" value=<?= $problem['archived'] ? "Unarchive": "Archive"?>>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</h2>
<?php if ($can_view): ?>
  <p class="markdown"><?= nl2br(htmlspecialchars($problem['statement'])) ?></p>
  <p>
    <em>Replace</em> <code>sorry</code> <em>in the template below with your solution.</em>
    <?php if ($problem['answer']): ?>
      <em>See</em> <a href="index.php?action=view_answers">Answer Bank</a> <em>for acceptible answer declarations.</em>
    <?php endif; ?>
    <em>Mathlib version used by the checker is v4.29.0.</em>
  </p>
  <div class="code-container">
    <button class="copy-button" type="button" onclick="copyCode(this)">Copy</button>
    <pre><?= htmlspecialchars($problem['template']) ?></pre>
  </div>
  <h3>Submit Solution</h3>
  <?php if ($user_id): ?>
    <form action="index.php?action=submit_solution" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="problem_id" value="<?= $problem['id'] ?>">
      <div>
      <textarea name="source_text" style="white-space: nowrap" rows="4" placeholder="Paste your code here..."></textarea>
      </div>
    <p>Or upload as a file (.lean):</p>
    <input type="file" name="source_file" accept=".lean">
    &nbsp;
    <input type="submit" value="Submit">
    </form>
  <?php else: ?>
    <p><a href="index.php?action=login">Login</a> to submit a solution.</p>
  <?php endif; ?>
  <h3>Recent Submissions</h3>
  <?php if ($recent_submissions): ?>
  <table>
    <thead>
      <tr>
        <th style="text-align: center">#</th>
        <th>User</th>
        <th>Time (UTC)</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($recent_submissions as $s): ?>
        <tr>
          <td>
            <a href="index.php?action=view_submission&id=<?= (int)$s['id'] ?>">
              <?= (int)$s['id'] ?>
            </a>
          </td>
          <td style="max-width: 400px">
            <?= htmlspecialchars($s['username']) ?>
          </td>
          <td><?= $s['time'] ?? "Long time ago" ?></td>
          <td class="status-cell">
            <span class="status-<?= str_replace(' ', '-', strtolower($s['status'])) ?>">
              <?= htmlspecialchars($s['status']) ?>
            </span>
            <a href="index.php?action=status_info">ⓘ</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <a href="index.php?action=view_submissions&id=<?= $problem['id'] ?>">View all</a>
  <?php else: ?>
    <p>None yet.</p>
  <?php endif; ?>
<?php else: ?>
  <p>Contest hasn't started.</p>
<?php endif; ?>
