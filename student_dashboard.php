<?php
session_start();

// Check if user is logged in and is student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'user') {
    header('Location: login.php');
    exit();
}

try {
    $host = 'localhost';
    $port = '5432';
    $dbname = 'название_вашей_бд';
    $username = 'postgres';
    $password_db = 'ваш_пароль';

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $database = new PDO($dsn, $username, $password_db);
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get grades for current student only
    $stmt = $database->prepare("
        SELECT g.*, t.name as teacher_name, t.surname as teacher_surname, t.patronymic as teacher_patronymic
        FROM grades g 
        JOIN users t ON g.teacher_id = t.id 
        WHERE g.student_id = :student_id 
        ORDER BY g.created_at DESC
    ");
    $stmt->bindValue(':student_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get statistics
    $stmt = $database->prepare("
        SELECT 
            COUNT(*) as total_grades,
            AVG(grade) as average_grade,
            COUNT(CASE WHEN grade = 5 THEN 1 END) as excellent_count,
            COUNT(CASE WHEN grade = 4 THEN 1 END) as good_count,
            COUNT(CASE WHEN grade = 3 THEN 1 END) as satisfactory_count,
            COUNT(CASE WHEN grade <= 2 THEN 1 END) as poor_count
        FROM grades 
        WHERE student_id = :student_id
    ");
    $stmt->bindValue(':student_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <title>Панель студента</title>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Система учёта оценок</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">Добро пожаловать, <?= htmlspecialchars($_SESSION['user_surname'] ?? '') . ' ' . htmlspecialchars($_SESSION['user_name'] ?? '') . (!empty($_SESSION['user_patronymic']) ? ' ' . htmlspecialchars($_SESSION['user_patronymic']) : '') ?> (Студент)</span>
                <a class="nav-link" href="logout.php">Выйти</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-12">
                <h2>Мои оценки</h2>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Всего оценок</h5>
                        <h3><?= $stats['total_grades'] ?: 0 ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Средний балл</h5>
                        <h3><?= $stats['average_grade'] ? number_format($stats['average_grade'], 2) : '0.00' ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Отлично (5)</h5>
                        <h3><?= $stats['excellent_count'] ?: 0 ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Хорошо (4)</h5>
                        <h3><?= $stats['good_count'] ?: 0 ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-secondary text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Удовлетворительно (3)</h5>
                        <h3><?= $stats['satisfactory_count'] ?: 0 ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Плохо (1–2)</h5>
                        <h3><?= $stats['poor_count'] ?: 0 ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grades Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>История оценок</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($grades)): ?>
                            <div class="text-center text-muted py-4">
                                <h5>У вас пока нет оценок</h5>
                                <p>Оценки появятся здесь после того, как преподаватель их выставит</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Предмет</th>
                                            <th>Оценка</th>
                                            <th>Описание</th>
                                            <th>Преподаватель</th>
                                            <th>Дата</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grades as $grade): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($grade['subject']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $grade['grade'] >= 4 ? 'success' : ($grade['grade'] >= 3 ? 'warning' : 'danger') ?> fs-6">
                                                        <?= $grade['grade'] ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($grade['description']) ?></td>
                                                <td><?php 
                                                    $teacherFullName = htmlspecialchars($grade['teacher_surname'] ?? '') . ' ' . htmlspecialchars($grade['teacher_name'] ?? '');
                                                    if (!empty($grade['teacher_patronymic'])) {
                                                        $teacherFullName .= ' ' . htmlspecialchars($grade['teacher_patronymic']);
                                                    }
                                                    echo trim($teacherFullName);
                                                ?></td>
                                                <td><?= date('d.m.Y H:i', strtotime($grade['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
