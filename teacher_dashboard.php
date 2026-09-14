<?php
session_start();

/* Check if teacher is logged in */
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once "db.php";

/* Get teacher name */
$teacher_name = $_SESSION["full_name"] ?? "Teacher";
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Teacher Dashboard</title>

    <link rel="stylesheet" href="teacher_dashboard.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap"
          rel="stylesheet">

</head>


<body>


<div class="container">


    <!-- =========================
         SIDEBAR
    ========================== -->

    <div class="sidebar">


        <!-- PROFILE -->

        <div class="profile">

            <img src="defaultpfp.jpg" alt="Teacher Profile">

            <h3>
                <?php echo htmlspecialchars($teacher_name); ?>
            </h3>

            <p>
                TEACHER
            </p>

        </div>



        <!-- MENU -->

        <div class="menu">

            <a href="teacher_dashboard.php" class="active">
                DASHBOARD
            </a>


            <a href="#">
                MY STUDENTS
            </a>


            <a href="#">
                PERFORMANCE
            </a>


            <a href="#">
                GRADES
            </a>


            <a href="#">
                ATTENDANCE
            </a>


            <a href="#">
                SCHOOL WORK
            </a>


            <a href="#">
                ASSIGNMENTS
            </a>


            <a href="#">
                NOTIFICATIONS
            </a>


            <a href="#">
                SETTINGS
            </a>


            <a href="logout.php">
                LOGOUT
            </a>

        </div>


    </div>



    <!-- =========================
         MAIN CONTENT
    ========================== -->

    <div class="main-content">


        <!-- =========================
             WELCOME
        ========================== -->

        <div class="welcome">

            <h1>

                WELCOME BACK,
                <?php echo htmlspecialchars($teacher_name); ?>!

            </h1>

        </div>



        <!-- =========================
             ATTENDANCE
        ========================== -->

        <div class="attendance-box">


            <div class="attendance-title">

                <h2>
                    ATTENDANCE OVERVIEW
                </h2>

            </div>


            <div class="legend">

                <span>

                    <span class="blue-dot"></span>

                    ABSENCE

                </span>


                <span>

                    <span class="purple-dot"></span>

                    PRESENT

                </span>

            </div>



            <!-- JANUARY -->

            <div class="chart-row">

                <span class="month">
                    Jan
                </span>


                <div class="chart-bars">

                    <div class="absence-bar january-absence">
                    </div>

                    <div class="present-bar january-present">
                    </div>

                </div>

            </div>



            <!-- FEBRUARY -->

            <div class="chart-row">

                <span class="month">
                    Feb
                </span>


                <div class="chart-bars">

                    <div class="absence-bar february-absence">
                    </div>

                    <div class="present-bar february-present">
                    </div>

                </div>

            </div>



            <!-- MARCH -->

            <div class="chart-row">

                <span class="month">
                    Mar
                </span>


                <div class="chart-bars">

                    <div class="absence-bar march-absence">
                    </div>

                    <div class="present-bar march-present">
                    </div>

                </div>

            </div>


        </div>



        <!-- =========================
             MY STUDENTS
        ========================== -->

        <div class="tables">


            <!-- STUDENTS -->

            <div class="table-box">

                <h3>
                    MY STUDENTS
                </h3>


                <table>

                    <tr>

                        <th>
                            STUDENT NAME
                        </th>

                        <th>
                            YEAR
                        </th>

                        <th>
                            SECTION
                        </th>

                        <th>
                            STATUS
                        </th>

                    </tr>


                    <tr>

                        <td></td>

                        <td></td>

                        <td></td>

                        <td>ACTIVE</td>

                    </tr>


                    <tr>

                        <td></td>

                        <td></td>

                        <td></td>

                        <td>ACTIVE</td>

                    </tr>


                    <tr>

                        <td></td>

                        <td></td>

                        <td></td>

                        <td>ACTIVE</td>

                    </tr>


                    <tr>

                        <td></td>

                        <td></td>

                        <td></td>

                        <td>ACTIVE</td>

                    </tr>

                </table>

            </div>



            <!-- PERFORMANCE -->

            <div class="table-box">

                <h3>
                    STUDENT PERFORMANCE
                </h3>


                <table>

                    <tr>

                        <th>
                            STUDENT
                        </th>

                        <th>
                            ACTIVITIES
                        </th>

                        <th>
                            QUIZZES
                        </th>

                        <th>
                            PERFORMANCE
                        </th>

                    </tr>


                    <tr>

                        <td></td>

                        <td></td>

                        <td></td>

                        <td></td>

                    </tr>


                    <tr>

                        <td></td>

                        <td></td>

                        <td></td>

                        <td></td>

                    </tr>


                    <tr>

                        <td></td>

                        <td></td>

                        <td></td>

                        <td></td>

                    </tr>


                    <tr>

                        <td></td>

                        <td></td>

                        <td></td>

                        <td></td>

                    </tr>

                </table>

            </div>


        </div>



        <!-- =========================
             GRADES
        ========================== -->

        <div class="section">

            <h2>
                GRADES
            </h2>


            <table>

                <tr>

                    <th>
                        STUDENT
                    </th>

                    <th>
                        PRELIM
                    </th>

                    <th>
                        MIDTERM
                    </th>

                    <th>
                        FINAL
                    </th>

                    <th>
                        REMARKS
                    </th>

                </tr>


                <tr>

                    <td></td>

                    <td></td>

                    <td></td>

                    <td></td>

                    <td></td>

                </tr>


                <tr>

                    <td></td>

                    <td></td>

                    <td></td>

                    <td></td>

                    <td></td>

                </tr>


                <tr>

                    <td></td>

                    <td></td>

                    <td></td>

                    <td></td>

                    <td></td>

                </tr>


                <tr>

                    <td></td>

                    <td></td>

                    <td></td>

                    <td></td>

                    <td></td>

                </tr>

            </table>

        </div>



        <!-- =========================
             ATTENDANCE RECORD
        ========================== -->

        <div class="section">

            <h2>
                ATTENDANCE RECORD
            </h2>


            <table>

                <tr>

                    <th>
                        STUDENT
                    </th>

                    <th>
                        PRESENT
                    </th>

                    <th>
                        ABSENT
                    </th>

                    <th>
                        LATE
                    </th>

                    <th>
                        TOTAL
                    </th>

                </tr>


                <tr>

                    <td></td>

                    <td></td>

                    <td></td>

                    <td></td>

                    <td></td>

                </tr>


                <tr>

                    <td></td>

                    <td></td>

                    <td></td>

                    <td></td>

                    <td></td>

                </tr>


                <tr>

                    <td></td>

                    <td></td>

                    <td></td>

                    <td></td>

                    <td></td>

                </tr>


            </table>

        </div>



        <!-- =========================
             SCHOOL WORK
        ========================== -->

        <div class="section">

            <h2>
                SCHOOL WORK
            </h2>


            <div class="work">

                <h3>
                    Add School Work
                </h3>

                <p>
                    Create and post school work for your assigned students.
                </p>

                <button>
                    ADD SCHOOL WORK
                </button>

            </div>


            <div class="work">

                <h3>
                    Current School Work
                </h3>

                <p>
                    No school work has been added yet.
                </p>

            </div>


        </div>



        <!-- =========================
             ASSIGNMENTS
        ========================== -->

        <div class="section">

            <h2>
                ASSIGNMENTS
            </h2>


            <div class="work">

                <h3>
                    Create Assignment
                </h3>

                <p>
                    Give an assignment to your assigned students.
                </p>

                <button>
                    CREATE ASSIGNMENT
                </button>

            </div>


            <div class="work">

                <h3>
                    Assigned Work
                </h3>

                <p>
                    No assignments have been created yet.
                </p>

            </div>


        </div>



        <!-- =========================
             NOTIFICATIONS
        ========================== -->

        <div class="section">

            <h2>
                NOTIFICATIONS
            </h2>


            <div class="notification">

                <b>
                    No new notifications
                </b>

                <p>
                    Notifications for your students and classes will appear here.
                </p>

            </div>


        </div>



        <!-- =========================
             SETTINGS
        ========================== -->

        <div class="section">

            <h2>
                SETTINGS
            </h2>


            <div class="setting-row">

                <span>
                    Account Settings
                </span>

                <button>
                    EDIT
                </button>

            </div>


            <div class="setting-row">

                <span>
                    Dashboard Settings
                </span>

                <button>
                    EDIT
                </button>

            </div>


        </div>



        <div class="bottom-space"></div>


    </div>


</div>


</body>

</html>