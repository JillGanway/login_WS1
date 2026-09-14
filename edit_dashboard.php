<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$message = "";

/* Get user's existing profile */
$stmt = $conn->prepare("SELECT * FROM dashboard_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$profile = $result->fetch_assoc() ?: [];

$stmt->close();


/* SAVE CHANGES */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* About Me */
    $about_me = $_POST["about_me"] ?? "";


    /* Skills */
    $skills_array = $_POST["skills"] ?? [];

    $skills_array = array_filter($skills_array, function ($skill) {
        return trim($skill) !== "";
    });

    $skills = implode(" | ", $skills_array);


    /* Contact */
    $email = $_POST["email"] ?? "";
    $phone = $_POST["phone"] ?? "";
    $location = $_POST["location"] ?? "";


    /* Education */

    $nursery_school = $_POST["nursery_school"] ?? "";
    $nursery_year = $_POST["nursery_year"] ?? "";

    $kinder_school = $_POST["kinder_school"] ?? "";
    $kinder_year = $_POST["kinder_year"] ?? "";

    $elementary_school = $_POST["elementary_school"] ?? "";
    $elementary_year = $_POST["elementary_year"] ?? "";

    $junior_high_school = $_POST["junior_high_school"] ?? "";
    $junior_high_year = $_POST["junior_high_year"] ?? "";

    $senior_high_school = $_POST["senior_high_school"] ?? "";
    $senior_high_year = $_POST["senior_high_year"] ?? "";
    $senior_high_strand = $_POST["senior_high_strand"] ?? "";

    $college_school = $_POST["college_school"] ?? "";
    $college_year = $_POST["college_year"] ?? "";
    $college_course = $_POST["college_course"] ?? "";


    /* PROFILE PICTURE */

    $allowed_pfps = [
        "defaultpfp.jpg",
        "pfp2.jpg",
        "pfp3.jpg",
        "pfp4.jpg",
        "pfp5.jpg"
    ];

    $pfp = $_POST["pfp"] ?? "defaultpfp.jpg";

    if (!in_array($pfp, $allowed_pfps)) {
        $pfp = "defaultpfp.jpg";
    }


    /* THEME */

    $allowed_themes = [
        "bg1.jpg",
        "bg2.jpg",
        "bg3.jpg",
        "bg4.jpg",
        "bg5.jpg"
    ];

    $theme = $_POST["theme"] ?? "bg1.jpg";

    if (!in_array($theme, $allowed_themes)) {
        $theme = "bg1.jpg";
    }


    /* Check if profile already exists */

    $check = $conn->prepare(
        "SELECT id FROM dashboard_profiles WHERE user_id = ?"
    );

    $check->bind_param("i", $user_id);
    $check->execute();

    $check_result = $check->get_result();

    $exists = $check_result->num_rows > 0;

    $check->close();


    /* UPDATE EXISTING PROFILE */

    if ($exists) {

        $sql = "UPDATE dashboard_profiles SET

            about_me = ?,
            skills = ?,

            email = ?,
            phone = ?,
            location = ?,

            nursery_school = ?,
            nursery_year = ?,

            kinder_school = ?,
            kinder_year = ?,

            elementary_school = ?,
            elementary_year = ?,

            junior_high_school = ?,
            junior_high_year = ?,

            senior_high_school = ?,
            senior_high_year = ?,
            senior_high_strand = ?,

            college_school = ?,
            college_year = ?,
            college_course = ?,

            pfp = ?,
            theme = ?

            WHERE user_id = ?";

        $stmt = $conn->prepare($sql);

        $types = str_repeat("s", 21) . "i";

        $stmt->bind_param(
            $types,

            $about_me,
            $skills,

            $email,
            $phone,
            $location,

            $nursery_school,
            $nursery_year,

            $kinder_school,
            $kinder_year,

            $elementary_school,
            $elementary_year,

            $junior_high_school,
            $junior_high_year,

            $senior_high_school,
            $senior_high_year,
            $senior_high_strand,

            $college_school,
            $college_year,
            $college_course,

            $pfp,
            $theme,

            $user_id
        );


    /* CREATE NEW PROFILE */

    } else {

        $sql = "INSERT INTO dashboard_profiles (

            user_id,

            about_me,
            skills,

            email,
            phone,
            location,

            nursery_school,
            nursery_year,

            kinder_school,
            kinder_year,

            elementary_school,
            elementary_year,

            junior_high_school,
            junior_high_year,

            senior_high_school,
            senior_high_year,
            senior_high_strand,

            college_school,
            college_year,
            college_course,

            pfp,
            theme

        ) VALUES (

            ?,

            ?,
            ?,

            ?,
            ?,
            ?,

            ?,
            ?,

            ?,
            ?,

            ?,
            ?,

            ?,
            ?,

            ?,
            ?,
            ?,

            ?,
            ?,
            ?,

            ?,
            ?

        )";

        $stmt = $conn->prepare($sql);

        $types = "i" . str_repeat("s", 21);

        $stmt->bind_param(
            $types,

            $user_id,

            $about_me,
            $skills,

            $email,
            $phone,
            $location,

            $nursery_school,
            $nursery_year,

            $kinder_school,
            $kinder_year,

            $elementary_school,
            $elementary_year,

            $junior_high_school,
            $junior_high_year,

            $senior_high_school,
            $senior_high_year,
            $senior_high_strand,

            $college_school,
            $college_year,
            $college_course,

            $pfp,
            $theme
        );
    }


    /* Execute */

    if ($stmt->execute()) {

        $message = "Profile updated successfully!";

        $stmt->close();


        /* Reload profile */

        $stmt = $conn->prepare(
            "SELECT * FROM dashboard_profiles WHERE user_id = ?"
        );

        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $result = $stmt->get_result();

        $profile = $result->fetch_assoc() ?: [];

        $stmt->close();

    } else {

        $message = "Something went wrong. Please try again.";

        $stmt->close();
    }
}


