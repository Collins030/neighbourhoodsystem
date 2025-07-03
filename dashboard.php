<?php
// dashboard.php - User dashboard

require_once 'config.php';

// Check if user is logged in
$user = verifyUserSession();
if (!$user) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Neighbourhood Connect</title>
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.2em;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .welcome-section {
            background: white;
            border-radius: 15px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .welcome-section h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 15px;
        }

        .welcome-section p {
            color: #666;
            font-size: 1.1em;
            line-height: 1.6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: 700;
            color: #4facfe;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #666;
            font-size: 1.1em;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-card h3 {
            color: #333;
            font-size: 1.5em;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .feature-card p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .feature-btn {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s ease;
        }

        .feature-btn:hover {
            transform: translateY(-2px);
        }

        .user-profile {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .user-profile h2 {
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .profile-field {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .profile-field label {
            display: block;
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .profile-field span {
            color: #333;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .user-info {
                gap: 15px;
            }

            .welcome-section {
                padding: 20px;
            }

            .welcome-section h1 {
                font-size: 2em;
            }

            .stat-card, .feature-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">🏘️ Neighbourhood Connect</div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <span>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></span>
                <button class="logout-btn" onclick="logout()">Logout</button>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="welcome-section">
            <h1>Welcome to Your Neighbourhood Dashboard</h1>
            <p>Connect with your community, share events, and build stronger relationships with your neighbours. Your local network starts here!</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🏠</div>
                <div class="stat-number">0</div>
                <div class="stat-label">Events Created</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-number">0</div>
                <div class="stat-label">Neighbours Connected</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-number">0</div>
                <div class="stat-label">Events Attended</div>
            </div>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <h3>📅 Create Events</h3>
                <p>Organize neighbourhood gatherings, block parties, community meetings, and more. Bring your community together!</p>
                <button class="feature-btn" onclick="alert('Event creation feature coming soon!')">Create Event</button>
            </div>
            <div class="feature-card">
                <h3>🔍 Browse Events</h3>
                <p>Discover what's happening in your neighbourhood. Join events, meet new people, and get involved in your community.</p>
                <button class="feature-btn" onclick="alert('Event browsing feature coming soon!')">Browse Events</button>
            </div>
            <div class="feature-card">
                <h3>💬 Community Chat</h3>
                <p>Connect with your neighbours through our community chat feature. Share updates, ask questions, and stay informed.</p>
                <button class="feature-btn" onclick="alert('Community chat feature coming soon!')">Join Chat</button>
            </div>
            <div class="feature-card">
                <h3>🗺️ Neighbourhood Map</h3>
                <p>View events and activities on an interactive map of your neighbourhood. See what's happening nearby!</p>
                <button class="feature-btn" onclick="alert('Neighbourhood map feature coming soon!')">View Map</button>
            </div>
        </div>

        <div class="user-profile">
            <h2>👤 Your Profile</h2>
            <div class="profile-info">
                <div class="profile-field">
                    <label>Full Name</label>
                    <span><?php echo htmlspecialchars($user['full_name']); ?></span>
                </div>
                <div class="profile-field">
                    <label>Username</label>
                    <span><?php echo htmlspecialchars($user['username']); ?></span>
                </div>
                <div class="profile-field">
                    <label>Email</label>
                    <span><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="profile-field">
                    <label>Member Since</label>
                    <span><?php echo date('F j, Y'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function logout() {
            if (confirm('Are you sure you want to logout?')) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'logout');
                    
                    const response = await fetch('auth_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        window.location.href = data.redirect;
                    } else {
                        alert('Logout failed. Please try again.');
                    }
                } catch (error) {
                    alert('Network error. Please try again.');
                }
            }
        }

        // Add some interactive animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stat numbers
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const finalValue = parseInt(stat.textContent);
                let currentValue = 0;
                const increment = finalValue / 20;
                
                const updateCounter = () => {
                    if (currentValue < finalValue) {
                        currentValue += increment;
                        stat.textContent = Math.round(currentValue);
                        setTimeout(updateCounter, 50);
                    } else {
                        stat.textContent = finalValue;
                    }
                };
                
                // Start animation after a delay
                setTimeout(updateCounter, 500);
            });
        });
    </script>
</body>
</html>