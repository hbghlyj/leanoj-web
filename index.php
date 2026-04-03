<?php
require_once __DIR__ . '/src/DiscuzBridge.php';
DiscuzBridge::syncSession();

$action = $_GET['action'] ?? "view_problems";
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
  $env = parse_ini_file("/var/www/leanoj/leanoj-web/.env", false, INI_SCANNER_RAW);
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

$db = new PDO("sqlite:/var/www/leanoj/leanoj-web/db.sqlite");
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

if ($action === "logout") {
  DiscuzBridge::clearCookies();
  redirect();
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
  // register and login removed - handled by Discuz!

  if ($action === "submit_solution" && $user_id) {
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
    $template = trim($_POST['template_text'] ?? "");
    $answer = (int)$_POST['answer'] ?: null;

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
      $stmt = $db->prepare("SELECT EXISTS(SELECT 1 from local_files WHERE id = :id)");
      $stmt->execute([":id" => $answer]);
      $answer_exists = (bool)$stmt->fetchColumn();
      if (!$answer_exists) {
        redirect("add_problem", [], "Answer not found");
      }
    }
    $stmt = $db->prepare("
      INSERT INTO problems (title, statement, template, answer, creator_id)
      VALUES (:title, :statement, :template, :answer, :creator_id)");
    $stmt->execute([
      ":title" => $title,
      ":statement" => $statement,
      ":template" => $template,
      ":answer" => $answer,
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
    
    $title = $is_admin ? trim($_POST['title'] ?? "") : $problem['title'];
    $answer = $is_admin ? ((int)$_POST['answer'] ?: null) : $problem['answer'];
    
    $statement = trim($_POST['statement'] ?? "");
    $template = trim($_POST['template_text'] ?? "");
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
      $stmt = $db->prepare("SELECT EXISTS(SELECT 1 from local_files WHERE id = :id)");
      $stmt->execute([":id" => $answer]);
      $answer_exists = (bool)$stmt->fetchColumn();
      if (!$answer_exists) {
        redirect("edit_problem", ["id" => $id], "Answer not found");
      }
    }
    $stmt = $db->prepare("
      UPDATE problems
      SET title = :title, statement = :statement, template = :template,
        answer = :answer
      WHERE id = :id");
    $stmt->execute([
      ":id" => $id,
      ":title" => $title,
      ":statement" => $statement,
      ":template" => $template,
      ":answer" => $answer,
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

  elseif ($action === "add_local_file" && $is_admin) {
    $path = trim($_POST['path'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $content = trim($_POST['content'] ?? "");
    
    if (strpos($path, '..') !== false) redirect("view_local_files", [], "Invalid path");
    if (strpos($path, '/var/www/leanoj/file/') !== 0) {
      $path = '/var/www/leanoj/file/' . ltrim($path, '/');
    }
    if (substr($path, -5) !== ".lean") {
      $path .= ".lean";
    }

    if (empty($path) || empty($content)) {
      redirect("view_local_files", [], "Path and content are required");
    }

    $stmt = $db->prepare("INSERT INTO local_files (path, creator_id, status) VALUES (:path, :creator_id, 'PENDING')");
    $stmt->execute([":path" => $path, ":creator_id" => $user_id]);
    $file_id = $db->lastInsertId();

    $dir = dirname($path);
    if (!is_dir($dir)) {
      mkdir($dir, 0777, true);
    }
    file_put_contents($path, $content);
    
    redirect("view_local_files");
  }

  elseif ($action === "edit_local_file" && $user_id) {
    $id = (int)$_POST['id'];
    $path = trim($_POST['path'] ?? "");
    $content = trim($_POST['content'] ?? "");

    if (strpos($path, '..') !== false) redirect("view_local_files", [], "Invalid path");
    if (strpos($path, '/var/www/leanoj/file/') !== 0) {
      $path = '/var/www/leanoj/file/' . ltrim($path, '/');
    }
    if (substr($path, -5) !== ".lean") {
      $path .= ".lean";
    }

    $stmt = $db->prepare("SELECT * FROM local_files WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $lf = $stmt->fetch();
    if (!$lf) redirect("view_local_files", [], "Not found");

    if (!$is_admin && $lf['creator_id'] != $user_id) {
        redirect("view_local_files", [], "Permission denied");
    }

    // Capture current state BEFORE overwriting
    $oldContent = @file_get_contents($lf['path']) ?: "";
    $time = date("Y-m-d\TH:i:s", time());
    $stmt = $db->prepare("INSERT INTO local_file_revisions (local_file_id, content, user_id, time) VALUES (:fid, :content, :uid, :time)");
    $stmt->execute([":fid" => $id, ":content" => $oldContent, ":uid" => $user_id, ":time" => $time]);

    $stmt = $db->prepare("UPDATE local_files SET path = :path, status = 'PENDING' WHERE id = :id");
    $stmt->execute([":path" => $path, ":id" => $id]);

    $dir = dirname($path);
    if (!is_dir($dir)) {
      mkdir($dir, 0777, true);
    }
    file_put_contents($path, $content);

    redirect("view_local_files");
  }

  elseif ($action === "rollback_local_file" && $user_id) {
    $rev_id = (int)$_POST['rev_id'];
    $stmt = $db->query("SELECT r.*, lf.path, lf.creator_id FROM local_file_revisions r JOIN local_files lf ON r.local_file_id = lf.id WHERE r.id = $rev_id");
    $rev = $stmt->fetch();
    if (!$rev) redirect("view_local_files", [], "Revision not found");

    if (!$is_admin && $rev['creator_id'] != $user_id) {
        redirect("view_local_files", [], "Permission denied");
    }

    // Capture current state BEFORE rollback
    $oldContent = @file_get_contents($rev['path']) ?: "";
    $time = date("Y-m-d\TH:i:s", time());
    $stmt = $db->prepare("INSERT INTO local_file_revisions (local_file_id, content, user_id, time) VALUES (:fid, :content, :uid, :time)");
    $stmt->execute([":fid" => $rev['local_file_id'], ":content" => $oldContent, ":uid" => $user_id, ":time" => $time]);

    $stmt = $db->prepare("UPDATE local_files SET status = 'PENDING' WHERE id = :id");
    $stmt->execute([":id" => $rev['local_file_id']]);

    file_put_contents($rev['path'], $rev['content']);

    redirect("view_local_file_history", ["id" => $rev['local_file_id']]);
  }

  elseif ($action === "delete_local_file_revision" && $is_admin) {
    $rev_id = (int)$_POST['rev_id'];
    $fid = (int)$_POST['fid'];
    $stmt = $db->prepare("DELETE FROM local_file_revisions WHERE id = :id");
    $stmt->execute([":id" => $rev_id]);
    redirect("view_local_file_history", ["id" => $fid]);
  }

  elseif ($action === "delete_local_file" && $user_id) {
    $id = (int)$_POST['id'];
    $stmt = $db->prepare("SELECT * FROM local_files WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $lf = $stmt->fetch();
    if (!$lf) redirect("view_local_files", [], "Not found");

    if (!$is_admin && $lf['creator_id'] != $user_id) {
        redirect("view_local_files", [], "Permission denied");
    }

    // 1. Unlink from problems
    $stmt = $db->prepare("UPDATE problems SET answer = NULL WHERE answer = :id");
    $stmt->execute([":id" => $id]);

    // 2. Clear history
    $stmt = $db->prepare("DELETE FROM local_file_revisions WHERE local_file_id = :id");
    $stmt->execute([":id" => $id]);

    // 3. Delete main record
    $stmt = $db->prepare("DELETE FROM local_files WHERE id = :id");
    $stmt->execute([":id" => $id]);

    // 4. Physical deletion
    if (file_exists($lf['path'])) {
        unlink($lf['path']);
    }

    // 5. Sweep artifacts
    $baseName = pathinfo($lf['path'], PATHINFO_FILENAME);
    $sweepCmd = "sudo /usr/local/bin/leanoj_sweep_artifacts.sh " . escapeshellarg($baseName);
    exec($sweepCmd . " 2>&1");

    redirect("view_local_files");
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

  elseif ($action === "view_local_files" && $user_id) {
    $stmt = $db->query("SELECT * FROM local_files");
    $local_files = $stmt->fetchAll();
    
    $uids = array_column($local_files, 'creator_id');
    $usernames = DiscuzBridge::getUsernames($uids);
    foreach ($local_files as &$lf) {
        $lf['creator_name'] = $usernames[$lf['creator_id']] ?? "Unknown";
    }
    
    $template = "templates/view_local_files.php";
  }

  elseif ($action === "view_local_file_history" && $user_id) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM local_files WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $lf = $stmt->fetch();
    if (!$lf) redirect("view_local_files", [], "Not found");

    $uids = [$lf['creator_id']];
    $usernames = DiscuzBridge::getUsernames($uids);
    $lf['creator_name'] = $usernames[$lf['creator_id']] ?? "Unknown";

    $can_edit = ($is_admin || $lf['creator_id'] == $user_id);

    $stmt = $db->prepare("SELECT * FROM local_file_revisions WHERE local_file_id = :id ORDER BY id DESC");
    $stmt->execute([":id" => $id]);
    $revisions = $stmt->fetchAll();
    
    $uids = array_column($revisions, 'user_id');
    $usernames = DiscuzBridge::getUsernames($uids);
    foreach ($revisions as &$r) {
        $r['username'] = $usernames[$r['user_id']] ?? "Unknown";
    }

    $template = "templates/view_local_file_history.php";
  }


  elseif ($action === "add_problem" && $is_admin) {
    $stmt = $db->query("SELECT id, path FROM local_files");
    $local_files = $stmt->fetchAll();
    $template = "templates/add_problem.php";
  }

  elseif ($action === "edit_problem" && $user_id) {
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
    $stmt = $db->query("SELECT id, path FROM local_files");
    $local_files = $stmt->fetchAll();
    $template = "templates/edit_problem.php";
  }

  elseif ($action === "add_local_file" && $is_admin) {
    $template = "templates/add_local_file.php";
  }

  elseif ($action === "edit_local_file" && $user_id) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM local_files WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $lf = $stmt->fetch();
    if (!$lf) redirect("view_local_files", [], "Not found");

    if (!$is_admin && $lf['creator_id'] != $user_id) {
        redirect("view_local_files", [], "Permission denied");
    }

    // Read current content directly from filesystem
    $content = @file_get_contents($lf['path']) ?: "";

    $template = "templates/edit_local_file.php";
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
  include $template;
  include "templates/footer.php";
}
?>
