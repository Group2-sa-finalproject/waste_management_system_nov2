
<?php
session_start();
require_once __DIR__ . "/../config/db_system.php";

// Admin authentication
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../index.php");
    exit;
}

$errorMessage = "";

// Generate regular route
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST['generate_regular_route'])
) {
    try {
        $pdo->beginTransaction();

        $routeHouseholds = $pdo->query("
            SELECT id
            FROM households
            WHERE latitude IS NOT NULL
              AND longitude IS NOT NULL
            ORDER BY id ASC
        ")->fetchAll(PDO::FETCH_COLUMN);

        if (count($routeHouseholds) === 0) {
            throw new Exception(
                "No registered households with coordinates found."
            );
        }

        $routeStmt = $pdo->prepare("
            INSERT INTO collection_routes
                (route_name, route_type, status, created_by)
            VALUES (?, 'regular', 'pending', ?)
        ");

        $routeStmt->execute([
            "Regular Collection Route - " . date("Y-m-d H:i"),
            $_SESSION['user_id']
        ]);

        $routeId = $pdo->lastInsertId();

        $stopStmt = $pdo->prepare("
            INSERT INTO route_stops
                (route_id, household_id, stop_order)
            VALUES (?, ?, ?)
        ");

        foreach ($routeHouseholds as $index => $householdId) {
            $stopStmt->execute([
                $routeId,
                $householdId,
                $index + 1
            ]);
        }

        $pdo->commit();

        header("Location: admin_routes.php?success=1");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $errorMessage = $e->getMessage();
    }
}

// Retrieve household and route data
try {
    $stmt = $pdo->query("
        SELECT
            h.id,
            h.house_address,
            h.latitude,
            h.longitude,
            u.fullname
        FROM households h
        INNER JOIN users u ON h.user_id = u.id
        WHERE h.latitude IS NOT NULL
          AND h.longitude IS NOT NULL
        ORDER BY h.id ASC
    ");

    $households = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $routeStmt = $pdo->query("
        SELECT
            h.id,
            h.house_address,
            h.latitude,
            h.longitude,
            u.fullname,
            rs.stop_order
        FROM collection_routes cr
        INNER JOIN route_stops rs ON cr.id = rs.route_id
        INNER JOIN households h ON rs.household_id = h.id
        INNER JOIN users u ON h.user_id = u.id
        WHERE cr.id = (
            SELECT MAX(id)
            FROM collection_routes
            WHERE route_type = 'regular'
        )
        ORDER BY rs.stop_order ASC
    ");

    $routeStops = $routeStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

$jsonFlags =
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP;

$householdsJson = json_encode($households, $jsonFlags);
$routeStopsJson = json_encode($routeStops, $jsonFlags);

if ($householdsJson === false || $routeStopsJson === false) {
    die("Unable to process location data.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Collection Route Management</title>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f5;
            color: #263238;
        }

        /* NAVBAR */
       .navbar {
    background: #198754;
    color: white;
    padding: 17px 35px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

        .navbar h2 {
            font-size: 21px;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .navbar a:hover {
            opacity: 0.8;
        }

        /* MAIN CONTENT */
        .main {
            max-width: 1250px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            font-size: 27px;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #718096;
            font-size: 14px;
        }

        /* SUMMARY CARDS */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }

      .summary-icon {
         width: 50px;
         height: 50px;
         background: #e6f4ec;
         color: #198754;
         display: flex;
         align-items: center;
         justify-content: center;
         border-radius: 10px;
         font-size: 21px;
}

        .summary-card h3 {
            font-size: 23px;
            margin-bottom: 4px;
        }

        .summary-card p {
            color: #718096;
            font-size: 13px;
        }

        /* WHITE CONTENT CARDS */
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }

        .card-header h2 {
            font-size: 19px;
        }

        .card-header p {
            color: #718096;
            font-size: 13px;
            margin-top: 5px;
        }

        /* BUTTONS */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 17px;
            border: none;
            border-radius: 7px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            transition: 0.2s;
        }

       .btn-green {
       background: #198754;
       color: white;
}
        .btn-green:hover {
    background: #146c43;
}

        .btn-blue {
            background: #1976d2;
            color: white;
        }

        .btn-blue:hover {
            background: #125ba1;
        }

        /* ALERTS */
        .alert {
            padding: 13px 16px;
            border-radius: 7px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .error {
            background: #ffebee;
            color: #c62828;
        }

        /* TABLE */
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 14px 12px;
            border-bottom: 1px solid #edf0ed;
            text-align: left;
            font-size: 13px;
        }

       th {
    background: #e6f4ec;
    color: #198754;
    font-weight: bold;
}

        tbody tr:hover {
            background: #f9fbf9;
        }

       .household-id {
    font-weight: bold;
    color: #198754;
}

        /* MAP */
        #map {
            width: 100%;
            height: 480px;
            border-radius: 10px;
            margin-top: 15px;
        }

        .map-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        #routeMessage {
            margin-top: 14px;
            font-size: 13px;
            color: #2e7d32;
        }

        .empty {
            text-align: center;
            padding: 25px;
            color: #718096;
        }

        @media(max-width: 600px) {
            .navbar {
                padding: 15px 20px;
            }

            .main {
                margin-top: 20px;
            }

            .card {
                padding: 18px;
            }

            .page-header h1 {
                font-size: 23px;
            }

            #map {
                height: 350px;
            }
        }
    </style>
</head>

<body>

<nav class="navbar">
    <h2><i class="fas fa-recycle"></i> Waste Management System</h2>

    <a href="dashboard.php">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</nav>

<main class="main">

    <div class="page-header">
        <h1>Collection Route Management</h1>
        <p>Manage registered household locations and generate collection routes.</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i>
            Regular collection route generated successfully!
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    <!-- SUMMARY -->
    <div class="summary-grid">

        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-house"></i>
            </div>
            <div>
                <h3><?php echo count($households); ?></h3>
                <p>Registered Households</p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-map-location-dot"></i>
            </div>
            <div>
                <h3><?php echo count($routeStops); ?></h3>
                <p>Stops in Latest Regular Route</p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-location-crosshairs"></i>
            </div>
            <div>
                <h3><?php echo count(array_filter($households, function($h) {
                    return !empty($h['latitude']) && !empty($h['longitude']);
                })); ?></h3>
                <p>Households with Coordinates</p>
            </div>
        </div>

    </div>

    <!-- HOUSEHOLDS -->
    <section class="card">

        <div class="card-header">
            <div>
                <h2><i class="fas fa-house-user"></i> Registered Households</h2>
                <p>View households with registered map coordinates.</p>
            </div>

            <form method="POST" action="admin_routes.php">
                <button
                    type="submit"
                    name="generate_regular_route"
                    value="1"
                    class="btn btn-green"
                    onclick="return confirm('Generate a new regular collection route?');"
                >
                    <i class="fas fa-route"></i>
                    Generate Regular Route
                </button>
            </form>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Household ID</th>
                        <th>Resident Name</th>
                        <th>House Address</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($households) > 0): ?>

                        <?php foreach ($households as $household): ?>
                            <tr>
                                <td class="household-id">
                                    #<?php echo htmlspecialchars($household['id']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($household['fullname']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($household['house_address']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($household['latitude']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($household['longitude']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="empty">
                                No registered households with coordinates found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </section>

    <!-- MAP -->
    <section class="card">

        <div class="card-header">
            <div>
                <h2><i class="fas fa-map-marked-alt"></i> Household Locations</h2>
                <p>View registered households and calculate a driving route.</p>
            </div>
        </div>

        <div class="map-controls">
            <button
                type="button"
                class="btn btn-blue"
                onclick="startRouteFromMyLocation()"
            >
                <i class="fas fa-location-arrow"></i>
                Start Route from My Location
            </button>
        </div>

        <p id="routeMessage"></p>

        <div id="map"></div>

    </section>

</main>

<script>
    const households = <?php echo $householdsJson; ?>;
    const routeStops = <?php echo $routeStopsJson; ?>;

    let map;
    let Route;

    async function initMap() {

        const routesLibrary = await google.maps.importLibrary("routes");
        Route = routesLibrary.Route;

        const defaultLocation = {
            lat: 15.2200,
            lng: 120.5700
        };

        map = new google.maps.Map(
            document.getElementById("map"),
            {
                zoom: 13,
                center: defaultLocation,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true
            }
        );

        const bounds = new google.maps.LatLngBounds();
        const infoWindow = new google.maps.InfoWindow();

        households.forEach(household => {

            const position = {
                lat: parseFloat(household.latitude),
                lng: parseFloat(household.longitude)
            };

            const marker = new google.maps.Marker({
                position: position,
                map: map,
                title: household.fullname
            });

            const content = `
                <div>
                    <strong>${escapeHTML(household.fullname)}</strong>
                    <br>
                    ${escapeHTML(household.house_address)}
                    <br>
                    <small>Household ID: ${escapeHTML(household.id)}</small>
                </div>
            `;

            marker.addListener("click", () => {
                infoWindow.setContent(content);
                infoWindow.open(map, marker);
            });

            bounds.extend(position);
        });

        if (households.length === 1) {
            map.setCenter(bounds.getCenter());
            map.setZoom(16);
        } else if (households.length > 1) {
            map.fitBounds(bounds);
        }
    }

    function escapeHTML(value) {
        return String(value).replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[char]);
    }

    function startRouteFromMyLocation() {

        const message = document.getElementById("routeMessage");

        if (!navigator.geolocation) {
            message.textContent = "Your browser does not support GPS.";
            return;
        }

        message.textContent = "Getting your current location...";

        navigator.geolocation.getCurrentPosition(

            async function(position) {

                const startLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };

                if (routeStops.length === 0) {
                    message.textContent =
                        "No households found in the latest regular route.";
                    return;
                }

                message.textContent =
                    "Location detected. Calculating driving route...";

                const lastStop = routeStops[routeStops.length - 1];

                const destination = {
                    lat: Number(lastStop.latitude),
                    lng: Number(lastStop.longitude)
                };

                const request = {
                    origin: startLocation,
                    destination: destination,

                    intermediates: routeStops.slice(0, -1).map(stop => ({
                        location: {
                            lat: Number(stop.latitude),
                            lng: Number(stop.longitude)
                        }
                    })),

                    travelMode: "DRIVING",
                    optimizeWaypointOrder: true,

                    fields: [
                        "path",
                        "optimizedIntermediateWaypointIndices"
                    ]
                };

                try {

                    const { routes } = await Route.computeRoutes(request);

                    if (!routes || routes.length === 0) {
                        message.textContent = "No driving route found.";
                        return;
                    }

                    const polylines = routes[0].createPolylines();

                    polylines.forEach(polyline => {
                        polyline.setMap(map);
                    });

                    message.textContent =
                        "Driving route displayed successfully!";

                } catch (error) {

                    message.textContent = "Route error: " + error.message;
                    console.error("Routes API error:", error);

                }
            },

            function(error) {

                message.textContent =
                    "Unable to get your location. Please allow location access.";

                console.error(error);

            },

            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            }
        );
    }
</script>

<script async
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCz8mz80N6FN03Q1q1nQFGeZLduXsZpNHY&callback=initMap">
</script>

</body>
</html>