/* Current theme */
$current_theme = $profile["theme"] ?? "bg1.jpg";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Profile</title>

    <link rel="stylesheet" href="edit_dashboard.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap"
          rel="stylesheet">

</head>


<body style="background-image: url('<?= htmlspecialchars($current_theme) ?>');">

<div class="edit-container">

<div class="edit-box">


<h1>Edit Profile</h1>

<p class="subtitle">
    Update the information displayed on your dashboard.
</p>


<?php if ($message): ?>

<div class="success-message">

    <?= htmlspecialchars($message) ?>

</div>

<?php endif; ?>


<form id="profile-form" method="POST">


<!-- ABOUT ME -->

<div class="input-box">

    <label>About Me</label>

    <textarea
        name="about_me"
        placeholder="Tell something about yourself..."
    ><?= htmlspecialchars($profile["about_me"] ?? "") ?></textarea>

</div>


<!-- EDUCATION -->

<div class="education-container">

<h2>Education</h2>


<!-- NURSERY -->

<div class="education-section">

<h3>Nursery</h3>

<div class="education-row">

<div class="input-box">

<label>School</label>

<input
    type="text"
    name="nursery_school"
    value="<?= htmlspecialchars($profile["nursery_school"] ?? "") ?>"
    placeholder="School name"
>

</div>


<div class="input-box">

<label>Year Started</label>

<input
    type="text"
    name="nursery_year"
    value="<?= htmlspecialchars($profile["nursery_year"] ?? "") ?>"
    placeholder="Year"
>

</div>

</div>

</div>


<!-- KINDER -->

<div class="education-section">

<h3>Kinder</h3>

<div class="education-row">

<div class="input-box">

<label>School</label>

<input
    type="text"
    name="kinder_school"
    value="<?= htmlspecialchars($profile["kinder_school"] ?? "") ?>"
    placeholder="School name"
>

</div>


<div class="input-box">

<label>Year Started</label>

<input
    type="text"
    name="kinder_year"
    value="<?= htmlspecialchars($profile["kinder_year"] ?? "") ?>"
    placeholder="Year"
>

</div>

</div>

</div>


<!-- ELEMENTARY -->

<div class="education-section">

<h3>Elementary</h3>

<div class="education-row">

<div class="input-box">

<label>School</label>

<input
    type="text"
    name="elementary_school"
    value="<?= htmlspecialchars($profile["elementary_school"] ?? "") ?>"
    placeholder="School name"
>

</div>


<div class="input-box">

<label>Year Started</label>

<input
    type="text"
    name="elementary_year"
    value="<?= htmlspecialchars($profile["elementary_year"] ?? "") ?>"
    placeholder="Year"
>

