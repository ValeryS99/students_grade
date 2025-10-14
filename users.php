<?php
session_start();
try {
    //$database = new PDO('sqlite:database.sqlite');
    $host = 'localhost';
    $port = '5432';
    $dbname = 'название_вашей_бд';
    $username = 'postgres';
    $password_db = 'ваш_пароль';

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

    $database = new PDO($dsn, $username, $password_db);

    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $database->query("SELECT * FROM users ORDER BY created_at DESC");
    //Извлекаем все строки результирующего набора и возвращает их в виде массива
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    die("Ошибка подключения к базе данных: " . $ex->getMessage());
}

if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <title>Пользователи</title>
    <style>
        body {
            background-color: #f8f9fa;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row d-flex">
            <table class="table table-primary">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ФИО</th>
                        <th>Логин</th>
                        <th>Роль</th>
                        <th>Дата регистрации</th>
                        <th>Действие</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php 
                                $fullName = htmlspecialchars($user['surname'] ?? '') . ' ' . htmlspecialchars($user['name'] ?? '');
                                if (!empty($user['patronymic'])) {
                                    $fullName .= ' ' . htmlspecialchars($user['patronymic']);
                                }
                                echo trim($fullName);
                            ?></td>
                            <td><?php echo htmlspecialchars($user['login']); ?></td>
                            <td>
                                <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                    <?= $user['role'] === 'admin' ? 'Преподаватель' : 'Студент' ?>
                                </span>
                            </td>
                            <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                            <td class="actions">
                                <a class="btn btn-warning" href="edit_user.php?id=<?php echo $user['id']; ?>">Редактировать</a>
                                <button class="btn btn-danger btn-sm"
                                    onclick="confirmDelete(<?php echo $user['id']; ?>)">
                                    Удалить
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="mt-3">
                <a class="btn btn-primary" href="index.php">Регистрация</a>
                <a class="btn btn-success" href="login.php">Вход</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a class="btn btn-info" href="<?= $_SESSION['user_role'] === 'admin' ? 'admin_dashboard.php' : 'student_dashboard.php' ?>">Моя панель</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script>
        function confirmDelete(userId, userName) {
            if (confirm('Вы уверены, что хотите удалить пользователя ?')) {
                window.location.href = 'delete_user.php?id=' + userId;
            }
        }
    </script>
</body>

</html>
