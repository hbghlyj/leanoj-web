<h2>
  <?= htmlspecialchars($contest['title']) ?>
  <?php if ($is_admin): ?>
    <span class="admin-link"><a href="index.php?action=edit_contest&id=<?= $contest['id'] ?>">[Edit]</a></span>
  <?php endif; ?>
</h2>

<h3>Problems</h3>
<?php if ($problems): ?>
  <table>
    <thead>
      <tr>
        <th style="text-align: center">#</th>
        <th>Title</th>
        <th>Solved</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($problems as $p): ?>
        <tr>
          <td><?= $p['id'] ?></td>
          <td style="max-width: 400px">
            <?= $p['is_solved'] ? "🎉 " : "" ?>
            <a href="index.php?action=view_problem&id=<?= (int)$p['id'] ?>">
              <?= htmlspecialchars($p['title']) ?>
            </a>
          </td>
          <td><?= (int)$p['solves'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<p><a href="index.php?action=results&id=<?= (int)$id ?>">Results</a></p>
<?php else: ?>
  <p>None yet.</p>
<?php endif; ?>
