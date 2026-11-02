
<?php
session_start();
include "../config/database.php";

// Only collectors can access this page
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    !in_array($_SESSION["role"], ["collector", "collectors"])
) {
    header("Location: ../index.php");
    exit;
}

$collector_id = (int) $_SESSION["user_id"];

// Create CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Handle scanner requests
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json");

    function respond($success, $message, $data = []) {
        echo json_encode([
            "success" => $success,
            "message" => $message,
            "data" => $data
        ]);
        exit;
    }

    // Verify CSRF token
    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        respond(false, "Invalid request. Please refresh the page.");
    }

    $action = $_POST["action"] ?? "";

    // Verify resident QR code
    if ($action === "verify") {

        $qr_data = trim($_POST["qr_data"] ?? "");

        if (!preg_match('/^RESIDENT\s*-\s*(\d+)$/i', $qr_data, $matches)) {
            respond(false, "Invalid resident QR code.");
        }

        $resident_id = (int) $matches[1];

        $stmt = mysqli_prepare($conn, "
            SELECT
                u.id,
                u.fullname,
                h.id AS household_id,
                h.house_address,
                s.subscription_status,
                s.payment_status
            FROM users u
            LEFT JOIN households h ON h.user_id = u.id
            LEFT JOIN subscriptions s ON s.user_id = u.id
            WHERE u.id = ?
              AND u.role = 'residents'
            LIMIT 1
        ");

        if (!$stmt) {
            respond(false, "Database error while checking resident.");
        }

        mysqli_stmt_bind_param($stmt, "i", $resident_id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $resident = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$resident) {
            respond(false, "Resident account not found.");
        }

        if (empty($resident["household_id"])) {
            respond(false, "This resident has not registered a household.");
        }

        if (
            $resident["subscription_status"] !== "Active" ||
            $resident["payment_status"] !== "Paid"
        ) {
            respond(false, "This resident has not paid for an active subscription. Collection is blocked.");
        }

        respond(true, "Resident verified successfully.", [
            "resident_id" => $resident["id"],
            "fullname" => $resident["fullname"],
            "household_id" => $resident["household_id"],
            "house_address" => $resident["house_address"]
        ]);
    }

    // Record waste collection
    if ($action === "collect") {

        $resident_id = (int) ($_POST["resident_id"] ?? 0);
        $segregation = $_POST["segregation"] ?? "";

        if ($resident_id <= 0) {
            respond(false, "Invalid resident.");
        }

        if (!in_array($segregation, [
            "Properly Segregated",
            "Not Properly Segregated"
        ])) {
            respond(false, "Invalid segregation status.");
        }

        mysqli_begin_transaction($conn);

        try {

            // Recheck resident and subscription on the server
            $stmt = mysqli_prepare($conn, "
                SELECT
                    u.id,
                    h.id AS household_id,
                    s.subscription_status,
                    s.payment_status
                FROM users u
                INNER JOIN households h ON h.user_id = u.id
                INNER JOIN subscriptions s ON s.user_id = u.id
                WHERE u.id = ?
                  AND u.role = 'residents'
                LIMIT 1
                FOR UPDATE
            ");

            if (!$stmt) {
                throw new Exception("Unable to verify resident.");
            }

            mysqli_stmt_bind_param($stmt, "i", $resident_id);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $resident = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (
                !$resident ||
                $resident["subscription_status"] !== "Active" ||
                $resident["payment_status"] !== "Paid"
            ) {
                throw new Exception("Resident subscription is inactive or unpaid. Collection is blocked.");
            }

            $household_id = (int) $resident["household_id"];

            // Award 10 points only for proper segregation
            $points = ($segregation === "Properly Segregated") ? 10 : 0;

            // Insert collection record
            $stmt = mysqli_prepare($conn, "
                INSERT INTO waste_collections (
                    household_id,
                    collector_id,
                    collection_date,
                    segregation_status,
                    collection_status,
                    eco_points_awarded,
                    collected_at
                )
                VALUES (?, ?, CURDATE(), ?, 'Collected', ?, NOW())
            ");

            if (!$stmt) {
                throw new Exception("Unable to create collection record.");
            }

            mysqli_stmt_bind_param(
                $stmt,
                "iisi",
                $household_id,
                $collector_id,
                $segregation,
                $points
            );

            if (!mysqli_stmt_execute($stmt)) {
                if (mysqli_errno($conn) == 1062) {
                    throw new Exception("This household has already been recorded as collected today.");
                }

                throw new Exception("Unable to save collection record.");
            }

            $collection_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Add eco-points only when properly segregated
            if ($points > 0) {

                $description = "Properly segregated household waste";

                $stmt = mysqli_prepare($conn, "
                    INSERT INTO eco_points (
                        user_id,
                        collection_id,
                        points,
                        description
                    )
                    VALUES (?, ?, ?, ?)
                ");

                if (!$stmt) {
                    throw new Exception("Unable to award eco-points.");
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    "iiis",
                    $resident_id,
                    $collection_id,
                    $points,
                    $description
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Unable to save eco-points.");
                }

                mysqli_stmt_close($stmt);
            }

            mysqli_commit($conn);

            $message = $points > 0
                ? "Collection recorded! 10 eco-points awarded."
                : "Collection recorded. No eco-points awarded.";

            respond(true, $message, [
                "points" => $points
            ]);

        } catch (Throwable $e) {
            mysqli_rollback($conn);
            respond(false, $e->getMessage());
        }
    }

    respond(false, "Unknown request.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Collector QR Scanner</title>

    <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">

    <style>
        body {
            background: #f4f7f6;
            font-family: Arial, sans-serif;
        }

        .navbar {
            background: #198754;
        }

        .scanner-container {
            max-width: 650px;
            margin: 40px auto;
        }

        .scanner-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,.08);
        }

        #reader {
            width: 100%;
            margin: 20px auto;
        }

        #resident-result {
            display: none;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-dark">
    <div class="container">
        <span class="navbar-brand">
            Waste Collection - Collector
        </span>
    </div>
</nav>

<div class="container scanner-container">

    <div class="scanner-card text-center">

        <h3 class="text-success">Resident QR Scanner</h3>

        <p class="text-muted">
            Scan the resident's QR code to verify their subscription.
        </p>

        <div id="reader"></div>

        <div id="scan-message" class="alert alert-info">
            Waiting for QR code...
        </div>

        <div id="resident-result">

            <hr>

            <h4 class="text-success">Resident Verified</h4>

            <p class="mb-1">
                <strong>Name:</strong>
                <span id="resident-name"></span>
            </p>

            <p class="mb-3">
                <strong>House Address:</strong>
                <span id="resident-address"></span>
            </p>

            <p class="text-success">
                <strong>Subscription:</strong> Active and Paid
            </p>

            <hr>

            <h5>Waste Segregation Assessment</h5>

            <button
                class="btn btn-success w-100 mb-2"
                onclick="recordCollection('Properly Segregated')"
            >
                Properly Segregated (+10 Eco-Points)
            </button>

            <button
                class="btn btn-warning w-100 mb-2"
                onclick="recordCollection('Not Properly Segregated')"
            >
                Not Properly Segregated (No Points)
            </button>

            <button
                class="btn btn-outline-success w-100"
                onclick="resetScanner()"
            >
                Back to Scanner
            </button>

        </div>

        <div class="mt-4">
            <a href="../admin/dashboard.php" class="btn btn-secondary w-100">
                Back to Dashboard
            </a>
        </div>

    </div>

</div>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/html5-qrcode"></script>

<script>
    const csrfToken = <?php echo json_encode($_SESSION["csrf_token"]); ?>;

    let residentId = null;
    let scanner = null;
    let scannerRunning = false;
    let busy = false;

    const message = document.getElementById("scan-message");
    const result = document.getElementById("resident-result");

    function showMessage(text, type = "info") {
        message.className = "alert alert-" + type;
        message.textContent = text;
        message.style.display = "block";
    }

    async function sendRequest(data) {
        const formData = new FormData();

        formData.append("csrf_token", csrfToken);

        for (const key in data) {
            formData.append(key, data[key]);
        }

        const response = await fetch("scanner.php", {
            method: "POST",
            body: formData
        });

        return await response.json();
    }

    async function startScanner() {
        scanner = new Html5Qrcode("reader");

        try {
            await scanner.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                handleScan
            );

            scannerRunning = true;
            showMessage("Camera active. Scan a resident QR code.", "info");

        } catch (error) {
            showMessage(
                "Unable to access camera. Please allow camera permission and use localhost or HTTPS.",
                "danger"
            );
        }
    }

    async function handleScan(decodedText) {
        if (busy) return;

        busy = true;

        try {
            await scanner.stop();
            scannerRunning = false;

            showMessage("Checking resident subscription...", "info");

            const response = await sendRequest({
                action: "verify",
                qr_data: decodedText
            });

            if (!response.success) {
                showMessage(response.message, "danger");
                setTimeout(resetScanner, 2500);
                return;
            }

            residentId = response.data.resident_id;

            document.getElementById("resident-name").textContent =
                response.data.fullname;

            document.getElementById("resident-address").textContent =
                response.data.house_address;

            result.style.display = "block";
            message.style.display = "none";

        } catch (error) {
            showMessage("Something went wrong. Please try again.", "danger");
            setTimeout(resetScanner, 2500);
        }
    }

    async function recordCollection(status) {
        if (!residentId) return;

        const buttons = document.querySelectorAll("#resident-result button");
        buttons.forEach(button => button.disabled = true);

        showMessage("Saving collection record...", "info");

        try {
            const response = await sendRequest({
                action: "collect",
                resident_id: residentId,
                segregation: status
            });

            if (response.success) {
                showMessage(response.message, "success");
                result.style.display = "none";

                setTimeout(resetScanner, 2500);

            } else {
                showMessage(response.message, "danger");
                buttons.forEach(button => button.disabled = false);
            }

        } catch (error) {
            showMessage("Unable to save collection. Please try again.", "danger");
            buttons.forEach(button => button.disabled = false);
        }
    }

    async function resetScanner() {
        residentId = null;
        busy = false;

        result.style.display = "none";
        message.style.display = "block";

        if (scanner && scannerRunning) {
            try {
                await scanner.stop();
            } catch (error) {}
        }

        scannerRunning = false;

        if (scanner) {
            try {
                await scanner.clear();
            } catch (error) {}
        }

        document.getElementById("reader").innerHTML = "";

        startScanner();
    }

    startScanner();
</script>

</body>
</html>
