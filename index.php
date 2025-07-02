<?php

// 資料庫連線設定
$host = 'localhost'; 
$db   = 'graduation';
$user = 'root';
$pass = '******';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

function write_log($message) {
    $filename = __DIR__ . "/log.txt";
    file_put_contents($filename, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    echo '資料庫連線錯誤'; exit;
}

// 後端驗證與入場標記
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data'])) {
    header('Content-Type: application/json; charset=utf-8');
    $qr_data = trim($_POST['qr_data']);
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $scan_time = date('Y-m-d H:i:s');

    if (empty($qr_data)) {
        write_log("[FAIL ] $scan_time | 空資料 | UA: $user_agent");
        echo json_encode([
            'success' => false, 
            'message' => '資料為空',
            'name' => '',
            'engname' => '',
            'class' => '',
            'class_number' => '',
            'checked_in_at' => '',
            'count' => '',
            'left' => ''
        ]);
        exit;
    }

    // 查詢票券
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE ticket_code = ?");
    $stmt->execute([$qr_data]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        write_log("[FAIL ] $scan_time | 無票券: $qr_data | UA: $user_agent");
        echo json_encode([
            'success' => false,
            'message' => '票券資料不存在！',
            'name' => '',
            'engname' => '',
            'class' => '',
            'class_number' => '',
            'checked_in_at' => '',
            'count' => '',
            'left' => ''
        ]);
        exit;
    }

    $checkin_count = (int)$ticket['checkin_count'];
    $name = $ticket['name'] ?? '';
    $engname = $ticket['engname'] ?? '';
    $class = $ticket['class'] ?? '';
    $class_number = $ticket['class_number'] ?? '';
    $checked_in_at = $ticket['last_checked_in_at'] ?? '';
    $left = 2 - $checkin_count;

    if ($checkin_count >= 2) { //大過2
        write_log("[LIMIT] $scan_time | $name | $qr_data | 已達上限($checkin_count) | UA: $user_agent");
        echo json_encode([
            'success' => false,
            'message' => '此票券已達入場次數上限！',
            'name' => $name,
            'engname' => $engname,
            'class' => $class,
            'class_number' => $class_number,
            'checked_in_at' => $checked_in_at,
            'count' => $checkin_count,
            'left' => 0
        ]);
        exit;
    }

    // 原子操作：加一入場次數
    $update = $pdo->prepare("
        UPDATE tickets
        SET checkin_count = checkin_count + 1,
            last_checked_in_at = NOW()
        WHERE ticket_code = ? AND checkin_count = ?
    ");
    $update->execute([$qr_data, $checkin_count]);

    if ($update->rowCount() === 1) {
        // 取得更新後資料
        $ticket_after = $pdo->prepare("SELECT name, engname, class, class_number, checkin_count, last_checked_in_at FROM tickets WHERE ticket_code = ?");
        $ticket_after->execute([$qr_data]);
        $row = $ticket_after->fetch();

        $left = 2 - (int)$row['checkin_count'];
        write_log("[PASS ] $scan_time | {$row['name']} | $qr_data | 第{$row['checkin_count']}次 | UA: $user_agent");
        echo json_encode([
            'success' => true,
            'message' => '驗證成功，第' . $row['checkin_count'] . '次入場，歡迎！',
            'name' => $row['name'],
            'engname' => $row['engname'],
            'class' => $row['class'],
            'class_number' => $row['class_number'],
            'checked_in_at' => $row['last_checked_in_at'],
            'count' => $row['checkin_count'],
            'left' => $left
        ]);
    } else {
        // 可能有競爭情況，重新查詢
        $ticket = $pdo->prepare("SELECT name, engname, class, class_number, checkin_count, last_checked_in_at FROM tickets WHERE ticket_code = ?");
        $ticket->execute([$qr_data]);
        $row = $ticket->fetch();

        $left = 2 - (int)$row['checkin_count'];
        write_log("[FAIL ] $scan_time | {$row['name']} | $qr_data | 入場失敗/競爭 | UA: $user_agent");
        echo json_encode([
            'success' => false,
            'message' => $row['checkin_count'] >= 2 ? '此票券已達入場次數上限！' : '入場失敗，請重試！',
            'name' => $row['name'],
            'engname' => $row['engname'],
            'class' => $row['class'],
            'class_number' => $row['class_number'],
            'checked_in_at' => $row['last_checked_in_at'],
            'count' => $row['checkin_count'],
            'left' => $left
        ]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>2025 RCPS Graduation Ticket System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 450px; margin: 2em auto; text-align: center;}
        #result { margin-top: 24px; font-size: 1.2em;}
        #reader { width: 100%; max-width: 320px; margin: 0 auto;}
        #manual-form {margin-top:2em; display:none;}
        #manual-form input {width:70%; font-size:1em;}
        #manual-form button {font-size:1em;}
        #show-manual {margin-top:1em;}
        .info-table {margin: 1em auto; border-collapse:collapse; width:90%;}
        .info-table th, .info-table td {border:1px solid #aaa; padding:4px 8px;}
        .info-table th {background: #f0f0f0;}
    </style>
</head>
<body>
    <h3><b>Raimondi College Primary Section</b></h3>
    <h3>2025 Graduation Ticket System</h3>
    <h4>QR Certification</h4>
    <div id="reader"></div>
    <div id="result"></div>
    <form id="manual-form">
        <label>Manual Entry QR Code：</label>
        <input type="text" name="qr_data" id="qr-manual" required autocomplete="off">
        <button type="submit">檢查</button>
    </form>
    <button id="show-manual">Manual InPut</button>
    <script>
    let scanning = true;
    function renderInfoTable(data) {
        return `
            <table class="info-table">
                <tr><th>中文姓名</th><td>${data.name || '-'}</td></tr>
                <tr><th>英文姓名</th><td>${data.engname || '-'}</td></tr>
                <tr><th>班別</th><td>${data.class || '-'}</td></tr>
                <tr><th>學號</th><td>${data.class_number || '-'}</td></tr>
            </table>
        `;
    }
    function onScanSuccess(decodedText, decodedResult) {
        if (!scanning) return;
        scanning = false;
        document.getElementById('result').innerHTML = '掃描到：' + decodedText + '<br>驗證中...';
        sendToBackend(decodedText);
    }

    const html5QrcodeScanner = new Html5QrcodeScanner(
        "reader", { fps: 10, qrbox: { width: 250, height: 250 } }, false);
    html5QrcodeScanner.render(onScanSuccess);

    function sendToBackend(qrData) {
        fetch('', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: new URLSearchParams({ qr_data: qrData })
        })
        .then(response => response.json())
        .then(data => {
            let html = renderInfoTable(data);
            if (data.success) {
                html += `<span style="color:green">${data.message}<br>入場時間：${data.checked_in_at || '-'}<br>剩餘可入場次數：${typeof data.left === "undefined" ? '-' : data.left}</span>`;
            } else {
                html += `<span style="color:red">${data.message}<br>${data.checked_in_at ? '最後入場時間：' + data.checked_in_at + '<br>' : ''}剩餘可入場次數：${typeof data.left === "undefined" ? '-' : data.left}</span>`;
            }
            document.getElementById('result').innerHTML = html;
            // 3秒後自動恢復掃描
            setTimeout(() => {
                scanning = true;
                document.getElementById('result').innerHTML = '';
            }, 3000);
        })
        .catch(() => {
            document.getElementById('result').innerHTML = '伺服器錯誤，請稍後再試';
            setTimeout(() => {
                scanning = true;
                document.getElementById('result').innerHTML = '';
            }, 3000);
        });
    }

    // 手動輸入流程
    document.getElementById('show-manual').onclick = function() {
        document.getElementById('manual-form').style.display = 'block';
        this.style.display = 'none';
        html5QrcodeScanner.clear();
    }
    document.getElementById('manual-form').onsubmit = function(e) {
        e.preventDefault();
        let value = document.getElementById('qr-manual').value.trim();
        if (value) {
            document.getElementById('result').innerHTML = '驗證中...';
            sendToBackend(value);
        }
    }
    </script>
</body>
</html>