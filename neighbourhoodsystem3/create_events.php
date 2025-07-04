<?php
// create_events.php - Enhanced Event creation with map integration and status field

require_once 'config.php';

// Check if user is logged in
$user = verifyUserSession();
if (!$user) {
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $location = $_POST['location'] ?? '';
    $address = $_POST['address'] ?? '';
    $max_attendees = $_POST['max_attendees'] ?? null;
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    
    // Basic validation
    if (empty($title) || empty($event_date) || empty($location)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=neighbourhood_system", 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check if events table has required columns, add them if not
            $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'latitude'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE events ADD COLUMN latitude DECIMAL(10, 8) NULL");
                $pdo->exec("ALTER TABLE events ADD COLUMN longitude DECIMAL(11, 8) NULL");
            }
            
            // Check if status column exists, add if not
            $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'status'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE events ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO events (user_id, title, description, event_date, end_date, location, address, max_attendees, latitude, longitude, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            
            $stmt->execute([
                $user['id'],
                $title,
                $description,
                $event_date,
                $end_date ?: null,
                $location,
                $address,
                $max_attendees ?: null,
                $latitude,
                $longitude
            ]);
            
            $success = "Event created successfully and will appear on the neighbourhood map!";
            
        } catch (PDOException $e) {
            $error = "Error creating event: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - Neighbourhood Connect</title>
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

        .form-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            align-items: start;
        }

        .form-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .map-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 20px;
        }

        .form-container h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 10px;
            text-align: center;
        }

        .form-container .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4facfe;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            margin-top: 20px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .required {
            color: red;
        }

        .map-section h3 {
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }

        .map-instructions {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #1565c0;
        }

        #map {
            height: 400px;
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 15px;
        }

        .location-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .location-info h4 {
            color: #333;
            margin-bottom: 10px;
        }

        .location-info p {
            color: #666;
            margin: 5px 0;
            font-size: 14px;
        }

        .map-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .map-btn {
            padding: 8px 16px;
            border: 2px solid #4facfe;
            background: white;
            color: #4facfe;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .map-btn:hover {
            background: #4facfe;
            color: white;
        }

        .coordinates-display {
            background: #f1f3f4;
            border-radius: 8px;
            padding: 10px;
            font-family: monospace;
            font-size: 12px;
            color: #666;
        }

        .location-requirement {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #856404;
        }

        .location-requirement strong {
            color: #b45309;
        }

        @media (max-width: 1024px) {
            .form-layout {
                grid-template-columns: 1fr;
            }
            
            .map-container {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-container {
                padding: 20px;
            }
            
            .container {
                padding: 20px 10px;
            }
            
            .map-actions {
                flex-direction: column;
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
                <a href="community_chat.php">Community Chat</a>
                <a href="neighbourhood_map.php">Map</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="form-layout">
            <div class="form-container">
                <h1>📅 Create Event</h1>
                <p class="subtitle">Organize a neighbourhood gathering and bring your community together!</p>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="location-requirement">
                    <strong>📍 Location Required:</strong> Please select a location on the map for your event to appear on the neighbourhood map and help attendees find it easily.
                </div>

                <form method="POST" id="eventForm">
                    <div class="form-group">
                        <label for="title">Event Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required 
                               placeholder="e.g., Neighbourhood BBQ, Book Club Meeting"
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" 
                                  placeholder="Tell your neighbours about your event..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="event_date">Start Date & Time <span class="required">*</span></label>
                            <input type="datetime-local" id="event_date" name="event_date" required
                                   value="<?php echo isset($_POST['event_date']) ? $_POST['event_date'] : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date & Time</label>
                            <input type="datetime-local" id="end_date" name="end_date"
                                   value="<?php echo isset($_POST['end_date']) ? $_POST['end_date'] : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="location">Location Name <span class="required">*</span></label>
                        <input type="text" id="location" name="location" required 
                               placeholder="e.g., Community Park, My Backyard"
                               value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="address">Full Address</label>
                        <input type="text" id="address" name="address" 
                               placeholder="Street address for easier navigation"
                               value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="max_attendees">Maximum Attendees</label>
                        <input type="number" id="max_attendees" name="max_attendees" min="1" 
                               placeholder="Leave empty for unlimited"
                               value="<?php echo isset($_POST['max_attendees']) ? $_POST['max_attendees'] : ''; ?>">
                    </div>

                    <!-- Hidden fields for coordinates -->
                    <input type="hidden" id="latitude" name="latitude" value="<?php echo isset($_POST['latitude']) ? $_POST['latitude'] : ''; ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo isset($_POST['longitude']) ? $_POST['longitude'] : ''; ?>">

                    <button type="submit" class="submit-btn">Create Event</button>
                </form>
            </div>

            <div class="map-container">
                <div class="map-section">
                    <h3>📍 Select Event Location</h3>
                    <div class="map-instructions">
                        <strong>How to use:</strong><br>
                        • Click on the map to select your event location<br>
                        • Use the search button to find your current location<br>
                        • The red marker shows your selected location<br>
                        • Location details will auto-fill in the form<br>
                        • <strong>Location is required for map display</strong>
                    </div>

                    <div class="map-actions">
                        <button type="button" class="map-btn" onclick="getCurrentLocation()">📍 Use My Location</button>
                        <button type="button" class="map-btn" onclick="searchAddress()">🔍 Search Address</button>
                        <button type="button" class="map-btn" onclick="clearLocation()">🗑️ Clear Location</button>
                    </div>

                    <div id="map"></div>

                    <div class="location-info" id="locationInfo" style="display: none;">
                        <h4>Selected Location</h4>
                        <p><strong>Address:</strong> <span id="selectedAddress">Not selected</span></p>
                        <p><strong>Coordinates:</strong> <span id="selectedCoords">Not selected</span></p>
                    </div>

                    <div class="coordinates-display" id="coordsDisplay">
                        Click on the map to select event location
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize the map
        let map = L.map('map').setView([-1.2921, 36.8219], 13); // Default to Nairobi coordinates
        let selectedMarker = null;

        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Initialize with saved coordinates if available
        const savedLat = document.getElementById('latitude').value;
        const savedLng = document.getElementById('longitude').value;
        if (savedLat && savedLng) {
            const lat = parseFloat(savedLat);
            const lng = parseFloat(savedLng);
            
            selectedMarker = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'custom-div-icon',
                    html: '<div style="background-color: #dc3545; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                    iconSize: [20, 20],
                    iconAnchor: [10, 10]
                })
            }).addTo(map);
            
            map.setView([lat, lng], 16);
            updateLocationDisplay(lat, lng);
            reverseGeocode(lat, lng);
        }

        // Handle map clicks
        map.on('click', function(e) {
            const lat = e.latlng.lat;
            const lng = e.latlng.lng;
            
            // Remove existing marker
            if (selectedMarker) {
                map.removeLayer(selectedMarker);
            }
            
            // Add new marker
            selectedMarker = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'custom-div-icon',
                    html: '<div style="background-color: #dc3545; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                    iconSize: [20, 20],
                    iconAnchor: [10, 10]
                })
            }).addTo(map);
            
            // Update form fields
            document.getElementById('latitude').value = lat.toFixed(6);
            document.getElementById('longitude').value = lng.toFixed(6);
            
            // Update display
            updateLocationDisplay(lat, lng);
            
            // Reverse geocode to get address
            reverseGeocode(lat, lng);
        });

        // Get current location
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    // Center map on user location
                    map.setView([lat, lng], 16);
                    
                    // Add marker
                    if (selectedMarker) {
                        map.removeLayer(selectedMarker);
                    }
                    
                    selectedMarker = L.marker([lat, lng], {
                        icon: L.divIcon({
                            className: 'custom-div-icon',
                            html: '<div style="background-color: #dc3545; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                            iconSize: [20, 20],
                            iconAnchor: [10, 10]
                        })
                    }).addTo(map);
                    
                    // Update form fields
                    document.getElementById('latitude').value = lat.toFixed(6);
                    document.getElementById('longitude').value = lng.toFixed(6);
                    
                    // Update display
                    updateLocationDisplay(lat, lng);
                    
                    // Reverse geocode to get address
                    reverseGeocode(lat, lng);
                    
                }, function(error) {
                    alert('Error getting location: ' + error.message);
                });
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        }

        // Search address
        function searchAddress() {
            const address = document.getElementById('address').value;
            if (!address) {
                alert('Please enter an address in the address field first.');
                return;
            }
            
            // Use a simple geocoding service
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        const lat = parseFloat(data[0].lat);
                        const lng = parseFloat(data[0].lon);
                        
                        // Center map on searched location
                        map.setView([lat, lng], 16);
                        
                        // Add marker
                        if (selectedMarker) {
                            map.removeLayer(selectedMarker);
                        }
                        
                        selectedMarker = L.marker([lat, lng], {
                            icon: L.divIcon({
                                className: 'custom-div-icon',
                                html: '<div style="background-color: #dc3545; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                                iconSize: [20, 20],
                                iconAnchor: [10, 10]
                            })
                        }).addTo(map);
                        
                        // Update form fields
                        document.getElementById('latitude').value = lat.toFixed(6);
                        document.getElementById('longitude').value = lng.toFixed(6);
                        
                        // Update display
                        updateLocationDisplay(lat, lng);
                        
                        // Update address field with the found address
                        document.getElementById('address').value = data[0].display_name;
                        document.getElementById('selectedAddress').textContent = data[0].display_name;
                        
                    } else {
                        alert('Address not found. Please try a different address.');
                    }
                })
                .catch(error => {
                    console.error('Error searching address:', error);
                    alert('Error searching address. Please try again.');
                });
        }

        // Clear location
        function clearLocation() {
            if (selectedMarker) {
                map.removeLayer(selectedMarker);
                selectedMarker = null;
            }
            
            document.getElementById('latitude').value = '';
            document.getElementById('longitude').value = '';
            document.getElementById('locationInfo').style.display = 'none';
            document.getElementById('coordsDisplay').textContent = 'Click on the map to select event location';
        }

        // Update location display
        function updateLocationDisplay(lat, lng) {
            document.getElementById('locationInfo').style.display = 'block';
            document.getElementById('selectedCoords').textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            document.getElementById('coordsDisplay').textContent = `Latitude: ${lat.toFixed(6)}, Longitude: ${lng.toFixed(6)}`;
        }

        // Reverse geocode to get address
        function reverseGeocode(lat, lng) {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(response => response.json())
                .then(data => {
                    if (data.display_name) {
                        document.getElementById('selectedAddress').textContent = data.display_name;
                        
                        // Auto-fill address field if empty
                        if (!document.getElementById('address').value) {
                            document.getElementById('address').value = data.display_name;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error reverse geocoding:', error);
                    document.getElementById('selectedAddress').textContent = 'Address not found';
                });
        }

        // Set minimum date to today
        const today = new Date();
        today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
        document.getElementById('event_date').min = today.toISOString().slice(0, 16);
        document.getElementById('end_date').min = today.toISOString().slice(0, 16);

        // Auto-update end date minimum when start date changes
        document.getElementById('event_date').addEventListener('change', function() {
            const startDate = this.value;
            if (startDate) {
                document.getElementById('end_date').min = startDate;
            }
        });

        // Enhanced form validation
        document.getElementById('eventForm').addEventListener('submit', function(e) {
            const lat = document.getElementById('latitude').value;
            const lng = document.getElementById('longitude').value;
            
            if (!lat || !lng) {
                e.preventDefault();
                alert('Please select a location on the map before submitting. This is required for your event to appear on the neighbourhood map.');
                return false;
            }
            
            // Additional validation
            const title = document.getElementById('title').value.trim();
            const eventDate = document.getElementById('event_date').value;
            const location = document.getElementById('location').value.trim();
            
            if (!title || !eventDate || !location) {
                e.preventDefault();
                alert('Please fill in all required fields (Title, Date, and Location).');
                return false;
            }
        });

        // Auto-fill location name when address is entered
        document.getElementById('address').addEventListener('blur', function() {
            const address = this.value;
            const locationField = document.getElementById('location');
            
            if (address && !locationField.value) {
                // Extract a simplified location name from the address
                const parts = address.split(',');
                if (parts.length > 0) {
                    locationField.value = parts[0].trim();
                }
            }
        });
    </script>
</body>
</html>