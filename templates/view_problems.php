<h2>
  Problems 
  <?php if ($user_id): ?>
    <span class="admin-link"><a href="index.php?action=add_problem">[Add]</a></span>
  <?php endif; ?>
</h2>
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

  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="index.php?action=view_problems&page=<?= $page - 1 ?>">&#9664 prev.</a>
    <?php endif; ?>
    <span>Page <?= $page ?> of <?= $total_pages ?></span>
    <?php if ($page < $total_pages): ?>
      <a href="index.php?action=view_problems&page=<?= $page + 1 ?>">next &#9654</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <p>None yet.</p>
<?php endif; ?>
