<?php
session_start();
$env_file = __DIR__ . '/.env';
if (!file_exists($env_file)) {
    die('Missing .env file');
}

$env = parse_ini_file($env_file, false, INI_SCANNER_RAW);
if ($env === false || empty($env['DB_PATH'])) {
    die('DB_PATH not configured in .env');
}

$db = new PDO("sqlite:" . $env['DB_PATH']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$action = $_GET['action'] ?? "view_problems";
$is_admin = ($_SESSION['username'] ?? "") === 'admin';
$user_id = (int)$_SESSION['user_id'] ?: null;

function validate_file($file_key, $max_size = 262144) {
  if ($_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
    return "Upload failed";
  }
  if ($_FILES[$file_key]['size'] > $max_size) {
    return "File too large (max 256KB)";
  }
  if (strpos(mime_content_type($_FILES[$file_key]['tmp_name']), 'text/') !== 0) {
    return "Invalid file";
  }
}

function redirect($action = "view_problems", $params = [], $message = "") {
  $query = $params;
  $query['action'] = $action;
  if ($message) {
    $query['message'] = $message;
  }
  header("Location: index.php?" . http_build_query($query));
  exit;
}

function separate_imports($content) {
  $lines = explode("\n", str_replace("\r", "", $content));
  $imports = [];
  $body = [];
  foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === "") {
      continue;
    }
    if (strpos($trimmed, "import") === 0) {
      $imports[] = $line;
    } else {
      $body[] = $line;
    }
  }
  return [
    "imports" => implode("\n", $imports),
    "body" => implode("\n", $body)
  ];
}

