<?php
#check & start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

#database connection - only require, not redefine
require_once 'config.php';

function loginUser($username, $password) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username OR email = :email");
        $stmt->execute(['username' => $username, 'email' => $username]);
        $user = $stmt->fetch();
        
        if ($user) {
            $storedPassword = $user['password'];
            $valid = false;
            
            // Check if it's an MD5 hash (32 characters, hexadecimal)
            if (preg_match('/^[a-f0-9]{32}$/', $storedPassword)) {
                // MD5 check (for demo user from database.sql)
                $valid = (md5($password) === $storedPassword);
            } else {
                // password_hash check (for future users)
                $valid = password_verify($password, $storedPassword);
            }
            
            if ($valid) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                return true;
            }
        }
        return false;
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function registerUser($username, $email, $password, $fullName = null, $phone = null) {
    try {
        $pdo = getDBConnection();
        
        $check = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
        $check->execute(['username' => $username, 'email' => $email]);
        if ($check->fetch()) {
            return false;
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, full_name, phone) 
            VALUES (:username, :email, :password, :full_name, :phone)
        ");
        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword,
            'full_name' => $fullName ?? $username,
            'phone' => $phone
        ]);
        
        $userId = $pdo->lastInsertId();
        
        $reward = $pdo->prepare("INSERT INTO user_rewards (user_id) VALUES (:user_id)");
        $reward->execute(['user_id' => $userId]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Register error: " . $e->getMessage());
        return false;
    }
}

function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) return null;
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT u.*, ur.total_points, ur.carbon_saved, ur.items_scanned, ur.times_recycled, ur.level
            FROM users u
            LEFT JOIN user_rewards ur ON u.id = ur.user_id
            WHERE u.id = :id
        ");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get user error: " . $e->getMessage());
        return null;
    }
}

// ==========================================
// GROUP PICKUP FUNCTIONS
// ==========================================

