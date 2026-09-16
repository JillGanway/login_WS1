<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}


$admin_username = $_SESSION["admin_username"];

/* Make sure activity submission columns exist. */
$conn->query("
CREATE TABLE IF NOT EXISTS activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    activity_name VARCHAR(255) NOT NULL,
    activity_date DATE NOT NULL,
    teacher_file VARCHAR(255) NULL,
    student_file VARCHAR(255) NULL,
    submission_status VARCHAR(30) NOT NULL DEFAULT 'Not Submitted',
    submitted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$activity_columns = [
    "teacher_file" => "VARCHAR(255) NULL",
    "student_file" => "VARCHAR(255) NULL",
    "submission_status" => "VARCHAR(30) NOT NULL DEFAULT 'Not Submitted'",
    "submitted_at" => "DATETIME NULL"
];

foreach ($activity_columns as $column_name => $column_definition) {
    $column_check = $conn->query("SHOW COLUMNS FROM activities LIKE '$column_name'");
    if ($column_check && $column_check->num_rows === 0) {
        $conn->query("ALTER TABLE activities ADD COLUMN $column_name $column_definition");
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "approve" || $action === "reject") {

        $user_id = intval($_POST["user_id"]);

        $stmt = $conn->prepare(
            "SELECT username, role FROM users WHERE id = ? AND is_admin = 0"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user) {

            $status = $action === "approve" ? "approved" : "rejected";
            $log_action = $action === "approve" ? "Account Approved" : "Account Rejected";

            $stmt = $conn->prepare(
                "UPDATE users SET status = ? WHERE id = ? AND is_admin = 0"
            );
            $stmt->bind_param("si", $status, $user_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare(
                "INSERT INTO activity_logs (user_id, username, role, action)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "isss",
                $user_id,
                $user["username"],
                $user["role"],
                $log_action
            );
            $stmt->execute();
            $stmt->close();

            $title = $action === "approve" ? "Account Approved" : "Account Rejected";
            $message = $user["username"] . " has been " . $status . " by " . $admin_username;
            $type = $action === "approve" ? "approval" : "rejection";

            $stmt = $conn->prepare(
                "INSERT INTO notifications (title, message, type)
                 VALUES (?, ?, ?)"
            );
            $stmt->bind_param("sss", $title, $message, $type);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === "assign_teacher_student") {

        $teacher_id = intval($_POST["teacher_id"]);
        $student_id = intval($_POST["student_id"]);

        $stmt = $conn->prepare(
            "INSERT IGNORE INTO teacher_students (teacher_id, student_id)
             VALUES (?, ?)"
        );
        $stmt->bind_param("ii", $teacher_id, $student_id);
        $stmt->execute();
        $stmt->close();
    }

    if ($action === "assign_teacher_subject") {

        $teacher_id = intval($_POST["teacher_id"]);
        $subject_id = intval($_POST["subject_id"]);

        $stmt = $conn->prepare(
            "INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id)
             VALUES (?, ?)"
        );
        $stmt->bind_param("ii", $teacher_id, $subject_id);
        $stmt->execute();
        $stmt->close();
    }

    if ($action === "assign_student_subject") {

        $student_id = intval($_POST["student_id"]);
        $subject_id = intval($_POST["subject_id"]);

        $stmt = $conn->prepare(
            "INSERT IGNORE INTO student_subjects (student_id, subject_id)
             VALUES (?, ?)"
        );
        $stmt->bind_param("ii", $student_id, $subject_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: admin_dashboard.php");
    exit;
}

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM users
     WHERE role = 'student' AND is_admin = 0 AND status = 'approved'"
);
$student_count = $result->fetch_assoc()["total"];

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM users
     WHERE role = 'teacher' AND is_admin = 0 AND status = 'approved'"
);
$teacher_count = $result->fetch_assoc()["total"];

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM users
     WHERE is_admin = 0 AND status = 'pending'"
);
$pending_count = $result->fetch_assoc()["total"];

$pending_users = $conn->query(
    "SELECT id, full_name, username, role
     FROM users
     WHERE is_admin = 0 AND status = 'pending'
     ORDER BY id DESC"
);

$teachers = $conn->query(
    "SELECT id, full_name, username
     FROM users
     WHERE role = 'teacher' AND is_admin = 0 AND status = 'approved'
     ORDER BY full_name"
);

$students = $conn->query(
    "SELECT id, full_name, username
     FROM users
     WHERE role = 'student' AND is_admin = 0 AND status = 'approved'
     ORDER BY full_name"
);

$subjects = $conn->query(
    "SELECT id, subject_name
     FROM subjects
     ORDER BY subject_name"
);

$notifications = $conn->query(
    "SELECT title, message, created_at
     FROM notifications
     ORDER BY created_at DESC
     LIMIT 20"
);

$history = $conn->query(
    "SELECT username, role, action, date_time
     FROM activity_logs
     ORDER BY date_time DESC
     LIMIT 50"
);

$teacher_students = $conn->query(
    "SELECT t.full_name AS teacher_name, s.full_name AS student_name
     FROM teacher_students ts
     JOIN users t ON ts.teacher_id = t.id
     JOIN users s ON ts.student_id = s.id
     ORDER BY t.full_name, s.full_name"
);

$teacher_subjects = $conn->query(
    "SELECT u.full_name AS teacher_name, sub.subject_name
     FROM teacher_subjects ts
     JOIN users u ON ts.teacher_id = u.id
     JOIN subjects sub ON ts.subject_id = sub.id
     ORDER BY u.full_name, sub.subject_name"
);

$student_subjects = $conn->query(
    "SELECT u.full_name AS student_name, sub.subject_name
     FROM student_subjects ss
     JOIN users u ON ss.student_id = u.id
     JOIN subjects sub ON ss.subject_id = sub.id
     ORDER BY u.full_name, sub.subject_name"
);

$teacher_activities = $conn->query(
    "SELECT a.activity_name, a.activity_date,
            t.full_name AS teacher_name,
            s.full_name AS student_name,
            sub.subject_name,
            a.teacher_file,
            a.student_file,
            a.submission_status,
            a.submitted_at
     FROM activities a
     JOIN users t ON a.teacher_id = t.id
     JOIN users s ON a.student_id = s.id
     JOIN subjects sub ON a.subject_id = sub.id
     ORDER BY a.activity_date DESC, a.id DESC"
);

$student_performance = $conn->query(
    "SELECT u.full_name AS student_name,
            sub.subject_name,
            sp.activity_score,
            sp.quiz_score,
            sp.performance_score
     FROM student_performance sp
     JOIN users u ON sp.student_id = u.id
     JOIN subjects sub ON sp.subject_id = sub.id
     ORDER BY u.full_name, sub.subject_name"
);

$all_attendance = $conn->query(
    "SELECT s.full_name AS student_name,
            t.full_name AS teacher_name,
            sub.subject_name,
            at.attendance_date,
            at.status
     FROM attendance at
     JOIN users s ON at.student_id = s.id
     JOIN users t ON at.teacher_id = t.id
     JOIN subjects sub ON at.subject_id = sub.id
     ORDER BY at.attendance_date DESC, at.id DESC"
);

$present_count = 0;
$absent_count = 0;
$late_count = 0;

$count_attendance = $conn->query(
    "SELECT status, COUNT(*) AS total
     FROM attendance
     GROUP BY status"
);

if ($count_attendance) {
    while ($count = $count_attendance->fetch_assoc()) {
        if (strtolower($count["status"]) === "present") {
            $present_count = $count["total"];
        } elseif (strtolower($count["status"]) === "absent") {
            $absent_count = $count["total"];
        } elseif (strtolower($count["status"]) === "late") {
            $late_count = $count["total"];
        }
    }
}

$teacher_monitor = $conn->query(
    "SELECT u.full_name AS teacher_name,
            (SELECT COUNT(*) FROM attendance at WHERE at.teacher_id = u.id) AS attendance_count,
            (SELECT COUNT(*) FROM activities a WHERE a.teacher_id = u.id) AS activity_count,
            (SELECT COUNT(*) FROM assignments a WHERE a.teacher_id = u.id) AS assignment_count,
            (SELECT COUNT(*) FROM student_performance sp WHERE sp.teacher_id = u.id) AS grade_count
     FROM users u
     WHERE u.role = 'teacher' AND u.is_admin = 0 AND u.status = 'approved'
     ORDER BY u.full_name"
);


$all_assignments = $conn->query(
    "SELECT a.assignment_name, a.due_date,
            t.full_name AS teacher_name,
            s.full_name AS student_name,
            sub.subject_name
     FROM assignments a
     JOIN users t ON a.teacher_id = t.id
     JOIN users s ON a.student_id = s.id
     JOIN subjects sub ON a.subject_id = sub.id
     ORDER BY a.due_date ASC, a.id DESC"
);

$attendance_chart_data = [];

$attendance_chart_query = $conn->query("
    SELECT MONTH(attendance_date) AS month_number,
           SUM(CASE WHEN LOWER(status) = 'absent' THEN 1 ELSE 0 END) AS absence,
           SUM(CASE WHEN LOWER(status) = 'present' THEN 1 ELSE 0 END) AS present
    FROM attendance
    GROUP BY MONTH(attendance_date)
    ORDER BY MONTH(attendance_date)
");

$month_names = [
    1 => "Jan", 2 => "Feb", 3 => "Mar", 4 => "Apr",
    5 => "May", 6 => "Jun", 7 => "Jul", 8 => "Aug",
    9 => "Sep", 10 => "Oct", 11 => "Nov", 12 => "Dec"
];

if ($attendance_chart_query) {
    while ($row = $attendance_chart_query->fetch_assoc()) {
        $attendance_chart_data[(int)$row["month_number"]] = [
            "absence" => (int)$row["absence"],
            "present" => (int)$row["present"]
        ];
    }
}

$admin_chart_max = 0;

foreach (range(1, 12) as $month_number) {
    $absence = $attendance_chart_data[$month_number]["absence"] ?? 0;
    $present = $attendance_chart_data[$month_number]["present"] ?? 0;
    $admin_chart_max = max($admin_chart_max, $absence, $present);
}

$admin_attendance_chart = [];

foreach (range(1, 12) as $month_number) {
    $absence = $attendance_chart_data[$month_number]["absence"] ?? 0;
    $present = $attendance_chart_data[$month_number]["present"] ?? 0;

    $admin_attendance_chart[] = [
        "month" => $month_names[$month_number],
        "absence" => $absence,
        "present" => $present,
        "absence_width" => $admin_chart_max > 0 ? round(($absence / $admin_chart_max) * 100, 2) : 0,
        "present_width" => $admin_chart_max > 0 ? round(($present / $admin_chart_max) * 100, 2) : 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin_dashboard.css">
</head>

<body>

<div class="dashboard">

    <aside class="sidebar">

        <div class="profile">
            <img src="defaultpfp.jpg" alt="Admin Profile">
            <h3>ADMIN</h3>
            <p><?php echo htmlspecialchars($admin_username); ?></p>
        </div>

        <nav class="menu">
            <a href="#dashboard" class="active">Dashboard</a>
            <a href="#courses">Courses</a>
            <a href="#students">Students</a>
            <a href="#teachers">Teachers</a>
            <a href="#assignments">Assignments</a>
            <a href="#teacher-work">Teacher Work</a>
            <a href="#activities-monitor">Activities</a>
            <a href="#performance">Student Performance</a>
            <a href="#monitor">User Monitor</a>
            <a href="#attendance-overview">Attendance Overview</a>
            <a href="#attendance">Attendance</a>
            <a href="#notifications">Notifications</a>
            <a href="#settings">Settings</a>
            <a href="logout.php" class="logout">Logout</a>
        </nav>

    </aside>

    <main class="main-content">

        <section id="dashboard" class="welcome">
            <h1>Welcome, <?php echo htmlspecialchars($admin_username); ?>!</h1>
            <p>This is the administrator dashboard.</p>
        </section>

        <section id="attendance-overview" class="attendance-box">

            <div class="attendance-title">
                <h2>ATTENDANCE OVERVIEW</h2>
            </div>

            <div class="legend">
                <span><span class="blue-dot"></span>ABSENCE</span>
                <span><span class="purple-dot"></span>PRESENT</span>
            </div>

            <?php foreach ($admin_attendance_chart as $chart): ?>
                <div class="chart-row">
                    <span class="month"><?php echo htmlspecialchars($chart["month"]); ?></span>

                    <div class="chart-bars">
                        <div class="absence-bar"
                             style="width: <?php echo $chart["absence_width"]; ?>%;"
                             title="Absence: <?php echo $chart["absence"]; ?>">
                        </div>

                        <div class="present-bar"
                             style="width: <?php echo $chart["present_width"]; ?>%;"
                             title="Present: <?php echo $chart["present"]; ?>">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        </section>

        <section class="section">

            <h2>System Overview</h2>

            <div class="cards">

                <div class="card">
                    <h3>Students</h3>
                    <strong><?php echo $student_count; ?></strong>
                    <p>Approved Students</p>
                </div>

                <div class="card">
                    <h3>Teachers</h3>
                    <strong><?php echo $teacher_count; ?></strong>
                    <p>School Employees</p>
                </div>

                <div class="card">
                    <h3>Pending</h3>
                    <strong><?php echo $pending_count; ?></strong>
                    <p>Account Requests</p>
                </div>

            </div>

        </section>

        <section class="section">

            <h2>Pending Account Requests</h2>

            <table>

                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($pending_users && $pending_users->num_rows > 0): ?>

                    <?php while ($user = $pending_users->fetch_assoc()): ?>

                        <tr>
                            <td><?php echo htmlspecialchars($user["full_name"]); ?></td>
                            <td><?php echo htmlspecialchars($user["username"]); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($user["role"])); ?></td>
                            <td>Pending</td>

                            <td>

                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user["id"]; ?>">
                                    <button type="submit" name="action" value="approve">Approve</button>
                                </form>

                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user["id"]; ?>">
                                    <button type="submit" name="action" value="reject">Reject</button>
                                </form>

                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5">No pending account requests.</td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </section>

        <section id="assignments" class="section">

            <h2>Assign Students and Subjects</h2>

            <div class="work">

                <h3>Assign Students to Teacher</h3>

                <form method="POST">

                    <input type="hidden" name="action" value="assign_teacher_student">

                    <select name="teacher_id" required>
                        <option value="">Select Teacher</option>

                        <?php
                        $teachers->data_seek(0);
                        while ($teacher = $teachers->fetch_assoc()):
                        ?>

                            <option value="<?php echo $teacher["id"]; ?>">
                                <?php echo htmlspecialchars($teacher["full_name"]); ?>
                            </option>

                        <?php endwhile; ?>

                    </select>

                    <select name="student_id" required>
                        <option value="">Select Student</option>

                        <?php
                        $students->data_seek(0);
                        while ($student = $students->fetch_assoc()):
                        ?>

                            <option value="<?php echo $student["id"]; ?>">
                                <?php echo htmlspecialchars($student["full_name"]); ?>
                            </option>

                        <?php endwhile; ?>

                    </select>

                    <button type="submit">Assign Student</button>

                </form>

            </div>

            <div class="work">

                <h3>Assign Subject to Teacher</h3>

                <form method="POST">

                    <input type="hidden" name="action" value="assign_teacher_subject">

                    <select name="teacher_id" required>
                        <option value="">Select Teacher</option>

                        <?php
                        $teachers->data_seek(0);
                        while ($teacher = $teachers->fetch_assoc()):
                        ?>

                            <option value="<?php echo $teacher["id"]; ?>">
                                <?php echo htmlspecialchars($teacher["full_name"]); ?>
                            </option>

                        <?php endwhile; ?>

                    </select>

                    <select name="subject_id" required>
                        <option value="">Select Subject</option>

                        <?php
                        $subjects->data_seek(0);
                        while ($subject = $subjects->fetch_assoc()):
                        ?>

                            <option value="<?php echo $subject["id"]; ?>">
                                <?php echo htmlspecialchars($subject["subject_name"]); ?>
                            </option>

                        <?php endwhile; ?>

                    </select>

                    <button type="submit">Assign Subject</button>

                </form>

            </div>

            <div class="work">

                <h3>Assign Subject to Student</h3>

                <form method="POST">

                    <input type="hidden" name="action" value="assign_student_subject">

                    <select name="student_id" required>
                        <option value="">Select Student</option>

                        <?php
                        $students->data_seek(0);
                        while ($student = $students->fetch_assoc()):
                        ?>

                            <option value="<?php echo $student["id"]; ?>">
                                <?php echo htmlspecialchars($student["full_name"]); ?>
                            </option>

                        <?php endwhile; ?>

                    </select>

                    <select name="subject_id" required>
                        <option value="">Select Subject</option>

                        <?php
                        $subjects->data_seek(0);
                        while ($subject = $subjects->fetch_assoc()):
                        ?>

                            <option value="<?php echo $subject["id"]; ?>">
                                <?php echo htmlspecialchars($subject["subject_name"]); ?>
                            </option>

                        <?php endwhile; ?>

                    </select>

                    <button type="submit">Assign Subject</button>

                </form>

            </div>

        </section>

        <section class="section">

            <h2>Teacher Assignments</h2>

            <table>

                <thead>
                    <tr>
                        <th>Assignment</th>
                        <th>Teacher</th>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Due Date</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($all_assignments && $all_assignments->num_rows > 0): ?>

                    <?php while ($row = $all_assignments->fetch_assoc()): ?>

                        <tr>
                            <td><?php echo htmlspecialchars($row["assignment_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["teacher_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["student_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["subject_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["due_date"]); ?></td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5">No assignments created yet.</td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </section>

        <section class="section">

            <h2>Teacher Assigned Students</h2>

            <table>

                <thead>
                    <tr>
                        <th>Teacher</th>
                        <th>Student</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($teacher_students && $teacher_students->num_rows > 0): ?>

                    <?php while ($row = $teacher_students->fetch_assoc()): ?>

                        <tr>
                            <td><?php echo htmlspecialchars($row["teacher_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["student_name"]); ?></td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="2">No students assigned yet.</td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </section>

        <section class="section">

            <h2>Teacher Subjects</h2>

            <table>

                <thead>
                    <tr>
                        <th>Teacher</th>
                        <th>Subject</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($teacher_subjects && $teacher_subjects->num_rows > 0): ?>

                    <?php while ($row = $teacher_subjects->fetch_assoc()): ?>

                        <tr>
                            <td><?php echo htmlspecialchars($row["teacher_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["subject_name"]); ?></td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="2">No subjects assigned yet.</td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </section>

        <section id="students" class="section">

            <h2>Student Subjects</h2>

            <table>

                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Subject</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($student_subjects && $student_subjects->num_rows > 0): ?>

                    <?php while ($row = $student_subjects->fetch_assoc()): ?>

                        <tr>
                            <td><?php echo htmlspecialchars($row["student_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["subject_name"]); ?></td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="2">No subjects assigned yet.</td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </section>

        <section id="teacher-work" class="section">

            <h2>Teacher Work Monitor</h2>

            <table>

                <thead>
                    <tr>
                        <th>Teacher</th>
                        <th>Attendance Taken</th>
                        <th>Activities</th>
                        <th>Assignments</th>
                        <th>Grades Updated</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($teacher_monitor && $teacher_monitor->num_rows > 0): ?>

                    <?php while ($teacher = $teacher_monitor->fetch_assoc()): ?>

                        <tr>
                            <td><?php echo htmlspecialchars($teacher["teacher_name"]); ?></td>
                            <td><?php echo $teacher["attendance_count"]; ?></td>
                            <td><?php echo $teacher["activity_count"]; ?></td>
                            <td><?php echo $teacher["assignment_count"]; ?></td>
                            <td><?php echo $teacher["grade_count"]; ?></td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5">No teacher records yet.</td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </section>

        <section id="activities-monitor" class="section">

            <h2>Teacher Activities and Student Submissions</h2>

            <table>

                <thead>
                    <tr>
                        <th>Activity</th>
                        <th>Teacher</th>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Date</th>
                        <th>Teacher File</th>
                        <th>Student Status</th>
                        <th>Student File</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($teacher_activities && $teacher_activities->num_rows > 0): ?>

                    <?php while ($row = $teacher_activities->fetch_assoc()): ?>

                        <tr>
                            <td><?php echo htmlspecialchars($row["activity_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["teacher_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["student_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["subject_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["activity_date"]); ?></td>

                            <td>
                                <?php if (!empty($row["teacher_file"])): ?>
                                    <a href="<?php echo htmlspecialchars($row["teacher_file"]); ?>" target="_blank">View File</a>
                                <?php else: ?>
                                    None
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row["submission_status"] ?: "Not Submitted"); ?>
                            </td>

                            <td>
                                <?php if (!empty($row["student_file"])): ?>
                                    <a href="<?php echo htmlspecialchars($row["student_file"]); ?>" target="_blank">View Submission</a>
                                <?php else: ?>
                                    None
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8">No activities uploaded yet.</td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </section>

        <section id="performance" class="section">

            <h2>Student Performance</h2>

            <table>

                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Activity Score</th>
                        <th>Quiz Score</th>
                        <th>Performance Score</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($student_performance && $student_performance->num_rows > 0): ?>

                    <?php while ($row = $student_performance->fetch_assoc()): ?>

                        <tr>
                            <td><?php echo htmlspecialchars($row["student_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["subject_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["activity_score"]); ?></td>
                            <td><?php echo htmlspecialchars($row["quiz_score"]); ?></td>
                            <td><?php echo htmlspecialchars($row["performance_score"]); ?></td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5">No student performance records yet.</td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </section>

        <section id="monitor" class="section">

            <h2>User Activity Monitor</h2>

            <table>

                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>

                <tbody>

                <?php
                $activity = $conn->query(
                    "SELECT username, role, action, date_time
                     FROM activity_logs
                     WHERE role != 'admin'
                     ORDER BY date_time DESC
                     LIMIT 30"
                );
                ?>

                <?php if ($activity && $activity->num_rows > 0): ?>

                    <?php while ($log = $activity->fetch_assoc()): ?>

                        <tr>
                            <td><?php echo htmlspecialchars($log["username"]); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($log["role"])); ?></td>
                            <td><?php echo htmlspecialchars($log["action"]); ?></td>
                            <td><?php echo htmlspecialchars($log["date_time"]); ?></td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="4">No user activity yet.</td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </section>

        <section id="attendance" class="section">

            <h2>Attendance Monitor</h2>

            <div class="cards">

                <div class="card">
                    <h3>Present</h3>
                    <strong><?php echo $present_count; ?></strong>
                    <p>Students Present</p>
                </div>

                <div class="card">
                    <h3>Absent</h3>
                    <strong><?php echo $absent_count; ?></strong>
                    <p>Students Absent</p>
                </div>

                <div class="card">
                    <h3>Late</h3>
                    <strong><?php echo $late_count; ?></strong>
                    <p>Late Students</p>
                </div>

            </div>

            <table>

                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Teacher</th>
                        <th>Subject</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($all_attendance && $all_attendance->num_rows > 0): ?>

                    <?php while ($row = $all_attendance->fetch_assoc()): ?>

                        <tr>
                            <td><?php echo htmlspecialchars($row["student_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["teacher_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["subject_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["attendance_date"]); ?></td>
                            <td><?php echo htmlspecialchars($row["status"]); ?></td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5">No attendance records yet.</td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </section>

        <section id="courses" class="section">

            <h2>Courses</h2>

            <div class="course-list">

                <?php
                $course_result = $conn->query(
                    "SELECT subject_name FROM subjects ORDER BY subject_name"
                );
                ?>

                <?php while ($course = $course_result->fetch_assoc()): ?>

                    <div class="course">
                        <h3><?php echo htmlspecialchars($course["subject_name"]); ?></h3>
                    </div>

                <?php endwhile; ?>

            </div>

        </section>

        <section id="notifications" class="section">

            <h2>Notifications</h2>

            <?php if ($notifications && $notifications->num_rows > 0): ?>

                <?php while ($notification = $notifications->fetch_assoc()): ?>

                    <div class="notification">
                        <h3><?php echo htmlspecialchars($notification["title"]); ?></h3>
                        <p><?php echo htmlspecialchars($notification["message"]); ?></p>
                        <small><?php echo htmlspecialchars($notification["created_at"]); ?></small>
                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="notification">
                    <h3>No notifications</h3>
                    <p>No new notifications.</p>
                </div>

            <?php endif; ?>

        </section>

        <section class="section">

            <h2>Login & Logout History</h2>

            <table>

                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Activity</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($history && $history->num_rows > 0): ?>

                    <?php while ($log = $history->fetch_assoc()): ?>

                        <tr>
                            <td><?php echo htmlspecialchars($log["username"]); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($log["role"])); ?></td>
                            <td><?php echo htmlspecialchars($log["action"]); ?></td>
                            <td><?php echo htmlspecialchars($log["date_time"]); ?></td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="4">No history yet.</td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </section>

        <section id="settings" class="section">

            <h2>Settings</h2>

            <div class="settings">

                <div class="setting-row">
                    <span>Admin Account</span>
                    <button type="button">Manage</button>
                </div>

                <div class="setting-row">
                    <span>System Settings</span>
                    <button type="button">Manage</button>
                </div>

                <div class="setting-row">
                    <span>Security</span>
                    <button type="button">Manage</button>
                </div>

            </div>

        </section>

        <footer>
            <p>WebPortfolio Admin Dashboard</p>
        </footer>

    </main>

</div>

</body>
</html>