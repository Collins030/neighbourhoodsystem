<?php
// neighbourhood_map.php - Enhanced Interactive neighbourhood map using events database
require_once 'config.php';

// Check if user is logged in
$user = verifyUserSession();
if (!$user) {
    header('Location: index.php');
    exit;
}

// Initialize database and ensure required columns exist
try {
    $pdo = new PDO("mysql:host=localhost;dbname=neighbourhood_system", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Ensure required columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'latitude'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE events ADD COLUMN latitude DECIMAL(10, 8) NULL");
        $pdo->exec("ALTER TABLE events ADD COLUMN longitude DECIMAL(11, 8) NULL");
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'status'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE events ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
        // Update existing events to have active status
        $pdo->exec("UPDATE events SET status = 'active' WHERE status IS NULL");
    }
    
    // Fetch all events with location data (both upcoming and past for historical view)
    $stmt = $pdo->prepare("
        SELECT e.*, u.full_name as organizer_name,
               CASE WHEN ea.user_id IS NOT NULL THEN 1 ELSE 0 END as is_attending,
               COUNT(ea2.user_id) as attendee_count,
               CASE WHEN e.event_date >= NOW() THEN 'upcoming' ELSE 'past' END as event_status
        FROM events e
        JOIN users u ON e.user_id = u.id
        LEFT JOIN event_attendees ea ON e.id = ea.event_id AND ea.user_id = ?
        LEFT JOIN event_attendees ea2 ON e.id = ea2.event_id
        WHERE e.status = 'active' AND e.latitude IS NOT NULL AND e.longitude IS NOT NULL
        GROUP BY e.id, u.full_name, ea.user_id
        ORDER BY e.event_date DESC
    ");
    $stmt->execute([$user['id']]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separate upcoming and past events
    $upcomingEvents = array_filter($events, function($event) {
        return $event['event_status'] === 'upcoming';
    });
    
    $pastEvents = array_filter($events, function($event) {
        return $event['event_status'] === 'past';
    });
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $events = [];
    $upcomingEvents = [];
    $pastEvents = [];
}

// Handle AJAX requests for event actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'toggle_attendance') {
        $eventId = $_POST['event_id'];
        
        try {
            // Check if user is already attending
            $stmt = $pdo->prepare("SELECT * FROM event_attendees WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$eventId, $user['id']]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Remove attendance
                $stmt = $pdo->prepare("DELETE FROM event_attendees WHERE event_id = ? AND user_id = ?");
                $stmt->execute([$eventId, $user['id']]);
                $attending = false;
            } else {
                // Add attendance
                $stmt = $pdo->prepare("INSERT INTO event_attendees (event_id, user_id) VALUES (?, ?)");
                $stmt->execute([$eventId, $user['id']]);
                $attending = true;
            }
            
            // Get updated attendee count
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM event_attendees WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $attendeeCount = $stmt->fetch()['count'];
            
            echo json_encode([
                'success' => true,
                'attending' => $attending,
                'attendee_count' => $attendeeCount
            ]);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'get_event_details') {
        $eventId = $_POST['event_id'];
        
        try {
            $stmt = $pdo->prepare("
                SELECT e.*, u.full_name as organizer_name,
                       CASE WHEN ea.user_id IS NOT NULL THEN 1 ELSE 0 END as is_attending,
                       COUNT(ea2.user_id) as attendee_count
                FROM events e
                JOIN users u ON e.user_id = u.id
                LEFT JOIN event_attendees ea ON e.id = ea.event_id AND ea.user_id = ?
                LEFT JOIN event_attendees ea2 ON e.id = ea2.event_id
                WHERE e.id = ? AND e.status = 'active'
                GROUP BY e.id, u.full_name, ea.user_id
            ");
            $stmt->execute([$user['id'], $eventId]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($event) {
                echo json_encode(['success' => true, 'event' => $event]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Event not found']);
            }
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
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
            position: relative;
            z-index: 1000;
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

        .map-container {
            position: relative;
            height: calc(100vh - 80px);
            width: 100%;
        }

        #map {
            height: 100%;
            width: 100%;
        }

        .map-controls {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .control-panel {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            min-width: 250px;
        }

        .control-panel h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 1.1em;
        }

        .filter-group {
            margin-bottom: 15px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }

        .filter-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 5px 10px;
            border: 2px solid #e1e5e9;
            background: white;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 12px;
        }

        .filter-btn.active {
            background: #4facfe;
            color: white;
            border-color: #4facfe;
        }

        .filter-btn:hover {
            border-color: #4facfe;
        }

        .stats-panel {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .stat-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .stat-number {
            font-size: 1.5em;
            font-weight: 700;
            color: #4facfe;
        }

        .stat-label {
            font-size: 0.9em;
            color: #666;
        }

        .legend {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }

        .legend-marker {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 10px;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .upcoming-marker {
            background: #28a745;
        }

        .past-marker {
            background: #6c757d;
        }

        .my-event-marker {
            background: #ffc107;
        }

        .legend-text {
            font-size: 0.9em;
            color: #555;
        }

        /* Custom popup styles */
        .leaflet-popup-content-wrapper {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .event-popup {
            max-width: 350px;
            padding: 0;
        }

        .event-popup-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 15px;
            border-radius: 10px 10px 0 0;
            margin: -20px -20px 15px -20px;
        }

        .event-popup-title {
            font-size: 1.2em;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .event-popup-organizer {
            font-size: 0.9em;
            opacity: 0.9;
        }

        .event-popup-body {
            padding: 0 20px 20px 20px;
            margin-top: -15px;
        }

        .event-detail {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            color: #555;
        }

        .event-detail-icon {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .event-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .event-btn {
            flex: 1;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9em;
        }

        .btn-primary {
            background: #4facfe;
            color: white;
        }

        .btn-primary:hover {
            background: #3d8bfe;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .attendee-count {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
            color: #666;
        }

        .mobile-toggle {
            display: none;
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 1001;
            background: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            font-size: 1.2em;
        }

        @media (max-width: 768px) {
            .map-controls {
                position: fixed;
                top: 100px;
                right: -280px;
                transition: right 0.3s ease;
                max-height: calc(100vh - 120px);
                overflow-y: auto;
            }

            .map-controls.active {
                right: 20px;
            }

            .mobile-toggle {
                display: block;
            }

            .control-panel {
                min-width: 260px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .loading-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #4facfe;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
            </div>
        </div>
    </div>

    <div class="map-container">
        <div id="map"></div>
        
        <button class="mobile-toggle" onclick="toggleControls()">⚙️</button>
        
        <div class="map-controls" id="mapControls">
            <div class="control-panel">
                <h3>🔍 Filter Events</h3>
                
                <div class="filter-group">
                    <label>Event Status:</label>
                    <div class="filter-buttons">
                        <button class="filter-btn active" data-filter="all">All</button>
                        <button class="filter-btn" data-filter="upcoming">Upcoming</button>
                        <button class="filter-btn" data-filter="past">Past</button>
                        <button class="filter-btn" data-filter="my-events">My Events</button>
                    </div>
                </div>
                
                <div class="filter-group">
                    <label>Actions:</label>
                    <div class="filter-buttons">
                        <button class="filter-btn" onclick="centerOnUser()">📍 My Location</button>
                        <button class="filter-btn" onclick="fitAllMarkers()">🎯 View All</button>
                    </div>
                </div>
            </div>
            
            <div class="stats-panel">
                <h3>📊 Statistics</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($upcomingEvents); ?></div>
                        <div class="stat-label">Upcoming Events</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($pastEvents); ?></div>
                        <div class="stat-label">Past Events</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count(array_filter($events, function($e) use ($user) { return $e['user_id'] == $user['id']; })); ?></div>
                        <div class="stat-label">My Events</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count(array_filter($events, function($e) { return $e['is_attending']; })); ?></div>
                        <div class="stat-label">Attending</div>
                    </div>
                </div>
            </div>
            
            <div class="legend">
                <h3>📍 Legend</h3>
                <div class="legend-item">
                    <div class="legend-marker upcoming-marker"></div>
                    <div class="legend-text">Upcoming Events</div>
                </div>
                <div class="legend-item">
                    <div class="legend-marker past-marker"></div>
                    <div class="legend-text">Past Events</div>
                </div>
                <div class="legend-item">
                    <div class="legend-marker my-event-marker"></div>
                    <div class="legend-text">My Events</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    <script>
        // Initialize map
        let map = L.map('map').setView([-1.2921, 36.8219], 13); // Default to Nairobi coordinates
        let markersGroup = L.markerClusterGroup({
            maxClusterRadius: 50,
            disableClusteringAtZoom: 16
        });
        let allMarkers = [];
        let currentFilter = 'all';

        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Events data from PHP
        const eventsData = <?php echo json_encode($events); ?>;
        const currentUserId = <?php echo $user['id']; ?>;

        // Initialize markers
        function initializeMarkers() {
            eventsData.forEach(event => {
                const lat = parseFloat(event.latitude);
                const lng = parseFloat(event.longitude);
                
                if (isNaN(lat) || isNaN(lng)) return;

                // Determine marker color based on event status and ownership
                let markerColor = '#28a745'; // Green for upcoming
                if (event.event_status === 'past') {
                    markerColor = '#6c757d'; // Gray for past
                } else if (event.user_id == currentUserId) {
                    markerColor = '#ffc107'; // Yellow for my events
                }

                // Create custom marker
                const marker = L.marker([lat, lng], {
                    icon: L.divIcon({
                        className: 'custom-div-icon',
                        html: `<div style="background-color: ${markerColor}; width: 25px; height: 25px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;">${event.event_status === 'upcoming' ? '📅' : '📋'}</div>`,
                        iconSize: [25, 25],
                        iconAnchor: [12, 12]
                    })
                });

                // Add event data to marker
                marker.eventData = event;
                marker.eventStatus = event.event_status;
                marker.isMyEvent = event.user_id == currentUserId;

                // Create popup content
                const popupContent = createPopupContent(event);
                marker.bindPopup(popupContent, {
                    maxWidth: 350,
                    className: 'event-popup'
                });

                // Add to arrays
                allMarkers.push(marker);
                markersGroup.addLayer(marker);
            });

            map.addLayer(markersGroup);
        }

        // Create popup content
        function createPopupContent(event) {
            const eventDate = new Date(event.event_date);
            const endDate = event.end_date ? new Date(event.end_date) : null;
            const isUpcoming = event.event_status === 'upcoming';
            const isMyEvent = event.user_id == currentUserId;
            const isAttending = event.is_attending == 1;

            let statusBadge = '';
            if (isMyEvent) {
                statusBadge = '<span style="background: #ffc107; color: #333; padding: 2px 8px; border-radius: 10px; font-size: 0.8em;">My Event</span>';
            } else if (isUpcoming) {
                statusBadge = '<span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8em;">Upcoming</span>';
            } else {
                statusBadge = '<span style="background: #6c757d; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8em;">Past Event</span>';
            }

            return `
                <div class="event-popup-header">
                    <div class="event-popup-title">${event.title}</div>
                    <div class="event-popup-organizer">by ${event.organizer_name} ${statusBadge}</div>
                </div>
                <div class="event-popup-body">
                    <div class="event-detail">
                        <div class="event-detail-icon">📅</div>
                        <div>${eventDate.toLocaleDateString()} at ${eventDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                    </div>
                    ${endDate ? `<div class="event-detail">
                        <div class="event-detail-icon">⏰</div>
                        <div>Until ${endDate.toLocaleDateString()} at ${endDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                    </div>` : ''}
                    <div class="event-detail">
                        <div class="event-detail-icon">📍</div>
                        <div>${event.location}</div>
                    </div>
                    ${event.address ? `<div class="event-detail">
                        <div class="event-detail-icon">🏠</div>
                        <div>${event.address}</div>
                    </div>` : ''}
                    ${event.description ? `<div class="event-detail">
                        <div class="event-detail-icon">📝</div>
                        <div>${event.description}</div>
                    </div>` : ''}
                    <div class="event-detail">
                        <div class="event-detail-icon">👥</div>
                        <div class="attendee-count">
                            <span id="attendee-count-${event.id}">${event.attendee_count}</span> attending
                            ${event.max_attendees ? `/ ${event.max_attendees} max` : ''}
                        </div>
                    </div>
                    ${isUpcoming ? `
                        <div class="event-actions">
                            ${!isMyEvent ? `
                                <button class="event-btn ${isAttending ? 'btn-success' : 'btn-primary'}" 
                                        onclick="toggleAttendance(${event.id})" 
                                        id="attend-btn-${event.id}">
                                    ${isAttending ? '✓ Attending' : '+ Join Event'}
                                </button>
                            ` : ''}
                            <button class="event-btn btn-secondary" onclick="viewEventDetails(${event.id})">
                                View Details
                            </button>
                        </div>
                    ` : `
                        <div class="event-actions">
                            <button class="event-btn btn-secondary" onclick="viewEventDetails(${event.id})">
                                View Details
                            </button>
                        </div>
                    `}
                </div>
            `;
        }

        // Filter events
        function filterEvents(filter) {
            currentFilter = filter;
            
            // Clear existing markers
            markersGroup.clearLayers();
            
            // Filter markers based on selection
            let filteredMarkers = allMarkers;
            
            switch (filter) {
                case 'upcoming':
                    filteredMarkers = allMarkers.filter(marker => marker.eventStatus === 'upcoming');
                    break;
                case 'past':
                    filteredMarkers = allMarkers.filter(marker => marker.eventStatus === 'past');
                    break;
                case 'my-events':
                    filteredMarkers = allMarkers.filter(marker => marker.isMyEvent);
                    break;
                default:
                    filteredMarkers = allMarkers;
            }
            
            // Add filtered markers to map
            filteredMarkers.forEach(marker => {
                markersGroup.addLayer(marker);
            });
            
            // Update active button
            document.querySelectorAll('.filter-btn[data-filter]').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-filter="${filter}"]`).classList.add('active');
        }

        // Event filter buttons
        document.querySelectorAll('.filter-btn[data-filter]').forEach(btn => {
            btn.addEventListener('click', function() {
                filterEvents(this.dataset.filter);
            });
        });

        // Toggle attendance
        function toggleAttendance(eventId) {
            const attendBtn = document.getElementById(`attend-btn-${eventId}`);
            const attendCountSpan = document.getElementById(`attendee-count-${eventId}`);
            
            // Disable button during request
            attendBtn.disabled = true;
            attendBtn.textContent = 'Processing...';
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=toggle_attendance&event_id=${eventId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update button
                    if (data.attending) {
                        attendBtn.textContent = '✓ Attending';
                        attendBtn.className = 'event-btn btn-success';
                    } else {
                        attendBtn.textContent = '+ Join Event';
                        attendBtn.className = 'event-btn btn-primary';
                    }
                    
                    // Update attendee count
                    attendCountSpan.textContent = data.attendee_count;
                    
                    // Update marker data
                    const marker = allMarkers.find(m => m.eventData.id == eventId);
                    if (marker) {
                        marker.eventData.is_attending = data.attending ? 1 : 0;
                        marker.eventData.attendee_count = data.attendee_count;
                    }
                } else {
                    alert('Error: ' + data.error);
                }
                
                attendBtn.disabled = false;
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
                attendBtn.disabled = false;
                attendBtn.textContent = 'Join Event';
            });
        }

        // View event details
        // View event details
        function viewEventDetails(eventId) {
            // Redirect to event details page or open in new tab
            window.open(`event_details.php?id=${eventId}`, '_blank');
        }

        // Center map on user location
        function centerOnUser() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    map.setView([lat, lng], 15);
                    
                    // Add a temporary marker for user location
                    const userMarker = L.marker([lat, lng], {
                        icon: L.divIcon({
                            className: 'user-location-marker',
                            html: '<div style="background-color: #007bff; width: 15px; height: 15px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3); animation: pulse 2s infinite;"></div>',
                            iconSize: [15, 15],
                            iconAnchor: [7, 7]
                        })
                    }).addTo(map);
                    
                    // Remove user marker after 5 seconds
                    setTimeout(() => {
                        map.removeLayer(userMarker);
                    }, 5000);
                    
                }, function(error) {
                    alert('Unable to get your location. Please check your browser settings.');
                });
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        }

        // Fit all markers in view
        function fitAllMarkers() {
            if (markersGroup.getLayers().length > 0) {
                map.fitBounds(markersGroup.getBounds(), {
                    padding: [20, 20]
                });
            } else {
                // If no markers, center on default location
                map.setView([-1.2921, 36.8219], 13);
            }
        }

        // Toggle mobile controls
        function toggleControls() {
            const controls = document.getElementById('mapControls');
            controls.classList.toggle('active');
        }

        // Close mobile controls when clicking outside
        document.addEventListener('click', function(event) {
            const controls = document.getElementById('mapControls');
            const toggleBtn = document.querySelector('.mobile-toggle');
            
            if (!controls.contains(event.target) && !toggleBtn.contains(event.target)) {
                controls.classList.remove('active');
            }
        });

        // Handle map resize
        window.addEventListener('resize', function() {
            map.invalidateSize();
        });

        // Add pulse animation for user location
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% {
                    transform: scale(1);
                    opacity: 1;
                }
                50% {
                    transform: scale(1.3);
                    opacity: 0.7;
                }
                100% {
                    transform: scale(1);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(style);

        // Initialize the map with markers
        initializeMarkers();

        // Auto-fit map to show all markers on load
        setTimeout(() => {
            fitAllMarkers();
        }, 500);

        // Add search functionality
        function searchEvents(query) {
            const searchTerm = query.toLowerCase();
            const filteredMarkers = allMarkers.filter(marker => {
                const event = marker.eventData;
                return event.title.toLowerCase().includes(searchTerm) ||
                       event.description.toLowerCase().includes(searchTerm) ||
                       event.location.toLowerCase().includes(searchTerm) ||
                       event.organizer_name.toLowerCase().includes(searchTerm);
            });

            // Clear existing markers
            markersGroup.clearLayers();
            
            // Add filtered markers
            filteredMarkers.forEach(marker => {
                markersGroup.addLayer(marker);
            });

            // Fit view to filtered markers
            if (filteredMarkers.length > 0) {
                map.fitBounds(markersGroup.getBounds(), {
                    padding: [20, 20]
                });
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            // Press 'L' to center on user location
            if (event.key === 'l' || event.key === 'L') {
                if (!event.target.matches('input, textarea')) {
                    event.preventDefault();
                    centerOnUser();
                }
            }
            
            // Press 'F' to fit all markers
            if (event.key === 'f' || event.key === 'F') {
                if (!event.target.matches('input, textarea')) {
                    event.preventDefault();
                    fitAllMarkers();
                }
            }
            
            // Press 'Escape' to close mobile controls
            if (event.key === 'Escape') {
                document.getElementById('mapControls').classList.remove('active');
            }
        });

        // Add tooltip for keyboard shortcuts
        const helpTooltip = document.createElement('div');
        helpTooltip.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px;
            border-radius: 5px;
            font-size: 12px;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        `;
        helpTooltip.innerHTML = `
            <strong>Keyboard Shortcuts:</strong><br>
            L - Center on your location<br>
            F - Fit all markers<br>
            ESC - Close controls
        `;
        document.body.appendChild(helpTooltip);

        // Show tooltip on hover over map
        let tooltipTimeout;
        map.getContainer().addEventListener('mouseenter', function() {
            tooltipTimeout = setTimeout(() => {
                helpTooltip.style.opacity = '1';
            }, 1000);
        });

        map.getContainer().addEventListener('mouseleave', function() {
            clearTimeout(tooltipTimeout);
            helpTooltip.style.opacity = '0';
        });

        // Performance optimization: debounce resize events
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                map.invalidateSize();
            }, 100);
        });

        console.log('Neighbourhood Map initialized successfully!');
        console.log(`Loaded ${eventsData.length} events on the map`);
    </script>
</body>
</html>