function createGroupPickup($userId, $centerId, $pickupDate, $pickupTime, $maxParticipants = 5, $meetingPoint = null, $notes = null) {
    try {
        $pdo = getDBConnection();
        
        $pdo->beginTransaction();
        
        // Create group
        $stmt = $pdo->prepare("
            INSERT INTO group_pickups (creator_id, center_id, pickup_date, pickup_time, max_participants, meeting_point, notes)
            VALUES (:creator_id, :center_id, :pickup_date, :pickup_time, :max_participants, :meeting_point, :notes)
        ");
        $stmt->execute([
            'creator_id' => $userId,
            'center_id' => $centerId,
            'pickup_date' => $pickupDate,
            'pickup_time' => $pickupTime,
            'max_participants' => $maxParticipants,
            'meeting_point' => $meetingPoint,
            'notes' => $notes
        ]);
        
        $groupId = $pdo->lastInsertId();
        
        // Add creator as first participant - with empty address as placeholder
        // (actual address is added in create_group_pickup.php with the form data)
        $stmt = $pdo->prepare("
            INSERT INTO group_pickup_participants (group_pickup_id, user_id, address, status)
            VALUES (:group_id, :user_id, '', 'accepted')
        ");
        $stmt->execute([
            'group_id' => $groupId,
            'user_id' => $userId
        ]);
        
        $pdo->commit();
        
        return $groupId;
    } catch (PDOException $e) {
        if (isset($pdo)) $pdo->rollBack();
        error_log("Create group pickup error: " . $e->getMessage());
        return false;
    }
}

function joinGroupPickup($userId, $groupId, $address, $estimatedWeight = null, $itemDescription = null) {
    try {
        $pdo = getDBConnection();
        
        // Check if group is open and not full
        $stmt = $pdo->prepare("
            SELECT * FROM group_pickups 
            WHERE id = :id AND status = 'open' 
            AND current_participants < max_participants
        ");
        $stmt->execute(['id' => $groupId]);
        $group = $stmt->fetch();
        
        if (!$group) {
            return ['error' => 'Group is full or no longer available'];
        }
        
        // Check if user already joined
        $stmt = $pdo->prepare("
            SELECT id FROM group_pickup_participants 
            WHERE group_pickup_id = :group_id AND user_id = :user_id
        ");
        $stmt->execute(['group_id' => $groupId, 'user_id' => $userId]);
        if ($stmt->fetch()) {
            return ['error' => 'You have already joined this group'];
        }
        
        $pdo->beginTransaction();
        
        // Add participant - FIXED: Added status field
        $stmt = $pdo->prepare("
            INSERT INTO group_pickup_participants (group_pickup_id, user_id, address, estimated_weight, item_description, status)
            VALUES (:group_id, :user_id, :address, :weight, :description, 'accepted')
        ");
        $stmt->execute([
            'group_id' => $groupId,
            'user_id' => $userId,
            'address' => $address,
            'weight' => $estimatedWeight,
            'description' => $itemDescription
        ]);
        
        // Update participant count
        $stmt = $pdo->prepare("
            UPDATE group_pickups 
            SET current_participants = current_participants + 1,
                estimated_total_weight = estimated_total_weight + :weight
            WHERE id = :id
        ");
        $stmt->execute([
            'weight' => $estimatedWeight ?? 0,
            'id' => $groupId
        ]);
        
        // Check if group is full
        $stmt = $pdo->prepare("
            UPDATE group_pickups 
            SET status = 'full' 
            WHERE id = :id AND current_participants >= max_participants
        ");
        $stmt->execute(['id' => $groupId]);
        
        $pdo->commit();
        
        return ['success' => true, 'group_id' => $groupId];
    } catch (PDOException $e) {
        if (isset($pdo)) $pdo->rollBack();
        error_log("Join group pickup error: " . $e->getMessage());
        return ['error' => 'Failed to join group'];
    }
}

function getNearbyGroupPickups($userId, $latitude, $longitude, $radius = 10) {
    try {
        $pdo = getDBConnection();
        
        // Get active groups with center locations
        $stmt = $pdo->prepare("
            SELECT 
                g.*,
                rc.name as center_name,
                rc.address as center_address,
                rc.latitude as center_lat,
                rc.longitude as center_lng,
                (6371 * acos(cos(radians(:lat)) * cos(radians(rc.latitude)) * 
                cos(radians(rc.longitude) - radians(:lng)) + 
                sin(radians(:lat)) * sin(radians(rc.latitude)))) AS distance,
                COUNT(p.id) as participant_count,
                GROUP_CONCAT(DISTINCT u.username) as participants
            FROM group_pickups g
            JOIN recycling_centers rc ON g.center_id = rc.id
            LEFT JOIN group_pickup_participants p ON g.id = p.group_pickup_id AND p.status != 'cancelled'
            LEFT JOIN users u ON p.user_id = u.id
            WHERE g.status IN ('open', 'full')
            AND g.pickup_date >= CURDATE()
            AND (6371 * acos(cos(radians(:lat)) * cos(radians(rc.latitude)) * 
                cos(radians(rc.longitude) - radians(:lng)) + 
                sin(radians(:lat)) * sin(radians(rc.latitude)))) <= :radius
            GROUP BY g.id
            ORDER BY g.pickup_date ASC, g.pickup_time ASC
        ");
        $stmt->execute([
            'lat' => $latitude,
            'lng' => $longitude,
            'radius' => $radius
        ]);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get nearby groups error: " . $e->getMessage());
        return [];
    }
}

function calculateSharedCost($groupId) {
    try {
        $pdo = getDBConnection();
        
        // Get group details
        $stmt = $pdo->prepare("
            SELECT 
                g.*,
                COUNT(p.id) as total_participants
            FROM group_pickups g
            LEFT JOIN group_pickup_participants p ON g.id = p.group_pickup_id AND p.status != 'cancelled'
            WHERE g.id = :id
            GROUP BY g.id
        ");
        $stmt->execute(['id' => $groupId]);
        $group = $stmt->fetch();
        
        if (!$group) return false;
        
        // Calculate base cost (RM5 base + weight surcharge)
        $baseFee = 5.00;
        $weightSurcharge = max(0, ($group['estimated_total_weight'] - 10) * 0.20);
        $totalCost = $baseFee + $weightSurcharge;
        
        // Share cost among participants
        $participantCount = $group['total_participants'];
        $sharePerPerson = $participantCount > 0 ? $totalCost / $participantCount : $totalCost;
        
        return [
            'total_cost' => $totalCost,
            'participant_count' => $participantCount,
            'share_per_person' => round($sharePerPerson, 2),
            'weight_surcharge' => $weightSurcharge,
            'savings' => $totalCost - $sharePerPerson
        ];
    } catch (PDOException $e) {
        error_log("Calculate shared cost error: " . $e->getMessage());
        return false;
    }
}

// ==========================================
// PARTNER REWARD FUNCTIONS (ONLY ONCE!)
// ==========================================

function getPartnerRewards() {
    try {
        $pdo = getDBConnection();
        // Check if table exists first
        $check = $pdo->query("SHOW TABLES LIKE 'partner_rewards'");
        if ($check->rowCount() == 0) {
            return [];
        }
        $stmt = $pdo->query("
            SELECT * FROM partner_rewards 
            WHERE is_active = TRUE 
            AND (valid_until IS NULL OR valid_until > CURDATE())
            ORDER BY points_required ASC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get partner rewards error: " . $e->getMessage());
        return [];
    }
}

function getUserRedemptions($userId) {
    try {
        $pdo = getDBConnection();
        // Check if table exists first
        $check = $pdo->query("SHOW TABLES LIKE 'user_redemptions'");
        if ($check->rowCount() == 0) {
            return [];
        }
        $stmt = $pdo->prepare("
            SELECT r.*, pr.partner_name, pr.logo_url, pr.description, pr.discount_value
            FROM user_redemptions r
            JOIN partner_rewards pr ON r.partner_reward_id = pr.id
            WHERE r.user_id = :user_id
            ORDER BY r.redeemed_at DESC
            LIMIT 10
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get user redemptions error: " . $e->getMessage());
        return [];
    }
}

function redeemPartnerReward($userId, $rewardId) {
    try {
        $pdo = getDBConnection();
        
        // Get reward details
        $stmt = $pdo->prepare("
            SELECT * FROM partner_rewards 
            WHERE id = :id AND is_active = TRUE 
            AND (valid_until IS NULL OR valid_until > CURDATE())
        ");
        $stmt->execute(['id' => $rewardId]);
        $reward = $stmt->fetch();
        
        if (!$reward) {
            return ['error' => 'Reward not available'];
        }
        
        // Check user points
        $stmt = $pdo->prepare("SELECT total_points FROM user_rewards WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $userPoints = $stmt->fetch()['total_points'] ?? 0;
        
        if ($userPoints < $reward['points_required']) {
            return ['error' => 'Insufficient points. You need ' . $reward['points_required'] . ' points'];
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Deduct points
        $stmt = $pdo->prepare("
            UPDATE user_rewards 
            SET total_points = total_points - :points 
            WHERE user_id = :user_id
        ");
        $stmt->execute([
            'points' => $reward['points_required'],
            'user_id' => $userId
        ]);
        
        // Generate redemption code
        $redemptionCode = strtoupper(substr($reward['partner_name'], 0, 3)) . '-' . 
                         strtoupper(bin2hex(random_bytes(4)));
        
        // Create redemption
        $stmt = $pdo->prepare("
            INSERT INTO user_redemptions (
                user_id, partner_reward_id, points_spent, 
                redemption_code, expires_at
            ) VALUES (
                :user_id, :reward_id, :points, 
                :code, DATE_ADD(NOW(), INTERVAL 30 DAY)
            )
        ");
        $stmt->execute([
            'user_id' => $userId,
            'reward_id' => $rewardId,
            'points' => $reward['points_required'],
            'code' => $redemptionCode
        ]);
        
        $redemptionId = $pdo->lastInsertId();
        
        // Record point transaction (only if table exists)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO point_transactions (user_id, amount, type, source, source_id, description)
                VALUES (:user_id, :amount, 'spent', 'reward', :redemption_id, :description)
            ");
            $stmt->execute([
                'user_id' => $userId,
                'amount' => $reward['points_required'],
                'redemption_id' => $redemptionId,
                'description' => "Redeemed: {$reward['partner_name']} - {$reward['description']}"
            ]);
        } catch (PDOException $e) {
            // Table might not exist, continue anyway
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'redemption_code' => $redemptionCode,
            'partner_name' => $reward['partner_name'],
            'discount' => $reward['discount_value'],
            'points_spent' => $reward['points_required'],
            'expires_at' => date('M d, Y', strtotime('+30 days'))
        ];
    } catch (PDOException $e) {
        if (isset($pdo)) $pdo->rollBack();
        error_log("Redeem reward error: " . $e->getMessage());
        return ['error' => 'Failed to redeem reward'];
    }
}

// ==========================================
// PAYMENT METHOD FUNCTIONS
// ==========================================

function addPaymentMethod($userId, $type, $data) {
    try {
        $pdo = getDBConnection();
        
        // Check if table exists first
        $check = $pdo->query("SHOW TABLES LIKE 'payment_methods'");
        if ($check->rowCount() == 0) {
            // Create table if it doesn't exist
            $pdo->exec("
                CREATE TABLE payment_methods (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    payment_type ENUM('bank', 'tng', 'grabpay') NOT NULL,
                    account_name VARCHAR(100) DEFAULT NULL,
                    account_number VARCHAR(50) DEFAULT NULL,
                    bank_name VARCHAR(100) DEFAULT NULL,
                    tng_phone VARCHAR(20) DEFAULT NULL,
                    is_default TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO payment_methods (user_id, payment_type, account_name, account_number, bank_name, tng_phone, is_default)
            VALUES (:user_id, :type, :account_name, :account_number, :bank_name, :tng_phone, :is_default)
        ");
        
        $stmt->execute([
            'user_id' => $userId,
            'type' => $type,
            'account_name' => $data['account_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'tng_phone' => $data['tng_phone'] ?? null,
            'is_default' => $data['is_default'] ? 1 : 0
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Add payment method error: " . $e->getMessage());
        return false;
    }
}

function getPaymentMethods($userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM payment_methods WHERE user_id = :user_id ORDER BY is_default DESC, created_at DESC");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get payment methods error: " . $e->getMessage());
        return [];
    }
}

function getNotifications($userId, $limit = 10) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT :limit
        ");
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get notifications error: " . $e->getMessage());
        return [];
    }
}

function markNotificationRead($notificationId, $userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function getPaymentTransactions($userId, $limit = 10) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM payment_transactions 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT :limit
        ");
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get payment transactions error: " . $e->getMessage());
        return [];
    }
}

function createPaymentTransaction($userId, $dropOffId, $weight, $materialType, $amount) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO payment_transactions (user_id, drop_off_id, weight_kg, material_type, amount, status)
            VALUES (:user_id, :drop_off_id, :weight, :material_type, :amount, 'pending')
        ");
        $stmt->execute([
            'user_id' => $userId,
            'drop_off_id' => $dropOffId,
            'weight' => $weight,
            'material_type' => $materialType,
            'amount' => $amount
        ]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Create payment transaction error: " . $e->getMessage());
        return false;
    }
}
?>
