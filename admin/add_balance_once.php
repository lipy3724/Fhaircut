<?php
require_once __DIR__ . '/../db_config.php';

$username = isset($_GET['username']) ? $_GET['username'] : '';
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;

header('Content-Type: application/json');

if ($username === '' || $amount == 0) {
	echo json_encode(['error' => 'username and amount are required']);
	exit;
}

if ($amount < 0) {
	echo json_encode(['error' => 'amount must be positive']);
	exit;
}

if ($stmt = mysqli_prepare($conn, "UPDATE users SET balance = balance + ? WHERE username = ?")) {
	mysqli_stmt_bind_param($stmt, 'ds', $amount, $username);
	if (!mysqli_stmt_execute($stmt)) {
		echo json_encode(['error' => mysqli_stmt_error($stmt)]);
		exit;
	}
	$affected = mysqli_stmt_affected_rows($stmt);
	mysqli_stmt_close($stmt);

	$res = mysqli_query($conn, "SELECT id, username, balance FROM users WHERE username='" . mysqli_real_escape_string($conn, $username) . "' LIMIT 1");
	$user = mysqli_fetch_assoc($res);
	echo json_encode(['affected_rows' => $affected, 'user' => $user], JSON_PRETTY_PRINT);
	exit;
} else {
	echo json_encode(['error' => mysqli_error($conn)]);
	exit;
}
?> 