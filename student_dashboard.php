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
                    <h2>Attendance Overview</h2>

                    <div class="legend">
                        <span>
                            <span class="blue-dot"></span>
                            Present
                        </span>

                        <span>
                            <span class="purple-dot"></span>
                            Absent
                        </span>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="month">January</div>

                    <div class="chart-bars">
                        <div class="absence-bar january-absence"></div>
                        <div class="present-bar january-present"></div>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="month">February</div>

                    <div class="chart-bars">
                        <div class="absence-bar february-absence"></div>
                        <div class="present-bar february-present"></div>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="month">March</div>

                    <div class="chart-bars">
                        <div class="absence-bar march-absence"></div>
                        <div class="present-bar march-present"></div>
                    </div>
                </div>

            </div>

        </section>

        <section id="school-work" class="section">

            <h2>School Work</h2>

            <div class="work">

                <h3>Assignments</h3>
                <p>View your assignments and submission status.</p>

                <button type="button" onclick="document.getElementById('assignments').scrollIntoView()">
                    View Assignments
                </button>

            </div>

            <div class="work">

                <h3>Activities</h3>
                <p>View your school activities and status.</p>

                <button type="button" onclick="document.getElementById('activities').scrollIntoView()">
                    View Activities
                </button>

            </div>

            <div class="work">

                <h3>Grades</h3>
                <p>View your grades and academic results.</p>

                <button type="button" onclick="document.getElementById('grades').scrollIntoView()">
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

                    <tr>
                        <td>Front-End Development</td>
                        <td>Web Portfolio</td>
                        <td>September 20, 2026</td>
                        <td>Pending</td>
                    </tr>

                    <tr>
                        <td>Networking</td>
                        <td>VLSM Activity</td>
                        <td>September 22, 2026</td>
                        <td>Pending</td>
                    </tr>

                    <tr>
                        <td>Database Management</td>
                        <td>MySQL Activity</td>
                        <td>September 25, 2026</td>
                        <td>Pending</td>
                    </tr>

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
                    </tr>
                </thead>

                <tbody>

                    <tr>
                        <td>Front-End Development</td>
                        <td>HTML and CSS Activity</td>
                        <td>September 14, 2026</td>
                        <td>Completed</td>
                    </tr>

                    <tr>
                        <td>Networking</td>
                        <td>Subnetting Activity</td>
                        <td>September 15, 2026</td>
                        <td>Pending</td>
                    </tr>

                    <tr>
                        <td>Database Management</td>
                        <td>SQL Activity</td>
                        <td>September 17, 2026</td>
                        <td>Pending</td>
                    </tr>

                </tbody>

            </table>

        </section>

        <section id="grades" class="section">

            <h2>My Grades</h2>

            <table>

                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Prelim</th>
                        <th>Midterm</th>
                        <th>Final</th>
                        <th>Grade</th>
                    </tr>
                </thead>

                <tbody>

                    <tr>
                        <td>Front-End Development</td>
                        <td>90</td>
                        <td>92</td>
                        <td>--</td>
                        <td>91</td>
                    </tr>

                    <tr>
                        <td>Networking</td>
                        <td>88</td>
                        <td>90</td>
                        <td>--</td>
                        <td>89</td>
                    </tr>

                    <tr>
                        <td>Database Management</td>
                        <td>91</td>
                        <td>93</td>
                        <td>--</td>
                        <td>92</td>
                    </tr>

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

            <div class="notification">
                <h3>Account Approved</h3>
                <p>Your account has been approved by the administrator.</p>
            </div>

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

</body>
</html>