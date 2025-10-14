<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $patronymic = trim($_POST['patronymic'] ?? '');
    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? '');

    $errors = [];

    if (empty($name)) {
        $errors[] = "Введите имя";
    }

    if (empty($surname)) {
        $errors[] = "Введите фамилию";
    }

    if (empty($login)) {
        $errors[] = "Введите логин";
    }

    if (empty($password)) {
        $errors[] = "Введите пароль";
    }

    if (empty($role) || !in_array($role, ['admin', 'user'])) {
        $errors[] = "Выберите роль";
    }

    if (!empty($name) && !preg_match('/^[a-zA-Zа-яА-ЯёЁ]+$/u', $name)) {
        $errors[] = "Имя содержит недопустимые символы!";
    }

    if (!empty($surname) && !preg_match('/^[a-zA-Zа-яА-ЯёЁ]+$/u', $surname)) {
        $errors[] = "Фамилия содержит недопустимые символы!";
    }

    if (!empty($patronymic) && !preg_match('/^[a-zA-Zа-яА-ЯёЁ]+$/u', $patronymic)) {
        $errors[] = "Отчество содержит недопустимые символы!";
    }

    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        header('Location: index.php');
        exit();
    }

    //преобразования специальных символов в HTML-сущности
    //ENT_QUOTES - флаг, указывающий какие кавычки преобразовывать: Преобразует и двойные (") и одинарные (') кавычки
    $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $surname = htmlspecialchars($surname, ENT_QUOTES, 'UTF-8');
    $patronymic = htmlspecialchars($patronymic, ENT_QUOTES, 'UTF-8');
    $login = htmlspecialchars($login, ENT_QUOTES, 'UTF-8');

    try {
        //PDO - PHP Data Object
        //$database = new PDO('sqlite:database.sqlite');

        $host = 'localhost';
        $port = '5432';
        $dbname = 'название_вашей_бд';
        $username = 'postgres';
        $password_db = 'ваш_пароль';

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

        $database = new PDO($dsn, $username, $password_db);

        //PDO::ATTR_ERRMODE - атрибут, который мы настраиваем (режим ошибок)
        //PDO::ERRMODE_EXCEPTION - значение, которое мы устанавливаем (режим исключений)
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Создаем таблицу пользователей с ролями (синтаксис PostgreSQL)
        $database->exec("
            CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                name TEXT NOT NULL,
                surname TEXT NOT NULL,
                patronymic TEXT,
                login TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role TEXT NOT NULL CHECK (role IN ('admin', 'user')),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Создаем таблицу оценок
        $database->exec("
            CREATE TABLE IF NOT EXISTS grades (
                id SERIAL PRIMARY KEY,
                student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                teacher_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                subject TEXT NOT NULL,
                grade INTEGER NOT NULL CHECK (grade >= 1 AND grade <= 5),
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Создаем индексы для оптимизации запросов
        $database->exec("
            CREATE INDEX IF NOT EXISTS idx_grades_student_id ON grades(student_id);
        ");
        $database->exec("
            CREATE INDEX IF NOT EXISTS idx_grades_teacher_id ON grades(teacher_id);
        ");

        $stmt = $database->prepare("SELECT id FROM users WHERE login = :login");
        $stmt->bindValue(':login', $login, PDO::PARAM_STR);
        //Выполняет подготовленный SQL-запрос с привязанными параметрами.
        $stmt->execute();

        //Извлекает следующую строку из результирующего набора.
        if ($stmt->fetch()) {
            $_SESSION['form_errors'] = ["Пользователь с таким логином уже существует."];
            header('Location: index.php');
            exit();
        }

        //Создает безопасный хеш пароля используя самый современный и рекомендуемый алгоритм
        $password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $database->prepare("
            INSERT INTO users (name, surname, patronymic, login, password, role) 
            VALUES (:name, :surname, :patronymic, :login, :password, :role)
        ");

        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':surname', $surname, PDO::PARAM_STR);
        $stmt->bindValue(':patronymic', $patronymic, PDO::PARAM_STR);
        $stmt->bindValue(':login', $login, PDO::PARAM_STR);
        $stmt->bindValue(':password', $password, PDO::PARAM_STR);
        $stmt->bindValue(':role', $role, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $_SESSION['user_id'] = $database->lastInsertId();
            $_SESSION['user_name'] = $name;
            $_SESSION['user_surname'] = $surname;
            $_SESSION['user_patronymic'] = $patronymic;
            $_SESSION['user_login'] = $login;
            $_SESSION['user_role'] = $role;
            if ($role === 'admin') {
                header('Location: admin_dashboard.php');
                exit();
            } else {
                header('Location: student_dashboard.php');
                exit();
            }
        } else {
            $_SESSION['form_errors'] = ["Ошибка при регистрации. Попробуйте еще раз."];
            header('Location: index.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['form_errors'] = ["Ошибка базы данных: " . $e->getMessage()];
        header('Location: index.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <title>Система учёта оценок - Регистрация</title>
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
                <h1>Форма регистрации</h1>
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
                            <label for="name">Ваше имя:</label>
                            <input type="text" id="name" name="name" placeholder="Введите имя" class="w-100 form-control" required>
                        </div>
                        <div class="mb-2">
                            <label for="surname">Ваша фамилия:</label>
                            <input type="text" id="surname" name="surname" placeholder="Введите фамилию" class="w-100 form-control" required>
                        </div>
                        <div class="mb-2">
                            <label for="patronymic">Ваше отчество (необязательно):</label>
                            <input type="text" id="patronymic" name="patronymic" placeholder="Введите отчество" class="w-100 form-control">
                        </div>
                        <div class="mb-2">
                            <label for="login">Ваш логин:</label>
                            <input type="text" id="login" name="login" placeholder="Введите логин" class="w-100 form-control" required>
                        </div>
                        <div class="mb-2">
                            <label for="password">Ваш пароль:</label>
                            <input type="password" id="password" name="password" placeholder="Введите пароль" class="w-100 form-control" required>
                        </div>
                        <div class="mb-2">
                            <label for="role">Ваша роль:</label>
                            <select id="role" name="role" class="w-100 form-control" required>
                                <option value="">Выберите роль</option>
                                <option value="admin">Преподаватель (Администратор)</option>
                                <option value="user">Студент</option>
                            </select>
                        </div>
                        <input type="submit" value="Зарегистрироваться" class="w-100 btn btn-primary">
                    </div>
                </form>
                <div class="mt-3 text-center">
                    <p>Уже есть аккаунт? <a href="login.php" class="btn btn-link">Войти</a></p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>

</html>
