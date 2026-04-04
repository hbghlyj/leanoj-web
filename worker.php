<?php
$env = parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW);
$db = new PDO("sqlite:" . $env['DB_PATH']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$toolchain = $env['LEAN_TOOLCHAIN'];
$checkerFiles = $env['CHECKER_FILES'];
$checkerBins = $env['CHECKER_BINS'];

function failStatus($db, $id, $status, $out) {
  writeStatus($db, $id, $status);
  @mkdir(__DIR__ . "/logs", 0777, true);
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

function writeStatus($db, $id, $status) {
  $stmt = $db->prepare("UPDATE submissions SET status = :status WHERE id = :id");
  $stmt->execute([":status" => $status, ":id" => $id]);
}

function parseMeta($file) {
  $meta = [];
  if (file_exists($file)) {
    foreach (file($file) as $line) {
      $parts = explode(":", trim($line), 2);
      if (count($parts) === 2) $meta[$parts[0]] = $parts[1];
    }
  }
  return $meta;
}

function process_submission($db, $row) {
  global $checkerFiles, $toolchain;
  
  $visited = [];
  $dependency_content = get_recursive_dependency_content($db, json_decode($row['dependencies'] ?: '[]', true), $visited);

  echo "Attempting AXLE API verification for submission #{$row['id']}...\n";
  $res = axle_api_call("verify_proof", [
    "formal_statement" => $dependency_content . $row['template'],
    "content" => $dependency_content . $row['source'],
    "environment" => "lean-4.28.0",
    "ignore_imports" => true,
    "timeout_seconds" => 120
  ]);

  if ($res && isset($res['okay'])) {
      if ($res['okay']) {
          echo "AXLE Success!\n";
          writeStatus($db, $row['id'], 'PASSED');
          return;
      } else {
          $errors = $res['tool_messages']['errors'] ?? ($res['lean_messages']['errors'] ?? ["Unknown error"]);
          $msg = "Error: " . implode("\n", $errors);
          failStatus($db, $row['id'], "ERROR", $msg);
          echo "AXLE Failure: $msg\n";
          return;
      }
  }
  
  echo "AXLE failed, falling back to local for #{$row['id']}...\n";
  @mkdir($checkerFiles . "/CheckerFiles", 0777, true);
  file_put_contents($checkerFiles . "/CheckerFiles/Submission.lean", $row["source"]);
  $metaFile = __DIR__ . "/meta.txt";
  $cmd = [
    "isolate --cg --run --processes=0 --meta=$metaFile",
    "--cg-mem=4194304", "--time=60.0", "--wall-time=300.0",
    "--dir=/lean=" . escapeshellarg($toolchain),
    "--dir=/checker-files=" . escapeshellarg($checkerFiles) . ":rw",
    "--chdir=/checker-files",
    "-- /lean/bin/lake build CheckerFiles.Submission:olean"
  ];
  $out = []; $err = 0;
  runCommand($cmd, $out, $err);
  $meta = parseMeta($metaFile);
  @unlink($metaFile);

  if (isset($meta['status']) && $meta['status'] === "TO") $status = "Timeout";
  elseif (isset($meta["cg-oom-killed"]) && $meta["cg-oom-killed"] === "1") $status = "Out of memory";
  elseif ($err) $status = "Compilation error";
  else $status = "PASSED";

  if ($status !== "PASSED") failStatus($db, $row['id'], $status, $out);
  else writeStatus($db, $row['id'], 'PASSED');
  echo "Submission #{$row['id']} complete: $status\n";
}

echo "Worker started...\n";
shell_exec("isolate --cg --init");

while (true) {
  $stmt = $db->query("SELECT s.*, p.template, p.dependencies FROM submissions s JOIN problems p ON s.problem = p.id WHERE s.status = 'PENDING' LIMIT 1");
  $row = $stmt->fetch();
  if ($row) {
    process_submission($db, $row);
  } else {
    sleep(2);
  }
}
