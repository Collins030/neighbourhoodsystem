<?php
// create_events.php - Event creation feature

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
    
    // Basic validation
    if (empty($title) || empty($event_date) || empty($location)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=neighbourhood_system", 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("
                INSERT INTO events (user_id, title, description, event_date, end_date, location, address, max_attendees)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $user['id'],
                $title,
                $description,
                $event_date,
                $end_date ?: null,
                $location,
                $address,
                $max_attendees ?: null
            ]);
            
            $success = "Event created successfully!";
            
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
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .form-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-container {
                padding: 20px;
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
        <div class="form-container">
            <h1>📅 Create Event</h1>
            <p class="subtitle">Organize a neighbourhood gathering and bring your community together!</p>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="title">Event Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" required 
                           placeholder="e.g., Neighbourhood BBQ, Book Club Meeting">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" 
                              placeholder="Tell your neighbours about your event..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="event_date">Start Date & Time <span class="required">*</span></label>
                        <input type="datetime-local" id="event_date" name="event_date" required>
                    </div>
                    <div class="form-group">
                        <label for="end_date">End Date & Time</label>
                        <input type="datetime-local" id="end_date" name="end_date">
                    </div>
                </div>

                <div class="form-group">
                    <label for="location">Location Name <span class="required">*</span></label>
                    <input type="text" id="location" name="location" required 
                           placeholder="e.g., Community Park, My Backyard">
                </div>

                <div class="form-group">
                    <label for="address">Full Address</label>
                    <input type="text" id="address" name="address" 
                           placeholder="Street address for easier navigation">
                </div>

                <div class="form-group">
                    <label for="max_attendees">Maximum Attendees</label>
                    <input type="number" id="max_attendees" name="max_attendees" min="1" 
                           placeholder="Leave empty for unlimited">
                </div>

                <button type="submit" class="submit-btn">Create Event</button>
            </form>
        </div>
    </div>

    <script>
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
    </script>
</body>
</html>