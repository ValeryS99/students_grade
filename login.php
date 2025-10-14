<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $errors = [];

    if (empty($login)) {
        $errors[] = "Введите логин";
    }

    if (empty($password)) {
        $errors[] = "Введите пароль";
    }

    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        header('Location: login.php');
        exit();
    }

    $login = htmlspecialchars($login, ENT_QUOTES, 'UTF-8');

    try {
        $host = 'localhost';
        $port = '5432';
        $dbname = 'название_вашей_бд';
        $username = 'postgres';
        $password_db = 'ваш_пароль';

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $database = new PDO($dsn, $username, $password_db);
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $database->prepare("SELECT id, name, surname, patronymic, login, password, role FROM users WHERE login = :login");
        $stmt->bindValue(':login', $login, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_surname'] = $user['surname'];
            $_SESSION['user_patronymic'] = $user['patronymic'];
            $_SESSION['user_login'] = $user['login'];
            $_SESSION['user_role'] = $user['role'];
            
            // Redirect based on role
            if ($user['role'] === 'admin') {
                header('Location: admin_dashboard.php');
            } else {
                header('Location: student_dashboard.php');
            }
            exit();
        } else {
            $_SESSION['form_errors'] = ["Неверный логин или пароль"];
            header('Location: login.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['form_errors'] = ["Ошибка базы данных: " . $e->getMessage()];
        header('Location: login.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <title>Система учёта оценок - Вход</title>
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <h1>Вход в систему</h1>
                <?php if (!empty($_SESSION['form_errors'])): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($_SESSION['form_errors'] as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php unset($_SESSION['form_errors']); ?>
                <?php endif; ?>

                <form method="POST" class="d-flex flex-column">
                    <div>
                        <div class="mb-2">
                            <label for="login">Логин:</label>
                            <input type="text" id="login" name="login" placeholder="Введите логин" class="w-100 form-control" required>
                        </div>
                        <div class="mb-2">
                            <label for="password">Пароль:</label>
                            <input type="password" id="password" name="password" placeholder="Введите пароль" class="w-100 form-control" required>
                        </div>
                        <input type="submit" value="Войти" class="w-100 btn btn-primary">
                    </div>
                </form>
                <div class="mt-3 text-center">
                    <p>Нет аккаунта? <a href="index.php" class="btn btn-link">Зарегистрироваться</a></p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
