<?php
require_once __DIR__ . '/src/DiscuzBridge.php';
$action = $_GET['action'] ?? "view_problems";
$template = "templates/view_problems.php"; // Default template
session_start();

// Local Development Bypass
if (isset($_GET['login_dev']) && ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1')) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['is_admin'] = true;
    header("Location: index.php");
    exit;
}

DiscuzBridge::syncSession();

$is_admin = (bool)($_SESSION['is_admin'] ?? false);
$user_id = (int)($_SESSION['user_id'] ?? 0);

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

function redirect($action = "view_problems", $params = [], $error = "") {
  $query = $params;
  $query['action'] = $action;
  if ($error) {
    $query['error'] = $error;
  }
  header("Location: index.php?" . http_build_query($query));
  exit;
}

function axle_api_call($tool, $payload) {
  $env = parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW);
  $apiKey = $env['AXLE_API_KEY'] ?? '';
  
  $ch = curl_init("https://axle.axiommath.ai/api/v1/" . $tool);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $apiKey,
    'User-Agent: Leanoj-Web/1.0'
  ]);
  curl_setopt($ch, CURLOPT_TIMEOUT, 120);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  $response = curl_exec($ch);
  $error = curl_error($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  if ($httpCode !== 200) {
    error_log("AXLE API Error [$tool]: HTTP $httpCode, Error: $error");
    return null;
  }
  return json_decode($response, true);
}

// verify_local_file removed - handled by background worker

