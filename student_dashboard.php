<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

$stmt = $conn->prepare("SELECT full_name, username, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$full_name = $user["full_name"];
$username = $user["username"];

$role = $user["role"];

/* Make sure the activity table and submission columns exist. */
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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_activity"])) {
    $activity_id = intval($_POST["activity_id"]);

    $check = $conn->prepare("
        SELECT id, activity_name
        FROM activities
        WHERE id = ? AND student_id = ?
    ");
    $check->bind_param("ii", $activity_id, $user_id);
    $check->execute();
    $activity_result = $check->get_result();
    $activity_data = $activity_result->fetch_assoc();
    $check->close();

    if ($activity_data && isset($_FILES["student_file"]) && $_FILES["student_file"]["error"] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . "/uploads/activities/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $original_name = basename($_FILES["student_file"]["name"]);
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed_extensions = ["pdf", "doc", "docx", "ppt", "pptx", "xls", "xlsx", "jpg", "jpeg", "png", "zip"];

        if (in_array($extension, $allowed_extensions, true)) {
            $safe_name = "student_" . $user_id . "_activity_" . $activity_id . "_" . time() . "." . $extension;
            $target_file = $upload_dir . $safe_name;

            if (move_uploaded_file($_FILES["student_file"]["tmp_name"], $target_file)) {
                $relative_file = "uploads/activities/" . $safe_name;

                $stmt = $conn->prepare("
                    UPDATE activities
                    SET student_file = ?, submission_status = 'Submitted', submitted_at = NOW()
                    WHERE id = ? AND student_id = ?
                ");
                $stmt->bind_param("sii", $relative_file, $activity_id, $user_id);
                $stmt->execute();
                $stmt->close();

                $log_stmt = $conn->prepare("
                    INSERT INTO activity_logs (user_id, username, role, action)
                    VALUES (?, ?, ?, ?)
                ");
                $log_action = "Submitted Activity: " . $activity_data["activity_name"];
                $log_stmt->bind_param("isss", $user_id, $username, $role, $log_action);
                $log_stmt->execute();
                $log_stmt->close();
            }
        }
    }

    header("Location: student_dashboard.php#activities");
    exit;
}

$subjects = $conn->query(
    "SELECT s.subject_name
     FROM student_subjects ss
     JOIN subjects s ON ss.subject_id = s.id
     WHERE ss.student_id = $user_id
     ORDER BY s.subject_name"
);

$teachers = $conn->query(
    "SELECT DISTINCT u.full_name
     FROM teacher_students ts
     JOIN users u ON ts.teacher_id = u.id
     WHERE ts.student_id = $user_id
     ORDER BY u.full_name"
);

$assignments = $conn->query(
    "SELECT s.subject_name, a.assignment_name, a.due_date
     FROM assignments a
     JOIN subjects s ON a.subject_id = s.id
     WHERE a.student_id = $user_id
     ORDER BY a.due_date ASC"
);

$activities = $conn->query(
    "SELECT a.id, s.subject_name, a.activity_name, a.activity_date,
            a.teacher_file, a.student_file, a.submission_status, a.submitted_at
     FROM activities a
     JOIN subjects s ON a.subject_id = s.id
     WHERE a.student_id = $user_id
     ORDER BY a.activity_date DESC"
);

$grades = $conn->query(
    "SELECT s.subject_name,
            sp.activity_score,
            sp.quiz_score,
            sp.performance_score
     FROM student_performance sp
     JOIN subjects s ON sp.subject_id = s.id
     WHERE sp.student_id = $user_id
     ORDER BY s.subject_name"
);

$attendance = $conn->query(
    "SELECT s.subject_name,
            at.attendance_date,
            at.status
     FROM attendance at
     JOIN subjects s ON at.subject_id = s.id
     WHERE at.student_id = $user_id
     ORDER BY at.attendance_date DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="student_dashboard.css">
</head>

<body>

<div class="container">

    <aside class="sidebar">

        <div class="profile">
            <img src="defaultpfp.jpg" alt="Profile">
            <h3><?php echo htmlspecialchars($full_name); ?></h3>
            <p><?php echo htmlspecialchars($username); ?></p>
        </div>

        <nav class="menu">
            <a href="#dashboard" class="active">Dashboard</a>
            <a href="#attendance-overview">Attendance Overview</a>
            <a href="#attendance">Attendance</a>
            <a href="#school-work">School Work</a>
            <a href="#assignments">Assignments</a>
            <a href="#activities">Activities</a>
            <a href="#grades">Grades</a>
            <a href="#subjects">My Subjects</a>
            <a href="#teachers">My Teachers</a>
            <a href="#notifications">Notifications</a>
            <a href="#settings">Settings</a>
            <a href="logout.php" class="logout">Logout</a>
        </nav>

    </aside>

    <main class="main-content">

        <section id="dashboard" class="welcome">
            <h1>Welcome, <?php echo htmlspecialchars($full_name); ?>!</h1>
            <p>Welcome to your student dashboard.</p>
        </section>


        <section id="attendance-overview" class="attendance-box">

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

        <section id="subjects" class="section">

            <h2>My Subjects</h2>

            <div class="cards">

                <?php if ($subjects && $subjects->num_rows > 0): ?>

                    <?php while ($subject = $subjects->fetch_assoc()): ?>

                        <div class="card">
                            <h3><?php echo htmlspecialchars($subject["subject_name"]); ?></h3>
                            <p>Assigned Subject</p>
                        </div>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="card">
                        <h3>No Subjects</h3>
                        <p>No subjects have been assigned yet.</p>
                    </div>

                <?php endif; ?>

            </div>

        </section>

        <section id="teachers" class="section">

            <h2>My Teachers</h2>

            <div class="cards">

                <?php if ($teachers && $teachers->num_rows > 0): ?>

                    <?php while ($teacher = $teachers->fetch_assoc()): ?>

                        <div class="card">
                            <h3><?php echo htmlspecialchars($teacher["full_name"]); ?></h3>
                            <p>Assigned Teacher</p>
                        </div>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="card">
                        <h3>No Teacher</h3>
                        <p>No teacher has been assigned yet.</p>
                    </div>

                <?php endif; ?>

            </div>

        </section>

        <section id="attendance" class="section">

            <h2>Attendance</h2>

            <div class="attendance-box">

                <div class="attendance-title">
                    <h2>Attendance Records</h2>
                </div>

                <table>

                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($attendance && $attendance->num_rows > 0): ?>

                            <?php while ($row = $attendance->fetch_assoc()): ?>

                                <tr>
                                    <td><?php echo htmlspecialchars($row["subject_name"]); ?></td>
                                    <td><?php echo date("F d, Y", strtotime($row["attendance_date"])); ?></td>
                                    <td><?php echo htmlspecialchars($row["status"]); ?></td>
                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="3">No attendance records yet.</td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

        <section id="school-work" class="section">

            <h2>School Work</h2>

            <div class="work">

                <h3>Assignments</h3>
                <p>View your assignments and submission status.</p>

                <button type="button" onclick="document.getElementById('assignments').scrollIntoView({behavior:'smooth'})">
                    View Assignments
                </button>

            </div>

            <div class="work">

                <h3>Activities</h3>
                <p>View your school activities and status.</p>

                <button type="button" onclick="document.getElementById('activities').scrollIntoView({behavior:'smooth'})">
                    View Activities
                </button>

            </div>

            <div class="work">

                <h3>Grades</h3>
                <p>View your grades and academic results.</p>

                <button type="button" onclick="document.getElementById('grades').scrollIntoView({behavior:'smooth'})">
                    View Grades
                </button>

            </div>

        </section>

        <section id="assignments" class="section">

            <h2>My Assignments</h2>

            <table>

                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Assignment</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($assignments && $assignments->num_rows > 0): ?>

                        <?php while ($row = $assignments->fetch_assoc()): ?>

                            <tr>
                                <td><?php echo htmlspecialchars($row["subject_name"]); ?></td>
                                <td><?php echo htmlspecialchars($row["assignment_name"]); ?></td>
                                <td><?php echo date("F d, Y", strtotime($row["due_date"])); ?></td>
                                <td>Pending</td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="4">No assignments yet.</td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </section>

        <section id="activities" class="section">

            <h2>My Activities</h2>

            <table>

                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Activity</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($activities && $activities->num_rows > 0): ?>

                        <?php while ($row = $activities->fetch_assoc()): ?>

                            <tr>
                                <td><?php echo htmlspecialchars($row["subject_name"]); ?></td>
                                <td><?php echo htmlspecialchars($row["activity_name"]); ?></td>
                                <td><?php echo date("F d, Y", strtotime($row["activity_date"])); ?></td>
                                <td><?php echo htmlspecialchars($row["submission_status"] ?: "Pending"); ?></td>
                                <td>
                                    <?php if ($row["teacher_file"]): ?>
                                        <a href="<?php echo htmlspecialchars($row["teacher_file"]); ?>" target="_blank">View Activity File</a>
                                    <?php endif; ?>

                                    <?php if ($row["submission_status"] === "Submitted"): ?>
                                        <span>Submitted</span>
                                    <?php else: ?>
                                        <button type="button"
                                            onclick="openActivityModal(<?php echo (int)$row["id"]; ?>, '<?php echo htmlspecialchars(addslashes($row["activity_name"]), ENT_QUOTES); ?>')">
                                            DO THE ACTIVITY
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5">No activities yet.</td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </section>

        <div id="activityModal" class="activity-modal">
            <div class="activity-sheet">
                <button type="button" class="close-modal" onclick="closeActivityModal()">×</button>

                <h2>Submit Activity</h2>
                <h3 id="modalActivityName"></h3>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="submit_activity" value="1">
                    <input type="hidden" name="activity_id" id="modalActivityId">

                    <label>Upload your file</label>
                    <input type="file" name="student_file" required
                        accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">

                    <button type="submit">UPLOAD FILE</button>
                </form>
            </div>
        </div>

        <section id="grades" class="section">

            <h2>My Grades</h2>

            <table>

                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Activity Score</th>
                        <th>Quiz Score</th>
                        <th>Performance Score</th>
                        <th>Overall Grade</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($grades && $grades->num_rows > 0): ?>

                        <?php while ($row = $grades->fetch_assoc()): ?>

                            <?php
                            $overall = (
                                $row["activity_score"] +
                                $row["quiz_score"] +
                                $row["performance_score"]
                            ) / 3;
                            ?>

                            <tr>
                                <td><?php echo htmlspecialchars($row["subject_name"]); ?></td>
                                <td><?php echo htmlspecialchars($row["activity_score"]); ?></td>
                                <td><?php echo htmlspecialchars($row["quiz_score"]); ?></td>
                                <td><?php echo htmlspecialchars($row["performance_score"]); ?></td>
                                <td><?php echo number_format($overall, 2); ?></td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5">No grades available yet.</td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </section>

        <section class="section">

            <h2>Student Information</h2>

            <div class="tables">

                <div class="table-box">

                    <h3>Personal Information</h3>

                    <table>

                        <tr>
                            <th>Name</th>
                            <td><?php echo htmlspecialchars($full_name); ?></td>
                        </tr>

                        <tr>
                            <th>Username</th>
                            <td><?php echo htmlspecialchars($username); ?></td>
                        </tr>

                        <tr>
                            <th>Role</th>
                            <td><?php echo ucfirst(htmlspecialchars($role)); ?></td>
                        </tr>

                    </table>

                </div>

                <div class="table-box">

                    <h3>Academic Information</h3>

                    <table>

                        <tr>
                            <th>Year Level</th>
                            <td>Student</td>
                        </tr>

                        <tr>
                            <th>Status</th>
                            <td>Active</td>
                        </tr>

                        <tr>
                            <th>School Year</th>
                            <td>2026-2027</td>
                        </tr>

                    </table>

                </div>

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

        <section id="settings" class="section">

            <h2>Settings</h2>

            <div class="setting-row">
                <span>Account</span>
                <button type="button">Manage</button>
            </div>

            <div class="setting-row">
                <span>Profile</span>
                <button type="button">Manage</button>
            </div>

            <div class="setting-row">
                <span>Security</span>
                <button type="button">Manage</button>
            </div>

        </section>

        <div class="bottom-space"></div>

        <footer>
            <p>WebPortfolio Student Dashboard</p>
        </footer>

    </main>

</div>

<style>
.activity-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.activity-sheet {
    width: 90%;
    max-width: 550px;
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-sizing: border-box;
    position: relative;
}

.activity-sheet h2 {
    margin-top: 0;
}

.activity-sheet h3 {
    margin-bottom: 25px;
}

.activity-sheet input[type="file"] {
    display: block;
    width: 100%;
    margin: 15px 0 25px;
    padding: 12px;
    border: 1px solid #ccc;
    box-sizing: border-box;
}

.activity-sheet button[type="submit"] {
    padding: 12px 20px;
    border: none;
    cursor: pointer;
}

.close-modal {
    position: absolute;
    right: 15px;
    top: 10px;
    border: none;
    background: none;
    font-size: 28px;
    cursor: pointer;
}
</style>

<script>
function openActivityModal(activityId, activityName) {
    document.getElementById("modalActivityId").value = activityId;
    document.getElementById("modalActivityName").textContent = activityName;
    document.getElementById("activityModal").style.display = "flex";
}

function closeActivityModal() {
    document.getElementById("activityModal").style.display = "none";
}

window.addEventListener("click", function(event) {
    const modal = document.getElementById("activityModal");

    if (event.target === modal) {
        closeActivityModal();
    }
});
</script>

</body>
</html>          