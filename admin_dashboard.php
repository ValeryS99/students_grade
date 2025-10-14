<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
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

    // Get all students
    $stmt = $database->query("SELECT id, name, surname, patronymic, login FROM users WHERE role = 'user' ORDER BY surname, name");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all grades with student and teacher names
    $stmt = $database->query("
        SELECT g.*, 
               s.name as student_name, s.surname as student_surname, s.patronymic as student_patronymic
        FROM grades g 
        JOIN users s ON g.student_id = s.id 
        WHERE g.teacher_id = :teacher_id 
        ORDER BY g.created_at DESC
    ");
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Handle grade operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_grade') {
        $student_id = (int)$_POST['student_id'];
        $subject = trim($_POST['subject']);
        $grade = (int)$_POST['grade'];
        $description = trim($_POST['description']);
        
        if ($student_id && $subject && $grade >= 1 && $grade <= 5) {
            try {
                $stmt = $database->prepare("
                    INSERT INTO grades (student_id, teacher_id, subject, grade, description) 
                    VALUES (:student_id, :teacher_id, :subject, :grade, :description)
                ");
                $stmt->bindValue(':student_id', $student_id, PDO::PARAM_INT);
                $stmt->bindValue(':teacher_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->bindValue(':subject', $subject, PDO::PARAM_STR);
                $stmt->bindValue(':grade', $grade, PDO::PARAM_INT);
                $stmt->bindValue(':description', $description, PDO::PARAM_STR);
                $stmt->execute();
                
                $_SESSION['success_message'] = "Оценка успешно добавлена";
                header('Location: admin_dashboard.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Ошибка при добавлении оценки: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_grade') {
        $grade_id = (int)$_POST['grade_id'];
        
        try {
            $stmt = $database->prepare("DELETE FROM grades WHERE id = :grade_id AND teacher_id = :teacher_id");
            $stmt->bindValue(':grade_id', $grade_id, PDO::PARAM_INT);
            $stmt->bindValue(':teacher_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Оценка удалена";
            header('Location: admin_dashboard.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Ошибка при удалении оценки: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <title>Панель преподавателя</title>
    <style>
        body {
            background-color: #f8f9fa;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Система учёта оценок</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">Добро пожаловать, <?= htmlspecialchars($_SESSION['user_surname'] ?? '') . ' ' . htmlspecialchars($_SESSION['user_name'] ?? '') . (!empty($_SESSION['user_patronymic']) ? ' ' . htmlspecialchars($_SESSION['user_patronymic']) : '') ?> (Преподаватель)</span>
                <a class="nav-link" href="logout.php">Выйти</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Добавить оценку</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_grade">
                            <div class="mb-3">
                                <label for="student_id" class="form-label">Студент:</label>
                                <select class="form-select" id="student_id" name="student_id" required>
                                    <option value="">Выберите студента</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?= $student['id'] ?>"><?php 
                                            $fullName = htmlspecialchars($student['surname'] ?? '') . ' ' . htmlspecialchars($student['name'] ?? '');
                                            if (!empty($student['patronymic'])) {
                                                $fullName .= ' ' . htmlspecialchars($student['patronymic']);
                                            }
                                            echo trim($fullName);
                                        ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="subject" class="form-label">Предмет:</label>
                                <input type="text" class="form-control" id="subject" name="subject" required>
                            </div>
                            <div class="mb-3">
                                <label for="grade" class="form-label">Оценка:</label>
                                <select class="form-select" id="grade" name="grade" required>
                                    <option value="">Выберите оценку</option>
                                    <option value="5">5 (Отлично)</option>
                                    <option value="4">4 (Хорошо)</option>
                                    <option value="3">3 (Удовлетворительно)</option>
                                    <option value="2">2 (Неудовлетворительно)</option>
                                    <option value="1">1 (Плохо)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Описание:</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Добавить оценку</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Мои оценки</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($grades)): ?>
                            <p class="text-muted">Оценок пока нет</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Студент</th>
                                            <th>Предмет</th>
                                            <th>Оценка</th>
                                            <th>Описание</th>
                                            <th>Дата</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grades as $grade): ?>
                                            <tr>
                                                <td><?php 
                                                    $studentFullName = htmlspecialchars($grade['student_surname'] ?? '') . ' ' . htmlspecialchars($grade['student_name'] ?? '');
                                                    if (!empty($grade['student_patronymic'])) {
                                                        $studentFullName .= ' ' . htmlspecialchars($grade['student_patronymic']);
                                                    }
                                                    echo trim($studentFullName);
                                                ?></td>
                                                <td><?= htmlspecialchars($grade['subject']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $grade['grade'] >= 4 ? 'success' : ($grade['grade'] >= 3 ? 'warning' : 'danger') ?>">
                                                        <?= $grade['grade'] ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($grade['description']) ?></td>
                                                <td><?= date('d.m.Y H:i', strtotime($grade['created_at'])) ?></td>
                                                <td>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить оценку?')">
                                                        <input type="hidden" name="action" value="delete_grade">
                                                        <input type="hidden" name="grade_id" value="<?= $grade['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">Удалить</button>
                                                    </form>
                                                </td>
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