$_env = parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW);
$_dbPath = $_env['DB_PATH'] ?? 'db.sqlite';
if (!str_starts_with($_dbPath, '/') && !preg_match('/^[A-Za-z]:/', $_dbPath)) {
    $_dbPath = __DIR__ . '/' . $_dbPath;
}
$db = new PDO("sqlite:" . $_dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

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

/**
 * Recursively fetch dependency templates.
 */
function get_recursive_dependency_content($db, $ids, &$visited = []) {
  $content = "";
  foreach ($ids as $id) {
    if (in_array((int)$id, $visited)) continue;
    $visited[] = (int)$id;

    $stmt = $db->prepare("SELECT template, dependencies FROM problems WHERE id = :id");
    $stmt->execute([':id' => (int)$id]);
    $row = $stmt->fetch();
    if (!$row) continue;

    // Recurse into sub-dependencies first
    $sub_deps = json_decode($row['dependencies'] ?: '[]', true);
    if (!empty($sub_deps)) {
      $content .= get_recursive_dependency_content($db, $sub_deps, $visited);
    }

    if (!empty($row['template'])) {
      $content .= $row['template'] . "\n";
    }
  }
  return $content;
}

if ($action === "logout") {
  DiscuzBridge::clearCookies();
  redirect();
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
  // register and login removed - handled by Discuz!

  if ($action === "submit_solution" && $user_id) {
    $problem_id = (int)($_POST['problem_id'] ?? 0);
    $time = date("Y-m-d\TH:i", time());
    $source_code = trim($_POST['source_text'] ?? "");

    $stmt = $db->prepare("SELECT template FROM problems WHERE id = :id");
    $stmt->execute([":id" => $problem_id]);
    $prob = $stmt->fetch();
    if (!$prob || empty($prob['template'])) {
        redirect("view_problem", ["id" => $problem_id], "Submissions are disabled for this problem.");
    }

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

  elseif ($action === "add_problem" && $user_id) {
    $title = trim($_POST['title'] ?? "");
    $statement = trim($_POST['statement'] ?? "");
    $template = trim($_POST['template_text'] ?? "");
    $deps = array_map('intval', (array)($_POST['dependencies'] ?? []));
    $deps_json = json_encode($deps);
    
    // Fetch recursive dependency contents
    $visited = [];
    $prepended_content = get_recursive_dependency_content($db, $deps, $visited);

    if (empty($title)) {
      redirect("add_problem", [], "Title is required");
    }
    
    $res = null;
    if (!empty($template)) {
        // Verify template with AXLE (with prepended dependencies)
        $res = axle_api_call("check", [
          "content" => $prepended_content . $template,
          "environment" => "lean-4.28.0",
          "ignore_imports" => true,
          "timeout_seconds" => 120
        ]);
        if ($res && isset($res['okay']) && !$res['okay']) {
          $errors = $res['lean_messages']['errors'] ?? ["Invalid Lean template (API Error)"];
          redirect("add_problem", [], "Template Error: " . implode("\n", $errors));
        }
    }

    $stmt = $db->prepare("
      INSERT INTO problems (title, statement, template, dependencies, creator_id)
      VALUES (:title, :statement, :template, :dependencies, :creator_id)");
    $stmt->execute([
      ":title" => $title,
      ":statement" => $statement,
      ":template" => $template,
      ":dependencies" => $deps_json,
      ":creator_id" => $user_id,
    ]);
    $problem_id = $db->lastInsertId();
    $time = date("Y-m-d\TH:i:s", time());
    $stmt = $db->prepare("
      INSERT INTO problem_revisions (problem_id, statement, template, user_id, time)
      VALUES (:problem_id, :statement, :template, :user_id, :time)");
    $stmt->execute([
      ":problem_id" => $problem_id,
      ":statement" => $statement,
      ":template" => $template,
      ":user_id" => $user_id,
      ":time" => $time,
    ]);
    redirect("view_problem", ["id" => $problem_id]);
  }

  elseif ($action === "edit_problem" && $user_id) {
    $id = (int)$_POST['id'] ?: null;
    $stmt = $db->prepare("SELECT * FROM problems WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $problem = $stmt->fetch();
    if (!$problem) {
      redirect("view_problems", [], "Not found");
    }
    
    $deps = array_map('intval', (array)($_POST['dependencies'] ?? []));
    $deps_json = json_encode($deps);

    $title = trim($_POST['title'] ?? "");
    $statement = trim($_POST['statement'] ?? "");
    $template = trim($_POST['template_text'] ?? "");
    if (empty($title)) {
      redirect("edit_problem", ["id" => $id], "Title is required");
    }
    
    // Fetch recursive dependency contents
    $visited = [];
    $prepended_content = get_recursive_dependency_content($db, $deps, $visited);

    if (!empty($template)) {
        // Verify template with AXLE (with prepended dependencies)
        $res = axle_api_call("check", [
          "content" => $prepended_content . $template,
          "environment" => "lean-4.28.0",
          "ignore_imports" => true,
          "timeout_seconds" => 120
        ]);
        if ($res && isset($res['okay']) && !$res['okay']) {
          $errors = $res['lean_messages']['errors'] ?? ["Invalid Lean template (API Error)"];
          redirect("edit_problem", ["id" => $id], "Template Error: " . implode("\n", $errors));
        }
    }

    $stmt = $db->prepare("
      UPDATE problems
      SET title = :title, statement = :statement, template = :template,
        dependencies = :dependencies
      WHERE id = :id");
    $stmt->execute([
      ":id" => $id,
      ":title" => $title,
      ":statement" => $statement,
      ":template" => $template,
      ":dependencies" => $deps_json,
    ]);
    
    if ($statement !== $problem['statement'] || $template !== $problem['template']) {
      $time = date("Y-m-d\TH:i:s", time());
      $stmt = $db->prepare("
        INSERT INTO problem_revisions (problem_id, statement, template, user_id, time)
        VALUES (:problem_id, :statement, :template, :user_id, :time)");
      $stmt->execute([
        ":problem_id" => $id,
        ":statement" => $statement,
        ":template" => $template,
        ":user_id" => $user_id,
        ":time" => $time,
      ]);
    }

    redirect("view_problem", ["id" => $id]);
  }

  elseif ($action === "delete_problem" && $user_id) {
    $id = (int)$_POST['id'] ?: null;
    $stmt = $db->prepare("SELECT creator_id FROM problems WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $problem = $stmt->fetch();
    if (!$problem) redirect("view_problems", [], "Not found");
    if (!$is_admin && $problem['creator_id'] != $user_id) {
      redirect("view_problem", ["id" => $id], "Unauthorized");
    }
    // Cascade deletions explicitly (in case foreign key cascade isn't on)
    $stmt = $db->prepare("DELETE FROM submissions WHERE problem = :id");
    $stmt->execute([":id" => $id]);
    $stmt = $db->prepare("DELETE FROM problem_revisions WHERE problem_id = :id");
    $stmt->execute([":id" => $id]);
    $stmt = $db->prepare("DELETE FROM problems WHERE id = :id");
    $stmt->execute([":id" => $id]);
    redirect("view_problems");
  }

  elseif ($action === "delete_submission" && $user_id) {
    $id = (int)$_POST['id'] ?: null;
    $stmt = $db->prepare("SELECT user, problem FROM submissions WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $sub = $stmt->fetch();
    if (!$sub) header("Location: {$_SERVER['HTTP_REFERER']}");
    if (!$is_admin && $sub['user'] != $user_id) {
      redirect("view_submission", ["id" => $id], "Unauthorized");
    }
    $stmt = $db->prepare("DELETE FROM submissions WHERE id = :id");
    $stmt->execute([":id" => $id]);
    redirect("view_submissions");
  }

  elseif ($action === "rollback_revision" && $user_id) {
    $rev_id = (int)$_POST['rev_id'] ?: null;
    $stmt = $db->prepare("SELECT * FROM problem_revisions WHERE id = :rev_id");
    $stmt->execute([":rev_id" => $rev_id]);
    $rev = $stmt->fetch();
    if (!$rev) redirect("view_problems", [], "Revision not found");
    
    $prob_id = $rev['problem_id'];
    $stmt = $db->prepare("UPDATE problems SET statement = :statement, template = :template WHERE id = :id");
    $stmt->execute([
      ":statement" => $rev['statement'],
      ":template" => $rev['template'],
      ":id" => $prob_id
    ]);
    
    $time = date("Y-m-d\TH:i:s", time());
    $stmt = $db->prepare("
      INSERT INTO problem_revisions (problem_id, statement, template, user_id, time)
      VALUES (:problem_id, :statement, :template, :user_id, :time)");
    $stmt->execute([
      ":problem_id" => $prob_id,
      ":statement" => $rev['statement'],
      ":template" => $rev['template'],
      ":user_id" => $user_id,
      ":time" => $time,
    ]);
    redirect("view_history", ["id" => $prob_id]);
  }

  elseif ($action === "delete_revision" && $is_admin) {
    $rev_id = (int)$_POST['rev_id'] ?: null;
    $prob_id = (int)$_POST['prob_id'] ?: null;
    $stmt = $db->prepare("DELETE FROM problem_revisions WHERE id = :rev_id");
    $stmt->execute([":rev_id" => $rev_id]);
    redirect("view_history", ["id" => $prob_id]);
  }

  elseif ($action === "rejudge" && $is_admin) {
    $id = (int)$_POST['id'] ?: null;
    $stmt = $db->prepare("UPDATE submissions SET status = 'PENDING' WHERE id = :id");
    $stmt->execute([":id" => $id]);
    header("Location: {$_SERVER['HTTP_REFERER']}");
    exit;
  }
}

if ($_SERVER['REQUEST_METHOD'] === "GET") {
  if ($action === "register") {
    $template = "templates/register.php";
  }

  elseif ($action === "login") {
    $template = "templates/login.php";
  }

  elseif ($action === "view_problems") {
    $per_page = 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $per_page;
    $stmt = $db->query("SELECT COUNT(*) FROM problems");
    $total_problems = $stmt->fetchColumn();
    $total_pages = ceil($total_problems / $per_page);
    $stmt = $db->prepare("
      SELECT p.*,
        (SELECT COUNT(DISTINCT user) FROM submissions
          WHERE problem = p.id AND status = 'PASSED' AND p.title != 'xyzzy') as solves
      FROM problems p
      ORDER BY p.id DESC
      LIMIT :limit OFFSET :offset");
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();
    $problems = $stmt->fetchAll();

    // Restore is_solved if user logged in
    foreach ($problems as &$p) {
        $p['is_solved'] = false;
        if ($user_id) {
            $checkStmt = $db->prepare("SELECT 1 FROM submissions WHERE problem = :pid AND user = :uid AND status = 'PASSED' LIMIT 1");
            $checkStmt->execute([":pid" => $p['id'], ":uid" => $user_id]);
            $p['is_solved'] = (bool)$checkStmt->fetchColumn();
        }
    }

    $template = "templates/view_problems.php";
  }

  elseif ($action === "about") {
    $template = "templates/about.php";
  }

  elseif ($action === "view_problem") {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("
      SELECT *
      FROM problems
      WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $problem = $stmt->fetch();
    if (!$problem) {
      redirect("view_problems", [], "Not found");
    }
    $stmt = $db->prepare("
      SELECT s.*
      FROM submissions s
      WHERE s.problem = :id
      ORDER BY s.id DESC LIMIT 10");
    $stmt->execute(["id" => $id]);
    $recent_submissions = $stmt->fetchAll();
    
    // Fetch usernames
    if (!empty($recent_submissions)) {
        $uids = array_column($recent_submissions, 'user');
        $usernames = DiscuzBridge::getUsernames($uids);
        foreach ($recent_submissions as &$s) {
            $s['username'] = $usernames[$s['user']] ?? "Unknown";
        }
    }
    // Decode dependencies and fetch recursive details
    $dep_ids = json_decode($problem['dependencies'] ?: '[]', true);
    $problem['dependency_details'] = [];
    if (!empty($dep_ids)) {
        $placeholders = implode(',', array_fill(0, count($dep_ids), '?'));
        $stmt = $db->prepare("SELECT id, title FROM problems WHERE id IN ($placeholders)");
        $stmt->execute($dep_ids);
        $problem['dependency_details'] = $stmt->fetchAll();
    }

    $can_view = true;
    $template = "templates/view_problem.php";
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
        SELECT s.*
        FROM submissions s
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
        SELECT s.*, p.title
        FROM submissions s
        JOIN problems p ON s.problem = p.id
        ORDER BY s.id DESC
        LIMIT :limit OFFSET :offset");
      $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
      $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    $submissions = $stmt->fetchAll();
    
    // Fetch usernames from Discuz
    $uids = array_column($submissions, 'user');
    $usernames = DiscuzBridge::getUsernames($uids);
    foreach ($submissions as &$s) {
        $s['username'] = $usernames[$s['user']] ?? "Unknown";
    }

    $template = "templates/view_submissions.php";
  }

  elseif ($action === "view_submission") {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("
      SELECT s.*, p.title
      FROM submissions s
      JOIN problems p ON s.problem = p.id
      WHERE s.id = :id");
    $stmt->execute([":id" => $id]);
    $submission = $stmt->fetch();
    if (!$submission) redirect("view_submissions", [], "Not found");
    
    // Fetch username
    $usernames = DiscuzBridge::getUsernames([$submission['user']]);
    $submission['username'] = $usernames[$submission['user']] ?? "Unknown";

    $show_source = true;
    $template = "templates/view_submission.php";
  }



  elseif ($action === "add_problem") {
    if (!$user_id) redirect("view_problems", [], "Please login first");
    $stmt = $db->query("SELECT id, title FROM problems WHERE template IS NOT NULL AND template != ''");
    $other_problems = $stmt->fetchAll();
    $template = "templates/add_problem.php";
  }

  elseif ($action === "edit_problem") {
    if (!$user_id) redirect("view_problems", [], "Please login first");
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM problems WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $problem = $stmt->fetch();
    if (!$problem) redirect("view_problems", [], "Not found");
    
    $stmt = $db->prepare("SELECT id, title FROM problems WHERE id != :id AND template IS NOT NULL AND template != ''");
    $stmt->execute([':id' => $id]);
    $other_problems = $stmt->fetchAll();

    // Decode dependencies
    $problem['dependencies'] = json_decode($problem['dependencies'] ?: '[]', true);

    $template = "templates/edit_problem.php";
  }



  elseif ($action === "view_history") {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM problems WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $problem = $stmt->fetch();
    if (!$problem) redirect("view_problems", [], "Not found");
    
    $stmt = $db->prepare("
      SELECT r.*
      FROM problem_revisions r
      WHERE r.problem_id = :id
      ORDER BY r.id DESC");
    $stmt->execute([":id" => $id]);
    $revisions = $stmt->fetchAll();

    $uids = array_column($revisions, 'user_id');
    $usernames = DiscuzBridge::getUsernames($uids);
    foreach ($revisions as &$r) {
        $r['username'] = $usernames[$r['user_id']] ?? "Unknown";
    }
    $template = "templates/view_history.php";
  }

  elseif ($action === "compare_revision") {
    $id = (int)$_GET['id'];
    $rev1_id = (int)($_GET['rev1'] ?? 0);
    $rev2_id = (int)($_GET['rev2'] ?? 0);
    
    $stmt = $db->prepare("SELECT * FROM problems WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $problem = $stmt->fetch();
    if (!$problem) redirect("view_problems", [], "Not found");

    // Fetch new version (rev1)
    if ($rev1_id) {
      $stmt = $db->prepare("
        SELECT r.*
        FROM problem_revisions r
        WHERE r.id = :rev_id AND r.problem_id = :id");
      $stmt->execute([":rev_id" => $rev1_id, ":id" => $id]);
      $rev1 = $stmt->fetch();
      if ($rev1) {
          $usernames = DiscuzBridge::getUsernames([$rev1['user_id']]);
          $rev1['username'] = $usernames[$rev1['user_id']] ?? "Unknown";
      }
    } else {
      $rev1 = [
        'id' => 0,
        'statement' => $problem['statement'],
        'template' => $problem['template'],
        'time' => 'Current',
        'username' => 'Live'
      ];
    }

    // Fetch old version (rev2) - if not provided, try to find the one before rev1
    if (!$rev2_id && $rev1_id) {
       $stmt = $db->prepare("SELECT id FROM problem_revisions WHERE problem_id = :pid AND id < :rid ORDER BY id DESC LIMIT 1");
       $stmt->execute([":pid" => $id, ":rid" => $rev1_id]);
       $rev2_id = (int)$stmt->fetchColumn();
    } elseif (!$rev2_id && !$rev1_id) {
       // Compare current (0) with the latest revision
       $stmt = $db->prepare("SELECT MAX(id) FROM problem_revisions WHERE problem_id = :pid");
       $stmt->execute([":pid" => $id]);
       $rev2_id = (int)$stmt->fetchColumn();
    }

    if ($rev2_id) {
      $stmt = $db->prepare("
        SELECT r.*
        FROM problem_revisions r
        WHERE r.id = :id");
      $stmt->execute([":id" => $rev2_id]);
      $rev2 = $stmt->fetch();
      if ($rev2) {
          $usernames = DiscuzBridge::getUsernames([$rev2['user_id']]);
          $rev2['username'] = $usernames[$rev2['user_id']] ?? "Unknown";
      }
    } else {
      $rev2 = null; // First revision
    }

    $template = "templates/compare_revision.php";
  }

  elseif ($action === "view_status") {
    $stmt = $db->query("SELECT COUNT(*) FROM submissions WHERE status = 'PENDING'");
    $pending_count = $stmt->fetchColumn();

    $stmt = $db->query("
      SELECT s.*, p.title
      FROM submissions s
      JOIN problems p ON s.problem = p.id
      WHERE s.status = 'PROCESSING'
      LIMIT 1");
    $active_job = $stmt->fetch();
    if ($active_job) {
        $usernames = DiscuzBridge::getUsernames([$active_job['user']]);
        $active_job['username'] = $usernames[$active_job['user']] ?? "Unknown";
    }

    $stmt = $db->query("
      SELECT s.*, p.title
      FROM submissions s
      JOIN problems p ON s.problem = p.id
      WHERE s.status NOT IN ('PENDING', 'PROCESSING')
      ORDER BY s.id DESC
      LIMIT 10");
    $recent_jobs = $stmt->fetchAll();
    
    $uids = array_column($recent_jobs, 'user');
    $usernames = DiscuzBridge::getUsernames($uids);
    foreach ($recent_jobs as &$j) {
        $j['username'] = $usernames[$j['user']] ?? "Unknown";
    }

    $template = "templates/view_status.php";
  }

  elseif ($action === "status_info") {
    $template = "templates/status_info.php";
  }

  include "templates/header.php";
  if (isset($template) && file_exists(__DIR__ . '/' . $template)) {
    include $template;
  } else {
    echo "<p>Page not found.</p>";
  }
  include "templates/footer.php";
}
?>
