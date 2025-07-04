<?php
// community_chat.php - Community chat feature

require_once 'config.php';

// Check if user is logged in
$user = verifyUserSession();
if (!$user) {
    header('Location: index.php');
    exit;
}

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=neighbourhood_system", 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        if ($action === 'send_message') {
            $message = trim($_POST['message'] ?? '');
            $message_type = $_POST['message_type'] ?? 'general';
            
            if (!empty($message)) {
                $stmt = $pdo->prepare("
                    INSERT INTO community_messages (user_id, message, message_type)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$user['id'], $message, $message_type]);
                
                // Redirect to prevent form resubmission
                header('Location: community_chat.php');
                exit;
            }
        } elseif ($action === 'reply') {
            $message_id = $_POST['message_id'] ?? '';
            $reply_text = trim($_POST['reply_text'] ?? '');
            
            if (!empty($reply_text) && !empty($message_id)) {
                $stmt = $pdo->prepare("
                    INSERT INTO message_replies (message_id, user_id, reply_text)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$message_id, $user['id'], $reply_text]);
                
                header('Location: community_chat.php');
                exit;
            }
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch messages with replies
try {
    $pdo = new PDO("mysql:host=localhost;dbname=neighbourhood_system", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("
        SELECT m.*, u.full_name as author_name,
               COUNT(r.id) as reply_count
        FROM community_messages m
        JOIN users u ON m.user_id = u.id
        LEFT JOIN message_replies r ON m.id = r.message_id
        GROUP BY m.id
        ORDER BY m.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch replies for each message
    foreach ($messages as &$message) {
        $stmt = $pdo->prepare("
            SELECT r.*, u.full_name as author_name
            FROM message_replies r
            JOIN users u ON r.user_id = u.id
            WHERE r.message_id = ?
            ORDER BY r.created_at ASC
        ");
        $stmt->execute([$message['id']]);
        $message['replies'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error = "Error fetching messages: " . $e->getMessage();
    $messages = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Chat - Neighbourhood Connect</title>
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

        .message-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .message-form h3 {
            color: #333;
            margin-bottom: 20px;
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

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4facfe;
        }

        .message-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            padding: 12px 24px;
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

        .btn:hover {
            transform: translateY(-2px);
        }

        .messages-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .message-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .message-author {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .author-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .author-info {
            display: flex;
            flex-direction: column;
        }

        .author-name {
            font-weight: 600;
            color: #333;
        }

        .message-time {
            font-size: 0.9em;
            color: #666;
        }

        .message-type {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .type-general {
            background: #e3f2fd;
            color: #1976d2;
        }

        .type-announcement {
            background: #fff3e0;
            color: #f57c00;
        }

        .type-question {
            background: #e8f5e8;
            color: #388e3c;
        }

        .message-content {
            color: #333;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .message-actions-bar {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .reply-btn {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 15px;
            transition: background 0.3s ease;
        }

        .reply-btn:hover {
            background: #f8f9fa;
        }

        .reply-count {
            font-size: 0.9em;
            color: #666;
        }

        .replies-container {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
        }

        .reply-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .reply-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .reply-author {
            font-weight: 600;
            color: #333;
            font-size: 0.9em;
        }

        .reply-time {
            font-size: 0.8em;
            color: #666;
        }

        .reply-content {
            color: #333;
            line-height: 1.5;
        }

        .reply-form {
            margin-top: 15px;
            display: none;
        }

        .reply-form.active {
            display: block;
        }

        .reply-form textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            min-height: 80px;
            margin-bottom: 10px;
        }

        .reply-form-actions {
            display: flex;
            gap: 10px;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 0.9em;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .message-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .message-actions-bar {
                flex-wrap: wrap;
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
                <a href="browse_events.php">Browse Events</a>
                <a href="neighbourhood_map.php">Map</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1>💬 Community Chat</h1>
            <p>Connect with your neighbours, share updates, and stay informed about your community!</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="message-form">
            <h3>Share with your community</h3>
            <form method="POST">
                <input type="hidden" name="action" value="send_message">
                
                <div class="form-group">
                    <label for="message_type">Message Type</label>
                    <select id="message_type" name="message_type">
                        <option value="general">General Discussion</option>
                        <option value="announcement">Announcement</option>
                        <option value="question">Question</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="message">Your Message</label>
                    <textarea id="message" name="message" required 
                              placeholder="What's on your mind? Share updates, ask questions, or start a conversation..."></textarea>
                </div>

                <div class="message-actions">
                    <span style="color: #666; font-size: 0.9em;">Be respectful and kind to your neighbours</span>
                    <button type="submit" class="btn btn-primary">Post Message</button>
                </div>
            </form>
        </div>

        <div class="messages-container">
            <?php if (empty($messages)): ?>
                <div class="message-card" style="text-align: center;">
                    <p style="color: #666; font-size: 1.1em;">No messages yet. Be the first to start a conversation!</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <div class="message-card">
                        <div class="message-header">
                            <div class="message-author">
                                <div class="author-avatar">
                                    <?php echo strtoupper(substr($message['author_name'], 0, 1)); ?>
                                </div>
                                <div class="author-info">
                                    <div class="author-name"><?php echo htmlspecialchars($message['author_name']); ?></div>
                                    <div class="message-time"><?php echo date('M j, Y \a\t g:i A', strtotime($message['created_at'])); ?></div>
                                </div>
                            </div>
                            <div class="message-type type-<?php echo $message['message_type']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $message['message_type'])); ?>
                            </div>
                        </div>

                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                        </div>

                        <div class="message-actions-bar">
                            <button class="reply-btn" onclick="toggleReplyForm(<?php echo $message['id']; ?>)">
                                💬 Reply
                            </button>
                            <?php if ($message['reply_count'] > 0): ?>
                                <span class="reply-count"><?php echo $message['reply_count']; ?> replies</span>
                            <?php endif; ?>
                        </div>

                        <div class="reply-form" id="reply-form-<?php echo $message['id']; ?>">
                            <form method="POST">
                                <input type="hidden" name="action" value="reply">
                                <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                <textarea name="reply_text" placeholder="Write your reply..." required></textarea>
                                <div class="reply-form-actions">
                                    <button type="submit" class="btn btn-primary btn-small">Reply</button>
                                    <button type="button" class="btn btn-secondary btn-small" onclick="toggleReplyForm(<?php echo $message['id']; ?>)">Cancel</button>
                                </div>
                            </form>
                        </div>

                        <?php if (!empty($message['replies'])): ?>
                            <div class="replies-container">
                                <?php foreach ($message['replies'] as $reply): ?>
                                    <div class="reply-card">
                                        <div class="reply-header">
                                            <div class="reply-author"><?php echo htmlspecialchars($reply['author_name']); ?></div>
                                            <div class="reply-time"><?php echo date('M j \a\t g:i A', strtotime($reply['created_at'])); ?></div>
                                        </div>
                                        <div class="reply-content">
                                            <?php echo nl2br(htmlspecialchars($reply['reply_text'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleReplyForm(messageId) {
            const form = document.getElementById('reply-form-' + messageId);
            const isActive = form.classList.contains('active');
            
            // Hide all reply forms
            document.querySelectorAll('.reply-form').forEach(f => f.classList.remove('active'));
            
            // Show this form if it wasn't active
            if (!isActive) {
                form.classList.add('active');
                form.querySelector('textarea').focus();
            }
        }

        // Auto-refresh messages every 30 seconds
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                window.location.reload();
            }
        }, 30000);
    </script>
</body>
</html>