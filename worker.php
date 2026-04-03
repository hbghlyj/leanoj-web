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
    if ($error) echo "CURL ERROR [$tool]: $error\n";
    else echo "HTTP ERROR [$tool]: $httpCode\n";
    return null;
  }
  return json_decode($response, true);
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
    SELECT s.*, p.template, p.title, p.answer as local_file_id, lf.path
    FROM submissions s
    JOIN problems p ON s.problem = p.id
    LEFT JOIN local_files lf ON p.answer = lf.id
    WHERE s.status = 'PENDING'
    LIMIT 1"
  );
  $stmt->execute();
  $row = $stmt->fetch();
  
  if ($row) {
    process_submission($db, $row);
    continue;
  }

  // Check local files
  $stmt = $db->prepare("SELECT * FROM local_files WHERE status = 'PENDING' LIMIT 1");
  $stmt->execute();
  $lf = $stmt->fetch();
  if ($lf) {
    process_local_file($db, $lf);
    continue;
  }

  sleep(1);
}

function process_local_file($db, $lf) {
  $id = $lf['id'];
  $path = $lf['path'];
  echo "Processing local file #$id ($path)...\n";
  writeStatus($db, $id, 'PROCESSING', true);

  $content = @file_get_contents($path);
  if ($content === false) {
    failLocal($db, $id, "File not found on disk");
    return;
  }

  // 1. Race Logic similar to index.php verify_local_file
  // Use AXLE 'check' tool
  echo "Attempting AXLE check for local file...\n";
  $res = axle_api_call("check", ["content" => $content, "environment" => "lean-4.28.0"]);
  if ($res && isset($res['okay'])) {
    if ($res['okay']) {
      echo "AXLE Success!\n";
      writeStatus($db, $id, 'PASSED', true);
      return;
    } else {
      $errors = $res['lean_messages']['errors'] ?? ["API reported failure"];
      failLocal($db, $id, "API Error: " . implode("\n", $errors));
      return;
    }
  }

  // 2. Fallback to local build
  echo "AXLE failed/unavailable, falling back to local build...\n";
  global $checkerFiles, $toolchain;
  file_put_contents($checkerFiles . "/CheckerFiles/LocalFile.lean", $content);
  $metaFile = __DIR__ . "/meta_local.txt";
  $cmd = [
    "isolate --cg --run --processes=0 --meta=$metaFile",
    "--cg-mem=4194304",
    "--time=120.0",
    "--dir=/lean=" . escapeshellarg($toolchain),
    "--dir=/checker-files=" . escapeshellarg($checkerFiles) . ":rw",
    "--chdir=/checker-files",
    "-- /lean/bin/lake build CheckerFiles.LocalFile:olean"
  ];
  runCommand($cmd, $out, $err);
  $meta = parseMeta($metaFile);
  @unlink($metaFile);
  
  if (isset($meta['status']) && $meta['status'] === "TO") {
    failLocal($db, $id, "Time out (Local)");
  } elseif ($err) {
    failLocal($db, $id, "Compilation Error (Local): " . implode("\n", $out));
  } else {
    echo "Local Success!\n";
    writeStatus($db, $id, 'PASSED', true);
  }
}

function failLocal($db, $id, $msg) {
  $stmt = $db->prepare("UPDATE local_files SET status = 'FAILED', log = :log WHERE id = :id");
  $stmt->bindValue(":log", $msg);
  $stmt->bindValue(":id", $id);
  $stmt->execute();
  echo "Local file #$id FAILED: $msg\n";
}

function process_submission($db, $row) {
  global $checkerFiles, $toolchain, $checkerBins;
  if (!$row['local_file_id']) {
    echo "Attempting AXLE API verification...\n";
    $payload = [
      "formal_statement" => $row['template'],
      "content" => $row['source'],
      "environment" => "lean-4.28.0"
    ];
    // echo "DEBUG: Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
    $res = axle_api_call("verify_proof", $payload);
    if ($res && isset($res['okay'])) {
        echo "AXLE result: " . ($res['okay'] ? "SUCCESS" : "FAILURE") . "\n";
        if ($res['okay']) {
            echo "AXLE Success!\n";
            writeStatus($db, $row['id'], 'PASSED');
            return;
        } else {
            // User requested to throw error if AXLE fails
            $errors = $res['tool_messages']['errors'] ?? [];
            if (empty($errors)) $errors = $res['lean_messages']['errors'] ?? ["Unknown API error"];
            $status_msg = "API Error: " . implode("\n", $errors);
            failStatus($db, $row['id'], $status_msg, $status_msg); // This writes to DB and LOG FILE
            echo "AXLE Failure: $status_msg\n";
            return;
        }
    } else {
       echo "AXLE API error or empty response.\n";
    }
    echo "AXLE API unavailable or skipped, falling back to local...\n";
  }

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

    if ($row['local_file_id']) {
      echo "Checking answer...\n";
      $answerContent = @file_get_contents($row['path']);
      if ($answerContent === false) {
          $status = "System error (Local file missing)";
          failStatus($db, $row['id'], $status, ["Failed to read " . $row['path']]);
          echo "Processed submission #{$row["id"]}: $status\n";
          return;
      }
      file_put_contents($checkerFiles . "/CheckerFiles/Answer.lean", $answerContent);
      $cmd = [
        "isolate --cg --run --processes=0",
        "--dir=/lean=" . escapeshellarg($toolchain),
        "--dir=/checker-files=" . escapeshellarg($checkerFiles) . ":rw",
        "--chdir=/checker-files",
        "-- /lean/bin/lake build CheckerFiles.Answer:olean"
      ];
      runCommand($cmd, $out, $err);
      if ($err) {
        $status = "System error";
        failStatus($db, $row['id'], $status, $out);
        echo "Processed submission #{$row["id"]}: $status\n";
        return;
      }

      echo "Checking answer...\n";
      $cmd = [
        "isolate --cg --run --processes=0",
        "--dir=/lean=" . escapeshellarg($toolchain),
        "--dir=/bin=" . escapeshellarg($checkerBins),
        "--dir=/checker-files=" . escapeshellarg($checkerFiles) . ":rw",
        "--chdir=/checker-files",
        "-- /lean/bin/lake env /bin/check_answer CheckerFiles.Answer CheckerFiles.Submission"
      ];
      runCommand($cmd, $out, $err);
      if ($err) {
        $status = "Bad answer";
        failStatus($db, $row['id'], $status, $out);
        echo "Processed submission #{$row["id"]}: $status\n";
      }
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