if ($action === "logout") {
  session_destroy();
  redirect("view_problems");
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
  if ($action === "register") {
    $username = trim($_POST['username'] ?? "");
    $password = $_POST['password'] ?? "";
    $repeat = $_POST['repeat-password'] ?? "";
    if (empty($username) || empty($password)) {
      redirect("register", [], "Fill in all fields");
    }
    if ($password !== $repeat) {
      redirect("register", [], "Passwords do not match");
    }
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([":username" => $username]);
    if ($stmt->fetch()) {
      redirect("register", [], "Username already taken");
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
    $stmt->execute([
      ":username" => $username,
      ":password" => $hash,
    ]);
    $_SESSION['user_id'] = $db->lastInsertId();
    $_SESSION['username'] = $username;
    redirect("view_problems");
  }

  elseif ($action === "login") {
    $username = trim($_POST['username'] ?? "");
    $password = $_POST['password'] ?? "";
    if (empty($username) || empty($password)) {
      redirect("login", [], "Fill in all fields");
    }
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute([":username" => $username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['username'] = $user['username'];
      redirect("view_problems");
    }
    redirect("login", [], "Invalid credentials");
  }

  elseif ($action === "submit_solution" && $user_id) {
    $problem_id = $_POST['problem_id'] ?? 0;
    $time = date("Y-m-d\TH:i", time());
    $source_code = trim($_POST['source_text'] ?? "");
    if (!empty($_FILES['source_file']['tmp_name'])) {
      $err = validate_file('source_file');
      if ($err) {
        redirect("view_problem", ["id" => $problem_id], $err);
      }
      $source_code = trim(file_get_contents($_FILES['source_file']['tmp_name']));
    }
    if (empty($source_code)) {
      redirect("view_problem", ["id" => $problem_id], "Solution can't be empty");
    }
    $stmt = $db->prepare("
      INSERT INTO submissions (problem, user, source, status, time)
      VALUES (:problem, :user, :source, :status, :time)");
    $stmt->execute([
      ":problem" => $problem_id,
      ":user" => $user_id,
      ":source" => $source_code,
      ":status" => "PENDING",
      ":time" => $time,
    ]);
    redirect("view_problem", ["id" => $problem_id]);
  }

  elseif ($action === "add_problem" && $is_admin) {
    $title = trim($_POST['title'] ?? "");
    $statement = trim($_POST['statement'] ?? "");
    $note = trim($_POST['note'] ?? "") ?: null;
    $template = trim($_POST['template_text'] ?? "");
    $answer = (int)$_POST['answer'] ?: null;
    $contest = (int)$_POST['contest'] ?: null;
    $archived = empty($contest);
    if (!empty($_FILES['template_file']['tmp_name'])) {
      $err = validate_file('template_file');
      if ($err) {
        redirect("add_problem", [], $err);
      }
      $template = trim(file_get_contents($_FILES['template_file']['tmp_name']));
    }
    if (empty($title) || empty($statement) || empty($template)) {
      redirect("add_problem", [], "Fill in required fields");
    }
    if ($answer) {
      $stmt = $db->prepare("SELECT EXISTS(SELECT 1 from answers WHERE id = :id)");
      $stmt->execute([":id" => $answer]);
      $answer_exists = (bool)$stmt->fetchColumn();
      if (!$answer_exists) {
        redirect("add_problem", [], "Answer not found");
      }
    }
    if ($contest) {
      $stmt = $db->prepare("SELECT EXISTS(SELECT 1 from contests WHERE id = :id)");
      $stmt->execute([":id" => $contest]);
      $contest_exists = (bool)$stmt->fetchColumn();
      if (!$contest_exists) {
        redirect("add_problem", [], "Contest not found");
      }
    }
    $stmt = $db->prepare("
      INSERT INTO problems (title, statement, template, note, answer, contest, archived)
      VALUES (:title, :statement, :template, :note, :answer, :contest, :archived)");
    $stmt->execute([
      ":title" => $title,
      ":statement" => $statement,
      ":note" => $note,
      ":template" => $template,
      ":answer" => $answer,
      ":contest" => $contest,
      ":archived" => $archived,
    ]);
    redirect("view_problem", ["id" => $db->lastInsertId()]);
  }

  elseif ($action === "edit_problem" && $is_admin) {
    $id = (int)$_POST['id'] ?: null;
    $title = trim($_POST['title'] ?? "");
    $statement = trim($_POST['statement'] ?? "");
    $template = trim($_POST['template_text'] ?? "");
    $note = trim($_POST['note'] ?? "") ?: null;
    $answer = (int)$_POST['answer'] ?: null;
    $contest = (int)$_POST['contest'] ?: null;
    $archived = empty($contest);
    if (empty($title) || empty($statement)) {
      redirect("edit_problem", ["id" => $id], "Fill in required fields");
    }
    if (!empty($_FILES['template_file']['tmp_name'])) {
      $err = validate_file('template_file');
      if ($err) {
        redirect("edit_problem", ["id" => $id], $err);
      }
      $template = trim(file_get_contents($_FILES['template_file']['tmp_name']));
    }
    if (empty($template)) {
      redirect("edit_problem", ["id" => $id], "Fill in required fields");
    }
    if ($answer) {
      $stmt = $db->prepare("SELECT EXISTS(SELECT 1 from answers WHERE id = :id)");
      $stmt->execute([":id" => $answer]);
      $answer_exists = (bool)$stmt->fetchColumn();
      if (!$answer_exists) {
        redirect("edit_problem", ["id" => $id], "Answer not found");
      }
    }
    if ($contest) {
      $stmt = $db->prepare("SELECT EXISTS(SELECT 1 from contests WHERE id = :id)");
      $stmt->execute([":id" => $contest]);
      $contest_exists = (bool)$stmt->fetchColumn();
      if (!$contest_exists) {
        redirect("edit_problem", ["id" => $id], "Contest not found");
      }
    }
    $stmt = $db->prepare("
      UPDATE problems
      SET title = :title, statement = :statement, note = :note, template = :template,
        answer = :answer, contest = :contest, archived = :archived
      WHERE id = :id");
    $stmt->execute([
      ":id" => $id,
      ":title" => $title,
      ":statement" => $statement,
      ":note" => $note,
      ":template" => $template,
      ":answer" => $answer,
      ":contest" => $contest,
      ":archived" => $archived,
    ]);
    redirect("view_problem", ["id" => $id]);
  }

  elseif ($action === "add_answer" && $is_admin) {
    $answer_source = trim($_POST['answer_text'] ?? "");
    if (!empty($_FILES['answer_file']['tmp_name'])) {
      $err = validate_file('answer_file');
      if ($err) {
        redirect("add_answer", [], $err);
      }
      $answer_source = trim(file_get_contents($_FILES['answer_file']['tmp_name']));
    }
    if (empty($answer_source)) {
      redirect("add_answer", [], "Fill in required fields");
    }
    $answer = separate_imports($answer_source);
    $stmt = $db->prepare("INSERT INTO answers (imports, body) VALUES (:imports, :body)");
    $stmt->execute([
      ":imports" => $answer['imports'],
      ":body" => $answer['body']
    ]);
    redirect("view_answers");
  }

  elseif ($action === "edit_answer" && $is_admin) {
    $id = (int)$_POST['id'];
    $answer_source = trim($_POST['answer_text'] ?? "");
    if (!empty($_FILES['answer_file']['tmp_name'])) {
      $err = validate_file('answer_file');
      if ($err) {
        redirect("edit_answer", ["id" => $id], $err);
      }
      $answer_source = trim(file_get_contents($_FILES['answer_file']['tmp_name']));
    }
    if (empty($answer_source)) {
      redirect("edit_answer", ["id" => $id], "Fill in required fields");
    }
    $answer = separate_imports($answer_source);
    $stmt = $db->prepare("
      UPDATE answers
      SET imports = :imports, body = :body
      WHERE id = :id");
    $stmt->execute([
      ":imports" => $answer['imports'],
      ":body" => $answer['body'],
      ":id" => $id,
    ]);
    redirect("view_answers");
  }

  elseif ($action === "add_contest" && $is_admin) {
    $title = trim($_POST['title'] ?? "");
    $start = $_POST['start'] ?? "";
    $end = $_POST['end'] ?? "";
    if (empty($title) || empty($start) || empty($end)) {
      redirect("add_contest", [], "Fill in required fields");
    }
    $stmt = $db->prepare("
      INSERT INTO contests (title, start, end)
      VALUES (:title, :start, :end)");
    $stmt->execute([
      ":title" => $title,
      ":start" => $start,
      ":end" => $end,
    ]);
    redirect("view_contest", ["id" => $db->lastInsertId()]);
  }

  elseif ($action === "edit_contest" && $is_admin) {
    $id = (int)$_POST['id'] ?: null;
    $title = trim($_POST['title'] ?? "");
    $start = $_POST['start'] ?? "";
    $end = $_POST['end'] ?? "";
    if (empty($title) || empty($start) || empty($end)) {
      redirect("edit_contest", ["id" => $id], "Fill in required fields");
    }
    $stmt = $db->prepare("
      UPDATE contests
      SET title = :title, start = :start, end = :end where id = :id");
    $stmt->execute([
      ":id" => $id,
      ":title" => $title,
      ":start" => $start,
      ":end" => $end,
    ]);
    redirect("view_contest", ["id" => $id]);
  }

  elseif ($action === "rejudge" && $is_admin) {
    $id = (int)$_POST['id'] ?: null;
    $stmt = $db->prepare("UPDATE submissions SET status = 'PENDING' WHERE id = :id");
    $stmt->execute([":id" => $id]);
    redirect("view_submission", ["id" => $id]);
  }

  elseif ($action === "toggle_archive" && $is_admin) {
    $id = (int)$_POST['id'];
    $stmt = $db->prepare("UPDATE problems SET archived = not archived WHERE id = :id");
    $stmt->execute([":id" => $id]);
    redirect("view_problem", ["id" => $id]);
  }
}

if ($_SERVER['REQUEST_METHOD'] === "GET") {
  include "templates/header.php";

  if ($action === "register") {
    include "templates/register.php";
  }

  elseif ($action === "login") {
    include "templates/login.php";
  }

  elseif ($action === "view_problems") {
    $per_page = 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $per_page;
    $stmt = $db->query("SELECT COUNT(*) FROM problems WHERE archived");
    $total_problems = $stmt->fetchColumn();
    $total_pages = ceil($total_problems / $per_page);
    $stmt = $db->prepare("
      SELECT p.*,
        (SELECT COUNT(DISTINCT user) FROM submissions
          WHERE problem = p.id AND status = 'PASSED' AND p.title != 'xyzzy') as solves,
        EXISTS(SELECT 1 FROM submissions WHERE problem = p.id AND user = :user_id AND
          status = 'PASSED' AND p.title != 'xyzzy') as is_solved
      FROM problems p
      WHERE p.archived
      ORDER BY p.id DESC
      LIMIT :limit OFFSET :offset");
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->bindValue(":user_id", $user_id);
    $stmt->execute();
    $problems = $stmt->fetchAll();
    include "templates/view_problems.php";
  }

  elseif ($action === "scoreboard") {
    $per_page = 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $per_page;
    $total_stmt = $db->query("SELECT COUNT(*) FROM users");
    $total_users = $total_stmt->fetchColumn();
    $total_pages = ceil($total_users / $per_page);
    $stmt = $db->prepare("
      SELECT u.username, COUNT(first_passes.problem) AS solved,
        MAX(first_passes.first_pass) AS last_first_pass
      FROM users u
      LEFT JOIN (SELECT s.user, s.problem, MIN(s.time) AS first_pass
        FROM submissions s
        JOIN problems p ON s.problem = p.id
        WHERE p.title != 'xyzzy' AND p.archived AND s.status = 'PASSED'
        GROUP BY s.user, s.problem
        ) AS first_passes ON u.id = first_passes.user
      GROUP BY u.id
      ORDER BY solved DESC, last_first_pass ASC
      LIMIT :limit OFFSET :offset");
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();
    $scoreboard = $stmt->fetchAll();
    include "templates/scoreboard.php";
  }

  elseif ($action === "about") {
    include "templates/about.php";
  }

  elseif ($action === "guide") {
    include "templates/guide.php";
  }

  elseif ($action === "view_problem") {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("
      SELECT p.*, c.start, c.end
      FROM problems p
      LEFT JOIN contests c on p.contest = c.id
      WHERE p.id = :id");
    $stmt->execute([":id" => $id]);
    $problem = $stmt->fetch();
    if (!$problem) {
      redirect("view_problems", [], "Not found");
    }
    $stmt = $db->prepare("
      SELECT s.*, u.username
      FROM submissions s
      JOIN users u ON s.user = u.id
      WHERE s.problem = :id
      ORDER BY s.id DESC LIMIT 10");
    $stmt->execute(["id" => $id]);
    $recent_submissions = $stmt->fetchAll();
    $can_view = true;
    if ($problem['contest']) {
      $cur = time();
      $start = strtotime($problem['start']);
      $can_view = $is_admin || $cur >= $start;
    }
    include "templates/view_problem.php";
  }

  elseif ($action === "view_submissions") {
    $per_page = 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $per_page;
    $id = (int)$_GET['id'];
    $for_problem = !empty($id);
    if ($for_problem) {
      $stmt = $db->prepare("SELECT * FROM problems WHERE id = :id");
      $stmt->execute(["id" => $id]);
      $problem = $stmt->fetch();
      if (!$problem) {
        redirect("view_problems", [], "Not found");
      }
      $stmt = $db->prepare("SELECT COUNT(*) FROM submissions WHERE problem = :problem_id");
      $stmt->execute([":problem_id" => $id]);
      $total_submissions = $stmt->fetchColumn();
      $total_pages = ceil($total_submissions / $per_page);
      $stmt = $db->prepare("
        SELECT s.*, u.username
        FROM submissions s
        JOIN users u ON s.user = u.id
        WHERE s.problem = :problem_id
        ORDER BY s.id DESC
        LIMIT :limit OFFSET :offset");
      $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
      $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
      $stmt->bindValue("problem_id", $id);
    } else {
      $stmt = $db->query("SELECT COUNT(*) FROM submissions");
      $total_submissions = $stmt->fetchColumn();
      $total_pages = ceil($total_submissions / $per_page);
      $stmt = $db->prepare("
        SELECT s.*, u.username, p.title
        FROM submissions s
        JOIN users u ON s.user = u.id
        JOIN problems p ON s.problem = p.id
        ORDER BY s.id DESC
        LIMIT :limit OFFSET :offset");
      $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
      $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    $submissions = $stmt->fetchAll();
    include "templates/view_submissions.php";
  }

  elseif ($action === "view_submission") {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("
      SELECT s.*, p.title, u.username
      FROM submissions s
      JOIN problems p ON s.problem = p.id
      JOIN users u ON s.user = u.id
      WHERE s.id = :id");
    $stmt->execute([":id" => $id]);
    $submission = $stmt->fetch();
    if (!$submission) {
      redirect("view_problems", [], "Not found");
    }
    $show_source = false;
    if ($user_id) {
      $stmt = $db->prepare("
        SELECT EXISTS(
          SELECT 1 FROM submissions
          WHERE problem = :problem AND user = :user AND status = 'PASSED')");
      $stmt->execute([
        ":problem" => $submission['problem'],
        ":user" => $user_id,
      ]);
      $is_solved = (bool)$stmt->fetchColumn();
      $is_owner = $submission['user'] === $user_id;
      $is_xyzzy = ($submission['title'] === 'xyzzy');
      $show_source = $is_admin || $is_owner || ($is_solved && !$is_xyzzy);
    }
    include "templates/view_submission.php";
  }

  elseif ($action === "view_answers") {
    $per_page = 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $per_page;
    $total_stmt = $db->query("SELECT COUNT(*) FROM answers");
    $total_problems = $total_stmt->fetchColumn();
    $total_pages = ceil($total_problems / $per_page);

    $stmt = $db->prepare("SELECT * FROM answers LIMIT :limit OFFSET :offset");
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();
    $answers = $stmt->fetchAll();
    include "templates/view_answers.php";
  }

  elseif ($action === "view_contests") {
    $per_page = 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $per_page;
    $total_stmt = $db->query("SELECT COUNT(*) FROM contests");
    $total_problems = $total_stmt->fetchColumn();
    $total_pages = ceil($total_problems / $per_page);

    $stmt = $db->prepare("
      SELECT * FROM contests ORDER BY id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();
    $contests = $stmt->fetchAll();
    include "templates/view_contests.php";
  }

  elseif ($action === "add_problem" && $is_admin) {
    include "templates/add_problem.php";
  }

  elseif ($action === "edit_problem" && $is_admin) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("
      SELECT p.*, a.body
      FROM problems p
      LEFT JOIN answers a ON p.answer = a.id
      WHERE p.id = :id");
    $stmt->execute([":id" => $id]);
    $problem = $stmt->fetch();
    if (!$problem) {
      redirect("view_problems", [], "Not found");
    }
    include "templates/edit_problem.php";
  }

  elseif ($action === "add_answer" && $is_admin) {
    include "templates/add_answer.php";
  }

  elseif ($action === "edit_answer" && $is_admin) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare(" SELECT * FROM answers WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $answer = $stmt->fetch();
    if (!$answer) {
      redirect("view_answers", [], "Not found");
    }
    $answer_source = trim($answer['imports'] . "\n\n" . $answer['body']);
    include "templates/edit_answer.php";
  }

  elseif ($action === "add_contest" && $is_admin) {
    include "templates/add_contest.php";
  }

  elseif ($action === "edit_contest" && $is_admin) {
    $id = (int)$_GET['id'] ?? 0;
    $stmt = $db->prepare("SELECT * FROM contests WHERE id = :id");
    $stmt->bindValue(":id", $id);
    $stmt->execute();
    $contest = $stmt->fetch();
    if (!$contest) {
      redirect("view_contests", [], "Not found");
    }
    include "templates/edit_contest.php";
  }

  elseif ($action === "view_contest") {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM contests where id = :id");
    $stmt->execute([":id" => $id]);
    $contest = $stmt->fetch();

    if (!$contest) {
      redirect("view_contests", [], "Not found");
    }
    $stmt = $db->prepare("
      SELECT p.*, (SELECT COUNT(DISTINCT user) FROM submissions
        WHERE (time IS NULL OR (:start <= time AND time <= :end))
          AND problem = p.id AND status = 'PASSED') as solves,
      EXISTS(SELECT 1 FROM submissions
        WHERE (time IS NULL OR (:start <= time AND time <= :end))
          AND problem = p.id AND user = :user_id AND status = 'PASSED'
      ) as is_solved
      FROM problems p WHERE p.contest = :id");
    $stmt->execute([
      ":user_id" => $user_id,
      ":start" => $contest['start'],
      ":end" => $contest['end'],
      ":id" => $id]);
    $problems = $stmt->fetchAll();
    include "templates/view_contest.php";
  }

  elseif ($action === "results") {
    $id = (int)$_GET['id'];
    $per_page = 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $per_page;
    $stmt = $db->prepare("
      SELECT COUNT(DISTINCT s.user)
      FROM submissions s
      JOIN problems p ON s.problem = p.id
      LEFT JOIN contests c ON p.contest = c.id
      WHERE (s.time IS NULL OR (c.start <= s.time AND s.time <= c.end))
        AND c.id = :id");
    $stmt->execute([":id" => $id]);
    $total_users = $stmt->fetchColumn();
    $total_pages = ceil($total_users / $per_page);

    $stmt = $db->prepare("
      SELECT u.username, COUNT(first_passes.problem) AS solved,
        MAX(first_passes.first_pass) AS last_first_pass
      FROM users u
      JOIN (SELECT s.user, s.problem, MIN(s.time) AS first_pass
        FROM submissions s
        JOIN problems p ON s.problem = p.id
        LEFT JOIN contests c ON p.contest = c.id
        WHERE (s.time IS NULL OR (c.start <= s.time AND s.time <= c.end))
          AND c.id = :id AND s.status = 'PASSED'
        GROUP BY s.user, s.problem
        ) AS first_passes ON u.id = first_passes.user
      GROUP BY u.id
      ORDER BY solved DESC, last_first_pass ASC
      LIMIT :limit OFFSET :offset");
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->bindValue(":id", $id);
    $stmt->execute();
    $results = $stmt->fetchAll();
    include "templates/results.php";
  }

  include "templates/footer.php";
}
?>
