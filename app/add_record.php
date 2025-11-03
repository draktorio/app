<?php
ob_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];

    try {
        $stmt = $conn->prepare("INSERT INTO records (name, description, created_at) VALUES (:name, :description, NOW())");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->execute();

        // Перенаправление после добавления записи
        header("Location: index.php");
        exit;
        ob_end_flush();
    } catch (PDOException $e) {
        // Закомментируйте следующую строку для проверки:
        // echo "Ошибка добавления записи: " . $e->getMessage();
    }
}
