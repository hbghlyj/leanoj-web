<?php
require_once __DIR__ . '/src/DiscuzBridge.php';
$action = $_GET['action'] ?? "view_problems";
$template = "templates/view_problems.php"; 
session_start();

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
  $ch = curl_init("https://axle.axiommath.ai/api/v1/" . $tool);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
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

$db = new PDO("sqlite:" . __DIR__ . "/db.sqlite");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function separate_imports($content) {
  $lines = explode("\n", str_replace("\r", "", $content));
  $imports = [];
  $body = [];
  foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === "") continue;
    if (strpos($trimmed, "import") === 0) {
      $imports[] = $line;
    } else {
      $body[] = $line;
    }
  }
  return ["imports" => implode("\n", $imports), "body" => implode("\n", $body)];
}

function get_recursive_dependency_content($db, $ids, &$visited = []) {
  $content = "";
  foreach ($ids as $id) {
    if (in_array((int)$id, $visited)) continue;
    $visited[] = (int)$id;

    $stmt = $db->prepare("SELECT template, dependencies FROM problems WHERE id = :id");
    $stmt->execute([':id' => (int)$id]);
    $row = $stmt->fetch();
    if (!$row) continue;

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
  if ($action === "submit_solution" && $user_id) {
    $problem_id = (int)($_POST['problem_id'] ?? 0);
    $time = date("Y-m-d\TH:i", time());
    $source_code = trim($_POST['source_text'] ?? "");

    $stmt = $db->prepare("SELECT template FROM problems WHERE id = :id");
    $stmt->execute([":id" => $problem_id]);
    $prob = $stmt->fetch();
    if (!$prob || empty($prob['template'])) {
        redirect("view_problem", ["id" => $problem_id], "Submissions are disabled.");
    }
    if (!empty($_FILES['source_file']['tmp_name'])) {
      $err = validate_file('source_file');
      if ($err) redirect("view_problem", ["id" => $problem_id], $err);
      $source_code = trim(file_get_contents($_FILES['source_file']['tmp_name']));
    }
    if (empty($source_code)) redirect("view_problem", ["id" => $problem_id], "Solution empty");
    
    $stmt = $db->prepare("INSERT INTO submissions (problem, user, source, status, time) VALUES (:problem, :user, :source, 'PENDING', :time)");
    $stmt->execute([":problem" => $problem_id, ":user" => $user_id, ":source" => $source_code, ":time" => $time]);
    redirect("view_problem", ["id" => $problem_id]);
  }

  elseif ($action === "add_problem" && $user_id) {
    $title = trim($_POST['title'] ?? "");
    $statement = trim($_POST['statement'] ?? "");
    $template = trim($_POST['template_text'] ?? "");
    $deps = array_map('intval', (array)($_POST['dependencies'] ?? []));
    $deps_json = json_encode($deps);
    
    $visited = [];
    $prepended_content = get_recursive_dependency_content($db, $deps, $visited);

    if (empty($title)) redirect("add_problem", [], "Title is required");
    
    if (!empty($template)) {
        $res = axle_api_call("check", [
          "content" => $prepended_content . $template,
          "environment" => "lean-4.28.0",
          "ignore_imports" => true,
          "timeout_seconds" => 120
        ]);
        if ($res && isset($res['okay']) && !$res['okay']) {
          $errors = $res['lean_messages']['errors'] ?? ["Invalid Lean template"];
          redirect("add_problem", [], "Template Error: " . implode("\n", $errors));
        }
    }

    $stmt = $db->prepare("INSERT INTO problems (title, statement, template, dependencies, creator_id) VALUES (:title, :statement, :template, :dependencies, :user_id)");
    $stmt->execute([":title" => $title, ":statement" => $statement, ":template" => $template, ":dependencies" => $deps_json, ":user_id" => $user_id]);
    
    $problem_id = $db->lastInsertId();
    $time = date("Y-m-d\TH:i:s", time());
    $stmt = $db->prepare("INSERT INTO problem_revisions (problem_id, statement, template, user_id, time) VALUES (:problem_id, :statement, :template, :user_id, :time)");
    $stmt->execute([":problem_id" => $problem_id, ":statement" => $statement, ":template" => $template, ":user_id" => $user_id, ":time" => $time]);
    
    redirect("view_problem", ["id" => $problem_id]);
  }

  elseif ($action === "edit_problem" && $user_id) {
    $id = (int)$_POST['id'] ?: null;
    $stmt = $db->prepare("SELECT * FROM problems WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $problem = $stmt->fetch();
    if (!$problem) redirect("view_problems", [], "Not found");
    
    $deps = array_map('intval', (array)($_POST['dependencies'] ?? []));
    $deps_json = json_encode($deps);
    $title = trim($_POST['title'] ?? "");
    $statement = trim($_POST['statement'] ?? "");
    $template = trim($_POST['template_text'] ?? "");

    if (empty($title)) redirect("edit_problem", ["id" => $id], "Title required");
    
    $visited = [];
    $prepended_content = get_recursive_dependency_content($db, $deps, $visited);

    if (!empty($template)) {
        $res = axle_api_call("check", [
          "content" => $prepended_content . $template,
          "environment" => "lean-4.28.0",
          "ignore_imports" => true,
          "timeout_seconds" => 120
        ]);
        if ($res && isset($res['okay']) && !$res['okay']) {
          $errors = $res['lean_messages']['errors'] ?? ["Invalid Lean template"];
          redirect("edit_problem", ["id" => $id], "Template Error: " . implode("\n", $errors));
        }
    }

    $stmt = $db->prepare("UPDATE problems SET title = :title, statement = :statement, template = :template, dependencies = :dependencies WHERE id = :id");
    $stmt->execute([":id" => $id, ":title" => $title, ":statement" => $statement, ":template" => $template, ":dependencies" => $deps_json]);
    
    if ($statement !== $problem['statement'] || $template !== $problem['template']) {
      $time = date("Y-m-d\TH:i:s", time());
      $stmt = $db->prepare("INSERT INTO problem_revisions (problem_id, statement, template, user_id, time) VALUES (:problem_id, :statement, :template, :user_id, :time)");
      $stmt->execute([":problem_id" => $id, ":statement" => $statement, ":template" => $template, ":user_id" => $user_id, ":time" => $time]);
    }
    redirect("view_problem", ["id" => $id]);
  }

  elseif ($action === "delete_problem" && $user_id) {
    $id = (int)$_POST['id'] ?: null;
    $stmt = $db->prepare("SELECT creator_id FROM problems WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $problem = $stmt->fetch();
    if (!$problem) redirect("view_problems", [], "Not found");
    if (!$is_admin && $problem['creator_id'] != $user_id) redirect("view_problem", ["id" => $id], "Permission denied");

    $db->prepare("DELETE FROM problems WHERE id = ?")->execute([$id]);
    $db->prepare("DELETE FROM submissions WHERE problem = ?")->execute([$id]);
    $db->prepare("DELETE FROM problem_revisions WHERE problem_id = ?")->execute([$id]);
    redirect("view_problems");
  }

  elseif ($action === "delete_submission" && $user_id) {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $db->prepare("SELECT user, problem FROM submissions WHERE id = ?");
    $stmt->execute([$id]);
    $sub = $stmt->fetch();
    if (!$sub) redirect("view_submissions");
    if (!$is_admin && $sub['user'] != $user_id) redirect("view_submission", ["id" => $id]);
    $db->prepare("DELETE FROM submissions WHERE id = ?")->execute([$id]);
    redirect("view_submissions", ["id" => $sub['problem']]);
  }

  // RECOVERED: ROLLBACK REVISION
  elseif ($action === "rollback_revision" && $user_id) {
    $rev_id = (int)$_POST['rev_id'] ?: null;
    $stmt = $db->prepare("SELECT * FROM problem_revisions WHERE id = :rev_id");
    $stmt->execute([":rev_id" => $rev_id]);
    $rev = $stmt->fetch();
    if (!$rev) redirect("view_problems", [], "Revision not found");
    
    $prob_id = $rev['problem_id'];
    $stmt = $db->prepare("SELECT creator_id FROM problems WHERE id = :id");
    $stmt->execute([":id" => $prob_id]);
    $prob = $stmt->fetch();
    if (!$is_admin && $prob['creator_id'] != $user_id) redirect("view_problem", ["id" => $prob_id], "Permission denied");

    $stmt = $db->prepare("UPDATE problems SET statement = :statement, template = :template WHERE id = :id");
    $stmt->execute([
      ":statement" => $rev['statement'],
      ":template" => $rev['template'],
      ":id" => $prob_id
    ]);
    
    $time = date("Y-m-d\TH:i:s", time());
    $stmt = $db->prepare("INSERT INTO problem_revisions (problem_id, statement, template, user_id, time) VALUES (:problem_id, :statement, :template, :user_id, :time)");
    $stmt->execute([
      ":problem_id" => $prob_id,
      ":statement" => $rev['statement'],
      ":template" => $rev['template'],
      ":user_id" => $user_id,
      ":time" => $time,
    ]);
    redirect("view_history", ["id" => $prob_id]);
  }

  // RECOVERED: DELETE REVISION
  elseif ($action === "delete_revision" && $is_admin) {
    $rev_id = (int)$_POST['rev_id'] ?: null;
    $prob_id = (int)$_POST['prob_id'] ?: null;
    $stmt = $db->prepare("DELETE FROM problem_revisions WHERE id = :rev_id");
    $stmt->execute([":rev_id" => $rev_id]);
    redirect("view_history", ["id" => $prob_id]);
  }
}

$error = $_GET['error'] ?? null;
if ($action === "view_problems") {
    $stmt = $db->query("SELECT p.*, (SELECT COUNT(*) FROM submissions s WHERE s.problem = p.id AND s.status = 'PASSED') as solves FROM problems p ORDER BY p.id DESC");
    $problems = $stmt->fetchAll();
    include 'templates/header.php';
    include 'templates/view_problems.php';
    include 'templates/footer.php';
} elseif ($action === "add_problem" && $user_id) {
    $stmt = $db->query("SELECT id, title FROM problems ORDER BY title ASC");
    $all_problems = $stmt->fetchAll();
    include 'templates/header.php';
    include 'templates/add_problem.php';
    include 'templates/footer.php';
} elseif ($action === "edit_problem" && $user_id) {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM problems WHERE id = ?");
    $stmt->execute([$id]);
    $problem = $stmt->fetch();
    if (!$problem) redirect("view_problems");
    
    $stmt = $db->query("SELECT id, title FROM problems WHERE id != $id ORDER BY title ASC");
    $all_problems = $stmt->fetchAll();
    $problem['deps_array'] = json_decode($problem['dependencies'] ?: '[]', true);
    include 'templates/header.php';
    include 'templates/edit_problem.php';
    include 'templates/footer.php';
} elseif ($action === "view_problem") {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM problems WHERE id = ?");
    $stmt->execute([$id]);
    $problem = $stmt->fetch();
    if (!$problem) redirect("view_problems");

    $dep_ids = json_decode($problem['dependencies'] ?: '[]', true);
    $problem['dependency_details'] = [];
    if (!empty($dep_ids)) {
      $placeholders = implode(',', array_fill(0, count($dep_ids), '?'));
      $stmt = $db->prepare("SELECT id, title FROM problems WHERE id IN ($placeholders)");
      $stmt->execute($dep_ids);
      $problem['dependency_details'] = $stmt->fetchAll();
    }
    
    // FETCH RECENT SUBMISSIONS
    $stmt = $db->prepare("SELECT * FROM submissions WHERE problem = ? ORDER BY id DESC LIMIT 5");
    $stmt->execute([$id]);
    $recent_submissions = $stmt->fetchAll();
    
    // RESOLVE USERNAMES VIA DISCUZ
    $uids = array_unique(array_column($recent_submissions, 'user'));
    $usernames = DiscuzBridge::getUsernames($uids);
    foreach ($recent_submissions as &$sub) {
        $sub['username'] = $usernames[$sub['user']] ?? "UID: " . $sub['user'];
    }
    unset($sub);

    include 'templates/header.php';
    include 'templates/view_problem.php';
    include 'templates/footer.php';
} elseif ($action === "view_submissions") {
    $problem_id = (int)($_GET['id'] ?? 0);
    if ($problem_id) {
      $stmt = $db->prepare("SELECT s.*, p.title as problem_title FROM submissions s JOIN problems p ON s.problem = p.id WHERE s.problem = ? ORDER BY s.id DESC");
      $stmt->execute([$problem_id]);
    } else {
      $stmt = $db->query("SELECT s.*, p.title as problem_title FROM submissions s JOIN problems p ON s.problem = p.id ORDER BY s.id DESC LIMIT 50");
    }
    $submissions = $stmt->fetchAll();
    
    // RESOLVE USERNAMES VIA DISCUZ
    $uids = array_unique(array_column($submissions, 'user'));
    $usernames = DiscuzBridge::getUsernames($uids);
    foreach ($submissions as &$sub) {
        $sub['username'] = $usernames[$sub['user']] ?? "UID: " . $sub['user'];
    }
    unset($sub);

    include 'templates/header.php';
    include 'templates/view_submissions.php';
    include 'templates/footer.php';
} elseif ($action === "view_submission") {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT s.*, p.title as problem_title FROM submissions s JOIN problems p ON s.problem = p.id WHERE s.id = ?");
    $stmt->execute([$id]);
    $submission = $stmt->fetch();
    if (!$submission) redirect("view_submissions");

    // RESOLVE USERNAME VIA DISCUZ
    $usernames = DiscuzBridge::getUsernames([$submission['user']]);
    $submission['username'] = $usernames[$submission['user']] ?? "UID: " . $submission['user'];

    $log = $submission['log'] ?? "No log available.";
    include 'templates/header.php';
    include 'templates/view_submission.php';
    include 'templates/footer.php';
} elseif ($action === "view_history") {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM problem_revisions WHERE problem_id = ? ORDER BY time DESC");
    $stmt->execute([$id]);
    $revisions = $stmt->fetchAll();

    // RESOLVE USERNAMES VIA DISCUZ
    $uids = array_unique(array_column($revisions, 'user_id'));
    $usernames = DiscuzBridge::getUsernames($uids);
    foreach ($revisions as &$rev) {
        $rev['username'] = $usernames[$rev['user_id']] ?? "UID: " . $rev['user_id'];
    }
    unset($rev);

    include 'templates/header.php';
    include 'templates/view_history.php';
    include 'templates/footer.php';
} elseif ($action === "compare_revision") {
    $id = (int)$_GET['id'];
    $rev1_id = (int)($_GET['rev1'] ?? 0);
    $rev2_id = (int)($_GET['rev2'] ?? 0);
    
    $stmt = $db->prepare("SELECT * FROM problems WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $problem = $stmt->fetch();
    if (!$problem) redirect("view_problems", [], "Not found");

    if ($rev1_id) {
      $stmt = $db->prepare("SELECT * FROM problem_revisions WHERE id = :rev_id AND problem_id = :id");
      $stmt->execute([":rev_id" => $rev1_id, ":id" => $id]);
      $rev1 = $stmt->fetch();
      if ($rev1) {
          $usernames = DiscuzBridge::getUsernames([$rev1['user_id']]);
          $rev1['username'] = $usernames[$rev1['user_id']] ?? "Unknown";
      }
    } else {
      $rev1 = ['id' => 0, 'statement' => $problem['statement'], 'template' => $problem['template'], 'time' => 'Current', 'username' => 'Live'];
    }

    if (!$rev2_id && $rev1_id) {
       $stmt = $db->prepare("SELECT id FROM problem_revisions WHERE problem_id = :pid AND id < :rid ORDER BY id DESC LIMIT 1");
       $stmt->execute([":pid" => $id, ":rid" => $rev1_id]);
       $rev2_id = (int)$stmt->fetchColumn();
    } elseif (!$rev2_id && !$rev1_id) {
       $stmt = $db->prepare("SELECT MAX(id) FROM problem_revisions WHERE problem_id = :pid");
       $stmt->execute([":pid" => $id]);
       $rev2_id = (int)$stmt->fetchColumn();
    }

    if ($rev2_id) {
      $stmt = $db->prepare("SELECT * FROM problem_revisions WHERE id = :id");
      $stmt->execute([":id" => $rev2_id]);
      $rev2 = $stmt->fetch();
      if ($rev2) {
          $usernames = DiscuzBridge::getUsernames([$rev2['user_id']]);
          $rev2['username'] = $usernames[$rev2['user_id']] ?? "Unknown";
      }
    } else {
      $rev2 = null;
    }
    include 'templates/header.php';
    include 'templates/compare_revision.php';
    include 'templates/footer.php';
} elseif ($action === "view_status") {
    $stmt = $db->query("SELECT COUNT(*) FROM submissions WHERE status = 'PENDING'");
    $pending_count = $stmt->fetchColumn();

    $stmt = $db->query("SELECT s.*, p.title FROM submissions s JOIN problems p ON s.problem = p.id WHERE s.status = 'PROCESSING' LIMIT 1");
    $active_job = $stmt->fetch();
    if ($active_job) {
        $usernames = DiscuzBridge::getUsernames([$active_job['user']]);
        $active_job['username'] = $usernames[$active_job['user']] ?? "Unknown";
    }

    $stmt = $db->query("SELECT s.*, p.title FROM submissions s JOIN problems p ON s.problem = p.id WHERE s.status NOT IN ('PENDING', 'PROCESSING') ORDER BY s.id DESC LIMIT 10");
    $recent_jobs = $stmt->fetchAll();
    $uids = array_unique(array_column($recent_jobs, 'user'));
    $usernames = DiscuzBridge::getUsernames($uids);
    foreach ($recent_jobs as &$j) {
        $j['username'] = $usernames[$j['user']] ?? "Unknown";
    }
    unset($j);

    include 'templates/header.php';
    include 'templates/view_status.php';
    include 'templates/footer.php';
} elseif ($action === "status_info") {
    include 'templates/header.php';
    include 'templates/status_info.php';
    include 'templates/footer.php';
} elseif ($action === "about") {
    include 'templates/header.php';
    include 'templates/about.php';
    include 'templates/footer.php';
} else {
    redirect("view_problems");
}
