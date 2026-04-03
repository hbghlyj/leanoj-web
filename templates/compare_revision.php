<h2>Comparing Revisions</h2>
<p>
  <strong>Newer Version:</strong> <?= htmlspecialchars($rev1['time']) ?> by <?= htmlspecialchars($rev1['username'] ?? 'Unknown') ?>
  <br>
  <strong>Older Version:</strong> <?= $rev2 ? htmlspecialchars($rev2['time']) : "Initial State (Empty)" ?>
</p>

<div style="display: flex; gap: 20px;">
  <div style="flex: 1;">
    <h3>Statement Differences</h3>
    <pre id="statement-diff" style="white-space: pre-wrap; background: #fdfdfd; border: 1px solid #ccc; padding: 10px;"></pre>
  </div>
  <div style="flex: 1;">
    <h3>Template Differences</h3>
    <pre id="template-diff" style="white-space: pre-wrap; background: #fdfdfd; border: 1px solid #ccc; padding: 10px;"></pre>
  </div>
</div>

<script>
  function renderDiff(oldStr, newStr, elementId) {
    const diff = Diff.diffLines(oldStr, newStr);
    const displayElement = document.getElementById(elementId);
    const fragment = document.createDocumentFragment();

    diff.forEach((part) => {
      const span = document.createElement('span');
      span.style.backgroundColor = part.added ? '#d4f2d4' : part.removed ? '#f2d4d4' : 'transparent';
      span.appendChild(document.createTextNode(part.value));
      fragment.appendChild(span);
    });

    displayElement.appendChild(fragment);
  }

  const oldStatement = <?= json_encode($rev2['statement'] ?? "") ?>;
  const newStatement = <?= json_encode($rev1['statement'] ?? "") ?>;
  renderDiff(oldStatement, newStatement, 'statement-diff');

  const oldTemplate = <?= json_encode($rev2['template'] ?? "") ?>;
  const newTemplate = <?= json_encode($rev1['template'] ?? "") ?>;
  renderDiff(oldTemplate, newTemplate, 'template-diff');
</script>

<div style="margin-top: 20px; display: flex; gap: 15px; align-items: center;">
  <a href="index.php?action=view_history&id=<?= (int)$problem['id'] ?>">Back to History</a>
  
  <?php if ($user_id): ?>
    <form method="POST" action="index.php?action=rollback_revision" onsubmit="return confirm('Rollback problem to this version?');" style="margin: 0;">
      <input type="hidden" name="rev_id" value="<?= (int)$rev1['id'] ?>">
      <input type="submit" value="Rollback to Newer Version" style="padding: 6px 12px; font-weight: bold; background: #cce5ff; border: 1px solid #b8daff; cursor: pointer;">
    </form>
  <?php endif; ?>
</div>
