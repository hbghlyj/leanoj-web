<?php
$env = parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW);
$db = new PDO("sqlite:" . $env['DB_PATH']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$toolchain = $env['LEAN_TOOLCHAIN'];
$checkerFiles = $env['CHECKER_FILES'];
$checkerBins = $env['CHECKER_BINS'];
$out = [];
$err = 0;

function failStatus($db, $id, $status, $out) {
  writeStatus($db, $id, $status);
  file_put_contents(__DIR__ . "/logs/submission_{$id}.log", is_array($out) ? implode("\n", $out) : $out);
}

function axle_api_call($tool, $payload) {
  $ch = curl_init("https://axle.axiommath.ai/api/v1/" . $tool);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'User-Agent: Leanoj-Worker/1.0'
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

function runCommand($cmd, &$out, &$err) {
  $out = [];
  $newCmd = [];
  foreach($cmd as $c) {
    $newCmd[] = $c;
    if (strpos($c, "--run") !== false) {
      $newCmd[] = "--env=GIT_CONFIG_COUNT=1";
      $newCmd[] = "--env=GIT_CONFIG_KEY_0=safe.directory";
      $newCmd[] = "--env=GIT_CONFIG_VALUE_0=*";
      $newCmd[] = "--env=LAKE_NO_NETWORK=1";
    }
  }
  exec(implode(" ", $newCmd) . " 2>&1", $out, $err);
}

function writeStatus($db, $id, $status, $is_local = false) {
  $table = $is_local ? "local_files" : "submissions";
  $stmt = $db->prepare("UPDATE $table SET status = :status WHERE id = :id");
  $stmt->bindValue(":status", $status);
  $stmt->bindValue(":id", $id);
  $stmt->execute();
}

function parseMeta($file) {
  $meta = [];
  foreach (file($file) as $line) {
    $parts = explode(":", trim($line), 2);
    if (count($parts) === 2) $meta[$parts[0]] = $parts[1];
  }
  return $meta;
}

echo "Worker started...\n";
shell_exec("isolate --cg --init");

while (true) {
  // Check submissions
  $stmt = $db->prepare("
    SELECT s.*, p.template, p.title, p.dependencies
    FROM submissions s
    JOIN problems p ON s.problem = p.id
    WHERE s.status = 'PENDING'
    LIMIT 1"
  );
  $stmt->execute();
  $row = $stmt->fetch();
  
  if ($row) {
    process_submission($db, $row);
    continue;
  }

  sleep(1);
}

function get_dependencies_content($db, $json_ids) {
  $ids = json_decode($json_ids ?: '[]', true);
  if (empty($ids)) return "";
  return get_recursive_dependency_content($db, $ids);
}

function process_submission($db, $row) {
  global $checkerFiles, $toolchain, $checkerBins;
  
  $dependency_content = get_dependencies_content($db, $row['dependencies']);

  echo "Attempting AXLE API verification...\n";
  $payload = [
    "formal_statement" => $dependency_content . $row['template'],
    "content" => $dependency_content . $row['source'],
    "environment" => "lean-4.28.0",
    "ignore_imports" => true,
    "timeout_seconds" => 120
  ];
  $res = axle_api_call("verify_proof", $payload);
  if ($res && isset($res['okay'])) {
      echo "AXLE result: " . ($res['okay'] ? "SUCCESS" : "FAILURE") . "\n";
      if ($res['okay']) {
          echo "AXLE Success!\n";
          writeStatus($db, $row['id'], 'PASSED');
          return;
      } else {
          $errors = $res['tool_messages']['errors'] ?? [];
          if (empty($errors)) $errors = $res['lean_messages']['errors'] ?? ["Unknown API error"];
          $status_msg = "API Error: " . implode("\n", $errors);
          failStatus($db, $row['id'], $status_msg, $status_msg); 
          echo "AXLE Failure: $status_msg\n";
          return;
      }
  } else {
     echo "AXLE API error or empty response.\n";
  }
  echo "AXLE API unavailable or failed, falling back to local...\n";

  $status = "";

  echo "Building submission...\n";
  file_put_contents($checkerFiles . "/CheckerFiles/Submission.lean", $row["source"]);
  $metaFile = __DIR__ . "/meta.txt";
  $cmd = [
    "isolate --cg --run --processes=0 --meta=$metaFile",
    "--cg-mem=4194304",
    "--time=60.0",
    "--wall-time=300.0",
    "--dir=/lean=" . escapeshellarg($toolchain),
    "--dir=/checker-files=" . escapeshellarg($checkerFiles) . ":rw",
    "--chdir=/checker-files",
    "-- /lean/bin/lake build CheckerFiles.Submission:olean"
  ];
  runCommand($cmd, $out, $err);
  # echo implode("\n", $out) . "\n";
  $meta = parseMeta($metaFile);
  unlink($metaFile);
  if (isset($meta['status']) && $meta['status'] === "TO") {
    $status = "Time out";
  } elseif (isset($mega["cg-oom-killed"]) && $meta["cg-oom-killed"] === "1") {
    $status = "Out of memory";
  } elseif ($err) {
    $status = "Compilation error";
  }
  if ($status) {
    failStatus($db, $row['id'], $status, $out);
    echo "Processed submission #{$row["id"]}: $status\n";
    return;
  }

  if ($row["title"] !== "xyzzy") {
    echo "Building template...\n";
    file_put_contents($checkerFiles . "/CheckerFiles/Template.lean", $row["template"]);
    $cmd = [
      "isolate --cg --run --processes=0",
      "--dir=/lean=" . escapeshellarg($toolchain),
      "--dir=/checker-files=" . escapeshellarg($checkerFiles) . ":rw",
      "--chdir=/checker-files",
      "-- /lean/bin/lake build CheckerFiles.Template:olean"
    ];
    runCommand($cmd, $out, $err);
    if ($err) {
      $status = "System error";
      failStatus($db, $row['id'], $status, $out);
      echo "Processed submission #{$row["id"]}: $status\n";
      return;
    }

    echo "Checking declarations...\n";
    $cmd = [
      "isolate --cg --run --processes=0",
      "--dir=/lean=" . escapeshellarg($toolchain),
      "--dir=/bin=" . escapeshellarg($checkerBins),
      "--dir=/checker-files=" . escapeshellarg($checkerFiles) . ":rw",
      "--chdir=/checker-files",
      "-- /lean/bin/lake env /bin/check CheckerFiles.Template CheckerFiles.Submission"
    ];
    runCommand($cmd, $out, $err);
    if ($err) {
      $status = "Template mismatch";
      failStatus($db, $row['id'], $status, $out);
      echo "Processed submission #{$row["id"]}: $status\n";
      return;
    }
  }



  $status = "PASSED";
  writeStatus($db, $row['id'], $status);
  echo "Processed submission #{$row["id"]}: $status\n";
}
