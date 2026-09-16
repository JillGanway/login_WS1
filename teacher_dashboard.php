<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$teacher_id = $_SESSION["user_id"];

$stmt = $conn->prepare("SELECT full_name, username, role FROM users WHERE id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();
$stmt->close();

if (!$teacher || $teacher["role"] !== "teacher") {
    header("Location: login.php");
    exit;
}

$teacher_name = $teacher["full_name"];
$username = $teacher["username"];

function logActivity($conn, $user_id, $username, $role, $action) {
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, username, role, action)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isss", $user_id, $username, $role, $action);
    $stmt->execute();
    $stmt->close();
}

$conn->query("
CREATE TABLE IF NOT EXISTS activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    activity_name VARCHAR(255) NOT NULL,
    activity_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");


/* Make sure activity submission columns exist. */
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

$conn->query(
"CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    assignment_name VARCHAR(255) NOT NULL,
    due_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("
CREATE TABLE IF NOT EXISTS student_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    activity_score DECIMAL(5,2) DEFAULT 0,
    quiz_score DECIMAL(5,2) DEFAULT 0,
    performance_score DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_performance (teacher_id, student_id, subject_id)
)");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "add_activity") {

        $student_id = intval($_POST["student_id"]);
        $subject_id = intval($_POST["subject_id"]);
        $activity_name = trim($_POST["activity_name"]);
        $activity_date = $_POST["activity_date"];
        $teacher_file = "";

        $check = $conn->prepare("
            SELECT id
            FROM teacher_students
            WHERE teacher_id = ? AND student_id = ?
        ");
        $check->bind_param("ii", $teacher_id, $student_id);
        $check->execute();
        $student_check = $check->get_result()->num_rows;
        $check->close();

        $check = $conn->prepare("
            SELECT id
            FROM teacher_subjects
            WHERE teacher_id = ? AND subject_id = ?
        ");
        $check->bind_param("ii", $teacher_id, $subject_id);
        $check->execute();
        $subject_check = $check->get_result()->num_rows;
        $check->close();

        if ($student_check > 0 && $subject_check > 0 && $activity_name !== "") {

            if (isset($_FILES["teacher_file"]) && $_FILES["teacher_file"]["error"] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . "/uploads/activities/";

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $original_name = basename($_FILES["teacher_file"]["name"]);
                $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                $allowed_extensions = [
                    "pdf", "doc", "docx", "ppt", "pptx",
                    "xls", "xlsx", "jpg", "jpeg", "png", "zip"
                ];

                if (in_array($extension, $allowed_extensions, true)) {
                    $safe_name = "teacher_" . $teacher_id . "_activity_" . time() . "_" . uniqid() . "." . $extension;
                    $target_file = $upload_dir . $safe_name;

                    if (move_uploaded_file($_FILES["teacher_file"]["tmp_name"], $target_file)) {
                        $teacher_file = "uploads/activities/" . $safe_name;
                    }
                }
            }

            $stmt = $conn->prepare("
                INSERT INTO activities
                (teacher_id, student_id, subject_id, activity_name, activity_date, teacher_file)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "iiisss",
                $teacher_id,
                $student_id,
                $subject_id,
                $activity_name,
                $activity_date,
                $teacher_file
            );

            if ($stmt->execute()) {
                logActivity($conn, $teacher_id, $username, "teacher", "Add Activity");
            }
            $stmt->close();
        }

        header("Location: teacher_dashboard.php#school-work");
        exit;
    }

    if ($action === "create_assignment") {

        $student_id = intval($_POST["student_id"]);
        $subject_id = intval($_POST["subject_id"]);
        $assignment_name = trim($_POST["assignment_name"]);
        $due_date = $_POST["due_date"];

        $check = $conn->prepare("
            SELECT id
            FROM teacher_students
            WHERE teacher_id = ? AND student_id = ?
        ");
        $check->bind_param("ii", $teacher_id, $student_id);
        $check->execute();
        $student_check = $check->get_result()->num_rows;
        $check->close();

        $check = $conn->prepare("
            SELECT id
            FROM teacher_subjects
            WHERE teacher_id = ? AND subject_id = ?
        ");
        $check->bind_param("ii", $teacher_id, $subject_id);
        $check->execute();
        $subject_check = $check->get_result()->num_rows;
        $check->close();

        if ($student_check > 0 && $subject_check > 0 && $assignment_name !== "") {

            $stmt = $conn->prepare("
                INSERT INTO assignments
                (teacher_id, student_id, subject_id, assignment_name, due_date)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "iiiss",
                $teacher_id,
                $student_id,
                $subject_id,
                $assignment_name,
                $due_date
            );

            if ($stmt->execute()) {
                logActivity($conn, $teacher_id, $username, "teacher", "Create Assignment");
            }
            $stmt->close();
        }

        header("Location: teacher_dashboard.php#assignments");
        exit;
    }

    if ($action === "record_attendance") {

        $student_id = intval($_POST["student_id"]);
        $subject_id = intval($_POST["subject_id"]);
        $attendance_date = $_POST["attendance_date"];
        $status = $_POST["status"];

        $check = $conn->prepare("
            SELECT id
            FROM teacher_students
            WHERE teacher_id = ? AND student_id = ?
        ");
        $check->bind_param("ii", $teacher_id, $student_id);
        $check->execute();
        $student_check = $check->get_result()->num_rows;
        $check->close();

        $check = $conn->prepare("
            SELECT id
            FROM teacher_subjects
            WHERE teacher_id = ? AND subject_id = ?
        ");
        $check->bind_param("ii", $teacher_id, $subject_id);
        $check->execute();
        $subject_check = $check->get_result()->num_rows;
        $check->close();

        if ($student_check > 0 && $subject_check > 0) {

            $check_attendance = $conn->prepare("
                SELECT id FROM attendance
                WHERE teacher_id = ? AND student_id = ? AND subject_id = ? AND attendance_date = ?
                LIMIT 1
            ");
            $check_attendance->bind_param("iiis", $teacher_id, $student_id, $subject_id, $attendance_date);
            $check_attendance->execute();
            $existing_attendance = $check_attendance->get_result()->fetch_assoc();
            $check_attendance->close();

            if ($existing_attendance) {
                $stmt = $conn->prepare("UPDATE attendance SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $status, $existing_attendance["id"]);
                if ($stmt->execute()) {
                    logActivity($conn, $teacher_id, $username, "teacher", "Update Attendance");
                }
                $stmt->close();
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO attendance
                    (teacher_id, student_id, subject_id, attendance_date, status)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("iiiss", $teacher_id, $student_id, $subject_id, $attendance_date, $status);
                if ($stmt->execute()) {
                    logActivity($conn, $teacher_id, $username, "teacher", "Record Attendance");
                }
                $stmt->close();
            }
        }

        header("Location: teacher_dashboard.php#attendance");
        exit;
    }

    if ($action === "save_performance") {

        $student_id = intval($_POST["student_id"]);
        $subject_id = intval($_POST["subject_id"]);
        $activity_score = floatval($_POST["activity_score"]);
        $quiz_score = floatval($_POST["quiz_score"]);
        $performance_score = floatval($_POST["performance_score"]);

        $check = $conn->prepare("
            SELECT id
            FROM teacher_students
            WHERE teacher_id = ? AND student_id = ?
        ");
        $check->bind_param("ii", $teacher_id, $student_id);
        $check->execute();
        $student_check = $check->get_result()->num_rows;
        $check->close();

        $check = $conn->prepare("
            SELECT id
            FROM teacher_subjects
            WHERE teacher_id = ? AND subject_id = ?
        ");
        $check->bind_param("ii", $teacher_id, $subject_id);
        $check->execute();
        $subject_check = $check->get_result()->num_rows;
        $check->close();

        if ($student_check > 0 && $subject_check > 0) {

            $stmt = $conn->prepare("
                INSERT INTO student_performance
                (teacher_id, student_id, subject_id, activity_score, quiz_score, performance_score)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                activity_score = VALUES(activity_score),
                quiz_score = VALUES(quiz_score),
                performance_score = VALUES(performance_score)
            ");

            $stmt->bind_param(
                "iiiddd",
                $teacher_id,
                $student_id,
                $subject_id,
                $activity_score,
                $quiz_score,
                $performance_score
            );

            $stmt->execute();
            $stmt->close();
        }

        header("Location: teacher_dashboard.php#performance");
        exit;
    }
}

$students = $conn->query("
    SELECT u.id, u.full_name, u.username
    FROM teacher_students ts
    JOIN users u ON ts.student_id = u.id
    WHERE ts.teacher_id = $teacher_id
    ORDER BY u.full_name
");

$subjects = $conn->query("
    SELECT s.id, s.subject_name
    FROM teacher_subjects ts
    JOIN subjects s ON ts.subject_id = s.id
    WHERE ts.teacher_id = $teacher_id
    ORDER BY s.subject_name
");

$student_list = [];
while ($row = $students->fetch_assoc()) {
    $student_list[] = $row;
}

$subject_list = [];
while ($row = $subjects->fetch_assoc()) {
    $subject_list[] = $row;
}

$activities = $conn->query("
    SELECT a.activity_name, a.activity_date,
           u.full_name AS student_name,
           s.subject_name
    FROM activities a
    JOIN users u ON a.student_id = u.id
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.teacher_id = $teacher_id
    ORDER BY a.activity_date DESC
");

$assignments = $conn->query("
    SELECT a.assignment_name, a.due_date,
           u.full_name AS student_name,
           s.subject_name
    FROM assignments a
    JOIN users u ON a.student_id = u.id
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.teacher_id = $teacher_id
    ORDER BY a.due_date ASC
");

$attendance_chart_data = [];

$attendance_chart_query = $conn->query("
    SELECT MONTH(attendance_date) AS month_number,
           SUM(CASE WHEN LOWER(status) = 'absent' THEN 1 ELSE 0 END) AS absence,
           SUM(CASE WHEN LOWER(status) = 'present' THEN 1 ELSE 0 END) AS present
    FROM attendance
    WHERE teacher_id = $teacher_id
    GROUP BY MONTH(attendance_date)
    ORDER BY MONTH(attendance_date)
");

$month_names = [1 => "Jan", 2 => "Feb", 3 => "Mar", 4 => "Apr", 5 => "May", 6 => "Jun", 7 => "Jul", 8 => "Aug", 9 => "Sep", 10 => "Oct", 11 => "Nov", 12 => "Dec"];

if ($attendance_chart_query) {
    while ($row = $attendance_chart_query->fetch_assoc()) {
        $attendance_chart_data[(int)$row["month_number"]] = [
            "absence" => (int)$row["absence"],
            "present" => (int)$row["present"]
        ];
    }
}

$chart_months = range(1, 12);
$chart_max = 0;

foreach ($chart_months as $month_number) {
    $absence = $attendance_chart_data[$month_number]["absence"] ?? 0;
    $present = $attendance_chart_data[$month_number]["present"] ?? 0;
    $chart_max = max($chart_max, $absence, $present);
}

$attendance_chart = [];
foreach ($chart_months as $month_number) {
    $absence = $attendance_chart_data[$month_number]["absence"] ?? 0;
    $present = $attendance_chart_data[$month_number]["present"] ?? 0;
    $attendance_chart[] = [
        "month" => $month_names[$month_number],
        "absence" => $absence,
        "present" => $present,
        "absence_width" => $chart_max > 0 ? round(($absence / $chart_max) * 100, 2) : 0,
        "present_width" => $chart_max > 0 ? round(($present / $chart_max) * 100, 2) : 0
    ];
}

$attendance_records = $conn->query("
    SELECT a.attendance_date, a.status,
           u.full_name AS student_name,
           s.subject_name
    FROM attendance a
    JOIN users u ON a.student_id = u.id
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.teacher_id = $teacher_id
    ORDER BY a.attendance_date DESC
");

$performance_records = $conn->query("
    SELECT p.activity_score,
           p.quiz_score,
           p.performance_score,
           u.full_name AS student_name,
           s.subject_name
    FROM student_performance p
    JOIN users u ON p.student_id = u.id
    JOIN subjects s ON p.subject_id = s.id
    WHERE p.teacher_id = $teacher_id
    ORDER BY u.full_name
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="teacher_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
</head>

<body>

<div class="container">

    <div class="sidebar">

        <div class="profile">

            <img src="defaultpfp.jpg" alt="Teacher Profile">

            <h3>
                <?php echo htmlspecialchars($teacher_name); ?>
            </h3>

            <p>TEACHER</p>

        </div>

        <div class="menu">

            <a href="#dashboard" class="active">DASHBOARD</a>
            <a href="#students">MY STUDENTS</a>
            <a href="#performance">PERFORMANCE</a>
            <a href="#grades">GRADES</a>
            <a href="#attendance">ATTENDANCE</a>
            <a href="#school-work">SCHOOL WORK</a>
            <a href="#assignments">ASSIGNMENTS</a>
            <a href="#notifications">NOTIFICATIONS</a>
            <a href="#settings">SETTINGS</a>
            <a href="logout.php">LOGOUT</a>

        </div>

    </div>

    <div class="main-content">

        <div id="dashboard" class="welcome">

            <h1>
                WELCOME BACK,
                <?php echo htmlspecialchars($teacher_name); ?>!
            </h1>

        </div>

        <div class="attendance-box">

            <div class="attendance-title">
                <h2>ATTENDANCE OVERVIEW</h2>
            </div>

            <div class="legend">
                <span><span class="blue-dot"></span>ABSENCE</span>
                <span><span class="purple-dot"></span>PRESENT</span>
            </div>

            <?php foreach ($attendance_chart as $chart): ?>
                <div class="chart-row">
                    <span class="month"><?php echo htmlspecialchars($chart["month"]); ?></span>
                    <div class="chart-bars">
                        <div class="absence-bar" style="width: <?php echo $chart["absence_width"]; ?>%;" title="Absence: <?php echo $chart["absence"]; ?>"></div>
                        <div class="present-bar" style="width: <?php echo $chart["present_width"]; ?>%;" title="Present: <?php echo $chart["present"]; ?>"></div>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>

        <div id="students" class="tables">

            <div class="table-box">

                <h3>MY STUDENTS</h3>

                <table>

                    <tr>
                        <th>STUDENT NAME</th>
                        <th>USERNAME</th>
                        <th>STATUS</th>
                    </tr>

                    <?php if (count($student_list) > 0): ?>

                        <?php foreach ($student_list as $student): ?>

                            <tr>
                                <td><?php echo htmlspecialchars($student["full_name"]); ?></td>
                                <td><?php echo htmlspecialchars($student["username"]); ?></td>
                                <td>ACTIVE</td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="3">No students assigned.</td>
                        </tr>

                    <?php endif; ?>

                </table>

            </div>

            <div class="table-box">

                <h3>MY SUBJECTS</h3>

                <table>

                    <tr>
                        <th>SUBJECT</th>
                    </tr>

                    <?php if (count($subject_list) > 0): ?>

                        <?php foreach ($subject_list as $subject): ?>

                            <tr>
                                <td><?php echo htmlspecialchars($subject["subject_name"]); ?></td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td>No subjects assigned.</td>
                        </tr>

                    <?php endif; ?>

                </table>

            </div>

        </div>

        <div id="performance" class="section">

            <h2>STUDENT PERFORMANCE</h2>

            <div class="work">

                <h3>Record Student Performance</h3>

                <form method="POST">

                    <input type="hidden" name="action" value="save_performance">

                    <select name="student_id" required>

                        <option value="">Select Student</option>

                        <?php foreach ($student_list as $student): ?>

                            <option value="<?php echo $student["id"]; ?>">
                                <?php echo htmlspecialchars($student["full_name"]); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <select name="subject_id" required>

                        <option value="">Select Subject</option>

                        <?php foreach ($subject_list as $subject): ?>

                            <option value="<?php echo $subject["id"]; ?>">
                                <?php echo htmlspecialchars($subject["subject_name"]); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <input type="number" name="activity_score" placeholder="Activity Score" min="0" max="100" step="0.01" required>

                    <input type="number" name="quiz_score" placeholder="Quiz Score" min="0" max="100" step="0.01" required>

                    <input type="number" name="performance_score" placeholder="Performance Score" min="0" max="100" step="0.01" required>

                    <button type="submit">SAVE PERFORMANCE</button>

                </form>

            </div>

            <table>

                <tr>
                    <th>STUDENT</th>
                    <th>SUBJECT</th>
                    <th>ACTIVITIES</th>
                    <th>QUIZZES</th>
                    <th>PERFORMANCE</th>
                </tr>

                <?php if ($performance_records && $performance_records->num_rows > 0): ?>

                    <?php while ($row = $performance_records->fetch_assoc()): ?>

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
                        <td colspan="5">No performance records yet.</td>
                    </tr>

                <?php endif; ?>

            </table>

        </div>

        <div id="grades" class="section">

            <h2>GRADES</h2>

            <table>

                <tr>
                    <th>STUDENT</th>
                    <th>SUBJECT</th>
                    <th>PRELIM</th>
                    <th>MIDTERM</th>
                    <th>FINAL</th>
                    <th>REMARKS</th>
                </tr>

                <?php foreach ($student_list as $student): ?>

                    <tr>
                        <td><?php echo htmlspecialchars($student["full_name"]); ?></td>
                        <td>-</td>
                        <td>-</td>
                        <td>-</td>
                        <td>-</td>
                        <td>-</td>
                    </tr>

                <?php endforeach; ?>

                <?php if (count($student_list) === 0): ?>

                    <tr>
                        <td colspan="6">No students assigned.</td>
                    </tr>

                <?php endif; ?>

            </table>

        </div>

        <div id="attendance" class="section">

            <h2>RECORD ATTENDANCE</h2>

            <div class="work">

                <h3>Record Student Attendance</h3>

                <form method="POST">

                    <input type="hidden" name="action" value="record_attendance">

                    <select name="student_id" required>

                        <option value="">Select Student</option>

                        <?php foreach ($student_list as $student): ?>

                            <option value="<?php echo $student["id"]; ?>">
                                <?php echo htmlspecialchars($student["full_name"]); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <select name="subject_id" required>

                        <option value="">Select Subject</option>

                        <?php foreach ($subject_list as $subject): ?>

                            <option value="<?php echo $subject["id"]; ?>">
                                <?php echo htmlspecialchars($subject["subject_name"]); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <input type="date" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" required>

                    <select name="status" required>

                        <option value="">Select Status</option>
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Late">Late</option>

                    </select>

                    <button type="submit">RECORD ATTENDANCE</button>

                </form>

            </div>

            <table>

                <tr>
                    <th>STUDENT</th>
                    <th>SUBJECT</th>
                    <th>DATE</th>
                    <th>STATUS</th>
                </tr>

                <?php if ($attendance_records && $attendance_records->num_rows > 0): ?>

                    <?php while ($row = $attendance_records->fetch_assoc()): ?>

                        <tr>
                            <td><?php echo htmlspecialchars($row["student_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["subject_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["attendance_date"]); ?></td>
                            <td><?php echo htmlspecialchars($row["status"]); ?></td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="4">No attendance records yet.</td>
                    </tr>

                <?php endif; ?>

            </table>

        </div>

        <div id="school-work" class="section">

            <h2>SCHOOL WORK</h2>

            <div class="work">

                <h3>Add Activity</h3>

                <p>Create and post an activity for your assigned students.</p>

                <form method="POST" enctype="multipart/form-data">

                    <input type="hidden" name="action" value="add_activity">

                    <select name="student_id" required>

                        <option value="">Select Student</option>

                        <?php foreach ($student_list as $student): ?>

                            <option value="<?php echo $student["id"]; ?>">
                                <?php echo htmlspecialchars($student["full_name"]); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <select name="subject_id" required>

                        <option value="">Select Subject</option>

                        <?php foreach ($subject_list as $subject): ?>

                            <option value="<?php echo $subject["id"]; ?>">
                                <?php echo htmlspecialchars($subject["subject_name"]); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <input type="text" name="activity_name" placeholder="Activity Name" required>

                    <input type="date" name="activity_date" value="<?php echo date('Y-m-d'); ?>" required>

                    <label>Upload Activity File</label>
                    <input type="file" name="teacher_file"
                           accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">

                    <button type="submit">ADD ACTIVITY</button>

                </form>

            </div>

            <div class="work">

                <h3>Current Activities</h3>

                <table>

                    <tr>
                        <th>ACTIVITY</th>
                        <th>STUDENT</th>
                        <th>SUBJECT</th>
                        <th>DATE</th>
                    </tr>

                    <?php if ($activities && $activities->num_rows > 0): ?>

                        <?php while ($row = $activities->fetch_assoc()): ?>

                            <tr>
                                <td><?php echo htmlspecialchars($row["activity_name"]); ?></td>
                                <td><?php echo htmlspecialchars($row["student_name"]); ?></td>
                                <td><?php echo htmlspecialchars($row["subject_name"]); ?></td>
                                <td><?php echo htmlspecialchars($row["activity_date"]); ?></td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="4">No activities have been added yet.</td>
                        </tr>

                    <?php endif; ?>

                </table>

            </div>

        </div>

        <div id="assignments" class="section">

            <h2>ASSIGNMENTS</h2>

            <div class="work">

                <h3>Create Assignment</h3>

                <p>Give an assignment to your assigned students.</p>

                <form method="POST">

                    <input type="hidden" name="action" value="create_assignment">

                    <select name="student_id" required>

                        <option value="">Select Student</option>

                        <?php foreach ($student_list as $student): ?>

                            <option value="<?php echo $student["id"]; ?>">
                                <?php echo htmlspecialchars($student["full_name"]); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <select name="subject_id" required>

                        <option value="">Select Subject</option>

                        <?php foreach ($subject_list as $subject): ?>

                            <option value="<?php echo $subject["id"]; ?>">
                                <?php echo htmlspecialchars($subject["subject_name"]); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <input type="text" name="assignment_name" placeholder="Assignment Name" required>

                    <input type="date" name="due_date" required>

                    <button type="submit">CREATE ASSIGNMENT</button>

                </form>

            </div>

            <div class="work">

                <h3>Assigned Work</h3>

                <table>

                    <tr>
                        <th>ASSIGNMENT</th>
                        <th>STUDENT</th>
                        <th>SUBJECT</th>
                        <th>DUE DATE</th>
                    </tr>

                    <?php if ($assignments && $assignments->num_rows > 0): ?>

                        <?php while ($row = $assignments->fetch_assoc()): ?>

                            <tr>
                                <td><?php echo htmlspecialchars($row["assignment_name"]); ?></td>
                                <td><?php echo htmlspecialchars($row["student_name"]); ?></td>
                                <td><?php echo htmlspecialchars($row["subject_name"]); ?></td>
                                <td><?php echo htmlspecialchars($row["due_date"]); ?></td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="4">No assignments have been created yet.</td>
                        </tr>

                    <?php endif; ?>

                </table>

            </div>

        </div>

        <div id="notifications" class="section">

            <h2>NOTIFICATIONS</h2>

            <div class="notification">

                <b>No new notifications</b>

                <p>
                    Notifications for your students and classes will appear here.
                </p>

            </div>

        </div>

        <div id="settings" class="section">

            <h2>SETTINGS</h2>

            <div class="setting-row">

                <span>Account Settings</span>

                <button type="button">
                    EDIT
                </button>

            </div>

            <div class="setting-row">

                <span>Dashboard Settings</span>

                <button type="button">
                    EDIT
                </button>

            </div>

        </div>

        <div class="bottom-space"></div>

    </div>

</div>

</body>
</html>