</div>

</div>

</div>


<!-- JUNIOR HIGH -->

<div class="education-section">

<h3>Junior High School</h3>

<div class="education-row">

<div class="input-box">

<label>School</label>

<input
    type="text"
    name="junior_high_school"
    value="<?= htmlspecialchars($profile["junior_high_school"] ?? "") ?>"
    placeholder="School name"
>

</div>


<div class="input-box">

<label>Year Started</label>

<input
    type="text"
    name="junior_high_year"
    value="<?= htmlspecialchars($profile["junior_high_year"] ?? "") ?>"
    placeholder="Year"
>

</div>

</div>

</div>


<!-- SENIOR HIGH -->

<div class="education-section">

<h3>Senior High School</h3>

<div class="education-row">

<div class="input-box">

<label>School</label>

<input
    type="text"
    name="senior_high_school"
    value="<?= htmlspecialchars($profile["senior_high_school"] ?? "") ?>"
    placeholder="School name"
>

</div>


<div class="input-box">

<label>Year Started</label>

<input
    type="text"
    name="senior_high_year"
    value="<?= htmlspecialchars($profile["senior_high_year"] ?? "") ?>"
    placeholder="Year"
>

</div>


<div class="input-box">

<label>Strand</label>

<input
    type="text"
    name="senior_high_strand"
    value="<?= htmlspecialchars($profile["senior_high_strand"] ?? "") ?>"
    placeholder="e.g. STEM, HUMSS, TVL"
>

</div>

</div>

</div>


<!-- COLLEGE -->

<div class="education-section">

<h3>College</h3>

<div class="education-row">

<div class="input-box">

<label>School</label>

<input
    type="text"
    name="college_school"
    value="<?= htmlspecialchars($profile["college_school"] ?? "") ?>"
    placeholder="School name"
>

</div>


<div class="input-box">

<label>Year Started</label>

<input
    type="text"
    name="college_year"
    value="<?= htmlspecialchars($profile["college_year"] ?? "") ?>"
    placeholder="Year"
>

</div>


<div class="input-box">

<label>Course</label>

<input
    type="text"
    name="college_course"
    value="<?= htmlspecialchars($profile["college_course"] ?? "") ?>"
    placeholder="Course"
>

</div>

</div>

</div>

</div>


<!-- SKILLS -->

<div class="skills-container">

<h2>Skills</h2>

<div id="skills-list">

<?php

$saved_skills = [];

if (!empty($profile["skills"])) {
    $saved_skills = explode(" | ", $profile["skills"]);
}

?>


<!-- SKILL 1 -->

<div class="skill-box">

<label>Skill 1</label>

<input
    type="text"
    name="skills[]"
    value="<?= htmlspecialchars($saved_skills[0] ?? "") ?>"
    placeholder="Enter a skill..."
>

</div>


<!-- SKILL 2 -->

<div class="skill-box">

<label>Skill 2</label>

<input
    type="text"
    name="skills[]"
    value="<?= htmlspecialchars($saved_skills[1] ?? "") ?>"
    placeholder="Enter a skill..."
>

</div>


<!-- SKILL 3 -->

<div class="skill-box">

<label>Skill 3</label>

<input
    type="text"
    name="skills[]"
    value="<?= htmlspecialchars($saved_skills[2] ?? "") ?>"
    placeholder="Enter a skill..."
>

</div>


<!-- EXTRA SAVED SKILLS -->

<?php if (count($saved_skills) > 3): ?>

    <?php for ($i = 3; $i < count($saved_skills); $i++): ?>

        <div class="skill-box">

            <label>Skill <?= $i + 1 ?></label>

            <input
                type="text"
                name="skills[]"
                value="<?= htmlspecialchars($saved_skills[$i]) ?>"
                placeholder="Enter a skill..."
            >

        </div>

    <?php endfor; ?>

<?php endif; ?>

</div>


<button type="button" id="add-skill-btn">
    + Add More Skills
</button>

</div>


<!-- CONTACT -->

<h2 class="section-title">
    Contact Information
</h2>


<div class="input-box">

<label>Email</label>

<input
    type="email"
    name="email"
    value="<?= htmlspecialchars($profile["email"] ?? "") ?>"
    placeholder="Your email"
>

</div>


<div class="input-box">

<label>Phone</label>

