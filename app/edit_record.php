<?php
include 'db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Получение текущих данных записи
    $stmt = $conn->prepare("SELECT * FROM records WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $record = $stmt->fetch();

    if (!$record) {
        echo "Запись не найдена!";
        exit;
    }
}

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $description = $_POST['description'];

    // Обновление записи
    $stmt = $conn->prepare("UPDATE records SET name = :name, description = :description WHERE id = :id");
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    // Перенаправление на главную страницу
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать запись</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Редактировать запись</h1>

    <form action="edit_record.php" method="POST">
        <input type="hidden" name="id" value="<?php echo $record['id']; ?>">
        
        <label for="name">Имя:</label><br>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($record['name']); ?>" required><br><br>

        <label for="description">Описание:</label><br>
        <textarea id="description" name="description" required><?php echo htmlspecialchars($record['description']); ?></textarea><br><br>

        <button type="submit">Сохранить изменения</button>
    </form>
</body>
</html>
