<?php
session_start();
require_once 'auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = getCurrentUser();
$username = $user['username'] ?? 'User';
$initial = strtoupper(substr($username, 0, 1));

// Get user rewards
$rewardPoints = $user['total_points'] ?? 0;
$carbonSaved = $user['carbon_saved'] ?? 0;
$itemsScanned = $user['items_scanned'] ?? 0;
$timesRecycled = $user['times_recycled'] ?? 0;
$level = $user['level'] ?? 'Bronze';

// Get leaderboard from database
try {
    $pdo = getDBConnection();
    $leaderboard = $pdo->query("SELECT * FROM leaderboard LIMIT 10")->fetchAll();
} catch (PDOException $e) {
    error_log("Leaderboard error: " . $e->getMessage());
    $leaderboard = [];
}

// Get recycling centers
try {
    $pdo = getDBConnection();
    $centers = $pdo->query("SELECT * FROM recycling_centers WHERE is_active = TRUE LIMIT 20")->fetchAll();
} catch (PDOException $e) {
    error_log("Centers error: " . $e->getMessage());
    $centers = [];
}

// Get material prices
try {
    $pdo = getDBConnection();
    $prices = $pdo->query("SELECT * FROM material_prices ORDER BY price_per_kg DESC LIMIT 5")->fetchAll();
} catch (PDOException $e) {
    error_log("Prices error: " . $e->getMessage());
    $prices = [];
}

// Get scan history
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM scan_history WHERE user_id = :user_id ORDER BY scanned_at DESC LIMIT 5");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $recentScans = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Scan history error: " . $e->getMessage());
    $recentScans = [];
}

// Get total users
try {
    $pdo = getDBConnection();
    $totalUsers = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'")->fetch()['count'];
} catch (PDOException $e) {
    error_log("Total users error: " . $e->getMessage());
    $totalUsers = 0;
}

// Get user rank
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 as rank_position 
        FROM user_rewards 
        WHERE total_points > (SELECT total_points FROM user_rewards WHERE user_id = :user_id)
    ");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $rankData = $stmt->fetch();
    $rank = $rankData['rank_position'] ?? 0;
} catch (PDOException $e) {
    error_log("Rank error: " . $e->getMessage());
    $rank = 0;
}

// Prepare center data for JavaScript
$centerData = [];
foreach ($centers as $c) {
    if ($c['latitude'] && $c['longitude']) {
        $centerData[] = [
            'id' => $c['id'],
            'name' => $c['name'],
            'lat' => floatval($c['latitude']),
            'lng' => floatval($c['longitude']),
            'address' => $c['address'],
            'phone' => $c['phone']
        ];
    }
}

// Get partner rewards
$partnerRewards = [];
try {
    $pdo = getDBConnection();
    $partnerRewards = $pdo->query("
        SELECT * FROM partner_rewards 
        WHERE is_active = TRUE 
        AND (valid_until IS NULL OR valid_until > CURDATE())
        ORDER BY points_required ASC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log("Partner rewards error: " . $e->getMessage());
}

// Get user redemptions
$userRedemptions = [];
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT r.*, pr.partner_name, pr.logo_url, pr.description, pr.discount_value
        FROM user_redemptions r
        JOIN partner_rewards pr ON r.partner_reward_id = pr.id
        WHERE r.user_id = :user_id
        ORDER BY r.redeemed_at DESC
        LIMIT 10
    ");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $userRedemptions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("User redemptions error: " . $e->getMessage());
}

// Get payment methods
$paymentMethods = [];
try {
    $pdo = getDBConnection();
    $paymentMethods = getPaymentMethods($_SESSION['user_id']);
} catch (PDOException $e) {
    error_log("Payment methods error: " . $e->getMessage());
}

// Get notifications
$notifications = [];
try {
    $pdo = getDBConnection();
    $notifications = getNotifications($_SESSION['user_id'], 10);
} catch (PDOException $e) {
    error_log("Notifications error: " . $e->getMessage());
}

