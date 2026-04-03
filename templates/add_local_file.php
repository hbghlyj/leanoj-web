<h2>Register Local Lean File</h2>
<p>Files will be automatically stored in <code>/var/www/leanoj/local_files/</code> and given a <code>.lean</code> extension if missing.</p>

<form method="POST" action="index.php?action=add_local_file">
  <div style="margin-bottom: 10px;">
    <label>Relative Path (from safe directory):</label>
    <input type="text" name="path" placeholder="e.g. algebra/linear_transformations" required style="width: 100%;">
  </div>

  <div style="margin-bottom: 10px;">
    <label>Initial Content (.lean code):</label>
    <textarea name="content" rows="20" style="width: 100%; font-family: monospace;" required></textarea>
  </div>
  <input type="submit" value="Register and Save to Disk">
</form>
