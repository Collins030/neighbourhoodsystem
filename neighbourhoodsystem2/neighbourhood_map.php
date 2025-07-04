<?php
// neighbourhood_map.php - Enhanced Interactive neighbourhood map with event integration

require_once 'config.php';

// Check if user is logged in
$user = verifyUserSession();
if (!$user) {
    header('Location: index.php');
    exit;
}

// Fetch events with location data
try {
    $pdo = new PDO("mysql:host=localhost;dbname=neighbourhood_system", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // First, ensure latitude and longitude columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'latitude'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE events ADD COLUMN latitude DECIMAL(10, 8) NULL");
        $pdo->exec("ALTER TABLE events ADD COLUMN longitude DECIMAL(11, 8) NULL");
    }
    
    $stmt = $pdo->prepare("
        SELECT e.*, u.full_name as organizer_name,
               CASE WHEN ea.user_id IS NOT NULL THEN 1 ELSE 0 END as is_attending,
               COUNT(ea2.user_id) as attendee_count
        FROM events e
        JOIN users u ON e.user_id = u.id
        LEFT JOIN event_attendees ea ON e.id = ea.event_id AND ea.user_id = ?
        LEFT JOIN event_attendees ea2 ON e.id = ea2.event_id
        WHERE e.status = 'active' AND e.event_date >= NOW()
        GROUP BY e.id, u.full_name, ea.user_id
        ORDER BY e.event_date ASC
    ");
    $stmt->execute([$user['id']]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create neighbourhood_locations table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS neighbourhood_locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        type VARCHAR(100) NOT NULL,
        description TEXT,
        latitude DECIMAL(10, 8) NOT NULL,
        longitude DECIMAL(11, 8) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Insert some default landmarks if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM neighbourhood_locations");
    if ($stmt->fetchColumn() == 0) {
        $landmarks = [
            ['name' => 'Nairobi National Park', 'type' => 'Park', 'description' => 'Wildlife park near the city', 'lat' => -1.3731, 'lng' => 36.8577],
            ['name' => 'Uhuru Park', 'type' => 'Park', 'description' => 'Central recreational park', 'lat' => -1.2921, 'lng' => 36.8219],
            ['name' => 'KICC', 'type' => 'Landmark', 'description' => 'Kenyatta International Convention Centre', 'lat' => -1.2884, 'lng' => 36.8233],
            ['name' => 'Nairobi City Market', 'type' => 'Market', 'description' => 'Traditional market', 'lat' => -1.2841, 'lng' => 36.8155],
            ['name' => 'University of Nairobi', 'type' => 'Institution', 'description' => 'Main university campus', 'lat' => -1.2966, 'lng' => 36.8083]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO neighbourhood_locations (name, type, description, latitude, longitude) VALUES (?, ?, ?, ?, ?)");
        foreach ($landmarks as $landmark) {
            $stmt->execute([$landmark['name'], $landmark['type'], $landmark['description'], $landmark['lat'], $landmark['lng']]);
        }
    }
    
    // Fetch neighbourhood locations
    $stmt = $pdo->prepare("SELECT * FROM neighbourhood_locations ORDER BY created_at DESC");
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
    $events = [];
    $locations = [];
}

// Handle event attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'attend_event') {
        $event_id = $_POST['event_id'];
        
        try {
            // Check if user is already attending
            $stmt = $pdo->prepare("SELECT * FROM event_attendees WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$event_id, $user['id']]);
            
            if ($stmt->rowCount() == 0) {
                // Add attendance
                $stmt = $pdo->prepare("INSERT INTO event_attendees (event_id, user_id) VALUES (?, ?)");
                $stmt->execute([$event_id, $user['id']]);
                echo json_encode(['success' => true, 'message' => 'Successfully registered for event!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'You are already registered for this event.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error registering for event: ' . $e->getMessage()]);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neighbourhood Map - Neighbourhood Connect</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5em;
            font-weight: 700;
        }

        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 20px;
            transition: background 0.3s ease;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .nav-links a.active {
            background: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .map-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 20px;
            height: calc(100vh - 120px);
        }

        .map-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .map-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .map-header h1 {
            font-size: 2em;
            margin-bottom: 5px;
        }

        .map-header p {
            opacity: 0.9;
        }

        #map {
            height: calc(100% - 80px);
            width: 100%;
        }

        .sidebar {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        .map-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .control-btn {
            padding: 8px 16px;
            border: 2px solid #4facfe;
            background: white;
            color: #4facfe;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 12px;
            flex: 1;
        }

        .control-btn:hover {
            background: #4facfe;
            color: white;
        }

        .control-btn.active {
            background: #4facfe;
            color: white;
        }

        .legend {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .legend h3 {
            margin-bottom: 10px;
            color: #333;
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }

        .legend-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
        }

        .legend-icon.event {
            background: #dc3545;
        }

        .legend-icon.park {
            background: #28a745;
        }

        .legend-icon.landmark {
            background: #007bff;
        }

        .legend-icon.market {
            background: #ffc107;
            color: #333;
        }

        .legend-icon.institution {
            background: #6f42c1;
        }

        .events-list {
            margin-top: 20px;
        }

        .event-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #dc3545;
            transition: transform 0.2s ease;
        }

        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .event-card h4 {
            color: #333;
            margin-bottom: 8px;
        }

        .event-card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .event-card .event-date {
            color: #dc3545;
            font-weight: bold;
        }

        .event-card .event-location {
            color: #28a745;
        }

        .event-card .event-attendees {
            color: #007bff;
        }

        .event-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .event-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .event-btn.primary {
            background: #dc3545;
            color: white;
        }

        .event-btn.primary:hover {
            background: #c82333;
        }

        .event-btn.secondary {
            background: #6c757d;
            color: white;
        }

        .event-btn.secondary:hover {
            background: #545b62;
        }

        .event-btn.success {
            background: #28a745;
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 1.5em;
            margin-bottom: 5px;
        }

        .stat-card p {
            font-size: 12px;
            opacity: 0.9;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }

        .quick-action {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            transition: transform 0.2s ease;
        }

        .quick-action:hover {
            transform: translateY(-2px);
            color: white;
        }

        .quick-action i {
            font-size: 1.5em;
            margin-bottom: 5px;
        }

        .location-card {
            background: #e3f2fd;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #2196f3;
        }

        .location-card h4 {
            color: #1976d2;
            margin-bottom: 8px;
        }

        .location-card p {
            color: #424242;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .location-card .location-type {
            background: #2196f3;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            display: inline-block;
        }

        @media (max-width: 1024px) {
            .map-layout {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .sidebar {
                order: -1;
                height: auto;
            }
            
            #map {
                height: 60vh;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .map-controls {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">🏘️ Neighbourhood Connect</div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="browse_events.php">Browse Events</a>
                <a href="create_events.php">Create Event</a>
                <a href="community_chat.php">Community Chat</a>
                <a href="neighbourhood_map.php" class="active">Map</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="map-layout">
            <div class="map-container">
                <div class="map-header">
                    <h1>🗺️ Neighbourhood Map</h1>
                    <p>Explore events and landmarks in your area</p>
                </div>
                <div id="map"></div>
            </div>

            <div class="sidebar">
                <div class="sidebar-header">
                    <h3>Map Controls</h3>
                </div>
                <div class="sidebar-content">
                    <div class="map-controls">
                        <button class="control-btn active" id="showAll">All</button>
                        <button class="control-btn" id="showEvents">Events</button>
                        <button class="control-btn" id="showLandmarks">Landmarks</button>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3><?php echo count($events); ?></h3>
                            <p>Active Events</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo count($locations); ?></h3>
                            <p>Landmarks</p>
                        </div>
                    </div>

                    <div class="quick-actions">
                        <a href="create_events.php" class="quick-action">
                            <div>📅</div>
                            <div>Create Event</div>
                        </a>
                        <a href="browse_events.php" class="quick-action">
                            <div>🔍</div>
                            <div>Browse Events</div>
                        </a>
                    </div>

                    <div class="legend">
                        <h3>Map Legend</h3>
                        <div class="legend-item">
                            <div class="legend-icon event">E</div>
                            <span>Events</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-icon park">P</div>
                            <span>Parks</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-icon landmark">L</div>
                            <span>Landmarks</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-icon market">M</div>
                            <span>Markets</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-icon institution">I</div>
                            <span>Institutions</span>
                        </div>
                    </div>

                    <div class="events-list">
                        <h3>Upcoming Events</h3>
                        <?php if (!empty($events)): ?>
                            <?php foreach (array_slice($events, 0, 5) as $event): ?>
                                <div class="event-card" data-event-id="<?php echo $event['id']; ?>">
                                    <h4><?php echo htmlspecialchars($event['title']); ?></h4>
                                    <p class="event-date">📅 <?php echo date('M j, Y g:i A', strtotime($event['event_date'])); ?></p>
                                    <p class="event-location">📍 <?php echo htmlspecialchars($event['location']); ?></p>
                                    <p class="event-attendees">👥 <?php echo $event['attendee_count']; ?> attending</p>
                                    <div class="event-actions">
                                        <button class="event-btn primary" onclick="centerOnEvent(<?php echo $event['latitude']; ?>, <?php echo $event['longitude']; ?>)">
                                            View on Map
                                        </button>
                                        <?php if (!$event['is_attending']): ?>
                                            <button class="event-btn secondary" onclick="attendEvent(<?php echo $event['id']; ?>)">
                                                Join
                                            </button>
                                        <?php else: ?>
                                            <button class="event-btn success" disabled>
                                                Joined
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No upcoming events with location data.</p>
                        <?php endif; ?>
                    </div>

                    <div class="location-list">
                        <h3>Nearby Landmarks</h3>
                        <?php foreach (array_slice($locations, 0, 5) as $location): ?>
                            <div class="location-card">
                                <h4><?php echo htmlspecialchars($location['name']); ?></h4>
                                <p><?php echo htmlspecialchars($location['description']); ?></p>
                                <span class="location-type"><?php echo htmlspecialchars($location['type']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize the map
        let map = L.map('map').setView([-1.2921, 36.8219], 13);
        
        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Layer groups for different marker types
        let eventMarkers = L.layerGroup();
        let landmarkMarkers = L.layerGroup();
        let allMarkers = L.layerGroup();

        // Add all layers to map initially
        eventMarkers.addTo(map);
        landmarkMarkers.addTo(map);

        // Custom marker icons
        function createCustomIcon(type, letter) {
            let color;
            switch(type) {
                case 'event': color = '#dc3545'; break;
                case 'Park': color = '#28a745'; break;
                case 'Landmark': color = '#007bff'; break;
                case 'Market': color = '#ffc107'; break;
                case 'Institution': color = '#6f42c1'; break;
                default: color = '#6c757d';
            }
            
            return L.divIcon({
                className: 'custom-div-icon',
                html: `<div style="background-color: ${color}; width: 30px; height: 30px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;">${letter}</div>`,
                iconSize: [30, 30],
                iconAnchor: [15, 15],
                popupAnchor: [0, -15]
            });
        }

        // Add event markers
        <?php foreach ($events as $event): ?>
            <?php if ($event['latitude'] && $event['longitude']): ?>
                let eventMarker<?php echo $event['id']; ?> = L.marker([<?php echo $event['latitude']; ?>, <?php echo $event['longitude']; ?>], {
                    icon: createCustomIcon('event', 'E')
                }).bindPopup(`
                    <div style="min-width: 200px;">
                        <h4><?php echo htmlspecialchars($event['title']); ?></h4>
                        <p><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($event['event_date'])); ?></p>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($event['location']); ?></p>
                        <p><strong>Organizer:</strong> <?php echo htmlspecialchars($event['organizer_name']); ?></p>
                        <p><strong>Attendees:</strong> <?php echo $event['attendee_count']; ?></p>
                        <?php if ($event['description']): ?>
                            <p><strong>Description:</strong> <?php echo htmlspecialchars(substr($event['description'], 0, 100)); ?>...</p>
                        <?php endif; ?>
                        <div style="margin-top: 10px;">
                            <?php if (!$event['is_attending']): ?>
                                <button onclick="attendEvent(<?php echo $event['id']; ?>)" style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">Join Event</button>
                            <?php else: ?>
                                <button disabled style="background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 5px;">Already Joined</button>
                            <?php endif; ?>
                        </div>
                    </div>
                `);
                eventMarkers.addLayer(eventMarker<?php echo $event['id']; ?>);
            <?php endif; ?>
        <?php endforeach; ?>

        // Add landmark markers
        <?php foreach ($locations as $location): ?>
            let landmarkMarker<?php echo $location['id']; ?> = L.marker([<?php echo $location['latitude']; ?>, <?php echo $location['longitude']; ?>], {
                icon: createCustomIcon('<?php echo $location['type']; ?>', '<?php echo strtoupper(substr($location['type'], 0, 1)); ?>')
            }).bindPopup(`
                <div style="min-width: 200px;">
                    <h4><?php echo htmlspecialchars($location['name']); ?></h4>
                    <p><strong>Type:</strong> <?php echo htmlspecialchars($location['type']); ?></p>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($location['description']); ?></p>
                </div>
            `);
            landmarkMarkers.addLayer(landmarkMarker<?php echo $location['id']; ?>);
        <?php endforeach; ?>

        // Control buttons functionality
        document.getElementById('showAll').addEventListener('click', function() {
            map.addLayer(eventMarkers);
            map.addLayer(landmarkMarkers);
            updateActiveButton('showAll');
        });

        document.getElementById('showEvents').addEventListener('click', function() {
            map.addLayer(eventMarkers);
            map.removeLayer(landmarkMarkers);
            updateActiveButton('showEvents');
        });

        document.getElementById('showLandmarks').addEventListener('click', function() {
            map.removeLayer(eventMarkers);
            map.addLayer(landmarkMarkers);
            updateActiveButton('showLandmarks');
        });

        function updateActiveButton(activeId) {
            document.querySelectorAll('.control-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.getElementById(activeId).classList.add('active');
        }

        // Center map on specific event
        function centerOnEvent(lat, lng) {
            map.setView([lat, lng], 16);
        }

        // Attend event function
        function attendEvent(eventId) {
            fetch('neighbourhood_map.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=attend_event&event_id=${eventId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload(); // Refresh to update UI
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error registering for event');
            });
        }

        // Add user's current location if available
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const userLat = position.coords.latitude;
                const userLng = position.coords.longitude;
                
                L.marker([userLat, userLng], {
                    icon: createCustomIcon('user', 'U')
                }).addTo(map)
                .bindPopup('Your Current Location');
                
                // Optionally center the map on user's location
                // map.setView([userLat, userLng], 14);
            });
        }

        // Add click event to event cards to center map
        document.querySelectorAll('.event-card').forEach(card => {
            card.addEventListener('click', function() {
                const eventId = this.dataset.eventId;
                // You can add logic here to center on the specific event
            });
        });
    </script>
</body>
</html>