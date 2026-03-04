<h2>Add Problem</h2>
<form method="POST" action="index.php?action=add_problem" enctype="multipart/form-data">
  <h3>Title</h3>
  <input type="text" name="title" required>
  <h3>Statement</h3>
  <textarea rows="4" name="statement" required></textarea>
  <h3>Template</h3>
  <textarea name="template_text" style="white-space: nowrap" rows="4"></textarea>
  <p>Or upload as a file (.lean):</p>
  <input type="file" name="template_file" accept=".lean">
  <h3>Answer ID</h3>
  <input type="number" name="answer">
  <h3>Contest ID</h3>
  <input type="number" name="contest">
  <input style="float: right" type="submit" value="Add Problem">
</form>
