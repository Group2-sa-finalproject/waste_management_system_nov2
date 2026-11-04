<?php
session_start();
include "../config/database.php";

// Check if the user is logged in as a resident
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "residents"
) {
    header("Location: ../index.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];
$service_fee = 150.00;
$message = "";
$message_type = "";

// Get resident information
$user_sql = mysqli_prepare(
    $conn,
    "SELECT id, fullname
     FROM users
     WHERE id = ? AND role = 'residents'
     LIMIT 1"
);

mysqli_stmt_bind_param($user_sql, "i", $user_id);
mysqli_stmt_execute($user_sql);

$user_result = mysqli_stmt_get_result($user_sql);
$user = mysqli_fetch_assoc($user_result);

mysqli_stmt_close($user_sql);

// Redirect if resident does not exist
if (!$user) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit;
}

// Create CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Process subscription
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["subscribe"])
) {
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        $message = "Invalid request. Please refresh the page and try again.";
        $message_type = "danger";

    } else {
        // Check for an existing subscription
        $check_sql = mysqli_prepare(
            $conn,
            "SELECT id FROM subscriptions WHERE user_id = ? LIMIT 1"
        );

        mysqli_stmt_bind_param($check_sql, "i", $user_id);
        mysqli_stmt_execute($check_sql);

        $check_result = mysqli_stmt_get_result($check_sql);
        $existing_subscription = mysqli_fetch_assoc($check_result);

        mysqli_stmt_close($check_sql);

        if ($existing_subscription) {
            $message = "You already have a subscription.";
            $message_type = "danger";

        } else {
            $subscription_status = "Pending Approval";
            $initial_payment_status = "Unpaid";

            // Insert subscription
            $insert_sql = mysqli_prepare(
                $conn,
                "INSERT INTO subscriptions
                    (user_id, service_fee, subscription_status, payment_status)
                 VALUES (?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $insert_sql,
                "idss",
                $user_id,
                $service_fee,
                $subscription_status,
                $initial_payment_status
            );

            if (mysqli_stmt_execute($insert_sql)) {
                mysqli_stmt_close($insert_sql);

                header("Location: subscription.php?success=1");
                exit;

            } else {
                $message = mysqli_stmt_errno($insert_sql) == 1062
                    ? "You already have a subscription."
                    : "Failed to save your subscription.";

                $message_type = "danger";

                mysqli_stmt_close($insert_sql);
            }
        }
    }
}

// Display success message
if (isset($_GET["success"]) && $_GET["success"] == "1") {
    $message = "Subscription submitted successfully!";
    $message_type = "success";
}

