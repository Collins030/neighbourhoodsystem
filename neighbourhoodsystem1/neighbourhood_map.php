<?php
// neighbourhood_map.php - Interactive neighbourhood map feature

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
    
    $stmt = $pdo->prepare("
        SELECT e.*, u.full_name as organizer_name,
               CASE WHEN ea.user_id IS NOT NULL THEN 1 ELSE 0 END as is_attending
        FROM events e
        JOIN users u ON e.user_id = u.id
        LEFT JOIN event_attendees ea ON e.id = ea.event_id AND ea.user_id = ?
        WHERE e.status = 'active' AND e.event_date >= NOW()
        ORDER BY e.event_date ASC
    ");
    $stmt->execute([$user['id']]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Also fetch neighbourhood locations
    $stmt = $pdo->prepare("
        SELECT * FROM neighbourhood_locations
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
    $events = [];
    $locations = [];
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            background: white;
            border-radius: 15px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .page-header h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 15px;
        }

        .page-header p {
            color: #666;
            font-size: 1.1em;
        }

        .map-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .map-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filter-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #4facfe;
            background: white;
            color: #4facfe;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn.active {
            background: #4facfe;
            color: white;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
        }

        .map-legend {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
        }

        .legend-marker {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
        }

        .marker-event {
            background: #4facfe;
        }

        .marker-attending {
            background: #28a745;
        }

        .marker-landmark {
            background: #ffc107;
        }

        #map {
            height: 500px;
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .info-panel {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .info-panel h3 {
            color: #333;
            margin-bottom: 20px;
        }

        .events-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .event-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #4facfe;
        }

        .event-card.attending {
            border-left-color: #28a745;
        }

        .event-card h4 {
            color: #333;
            margin-bottom: 10px;
        }

        .event-meta {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 10px;
        }

        .event-meta span {
            color: #666;
            font-size: 0.9em;
        }

        .event-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #4facfe;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .location-search {
            margin-bottom: 20px;
        }

        .location-search input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
        }

        .location-search input:focus {
            outline: none;
            border-color: #4facfe;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        @media (max-width: 768px) {
            .map-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-controls {
                justify-content: center;
            }
            
            .map-legend {
                justify-content: center;
            }
            
            .container {
                padding: 20px 10px;
            }
            
            .page-header {
                padding: 20px;
            }
            
            .page-header h1 {
                font-size: 2em;
            }
            
            .events-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">Neighbourhood Connect</div>
            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="events.php">Events</a>
                <a href="neighbourhood_map.php">Map</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="alert alert-info">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h1>Neighbourhood Map</h1>
            <p>Discover events and landmarks in your neighbourhood</p>
        </div>

        <div class="map-container">
            <div class="map-controls">
                <div class="filter-controls">
                    <button class="filter-btn active" data-filter="all">All Items</button>
                    <button class="filter-btn" data-filter="events">Events</button>
                    <button class="filter-btn" data-filter="attending">My Events</button>
                    <button class="filter-btn" data-filter="landmarks">Landmarks</button>
                </div>
                <div class="map-legend">
                    <div class="legend-item">
                        <span class="legend-marker marker-event"></span>
                        <span>Events</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-marker marker-attending"></span>
                        <span>Attending</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-marker marker-landmark"></span>
                        <span>Landmarks</span>
                    </div>
                </div>
            </div>

            <div class="location-search">
                <input type="text" id="locationSearch" placeholder="Search for locations...">
            </div>

            <div id="map"></div>
        </div>

        <div class="info-panel">
            <h3>Upcoming Events</h3>
            <?php if (empty($events)): ?>
                <p>No upcoming events found.</p>
            <?php else: ?>
                <div class="events-list">
                    <?php foreach ($events as $event): ?>
                        <div class="event-card <?php echo $event['is_attending'] ? 'attending' : ''; ?>">
                            <h4><?php echo htmlspecialchars($event['title']); ?></h4>
                            <div class="event-meta">
                                <span><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($event['event_date'])); ?></span>
                                <span><strong>Location:</strong> <?php echo htmlspecialchars($event['location']); ?></span>
                                <span><strong>Organizer:</strong> <?php echo htmlspecialchars($event['organizer_name']); ?></span>
                            </div>
                            <p><?php echo htmlspecialchars(substr($event['description'], 0, 100)) . '...'; ?></p>
                            <div class="event-actions">
                                <button class="btn btn-primary" onclick="viewEvent(<?php echo $event['id']; ?>)">View Details</button>
                                <?php if (!$event['is_attending']): ?>
                                    <button class="btn btn-secondary" onclick="attendEvent(<?php echo $event['id']; ?>)">Attend</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize the map
        let map = L.map('map').setView([-1.2921, 36.8219], 13); // Default to Nairobi coordinates

        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Store markers for filtering
        let markers = {
            events: [],
            attending: [],
            landmarks: []
        };

        // Event data from PHP
        const events = <?php echo json_encode($events); ?>;
        const locations = <?php echo json_encode($locations); ?>;

        // Add event markers
        events.forEach(event => {
            if (event.latitude && event.longitude) {
                const isAttending = event.is_attending == 1;
                const markerColor = isAttending ? 'green' : 'blue';
                
                const marker = L.marker([event.latitude, event.longitude], {
                    icon: L.divIcon({
                        className: 'custom-div-icon',
                        html: `<div style="background-color: ${markerColor}; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white;"></div>`,
                        iconSize: [20, 20],
                        iconAnchor: [10, 10]
                    })
                }).addTo(map);

                const popupContent = `
                    <div style="min-width: 200px;">
                        <h4>${event.title}</h4>
                        <p><strong>Date:</strong> ${new Date(event.event_date).toLocaleDateString()}</p>
                        <p><strong>Location:</strong> ${event.location}</p>
                        <p><strong>Organizer:</strong> ${event.organizer_name}</p>
                        <p>${event.description.substring(0, 100)}...</p>
                        <button onclick="viewEvent(${event.id})" style="background: #4facfe; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">View Details</button>
                    </div>
                `;

                marker.bindPopup(popupContent);
                
                markers.events.push(marker);
                if (isAttending) {
                    markers.attending.push(marker);
                }
            }
        });

        // Add landmark markers
        locations.forEach(location => {
            if (location.latitude && location.longitude) {
                const marker = L.marker([location.latitude, location.longitude], {
                    icon: L.divIcon({
                        className: 'custom-div-icon',
                        html: '<div style="background-color: #ffc107; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white;"></div>',
                        iconSize: [20, 20],
                        iconAnchor: [10, 10]
                    })
                }).addTo(map);

                const popupContent = `
                    <div style="min-width: 200px;">
                        <h4>${location.name}</h4>
                        <p><strong>Type:</strong> ${location.type}</p>
                        <p>${location.description || 'No description available'}</p>
                    </div>
                `;

                marker.bindPopup(popupContent);
                markers.landmarks.push(marker);
            }
        });

        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active class from all buttons
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                // Add active class to clicked button
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                
                // Hide all markers
                Object.values(markers).forEach(markerArray => {
                    markerArray.forEach(marker => {
                        map.removeLayer(marker);
                    });
                });
                
                // Show filtered markers
                if (filter === 'all') {
                    Object.values(markers).forEach(markerArray => {
                        markerArray.forEach(marker => {
                            map.addLayer(marker);
                        });
                    });
                } else if (markers[filter]) {
                    markers[filter].forEach(marker => {
                        map.addLayer(marker);
                    });
                }
            });
        });

        // Location search functionality
        document.getElementById('locationSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            // Filter events and locations based on search term
            const filteredEvents = events.filter(event => 
                event.title.toLowerCase().includes(searchTerm) ||
                event.location.toLowerCase().includes(searchTerm)
            );
            
            const filteredLocations = locations.filter(location => 
                location.name.toLowerCase().includes(searchTerm) ||
                location.type.toLowerCase().includes(searchTerm)
            );
            
            // You could implement more sophisticated search functionality here
            // For now, this is a basic implementation
        });

        // Function to view event details
        function viewEvent(eventId) {
            window.location.href = `event_details.php?id=${eventId}`;
        }

        // Function to attend an event
        function attendEvent(eventId) {
            if (confirm('Are you sure you want to attend this event?')) {
                // Make AJAX call to attend event
                fetch('attend_event.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({event_id: eventId})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Successfully registered for event!');
                        location.reload();
                    } else {
                        alert('Error registering for event: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error registering for event');
                });
            }
        }

        // Try to get user's location and center map
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                map.setView([lat, lng], 15);
                
                // Add user location marker
                L.marker([lat, lng], {
                    icon: L.divIcon({
                        className: 'custom-div-icon',
                        html: '<div style="background-color: #dc3545; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white;"></div>',
                        iconSize: [20, 20],
                        iconAnchor: [10, 10]
                    })
                }).addTo(map).bindPopup('Your Location');
            });
        }
    </script>
</body>
</html>