<h2>Local Files (Reference Solutions)</h2>
<p><a href="index.php?action=add_local_file" class="button">Register New Local File</a></p>

<?php if ($local_files): ?>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Safe Path</th>
        <th>Status</th>
        <th>Creator</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($local_files as $lf): ?>
        <tr>
          <td><?= $lf['id'] ?></td>
          <td><code><?= htmlspecialchars(str_replace('/var/www/leanoj/file/', '', $lf['path'])) ?></code></td>
          <td>
            <span class="status-<?= strtolower($lf['status']) ?>"><?= htmlspecialchars($lf['status']) ?></span>
            <?php if (!empty($lf['log'])): ?>
              <br><small style="color:red;"><?= htmlspecialchars(mb_substr($lf['log'], 0, 100)) ?>...</small>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($lf['creator_name'] ?? "Admin") ?></td>
          <td>
            <?php if ($is_admin || $lf['creator_id'] == $_SESSION['user_id']): ?>
              <a href="index.php?action=edit_local_file&id=<?= $lf['id'] ?>">[Edit]</a>
              <form method="POST" action="index.php?action=delete_local_file" style="display:inline;" onsubmit="return confirm('Are you sure? This will delete the physical file and all compiled artifacts.');">
                <input type="hidden" name="id" value="<?= $lf['id'] ?>">
                <button type="submit" style="background:none; border:none; color:red; cursor:pointer; padding:0; font:inherit; text-decoration:underline;">[Delete]</button>
              </form>
            <?php endif; ?>
            <a href="index.php?action=view_local_file_history&id=<?= $lf['id'] ?>">[History]</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?>
  <p>No local files registered yet.</p>
<?php endif; ?>
