<!DOCTYPE html>
<html>
<head>
  <title>Lean Online Judge</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
  <script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/marked-katex-extension/lib/index.umd.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jsdiff/5.1.0/diff.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      marked.use(markedKatex({
        throwOnError: false,
        displayMode: false,
        nonStandard: true
      }));
      const els = document.querySelectorAll('.markdown');
      for (const el of els) {
        const rawText = el.textContent.trim();
        el.innerHTML = marked.parse(rawText);
      }
    });
  </script>
  <script>
    function copyCode(button) {
      const code = button.nextElementSibling.innerText;
      navigator.clipboard.writeText(code).then(() => {
        const originalText = button.innerText;
        button.innerText = "Copied!";
        setTimeout(() => { button.innerText = originalText; }, 2000);
      });
    }
  </script>
  <style>
    :root {
      --bg: #ffffff;
      --primary: lightblue;
      --primary-hover: #47f;
      --secondary: #ffff00;
      --text: #000000;
      --border: #aaa;
      --code-bg: #eee;
      --code-text: #000000;
    }
    body {
      font-family: sans-serif;
      background-color: var(--bg);
      color: var(--text);
      line-height: 1.6;
      width: 900px;
      margin: 20px auto;
      font-size: 0.95rem;
      border: 1px solid #ccc;
      padding-top: 40px;
      padding-bottom: 20px;
    }
    .main-container {
      width: 800px;
      margin: 0 auto;
      background: var(--card-bg);
      background-color: white;
    }
    .logo {
      font-size: 2.0rem;
      line-height: 1.6em;
      font-weight: 800;
      color: var(--primary);
      text-decoration: none;
      letter-spacing: -0.5px;
    }
    nav {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin: 20px 0 20px 0;
    }
    hr {
      border: 1px dashed black;
    }
    input {
      padding: 5px;
    }
    input[name="username"],
    input[name="password"],
    input[name="repeat-password"] {
      margin-bottom: 18px;
    }
    input[name="title"] {
      width: 100%;
      box-sizing: border-box;
    }
    input[type="file"] {
      border: 1px solid var(--border);
    }
    textarea {
      width: 100%;
      box-sizing: border-box;
      padding: 5px;
      font-size: 0.9em;
      min-height: 30px;
      resize: vertical;
    }
    table {
      table-layout: auto;
      border-collapse: collapse;
      border: 1px solid var(--border);
      margin: 10px 0;
    }
    th, td {
      border: 1px solid var(--border);
      padding: 2px 12px;
      text-align: left;
    }
    th {
      background-color: #eee;
      color: #333;
      font-size: 0.9em;
    }
    .code-container {
      position: relative;
      background: var(--code-bg);
      color: var(--code-text);
      padding: 10px;
      border: 1px solid var(--border);
      overflow-x: auto;
    }
    pre {
      margin: 0;
      font-size: 0.8rem;
      overflow-x: auto;
    }
    .copy-button {
      position: absolute;
      top: 10px;
      right: 10px;
      font-size: 0.7rem;
      padding: 1px 6px;
    }
    .error {
      background: #fff5f5;
      color: #c53030;
      padding: 15px;
      margin-bottom: 10px;
    }
    .message {
      background: #fffaf0;
      color: #9c4221;
      padding: 1px 15px;
      margin-bottom: 10px;
    }
    .admin-link {
      font-weight: bold;
      font-size: 0.6em;
    }
    .status-cell {
      font-size: 0.8rem;
      color: darkblue;
    }
    .status-passed {
      font-size: 0.7rem;
      color: green;
      font-weight: bold;
    }
    .status-pending {
      font-size: 0.7rem;
      color: orange;
      font-weight: bold;
    }
    a {
      text-decoration: none;
    }

  </style>
</head>
<body>
<div class="main-container">
  <a href="index.php?action=about" class="logo">Lean Online Judge</a>
  <nav>
    <a href="index.php?action=about">User Handbook</a>
    <a href="index.php?action=view_status">Status</a>
    <a href="index.php?action=view_problems">Problems</a>
    <a href="index.php?action=view_submissions">Submissions</a>
    <a href="index.php?action=view_local_files">Local Files</a>
    <?php if (isset($_SESSION['user_id'])): ?>
      <span>
        <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> |
        <a href="index.php?action=logout">Logout</a>
      </span>
    <?php else: ?>
      <span>
        <a href="/bbs/member.php?mod=logging&action=login">Login</a> |
        <a href="/bbs/member.php?mod=register">Register</a>
      </span>
    <?php endif; ?>
  </nav>
  <hr>

  <?php if (isset($_GET['error'])): ?>
    <div class="error"><?= htmlspecialchars($_GET['error']) ?></div>
  <?php endif; ?>
