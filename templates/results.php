<h2>Results</h2>
<?php if ($results): ?>
  <table>
    <thead>
      <tr>
        <th>Rank</th>
        <th>Username</th>
        <th>Solved</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($results as $index => $row): ?>
        <tr>
          <td><?= $offset + $index + 1 ?></td>
          <td style="max-width: 400px">
            <?= htmlspecialchars($row['username']) ?>
          </td>
          <td><?= (int)$row['solved'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="index.php?action=results&id=<?= $id ?>&page=<?= $page - 1 ?>">&#9664; prev.</a>
    <?php endif; ?>
    <span>Page <?= $page ?> of <?= $total_pages ?></span>
    <?php if ($page < $total_pages): ?>
      <a href="index.php?action=results&id=<?= $id ?>&page=<?= $page + 1 ?>">next &#9654;</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <p>None yet.</p>
<?php endif; ?>
