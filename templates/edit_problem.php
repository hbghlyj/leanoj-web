<h2>Edit Problem</h2>
<form method="POST" action="index.php?action=edit_problem" enctype="multipart/form-data">
  <input type="hidden" name="id" value="<?= $problem['id'] ?>">
  <h3>Title</h3>
  <input type="text" name="title" value="<?= $problem['title'] ?>" required>
  <h3>Statement</h3>
  <textarea rows="4" name="statement" required><?= $problem['statement'] ?></textarea>
  <h3>Template</h3>
  <textarea name="template_text" style="white-space: nowrap" rows="4"><?= $problem['template'] ?></textarea>
  <p>Or upload as a file (.lean)</p>
  <input type="file" name="template_file" accept=".lean">
  <h3>Answer ID</h3>
  <input type ="number" name="answer" value="<?= $problem['answer'] ?>">
  <h3>Contest ID</h3>
  <input type="number" name="contest" value="<?= $problem['contest'] ?>">
  <input style="float: right" type="submit" value="Save Changes">
</form>