<input
    type="text"
    name="phone"
    value="<?= htmlspecialchars($profile["phone"] ?? "") ?>"
    placeholder="Your phone number"
>

</div>


<div class="input-box">

<label>Location</label>

<input
    type="text"
    name="location"
    value="<?= htmlspecialchars($profile["location"] ?? "") ?>"
    placeholder="Your location"
>

</div>


<!-- PROFILE PICTURE -->

<div class="pfp-container">

<h2>Profile Picture</h2>

<p class="pfp-subtitle">
    Choose your profile picture
</p>


<div class="pfp-options">


<label class="pfp-option">

<input
    type="radio"
    name="pfp"
    value="defaultpfp.jpg"
    <?= ($profile["pfp"] ?? "defaultpfp.jpg") == "defaultpfp.jpg" ? "checked" : "" ?>
>

<img
    src="defaultpfp.jpg"
    alt="Default Profile Picture"
>

</label>


<label class="pfp-option">

<input
    type="radio"
    name="pfp"
    value="pfp2.jpg"
    <?= ($profile["pfp"] ?? "") == "pfp2.jpg" ? "checked" : "" ?>
>

<img
    src="pfp2.jpg"
    alt="Profile Picture 2"
>

</label>


<label class="pfp-option">

<input
    type="radio"
    name="pfp"
    value="pfp3.jpg"
    <?= ($profile["pfp"] ?? "") == "pfp3.jpg" ? "checked" : "" ?>
>

<img
    src="pfp3.jpg"
    alt="Profile Picture 3"
>

</label>


<label class="pfp-option">

<input
    type="radio"
    name="pfp"
    value="pfp4.jpg"
    <?= ($profile["pfp"] ?? "") == "pfp4.jpg" ? "checked" : "" ?>
>

<img
    src="pfp4.jpg"
    alt="Profile Picture 4"
>

</label>


<label class="pfp-option">

<input
    type="radio"
    name="pfp"
    value="pfp5.jpg"
    <?= ($profile["pfp"] ?? "") == "pfp5.jpg" ? "checked" : "" ?>
>

<img
    src="pfp5.jpg"
    alt="Profile Picture 5"
>

</label>


</div>

</div>


<!-- THEME -->

<div class="theme-container">

<h2>Theme</h2>

<p class="theme-subtitle">
    Choose your background theme
</p>


<div class="theme-options">


<label class="theme-option">

<input
    type="radio"
    name="theme"
    value="bg2.jpg"
    <?= ($profile["theme"] ?? "bg1.jpg") == "bg2.jpg" ? "checked" : "" ?>
>

<img
    src="bg2.jpg"
    alt="Theme 2"
>

</label>


<label class="theme-option">

<input
    type="radio"
    name="theme"
    value="bg3.jpg"
    <?= ($profile["theme"] ?? "bg1.jpg") == "bg3.jpg" ? "checked" : "" ?>
>

<img
    src="bg3.jpg"
    alt="Theme 3"
>

</label>


<label class="theme-option">

<input
    type="radio"
    name="theme"
    value="bg4.jpg"
    <?= ($profile["theme"] ?? "bg1.jpg") == "bg4.jpg" ? "checked" : "" ?>
>

<img
    src="bg4.jpg"
    alt="Theme 4"
>

</label>


<label class="theme-option">

<input
    type="radio"
    name="theme"
    value="bg5.jpg"
    <?= ($profile["theme"] ?? "bg1.jpg") == "bg5.jpg" ? "checked" : "" ?>
>

<img
    src="bg5.jpg"
    alt="Theme 5"
>

</label>


</div>

</div>


<!-- SAVE -->

<button type="submit">
    Save Changes
</button>


</form>


<a href="dashboard.php" class="back-link">
    ← Back to Dashboard
</a>


</div>

</div>


<script>

/* ADD MORE SKILLS */

let skillCount = document.querySelectorAll(".skill-box").length;

document.getElementById("add-skill-btn").addEventListener("click", function () {

    skillCount++;

    const skillBox = document.createElement("div");

    skillBox.className = "skill-box";

    skillBox.innerHTML = `
        <label>Skill ${skillCount}</label>

        <input
            type="text"
            name="skills[]"
            placeholder="Enter a skill..."
        >
    `;

    document.getElementById("skills-list").appendChild(skillBox);

});

</script>


</body>
</html>