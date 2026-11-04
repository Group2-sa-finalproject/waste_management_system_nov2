
<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

// Get resident ID from session
$user_id = (int)(
    $_SESSION['user_id']
    ?? $_SESSION['resident_id']
    ?? $_SESSION['household_id']
    ?? $_SESSION['id']
    ?? 0
);

if ($user_id <= 0) {
    header("Location: ../login.php");
    exit;
}

$error = "";
$success = isset($_GET['success']);
$selected_type = "";
$message = "";

// Submit feedback
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $selected_type = trim($_POST['feedback_type'] ?? '');
    $message = trim($_POST['message'] ?? '');

    $allowed_types = [
        'Complaint',
        'Suggestion',
        'Satisfaction'
    ];

    if (!in_array($selected_type, $allowed_types, true)) {
        $error = "Please select a valid feedback type.";

    } elseif ($message === '') {
        $error = "Please enter your feedback message.";

    } else {

        $sql = "INSERT INTO feedback
                (user_id, feedback_type, message)
                VALUES (?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error = "Database error: " . $conn->error;

        } else {

            $stmt->bind_param(
                "iss",
                $user_id,
                $selected_type,
                $message
            );

            if ($stmt->execute()) {
                $stmt->close();

                header("Location: feedback.php?success=1");
                exit;

            } else {
                $error = "Unable to submit feedback: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Send Feedback</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f7f6;
    color: #333;
}

.navbar {
    background: #198754;
    color: white;
    padding: 18px 25px;
}

.navbar h2 {
    margin: 0;
    font-size: 22px;
}

.container {
    max-width: 750px;
    margin: 40px auto;
    padding: 15px;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}

h3 {
    color: #198754;
    margin-top: 0;
}

label {
    display: block;
    margin: 18px 0 8px;
    font-weight: bold;
}

select, textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 15px;
}

textarea {
    min-height: 150px;
    resize: vertical;
}

button, .btn {
    display: inline-block;
    border: none;
    border-radius: 5px;
    padding: 12px 18px;
    margin-top: 20px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
}

button {
    background: #198754;
    color: white;
}

.btn {
    background: #6c757d;
    color: white;
}

button:hover {
    background: #146c43;
}

.btn:hover {
    background: #565e64;
}

.success {
    background: #d4edda;
    color: #155724;
    padding: 12px;
    border-radius: 5px;
}

.error {
    background: #f8d7da;
    color: #721c24;
    padding: 12px;
    border-radius: 5px;
}
</style>
</head>

<body>

<div class="navbar">
    <h2>Waste Collection Resident Portal</h2>
</div>

<div class="container">
<div class="card">

<h3>Send Feedback</h3>

<p>
Share your complaints, suggestions, or satisfaction
about the waste collection service.
</p>

<?php if ($success): ?>
    <p class="success">
        Your feedback has been submitted successfully!
    </p>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <p class="error">
        <?= htmlspecialchars($error) ?>
    </p>
<?php endif; ?>

<form method="POST" action="feedback.php">

    <label for="feedback_type">Feedback Type</label>

    <select name="feedback_type" id="feedback_type" required>

        <option value="">-- Select Feedback Type --</option>

        <option value="Complaint"
            <?= $selected_type === 'Complaint' ? 'selected' : '' ?>>
            Complaint
        </option>

        <option value="Suggestion"
            <?= $selected_type === 'Suggestion' ? 'selected' : '' ?>>
            Suggestion
        </option>

        <option value="Satisfaction"
            <?= $selected_type === 'Satisfaction' ? 'selected' : '' ?>>
            Satisfaction
        </option>

    </select>

    <label for="message">Your Message</label>

    <textarea
        name="message"
        id="message"
        placeholder="Write your feedback here..."
        required
    ><?= htmlspecialchars($message) ?></textarea>

    <button type="submit">
        Submit Feedback
    </button>

    <a href="feedback_history.php" class="btn">
        My Feedbacks
    </a>

</form>

</div>
</div>

</body>
</html>
