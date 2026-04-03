<h2>Judge Queue Status</h2>

<div style="background: #f9f9f9; padding: 15px; border: 1px solid #ccc; margin-bottom: 20px;">
    <strong>Pending Submissions:</strong> <?= (int)$pending_count ?> in queue.
</div>

<h3>Current Activity</h3>
<?php if ($active_job): ?>
    <div style="background: #eef; padding: 15px; border: 1px left solid blue; margin-bottom: 20px;">
        <strong>Processing Submission #<?= $active_job['id'] ?></strong><br>
        Problem: <?= htmlspecialchars($active_job['title']) ?><br>
        User: <?= htmlspecialchars($active_job['username']) ?>
    </div>
<?php else: ?>
    <p>No active jobs at the moment.</p>
<?php endif; ?>

<h3>Recent Results</h3>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Problem</th>
            <th>User</th>
            <th>Result</th>
            <th>Time</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($recent_jobs as $job): ?>
            <tr>
                <td><?= $job['id'] ?></td>
                <td><?= htmlspecialchars($job['title']) ?></td>
                <td><?= htmlspecialchars($job['username']) ?></td>
                <td>
                    <span class="<?= strpos($job['status'], 'PASSED') !== false ? 'status-passed' : 'status-pending' ?>">
                        <?= htmlspecialchars($job['status']) ?>
                    </span>
                </td>
                <td><?= $job['time'] ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
