<h2>
  Contests 
  <?php if (($_SESSION['username'] ?? '') === 'admin'): ?>
    <span class="admin-link"><a href="index.php?action=add_contest">[Add]</a></span>
  <?php endif; ?>
</h2>
<?php if ($contests): ?>
  <table>
    <thead>
      <tr>
        <th style="text-align: center">#</th>
        <th>Title</th>
        <th>Start (UTC)</th>
        <th>End (UTC)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($contests as $c): ?>
        <tr>
          <td><?= $c['id'] ?></td>
          <td style="max-width: 300px">
            <a href="index.php?action=view_contest&id=<?= (int)$c['id'] ?>">
              <?= htmlspecialchars($c['title']) ?>
            </a>
          </td>
          <td><?= $c['start'] ?></td>
          <td><?= $c['end'] ?></td>
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
