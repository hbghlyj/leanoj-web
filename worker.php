<?php
$db = new PDO("sqlite:" . __DIR__ . "/db.sqlite");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function failStatus($db, $id, $status, $out) {
  $stmt = $db->prepare("UPDATE submissions SET status = :status WHERE id = :id");
  $stmt->execute([":status" => $status, ":id" => $id]);
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

function process_submission($db, $row) {
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
          $stmt = $db->prepare("UPDATE submissions SET status = 'PASSED' WHERE id = :id");
          $stmt->execute([":id" => $row['id']]);
          return;
      } else {
          $errors = $res['tool_messages']['errors'] ?? ($res['lean_messages']['errors'] ?? ["Unknown error"]);
          $msg = "Error: " . implode("\n", $errors);
          failStatus($db, $row['id'], "ERROR", $msg);
          echo "AXLE Failure: $msg\n";
          return;
      }
  }
  
  echo "AXLE API unavailable for submission #{$row['id']}.\n";
  failStatus($db, $row['id'], "SYSTEM ERROR", "AXLE API verification failed or timed out.");
}

echo "Worker started (AXLE Only Mode)...\n";

while (true) {
  $stmt = $db->query("SELECT s.*, p.template, p.dependencies FROM submissions s JOIN problems p ON s.problem = p.id WHERE s.status = 'PENDING' LIMIT 1");
  $row = $stmt->fetch();
  if ($row) {
    process_submission($db, $row);
  } else {
    sleep(2);
  }
}
