<?php
// browse_events.php - Event browsing feature

require_once 'config.php';

// Check if user is logged in
$user = verifyUserSession();
if (!$user) {
    header('Location: index.php');
    exit;
}

// Handle event actions (join/leave)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $event_id = $_POST['event_id'] ?? '';
    $action = $_POST['action'];
    
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=neighbourhood_system", 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        if ($action === 'join') {
            // Check if user is already attending
            $stmt = $pdo->prepare("SELECT id FROM event_attendees WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$event_id, $user['id']]);
            
            if (!$stmt->fetch()) {
                // Add user to event
                $stmt = $pdo->prepare("INSERT INTO event_attendees (event_id, user_id) VALUES (?, ?)");
                $stmt->execute([$event_id, $user['id']]);
                
                // Update event attendee count
                $stmt = $pdo->prepare("UPDATE events SET current_attendees = current_attendees + 1 WHERE id = ?");
                $stmt->execute([$event_id]);
                
                $success = "Successfully joined the event!";
            }
        } elseif ($action === 'leave') {
            // Remove user from event
            $stmt = $pdo->prepare("DELETE FROM event_attendees WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$event_id, $user['id']]);
            
            // Update event attendee count
            $stmt = $pdo->prepare("UPDATE events SET current_attendees = current_attendees - 1 WHERE id = ?");
            $stmt->execute([$event_id]);
            
            $success = "Successfully left the event!";
        }
        
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch events
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
    
} catch (PDOException $e) {
    $error = "Error fetching events: " . $e->getMessage();
    $events = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Events - Neighbourhood Connect</title>
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

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .event-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .event-card:hover {
            transform: translateY(-5px);
        }

        .event-card h3 {
            color: #333;
            font-size: 1.5em;
            margin-bottom: 15px;
        }

        .event-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
        }

        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
        }

        .event-meta-item .icon {
            font-size: 1.2em;
        }

        .event-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .event-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .attendee-count {
            color: #666;
            font-size: 0.9em;
        }

        .organizer-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .organizer-info small {
            color: #666;
        }

        .no-events {
            text-align: center;
            color: #666;
            font-size: 1.2em;
            margin-top: 40px;
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

        .create-event-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .create-event-btn:hover {
            transform: scale(1.1);
        }

        @media (max-width: 768px) {
            .events-grid {
                grid-template-columns: 1fr;
            }
            
            .event-card {
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
                <a href="create_events.php">Create Event</a>
                <a href="community_chat.php">Community Chat</a>
                <a href="neighbourhood_map.php">Map</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1>🔍 Browse Events</h1>
            <p>Discover what's happening in your neighbourhood and join the fun!</p>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (empty($events)): ?>
            <div class="no-events">
                <p>No events available at the moment.</p>
                <p>Why not <a href="create_events.php">create one</a> yourself?</p>
            </div>
        <?php else: ?>
            <div class="events-grid">
                <?php foreach ($events as $event): ?>
                    <div class="event-card">
                        <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                        
                        <div class="organizer-info">
                            <small>Organized by: <strong><?php echo htmlspecialchars($event['organizer_name']); ?></strong></small>
                        </div>
                        
                        <div class="event-meta">
                            <div class="event-meta-item">
                                <span class="icon">📅</span>
                                <span><?php echo date('F j, Y \a\t g:i A', strtotime($event['event_date'])); ?></span>
                            </div>
                            <div class="event-meta-item">
                                <span class="icon">📍</span>
                                <span><?php echo htmlspecialchars($event['location']); ?></span>
                            </div>
                            <div class="event-meta-item">
                                <span class="icon">👥</span>
                                <span class="attendee-count">
                                    <?php echo $event['current_attendees']; ?> attending
                                    <?php if ($event['max_attendees']): ?>
                                        / <?php echo $event['max_attendees']; ?> max
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($event['description']): ?>
                            <div class="event-description">
                                <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="event-actions">
                            <div class="attendee-count">
                                <?php echo $event['current_attendees']; ?> attending
                                <?php if ($event['max_attendees']): ?>
                                    / <?php echo $event['max_attendees']; ?> max
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($event['user_id'] == $user['id']): ?>
                                <button class="btn btn-secondary" disabled>Your Event</button>
                            <?php elseif ($event['is_attending']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                    <input type="hidden" name="action" value="leave">
                                    <button type="submit" class="btn btn-danger">Leave Event</button>
                                </form>
                            <?php else: ?>
                                <?php if ($event['max_attendees'] && $event['current_attendees'] >= $event['max_attendees']): ?>
                                    <button class="btn btn-secondary" disabled>Event Full</button>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                        <input type="hidden" name="action" value="join">
                                        <button type="submit" class="btn btn-primary">Join Event</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <button class="create-event-btn" onclick="window.location.href='create_events.php'" title="Create Event">
        ➕
    </button>
</body>
</html>