// Get payment transactions
$paymentTransactions = [];
try {
    $pdo = getDBConnection();
    $paymentTransactions = getPaymentTransactions($_SESSION['user_id'], 10);
} catch (PDOException $e) {
    error_log("Payment transactions error: " . $e->getMessage());
}

// News data
$news = [
    [
        'title' => '🌍 New Recycling Center Opens in KL',
        'desc' => 'A new state-of-the-art recycling center has opened in Kuala Lumpur, accepting all types of recyclable materials.',
        'date' => '2 hours ago',
        'icon' => 'fa-building'
    ],
    [
        'title' => '♻️ Plastic Prices Increase by 5%',
        'desc' => 'PET plastic prices have increased due to high demand. Great time to recycle your plastic bottles!',
        'date' => '5 hours ago',
        'icon' => 'fa-chart-line'
    ],
    [
        'title' => '🏆 EcoWarrior Reaches 10,000 Points',
        'desc' => 'Congratulations to EcoWarrior for reaching 10,000 recycling points! Keep up the great work!',
        'date' => '1 day ago',
        'icon' => 'fa-trophy'
    ],
    [
        'title' => '📱 SmartWaste App Update v2.0',
        'desc' => 'New features added: QR scanner improvements, location tracking, and reward redemption system.',
        'date' => '2 days ago',
        'icon' => 'fa-mobile-screen'
    ],
    [
        'title' => '🌱 Weekly Challenge: Recycle 5kg of Plastic',
        'desc' => 'Complete this week\'s challenge to earn 100 bonus points! Every plastic bottle counts.',
        'date' => '3 days ago',
        'icon' => 'fa-leaf'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWaste</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="app-container">
    
    <!-- HEADER -->
    <header>
        <div class="logo"><i class="fas fa-recycle"></i> SmartWaste</div>
        <div class="user">
            <div class="avatar"><?php echo $initial; ?></div>
            <span><?php echo htmlspecialchars($username); ?></span>
            <form action="logout.php" method="POST">
                <button class="logout-btn"><i class="fas fa-sign-out-alt"></i></button>
            </form>
        </div>
    </header>

    <!-- ===== PAGE 1: HOME ===== -->
    <div id="home" class="page active">
        <!-- Quick Stats -->
        <div class="stats-row">
            <div class="stat-mini">
                <span class="stat-mini-icon">⭐</span>
                <div>
                    <div class="stat-mini-num"><?php echo number_format($rewardPoints); ?></div>
                    <div class="stat-mini-label">Points</div>
                </div>
            </div>
            <div class="stat-mini">
                <span class="stat-mini-icon">🏆</span>
                <div>
                    <div class="stat-mini-num">#<?php echo $rank; ?></div>
                    <div class="stat-mini-label">Rank</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-title"><i class="fas fa-bolt"></i> Quick Actions</div>
            <div class="action-grid">
                <button class="action-btn" onclick="showPage('locator')">
                    <i class="fas fa-map-pin"></i>
                    <span>Locate</span>
                </button>
                <button class="action-btn" onclick="showPage('rewards')">
                    <i class="fas fa-gift"></i>
                    <span>Rewards</span>
                </button>
                <button class="action-btn" onclick="showPage('profile')">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </button>
                <button class="action-btn" onclick="createGroupPickup()">
                    <i class="fas fa-users"></i>
                    <span>Group Pickup</span>
                </button>
            </div>
        </div>

        <!-- ============================================= -->
        <!-- GROUP PICKUP - NEARBY GROUPS -->
        <!-- ============================================= -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-users"></i> Pickup Groups Near You
                <button class="btn btn-green btn-sm" onclick="createGroupPickup()" style="margin-left:auto;">
                    <i class="fas fa-plus"></i> Create
                </button>
            </div>
            
            <div id="nearbyGroups">
                <?php
                try {
                    $pdo = getDBConnection();
                    $groups = $pdo->query("
                        SELECT 
                            g.*,
                            rc.name as center_name,
                            rc.address as center_address,
                            COUNT(p.id) as participant_count,
                            GROUP_CONCAT(DISTINCT u.username SEPARATOR ', ') as participants
                        FROM group_pickups g
                        JOIN recycling_centers rc ON g.center_id = rc.id
                        LEFT JOIN group_pickup_participants p ON g.id = p.group_pickup_id AND p.status != 'cancelled'
                        LEFT JOIN users u ON p.user_id = u.id
                        WHERE g.status IN ('open', 'full')
                        AND g.pickup_date >= CURDATE()
                        GROUP BY g.id
                        ORDER BY g.pickup_date ASC, g.pickup_time ASC
                        LIMIT 5
                    ")->fetchAll();
                    
                    foreach ($groups as $group):
                        $isFull = $group['current_participants'] >= $group['max_participants'];
                        $spotsLeft = $group['max_participants'] - $group['current_participants'];
                        $costShare = calculateSharedCost($group['id']);
                        $displayDate = date('l, M d', strtotime($group['pickup_date']));
                        $displayTime = date('h:i A', strtotime($group['pickup_time']));
                ?>
                    <div class="group-item">
                        <div class="group-item-header">
                            <div>
                                <div class="group-item-center">🏢 <?php echo htmlspecialchars($group['center_name']); ?></div>
                                <div class="group-item-address">📍 <?php echo htmlspecialchars($group['center_address']); ?></div>
                                <div class="group-item-time">🕐 <?php echo $displayDate; ?> at <?php echo $displayTime; ?></div>
                            </div>
                            <div class="group-item-right">
                                <div class="group-item-participants">
                                    <i class="fas fa-user"></i> <?php echo $group['current_participants']; ?>/<?php echo $group['max_participants']; ?>
                                </div>
                                <?php if ($costShare): ?>
                                    <div class="group-item-price">RM <?php echo number_format($costShare['share_per_person'], 2); ?></div>
                                    <div class="group-item-savings">Save RM <?php echo number_format($costShare['savings'], 2); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($group['participants']): ?>
                            <div class="group-item-participants-list">👥 <?php echo htmlspecialchars($group['participants']); ?></div>
                        <?php endif; ?>
                        
                        <div class="group-item-actions">
                            <?php if ($isFull): ?>
                                <span class="tag tag-full">Full</span>
                            <?php else: ?>
                                <span class="tag tag-available"><?php echo $spotsLeft; ?> spots left</span>
                            <?php endif; ?>
                            
                            <?php if ($group['creator_id'] == $_SESSION['user_id']): ?>
                                <span class="tag tag-your-group">Your Group</span>
                            <?php endif; ?>
                            
                            <button class="btn btn-green btn-sm" onclick="joinGroup(<?php echo $group['id']; ?>)">
                                <i class="fas fa-handshake"></i> Join
                            </button>
                            
                            <button class="btn btn-blue btn-sm" onclick="viewGroupDetails(<?php echo $group['id']; ?>)">
                                <i class="fas fa-info-circle"></i> Details
                            </button>
                        </div>
                    </div>
                <?php 
                    endforeach;
                    if (empty($groups)):
                ?>
                    <div class="empty-state">
                        <i class="fas fa-users" style="font-size:2rem;display:block;margin-bottom:8px;color:#d0e0d6;"></i>
                        No active group pickups nearby.<br>
                        <button class="btn btn-green btn-sm" onclick="createGroupPickup()" style="margin-top:8px;">
                            <i class="fas fa-plus"></i> Create One
                        </button>
                    </div>
                <?php 
                    endif;
                } catch (PDOException $e) {
                    error_log("Groups error: " . $e->getMessage());
                    echo '<div class="empty-state">Unable to load groups. Please create one!</div>';
                }
                ?>
            </div>
        </div>

        <!-- News / What's New -->
        <div class="card">
            <div class="card-title"><i class="fas fa-newspaper"></i> What's New</div>
            <div class="news-list">
                <?php foreach ($news as $item): ?>
                    <div class="news-item">
                        <div class="news-icon"><i class="fas <?php echo $item['icon']; ?>"></i></div>
                        <div class="news-content">
                            <div class="news-title"><?php echo $item['title']; ?></div>
                            <div class="news-desc"><?php echo $item['desc']; ?></div>
                            <div class="news-date"><i class="far fa-clock"></i> <?php echo $item['date']; ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ===== PAGE 2: LOCATOR ===== -->
    <div id="locator" class="page">
        <h2 class="page-title"><i class="fas fa-map-pin"></i> Near You</h2>
        
        <div id="locBanner" class="banner waiting">📍 Click <strong>"Get My Location"</strong> to request permission.</div>
        
        <div class="locate-box">
            <div id="map"></div>
            
            <div class="status-row">
                <span id="locStatus">
                    <span class="dot waiting" id="locDot"></span>
                    <span id="locText">Click "Get My Location"</span>
                </span>
                <button class="btn-locate" onclick="getLocation()" id="locBtn">
                    <i class="fas fa-location-dot"></i> Get My Location
                </button>
            </div>
            <div class="coords" id="locCoords"></div>
        </div>

        <div class="nearest-box" id="nearestBox">
            <div class="nearest-inner">
                <div>
                    <div class="nearest-label">⭐ Nearest Center</div>
                    <div class="nearest-name" id="nearestName">-</div>
                    <div class="nearest-addr" id="nearestAddr">-</div>
                </div>
                <div style="text-align:right;">
                    <div class="nearest-label">Distance</div>
                    <div class="nearest-dist" id="nearestDist">-</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">
                <i class="fas fa-list"></i> All Centers
                <span class="center-count" id="centerCount">0 centers</span>
            </div>
            <div id="centerList">
                <?php foreach ($centers as $center): ?>
                    <div class="item" data-lat="<?php echo $center['latitude']; ?>" data-lng="<?php echo $center['longitude']; ?>">
                        <div class="info">
                            <h4><?php echo htmlspecialchars($center['name']); ?></h4>
                            <p><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($center['address']); ?></p>
                        </div>
                        <div class="dist" id="dist_<?php echo $center['id']; ?>">
                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($center['phone'] ?? 'N/A'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($centers)): ?>
                    <div class="empty-state">No centers found</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== PAGE 3: REWARDS ===== -->
    <div id="rewards" class="page">
        <h2 class="page-title"><i class="fas fa-gift"></i> Rewards</h2>
        
        <div class="reward-summary">
            <div class="reward-total">
                <div class="reward-total-label">Available Points</div>
                <div class="reward-total-amount"><?php echo number_format($rewardPoints); ?></div>
            </div>
            <div class="reward-level">
                <div class="reward-level-label">Current Level</div>
                <div class="reward-level-badge"><?php echo $level; ?></div>
            </div>
        </div>

        <!-- Partner Rewards -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-store"></i> Partner Rewards
                <span class="center-count" style="margin-left:auto;">Redeem with points</span>
            </div>
            
            <?php if (!empty($partnerRewards)): ?>
            <div class="partner-grid">
                <?php foreach ($partnerRewards as $reward): 
                    $isGrabFood = strpos($reward['partner_name'], 'GrabFood') !== false;
                    $isTealive = strpos($reward['partner_name'], 'Tealive') !== false;
                    $isMcD = strpos($reward['partner_name'], 'McDonald') !== false;
                    $isKFC = strpos($reward['partner_name'], 'KFC') !== false;
                    $isPizzaHut = strpos($reward['partner_name'], 'Pizza Hut') !== false;
                    $isStarbucks = strpos($reward['partner_name'], 'Starbucks') !== false;
                    $isFoodpanda = strpos($reward['partner_name'], 'Foodpanda') !== false;
                    
                    $bgColor = '#0f3d26';
                    $link = '#';
                    $imageText = '🎁';
                    $badgeText = 'Popular';
                    
                    if ($isGrabFood) {
                        $bgColor = '#00b14f';
                        $link = 'https://www.grab.com/my/food/';
                        $imageText = '🍔';
                        $badgeText = 'Popular';
                    } elseif ($isTealive) {
                        $bgColor = '#6b2c8c';
                        $link = 'https://www.tealive.com.my/';
                        $imageText = '🧋';
                        $badgeText = 'Trending';
                    } elseif ($isMcD) {
                        $bgColor = '#da291c';
                        $link = 'https://www.mcdonalds.com.my/';
                        $imageText = '🍟';
                        $badgeText = 'Hot';
                    } elseif ($isKFC) {
                        $bgColor = '#e4002b';
                        $link = 'https://www.kfc.com.my/';
                        $imageText = '🍗';
                        $badgeText = 'Best Value';
                    } elseif ($isPizzaHut) {
                        $bgColor = '#0066b3';
                        $link = 'https://www.pizzahut.com.my/';
                        $imageText = '🍕';
                        $badgeText = 'Exclusive';
                    } elseif ($isStarbucks) {
                        $bgColor = '#006241';
                        $link = 'https://www.starbucks.com.my/';
                        $imageText = '☕';
                        $badgeText = 'Premium';
                    } elseif ($isFoodpanda) {
                        $bgColor = '#d70f64';
                        $link = 'https://www.foodpanda.my/';
                        $imageText = '🛵';
                        $badgeText = 'Popular';
                    }
                ?>
                    <div class="partner-card" style="background:#ffffff;border-radius:16px;overflow:hidden;cursor:pointer;box-shadow:0 2px 12px rgba(0,0,0,0.08);transition:transform 0.2s, box-shadow 0.2s;border:1px solid #f0f0f0;">
                        <!-- Image Placeholder with border-radius: 16px on all edges -->
                        <div class="partner-card-image" style="background:<?php echo $bgColor; ?>;height:110px;display:flex;align-items:center;justify-content:center;position:relative;border-radius:16px;overflow:hidden;margin:0;">
                            <div style="font-size:3.5rem;opacity:0.9;filter:drop-shadow(0 4px 8px rgba(0,0,0,0.2));"><?php echo $imageText; ?></div>
                            <div style="position:absolute;bottom:8px;right:10px;background:rgba(255,255,255,0.25);padding:2px 14px;border-radius:12px;font-size:0.5rem;font-weight:700;color:#fff;text-transform:uppercase;backdrop-filter:blur(4px);letter-spacing:0.5px;">
                                <?php echo $badgeText; ?>
                            </div>
                        </div>
                        <div class="partner-card-content" style="padding:12px 14px 14px;">
                            <div class="partner-card-brand" style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?php echo $bgColor; ?>;"></span>
                                <span style="font-size:0.65rem;font-weight:600;color:#5f7f6b;"><?php echo htmlspecialchars($reward['partner_name']); ?></span>
                            </div>
                            <div class="partner-card-title" style="font-weight:700;font-size:0.9rem;color:#0f3d26;margin-bottom:3px;"><?php echo htmlspecialchars($reward['discount_value']); ?></div>
                            <div class="partner-card-desc" style="font-size:0.7rem;color:#5f7f6b;margin-bottom:8px;line-height:1.4;"><?php echo htmlspecialchars($reward['description']); ?></div>
                            <div class="partner-card-footer" style="display:flex;justify-content:space-between;align-items:center;flex-direction:column;gap:8px;">
                                <div class="partner-card-points" style="font-weight:700;font-size:0.85rem;color:#0f3d26;width:100%;text-align:center;"><?php echo number_format($reward['points_required']); ?> points</div>
                                <button class="partner-card-btn" onclick="redeemReward(<?php echo $reward['id']; ?>, <?php echo $reward['points_required']; ?>, '<?php echo $link; ?>')" style="background:<?php echo $bgColor; ?>;color:#fff;border:none;padding:6px 16px;border-radius:20px;font-size:0.7rem;font-weight:600;cursor:pointer;transition:0.2s;font-family:inherit;width:100%;">
                                    Redeem Now →
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-store" style="font-size:2rem;display:block;margin-bottom:8px;color:#d0e0d6;"></i>
                    No partner rewards available at the moment.<br>
                    <span style="font-size:0.7rem;color:#8baa98;">Check back soon for new offers!</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- My Redemptions -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-history"></i> My Redemptions
            </div>
            <?php if (!empty($userRedemptions)): ?>
            <div class="redemption-list">
                <?php foreach ($userRedemptions as $redemption): 
                    $isGrabFood = strpos($redemption['partner_name'], 'GrabFood') !== false;
                    $bgColor = $isGrabFood ? '#00b14f' : '#6b2c8c';
                ?>
                    <div class="redemption-item" style="border-left:4px solid <?php echo $bgColor; ?>;padding-left:12px;">
                        <div>
                            <div class="redemption-item-name">
                                <span style="color:<?php echo $bgColor; ?>;">●</span>
                                <?php echo htmlspecialchars($redemption['partner_name']); ?>
                            </div>
                            <div class="redemption-item-desc"><?php echo htmlspecialchars($redemption['description']); ?></div>
                            <div class="redemption-item-code">Code: <strong><?php echo $redemption['redemption_code']; ?></strong></div>
                        </div>
                        <div style="text-align:right;">
                            <div class="redemption-item-discount"><?php echo $redemption['discount_value']; ?></div>
                            <div class="redemption-item-points">-<?php echo number_format($redemption['points_spent']); ?> pts</div>
                            <span class="tag" style="background:<?php echo $redemption['status'] === 'used' ? '#d4edda' : '#fff3cd'; ?>;font-size:0.55rem;">
                                <?php echo ucfirst($redemption['status']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-receipt" style="font-size:2rem;display:block;margin-bottom:8px;color:#d0e0d6;"></i>
                    No redemptions yet.<br>
                    <span style="font-size:0.7rem;color:#8baa98;">Start earning points to redeem rewards!</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== PAGE 4: PROFILE ===== -->
    <div id="profile" class="page">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <div class="profile-avatar-circle">
                    <?php echo $initial; ?>
                </div>
                <div class="profile-avatar-badge">
                    <i class="fas fa-crown"></i>
                </div>
            </div>
            <div class="profile-name"><?php echo htmlspecialchars($username); ?></div>
            <div class="profile-level">🏆 <?php echo $level; ?> Level</div>
            <div class="profile-stats" style="grid-template-columns: 1fr 1fr;">
                <div class="profile-stat">
                    <div class="profile-stat-number"><?php echo number_format($rewardPoints); ?></div>
                    <div class="profile-stat-label">Points</div>
                </div>
                <div class="profile-stat">
                    <div class="profile-stat-number">#<?php echo $rank; ?></div>
                    <div class="profile-stat-label">Rank</div>
                </div>
            </div>
        </div>

        <!-- Payment Method -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-wallet"></i> Payment Method
                <button class="btn btn-green btn-sm" onclick="addPaymentMethod()" style="margin-left:auto;">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>
            <?php if (!empty($paymentMethods)): ?>
                <?php foreach ($paymentMethods as $method): ?>
                    <div class="payment-item" style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #eaf3ec;">
                        <div>
                            <div style="font-weight:600;font-size:0.9rem;">
                                <?php if ($method['payment_type'] == 'bank'): ?>
                                     <?php echo htmlspecialchars($method['bank_name']); ?>
                                <?php elseif ($method['payment_type'] == 'tng'): ?>
                                     Touch 'n Go
                                <?php elseif ($method['payment_type'] == 'grabpay'): ?>
                                     GrabPay
                                <?php endif; ?>
                                <?php if ($method['is_default']): ?>
                                    <span class="tag" style="background:#d4edda;color:#155724;font-size:0.55rem;">Default</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:0.75rem;color:#5f7f6b;">
                                <?php if ($method['payment_type'] == 'bank'): ?>
                                    <?php echo htmlspecialchars($method['account_name']); ?> - <?php echo htmlspecialchars($method['account_number']); ?>
                                <?php elseif ($method['payment_type'] == 'tng'): ?>
                                    <?php echo htmlspecialchars($method['tng_phone']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <button class="btn btn-blue btn-sm" onclick="setDefaultPayment(<?php echo $method['id']; ?>)">Set Default</button>
                            <button class="btn btn-gray btn-sm" onclick="removePayment(<?php echo $method['id']; ?>)"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-wallet" style="font-size:1.5rem;display:block;margin-bottom:8px;color:#d0e0d6;"></i>
                    No payment method added yet.<br>
                    <span style="font-size:0.7rem;color:#8baa98;">Add your bank account or Touch 'n Go to receive payments</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment History -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-history"></i> Payment History
            </div>
            <?php if (!empty($paymentTransactions)): ?>
                <?php foreach ($paymentTransactions as $transaction): ?>
                    <div class="transaction-item" style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #eaf3ec;">
                        <div>
                            <div style="font-weight:600;font-size:0.85rem;color:#0f3d26;">
                                <?php echo htmlspecialchars($transaction['material_type']); ?>
                            </div>
                            <div style="font-size:0.7rem;color:#5f7f6b;">
                                <?php echo $transaction['weight_kg']; ?> kg • <?php echo date('M d, h:i A', strtotime($transaction['created_at'])); ?>
                            </div>
                            <div style="font-size:0.65rem;color:#5f7f6b;">
                                Status: 
                                <span style="color:<?php echo $transaction['status'] == 'completed' ? '#28a745' : '#f6c445'; ?>;">
                                    <?php echo ucfirst($transaction['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:700;font-size:1.1rem;color:#0f3d26;">
                                +RM <?php echo number_format($transaction['amount'], 2); ?>
                            </div>
                            <?php if ($transaction['status'] == 'completed'): ?>
                                <span class="tag" style="background:#d4edda;color:#155724;">✅ Paid</span>
                            <?php else: ?>
                                <span class="tag" style="background:#fff3cd;color:#856404;">⏳ Pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">No payment transactions yet.<br>Start recycling to earn money!</div>
            <?php endif; ?>
        </div>

        <!-- Notifications -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-bell"></i> Notifications
                <?php 
                $unreadCount = 0;
                foreach ($notifications as $n) {
                    if (!$n['is_read']) $unreadCount++;
                }
                ?>
                <?php if ($unreadCount > 0): ?>
                    <span class="tag" style="background:#dc3545;color:#fff;font-size:0.55rem;"><?php echo $unreadCount; ?> new</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item" style="display:flex;gap:12px;padding:10px 0;border-bottom:1px solid #eaf3ec;<?php echo !$notif['is_read'] ? 'background:#f8fbf9;padding:10px 12px;border-radius:8px;' : ''; ?>">
                        <div style="font-size:1.2rem;color:<?php echo $notif['type'] == 'payment' ? '#28a745' : ($notif['type'] == 'success' ? '#1f8a4a' : '#17a2b8'); ?>;">
                            <i class="fas <?php echo $notif['icon'] ?? ($notif['type'] == 'payment' ? 'fa-money-bill-wave' : 'fa-bell'); ?>"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="font-weight:600;font-size:0.85rem;"><?php echo htmlspecialchars($notif['title']); ?></div>
                            <div style="font-size:0.75rem;color:#5f7f6b;"><?php echo htmlspecialchars($notif['message']); ?></div>
                            <div style="font-size:0.6rem;color:#8baa98;margin-top:2px;">
                                <?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?>
                            </div>
                        </div>
                        <?php if (!$notif['is_read']): ?>
                            <button class="btn btn-sm btn-gray" onclick="markRead(<?php echo $notif['id']; ?>)" style="font-size:0.55rem;padding:2px 8px;">Mark read</button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">No notifications yet</div>
            <?php endif; ?>
        </div>

        <!-- 3D Podium Leaderboard -->
        <div class="card podium-card">
            <div class="card-title"><i class="fas fa-trophy"></i> Top Recyclers</div>
            <div class="podium-container">
                <?php 
                $top3 = array_slice($leaderboard, 0, 3);
                $podiumData = [];
                
                if (isset($top3[0])) {
                    $podiumData[] = ['user' => $top3[0], 'class' => 'podium-gold', 'medal' => '🥇', 'height' => '100%', 'rank' => '1st'];
                } else { $podiumData[] = null; }
                
                if (isset($top3[1])) {
                    $podiumData[] = ['user' => $top3[1], 'class' => 'podium-silver', 'medal' => '🥈', 'height' => '75%', 'rank' => '2nd'];
                } else { $podiumData[] = null; }
                
                if (isset($top3[2])) {
                    $podiumData[] = ['user' => $top3[2], 'class' => 'podium-bronze', 'medal' => '🥉', 'height' => '55%', 'rank' => '3rd'];
                } else { $podiumData[] = null; }
                
                $displayOrder = [1, 0, 2];
                foreach ($displayOrder as $index):
                    $data = $podiumData[$index] ?? null;
                    if ($data):
                        $user = $data['user'];
                        $bgColor = $data['class'] === 'podium-gold' ? '#ffd700' : ($data['class'] === 'podium-silver' ? '#c0c0c0' : '#cd7f32');
                ?>
                    <div class="podium-item <?php echo $data['class']; ?>" style="height: <?php echo $data['height']; ?>;">
                        <div class="podium-medal"><?php echo $data['medal']; ?></div>
                        <div class="podium-avatar" style="background: <?php echo $bgColor; ?>;">
                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        </div>
                        <div class="podium-name"><?php echo htmlspecialchars($user['username']); ?></div>
                        <div class="podium-points"><?php echo number_format($user['total_points']); ?> pts</div>
                        <div class="podium-body"></div>
                        <div class="podium-base"></div>
                        <div class="podium-rank-badge"><?php echo $data['rank']; ?></div>
                    </div>
                <?php 
                    else:
                ?>
                    <div class="podium-item" style="height: 30%; opacity: 0.3;">
                        <div class="podium-medal">?</div>
                        <div class="podium-avatar" style="background: #ddd;">?</div>
                        <div class="podium-name">Empty</div>
                        <div class="podium-points">0 pts</div>
                        <div class="podium-body" style="background: #ddd; height: 20px;"></div>
                        <div class="podium-base" style="background: #bbb;"></div>
                        <div class="podium-rank-badge">-</div>
                    </div>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
        </div>

        <!-- Full Leaderboard -->
        <div class="card">
            <div class="card-title"><i class="fas fa-ranking-star"></i> Leaderboard</div>
            <?php foreach (array_slice($leaderboard, 0, 10) as $index => $user): ?>
                <div class="leader-item">
                    <span class="rank <?php echo $index === 0 ? 'gold' : ($index === 1 ? 'silver' : ($index === 2 ? 'bronze' : '')); ?>">
                        <?php echo $index + 1; ?>
                    </span>
                    <div class="info">
                        <div class="name">
                            <?php echo htmlspecialchars($user['username']); ?>
                            <?php if ($user['user_id'] == $_SESSION['user_id']): ?>
                                <span class="you-tag">(You)</span>
                            <?php endif; ?>
                        </div>
                        <div class="pts"><?php echo number_format($user['total_points']); ?> points</div>
                    </div>
                    <span class="badge"><?php echo $user['level']; ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (empty($leaderboard)): ?>
                <div class="empty-state">No users yet</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== BOTTOM NAV ===== -->
    <nav class="bottom-nav">
        <button class="active" onclick="showPage('home')"><i class="fas fa-house"></i> Home</button>
        <button onclick="showPage('locator')"><i class="fas fa-map-pin"></i> Locate</button>
        <button onclick="showPage('rewards')"><i class="fas fa-gift"></i> Rewards</button>
        <button onclick="showPage('profile')"><i class="fas fa-user"></i> Profile</button>
    </nav>
</div>

<!-- ===== TOAST ===== -->
<div class="toast" id="toast"></div>

<!-- ===== LEAFTLET JS ===== -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- ===== JAVASCRIPT ===== -->
<script>
    var centerData = <?php echo json_encode($centerData); ?>;
    var username = '<?php echo htmlspecialchars($username); ?>';
    var rewardPoints = <?php echo $rewardPoints; ?>;
</script>
<script src="script.js"></script>

</body>
</html>