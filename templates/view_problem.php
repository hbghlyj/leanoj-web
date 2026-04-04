<h2>
  <?= htmlspecialchars($problem['title']) ?>
  <?php if ($user_id): ?>
    <span class="admin-link">
      <a href="index.php?action=edit_problem&id=<?= (int)$problem['id'] ?>">[Edit]</a>
      <a href="index.php?action=view_history&id=<?= (int)$problem['id'] ?>">[History]</a>
    </span>
  <?php endif; ?>
  <?php if ($is_admin || ($user_id && $problem['owner_id'] == $user_id)): ?>
    <form method="POST" action="index.php?action=delete_problem" style="display:inline;" onsubmit="return confirm('Delete this problem and all its history/submissions?');">
      <input type="hidden" name="id" value="<?= (int)$problem['id'] ?>">
      <input type="submit" value="Delete">
    </form>
  <?php endif; ?>
</h2>
  <p class="markdown"><?= nl2br(htmlspecialchars($problem['statement'])) ?></p>
  <p>
    <em>Replace</em> <code>sorry</code> <em>in the template below with your solution.</em>
    <?php if ($problem['dependency_details']): ?>
      <em>Dependencies:</em>
      <?php foreach ($problem['dependency_details'] as $dep): ?>
        <a href="index.php?action=view_problem&id=<?= $dep['id'] ?>"><?= htmlspecialchars($dep['title']) ?></a> 
      <?php endforeach; ?>
    <?php endif; ?>
    <em>Mathlib version used by the checker is v4.29.0.</em>
  </p>
  <div class="code-container">
    <button class="copy-button" type="button" onclick="copyCode(this)">Copy</button>
    <pre><?= htmlspecialchars($problem['template']) ?></pre>
  </div>
  <h3>Submit Solution</h3>
  <?php if ($user_id): ?>
    <?php if (!empty($problem['template'])): ?>
      <p style="font-size: 0.85em; color: #666;">
        Tip: Verify your proof on the <strong><a href="https://live.lean-lang.org/" target="_blank">Lean 4 Web Editor</a></strong> before submitting.
      </p>
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
      <p class="warning-box" style="color: #d9534f; background: #f9f2f2; padding: 10px; border-left: 5px solid #d9534f;">
        <strong>Submissions Disabled:</strong> A Lean 4 template is required to enable submissions for this problem.
      </p>
    <?php endif; ?>
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
            <?php if ($is_admin || ($user_id && $s['user'] == $user_id)): ?>
              <form method="POST" action="index.php?action=delete_submission" style="display:inline;" onsubmit="return confirm('Delete submission?');">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <input type="submit" value="x" style="padding: 0 4px; color: red;">
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <a href="index.php?action=view_submissions&id=<?= $problem['id'] ?>">View all</a>
  <?php else: ?>
    <p>None yet.</p>
  <?php endif; ?>
