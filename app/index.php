<?php
include 'db.php';

// Получение всех записей
$stmt = $conn->prepare("SELECT * FROM records ORDER BY created_at DESC");
$stmt->execute();
$records = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Записи</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script>
        // Функция для обновления счётчика
        function updateRecordCount() {
            $.ajax({
                url: 'count_records.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    $('#record-count').text(data.count);
                }
            });
        }

        // Запуск обновления счётчика каждые 5 секунд
        $(document).ready(function() {
            updateRecordCount();
            setInterval(updateRecordCount, 5000);
        });
    </script>
</head>
<body>
    <h1>Список записей</h1>
    <p>Количество записей: <span id="record-count">0</span></p>

    <?php foreach ($records as $record): ?>
        <div class="record">
            <h2><?php echo htmlspecialchars($record['name']); ?></h2>
            <p><?php echo htmlspecialchars($record['description']); ?></p>
            <p>Добавлено: <?php echo $record['created_at']; ?></p>
            <form action="delete_record.php" method="POST" class="inline-form">
                <input type="hidden" name="id" value="<?php echo $record['id']; ?>">
                <button type="submit">Удалить</button>
            </form>
            <form action="edit_record.php" method="GET" class="inline-form">
                <input type="hidden" name="id" value="<?php echo $record['id']; ?>">
                <button type="submit">Редактировать</button>
            </form>
        </div>
        <hr>
    <?php endforeach; ?>

    <h2>Добавить новую запись</h2>
    <form action="add_record.php" method="POST">
        <label for="name">Имя:</label><br>
        <input type="text" id="name" name="name" required><br><br>

        <label for="description">Описание:</label><br>
        <textarea id="description" name="description" required></textarea><br><br>

        <button type="submit">Добавить запись</button>
    </form>
</body>
</html>