// Get subscription information
$subscription_sql = mysqli_prepare(
    $conn,
    "SELECT id, service_fee, subscription_status,
            payment_status, created_at
     FROM subscriptions
     WHERE user_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($subscription_sql, "i", $user_id);
mysqli_stmt_execute($subscription_sql);

$subscription_result = mysqli_stmt_get_result($subscription_sql);
$subscription = mysqli_fetch_assoc($subscription_result);

mysqli_stmt_close($subscription_sql);

// Get latest payment record
$payment_sql = mysqli_prepare(
    $conn,
    "SELECT amount, status, paid_at
     FROM resident_payments
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 1"
);

mysqli_stmt_bind_param($payment_sql, "i", $user_id);
mysqli_stmt_execute($payment_sql);

$payment_result = mysqli_stmt_get_result($payment_sql);
$payment = mysqli_fetch_assoc($payment_result);

mysqli_stmt_close($payment_sql);

// Default payment information
$payment_status = "Unpaid";
$payment_amount = 0;
$paid_at = null;

if ($payment) {
    $payment_status = $payment["status"];
    $payment_amount = (float) $payment["amount"];
    $paid_at = $payment["paid_at"];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Waste Collection Subscription</title>

    <link
        rel="stylesheet"
        href="../assets/vendor/bootstrap/css/bootstrap.min.css"
    >

    <style>
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: white;
        }

        /* Navigation bar */
        .navbar {
            background: #198754;
        }

        .navbar-brand {
            font-weight: bold;
            white-space: normal;
        }

        /* Main container */
        .subscription-container {
            max-width: 1000px;
            margin: 35px auto;
            padding: 0 20px;
        }

        /* White card */
        .subscription-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.12);
            border: 1px solid #eeeeee;
            margin-bottom: 25px;
        }

        /* Headings */
        .subscription-card h2,
        .subscription-card h4 {
            color: #198754;
            font-weight: bold;
        }

        .subscription-card h2 {
            margin-bottom: 15px;
        }

        .subscription-card h4 {
            margin-top: 20px;
            margin-bottom: 15px;
        }

        /* Service fee */
        .service-fee {
            background: #f1f8f4;
            border: 1px solid #d5eadd;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }

        .service-fee h2 {
            font-size: 32px;
            margin: 5px 0;
        }

        /* Subscription information */
        .info-label {
            font-weight: bold;
        }

        /* Subscribe button */
        .btn-subscribe {
            width: 100%;
            background: #198754;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
        }

        .btn-subscribe:hover {
            background: #146c43;
            color: white;
        }

        /* Back button */
        .btn-dashboard {
            color: #198754;
            border: 1px solid #198754;
        }

        .btn-dashboard:hover {
            background: #198754;
            color: white;
        }

        /* Mobile layout */
        @media (max-width: 576px) {
            .subscription-container {
                margin: 20px auto;
                padding: 0 12px;
            }

            .subscription-card {
                padding: 20px;
            }

            .navbar-brand {
                font-size: 15px;
                max-width: 70%;
            }
        }
    </style>
</head>

<body>

<!-- Navigation -->
<nav class="navbar navbar-dark">
    <div class="container">

        <span class="navbar-brand">
            Waste Collection Management System
        </span>

        <a href="../logout.php" class="btn btn-light">
            Logout
        </a>

    </div>
</nav>

<div class="subscription-container">

    <!-- Main Subscription Card -->
    <div class="subscription-card">

        <h2>Waste Collection Subscription</h2>

        <p>
            Welcome,
            <?php echo htmlspecialchars($user["fullname"]); ?>!
        </p>

        <hr>

        <!-- Service Information -->
        <h4>Service Information</h4>

        <p>
            Subscribe your household to the waste collection service.
        </p>

        <div class="service-fee">

            <p class="mb-1">Your Balance</p>

            <h2>
                ₱<?php
                echo number_format(
                    $subscription
                        ? (float) $subscription["service_fee"]
                        : $service_fee,
                    2
                );
                ?>
            </h2>

            <small class="text-muted">Per month</small>

        </div>

        <hr>

        <!-- Subscription Terms -->
        <h4>Subscription Terms</h4>

        <ul>
            <li>The service is intended for registered resident households.</li>
            <li>The monthly service fee is ₱150.00.</li>
            <li>Administrator approval is required before the service begins.</li>
            <li>Payment must be recorded before the service can begin.</li>
            <li>Submitting a subscription does not automatically activate the service.</li>
        </ul>

        <hr>

        <!-- Payment Instructions -->
        <h4>Payment Instructions</h4>

        <p>
            After subscribing, click Pay Now to proceed to the payment page.
            Follow the payment instructions provided by the administrator.
        </p>

    </div>

    <!-- Success or Error Message -->
    <?php if (!empty($message)) { ?>

        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php } ?>

    <!-- Subscription Details Card -->
    <div class="subscription-card">

        <?php if (!$subscription) { ?>

            <h4>Subscribe Now</h4>

            <p>
                Submit your subscription request for administrator approval.
            </p>

            <form method="POST" action="subscription.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
                >

                <button
                    type="submit"
                    name="subscribe"
                    class="btn btn-subscribe"
                >
                    Subscribe
                </button>

            </form>

        <?php } else { ?>

            <h4>Your Subscription Details</h4>

            <table class="table table-bordered mt-3">

                <tr>
                    <th>Subscription ID</th>
                    <td><?php echo (int) $subscription["id"]; ?></td>
                </tr>

                <tr>
                    <th>Date Submitted</th>
                    <td>
                        <?php
                        echo htmlspecialchars(
                            date("F d, Y", strtotime($subscription["created_at"]))
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <th>Service Fee</th>
                    <td>
                        ₱<?php echo number_format((float) $subscription["service_fee"], 2); ?>
                    </td>
                </tr>

                <tr>
                    <th>Subscription Status</th>
                    <td>
                        <?php echo htmlspecialchars($subscription["subscription_status"]); ?>
                    </td>
                </tr>

                <tr>
                    <th>Subscription Payment Status</th>
                    <td>
                        <?php echo htmlspecialchars($subscription["payment_status"]); ?>
                    </td>
                </tr>

                <tr>
                    <th>Latest Payment Record</th>
                    <td>
                        <?php echo htmlspecialchars($payment_status); ?>
                    </td>
                </tr>

                <?php if ($payment_status === "Paid") { ?>

                    <tr>
                        <th>Amount Recorded</th>
                        <td>
                            ₱<?php echo number_format($payment_amount, 2); ?>
                        </td>
                    </tr>

                    <tr>
                        <th>Date Paid</th>
                        <td>
                            <?php
                            echo !empty($paid_at)
                                ? htmlspecialchars(date("F d, Y h:i A", strtotime($paid_at)))
                                : "Not available";
                            ?>
                        </td>
                    </tr>

                <?php } ?>

            </table>

            <!-- Subscription Status Message -->
            <?php if ($subscription["subscription_status"] === "Pending Approval") { ?>

                <div class="alert alert-warning">
                    Your subscription is waiting for administrator approval.
                </div>

            <?php } elseif ($subscription["subscription_status"] === "Rejected") { ?>

                <div class="alert alert-danger">
                    Your subscription has been rejected.
                    Please contact the administrator.
                </div>

            <?php } elseif ($subscription["subscription_status"] === "Active") { ?>

                <div class="alert alert-success">
                    Your subscription is active.
                </div>

            <?php } ?>

            <!-- Payment Status Message -->
            <?php if ($payment_status === "Paid") { ?>

                <div class="alert alert-success">
                    Payment Recorded
                </div>

            <?php } else { ?>

                <div class="alert alert-warning">
                    Your payment has not yet been recorded as paid.
                </div>

            <?php } ?>

            <!-- Pay Now Button -->
            <?php if ($payment_status !== "Paid") { ?>

                <a
                    href="payment.php?subscription_id=<?php echo (int) $subscription["id"]; ?>"
                    class="btn btn-primary"
                >
                    Pay Now
                </a>

            <?php } ?>

        <?php } ?>

        <a href="dashboard.php" class="btn btn-dashboard mt-3">
            Back to Dashboard
        </a>

    </div>

</div>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>
</html>