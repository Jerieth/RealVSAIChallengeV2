<?php
// Simple health check that always returns 200 OK
header('Content-Type: application/json');
http_response_code(200);

// Basic server info
$serverInfo = [
    'status' => 'healthy',
    'server' => 'PHP ' . PHP_VERSION,
    'time' => date('Y-m-d H:i:s')
];

// Add process information if available
if (file_exists('php_server.pid')) {
    $pid = trim(file_get_contents('php_server.pid'));
    if (is_numeric($pid)) {
        $serverInfo['server_pid'] = $pid;
        
        // Check if process exists
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("tasklist /FI \"PID eq $pid\" 2>NUL", $output, $result);
            $running = count($output) > 1;
        } else {
            exec("ps -p $pid", $output, $result);
            $running = ($result === 0);
        }
        
        $serverInfo['process_running'] = $running;
    }
}

// Add database status
try {
    if (file_exists('data/realvsai.db')) {
        $pdo = new PDO('sqlite:data/realvsai.db');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->query("PRAGMA integrity_check");
        $result = $stmt->fetchColumn();
        $serverInfo['database_status'] = ($result === 'ok') ? 'healthy' : 'integrity issues';
    } else {
        $serverInfo['database_status'] = 'missing';
    }
} catch (Exception $e) {
    $serverInfo['database_status'] = 'error: ' . $e->getMessage();
}

// Return JSON response
echo json_encode($serverInfo);