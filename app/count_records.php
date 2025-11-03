<?php
// count_records.php
include 'db.php';

// Подсчитываем количество записей в таблице
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM records");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

// Возвращаем результат в формате JSON
echo json_encode($result);
?>