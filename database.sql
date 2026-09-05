-- ======================================================
-- SmartWaste Database (FULL VERSION)
-- 超多资料！demo 可以登录 (密码: demo123)
-- ======================================================

DROP DATABASE IF EXISTS smartwaste;
CREATE DATABASE smartwaste;
USE smartwaste;

-- ======================================================
-- 1. USERS TABLE
-- ======================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    profile_pic VARCHAR(255),
    role ENUM('user', 'admin', 'collector') DEFAULT 'user',
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- ======================================================
-- 2. USER REWARDS TABLE
-- ======================================================
CREATE TABLE user_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_points INT DEFAULT 0,
    carbon_saved DECIMAL(10, 2) DEFAULT 0.00,
    items_scanned INT DEFAULT 0,
    current_streak INT DEFAULT 0,
    longest_streak INT DEFAULT 0,
    last_scan_date DATE,
    level ENUM('Bronze', 'Silver', 'Gold', 'Platinum') DEFAULT 'Bronze',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user (user_id)
);

-- ======================================================
-- 3. SCAN HISTORY TABLE
-- ======================================================
CREATE TABLE scan_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    material_type VARCHAR(50) NOT NULL,
    material_category ENUM('plastic', 'metal', 'paper', 'glass', 'e_waste', 'organic', 'other') DEFAULT 'other',
    weight DECIMAL(8, 2),
    points_earned INT DEFAULT 0,
    scan_type ENUM('barcode', 'qr', 'image', 'manual') DEFAULT 'manual',
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    location_lat DECIMAL(10, 8),
    location_lng DECIMAL(11, 8),
    image_url VARCHAR(255),
    notes TEXT,
    scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    verified_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ======================================================
-- 4. RECYCLING CENTERS TABLE
-- ======================================================
CREATE TABLE recycling_centers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(50),
    state VARCHAR(50),
    postal_code VARCHAR(20),
    phone VARCHAR(20),
    email VARCHAR(100),
    website VARCHAR(255),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    operating_hours TEXT,
    accepted_materials TEXT,
    rating DECIMAL(3, 2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ======================================================
-- 5. MATERIAL PRICES TABLE
-- ======================================================
CREATE TABLE material_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_name VARCHAR(50) NOT NULL UNIQUE,
    category VARCHAR(50),
    price_per_kg DECIMAL(10, 2) NOT NULL,
    price_per_unit DECIMAL(10, 2),
    currency VARCHAR(10) DEFAULT 'RM',
    change_percent DECIMAL(5, 2) DEFAULT 0.00,
    is_up BOOLEAN DEFAULT TRUE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    notes TEXT
);

-- ======================================================
-- 6. TRANSACTIONS TABLE
-- ======================================================
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    center_id INT NULL,
    material_id INT NOT NULL,
    quantity DECIMAL(8, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    points_earned INT DEFAULT 0,
    transaction_type ENUM('sell', 'donate', 'exchange') DEFAULT 'sell',
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (center_id) REFERENCES recycling_centers(id) ON DELETE SET NULL,
    FOREIGN KEY (material_id) REFERENCES material_prices(id)
);

-- ======================================================
-- 7. LEADERBOARD VIEW
-- ======================================================
CREATE OR REPLACE VIEW leaderboard AS
SELECT 
    u.id AS user_id,
    u.username,
    u.full_name,
    ur.total_points,
    ur.carbon_saved,
    ur.items_scanned,
    ur.level,
    RANK() OVER (ORDER BY ur.total_points DESC) AS rank_position
FROM users u
JOIN user_rewards ur ON u.id = ur.user_id
WHERE u.role = 'user'
ORDER BY ur.total_points DESC;

-- ======================================================
-- 8. COLLECTION POINTS TABLE
-- ======================================================
CREATE TABLE collection_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    capacity INT DEFAULT 100,
    current_load INT DEFAULT 0,
    status ENUM('available', 'full', 'maintenance') DEFAULT 'available',
    last_collected TIMESTAMP NULL,
    schedule TEXT,
    contact_phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ======================================================
-- 9. NOTIFICATIONS TABLE
-- ======================================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ======================================================
-- 10. SYSTEM LOGS TABLE
-- ======================================================
CREATE TABLE system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ======================================================
-- ======================================================
-- SEED DATA - 超多资料！
-- ======================================================
-- ======================================================

-- ======================================================
-- USERS (20个用户)
-- ======================================================

-- ✅ demo 可以登录 (密码: demo123)
INSERT INTO users (id, username, email, password, full_name, phone, address, role, is_verified, created_at) VALUES
(1, 'demo', 'demo@smartwaste.com', MD5('demo123'), 'Demo User', '012-3456789', '123 Demo Street, KL', 'user', TRUE, NOW() - INTERVAL 30 DAY);

-- ❌ 其他用户 (展示用，不能登录)
INSERT INTO users (id, username, email, password, full_name, phone, address, role, is_verified, created_at) VALUES
(2, 'EcoWarrior', 'eco@smartwaste.com', 'fake_hash_12345', 'Eco Warrior', '012-3456780', '45 Green Lane, PJ', 'user', TRUE, NOW() - INTERVAL 180 DAY),
(3, 'GreenMaster', 'green@smartwaste.com', 'fake_hash_12345', 'Green Master', '012-3456781', '78 Sustainable Ave, Cyberjaya', 'user', TRUE, NOW() - INTERVAL 150 DAY),
(4, 'RecyclePro', 'recycle@smartwaste.com', 'fake_hash_12345', 'Recycle Pro', '012-3456782', '12 Recycling Blvd, KL', 'user', TRUE, NOW() - INTERVAL 120 DAY),
(5, 'TrashHunter', 'trash@smartwaste.com', 'fake_hash_12345', 'Trash Hunter', '012-3456783', '99 Waste St, PJ', 'user', TRUE, NOW() - INTERVAL 100 DAY),
(6, 'WasteBuster', 'buster@smartwaste.com', 'fake_hash_12345', 'Waste Buster', '012-3456784', '56 Clean Road, Shah Alam', 'user', TRUE, NOW() - INTERVAL 90 DAY),
(7, 'GreenGuru', 'guru@smartwaste.com', 'fake_hash_12345', 'Green Guru', '012-3456785', '33 Eco Park, KL', 'user', TRUE, NOW() - INTERVAL 80 DAY),
(8, 'ZeroWasteHero', 'zero@smartwaste.com', 'fake_hash_12345', 'Zero Waste Hero', '012-3456786', '88 Sustainable Circle, PJ', 'user', TRUE, NOW() - INTERVAL 70 DAY),
(9, 'CarbonCrusher', 'carbon@smartwaste.com', 'fake_hash_12345', 'Carbon Crusher', '012-3456787', '22 Green Valley, Cyberjaya', 'user', TRUE, NOW() - INTERVAL 60 DAY),
(10, 'PlanetSaver', 'planet@smartwaste.com', 'fake_hash_12345', 'Planet Saver', '012-3456788', '77 Eco Avenue, KL', 'user', TRUE, NOW() - INTERVAL 50 DAY),
(11, 'GreenThumb', 'thumb@smartwaste.com', 'fake_hash_12345', 'Green Thumb', '012-3456789', '11 Garden Road, PJ', 'user', TRUE, NOW() - INTERVAL 45 DAY),
(12, 'EcoFreak', 'freak@smartwaste.com', 'fake_hash_12345', 'Eco Freak', '012-3456790', '22 Nature Lane, KL', 'user', TRUE, NOW() - INTERVAL 40 DAY),
(13, 'RecycleKing', 'king@smartwaste.com', 'fake_hash_12345', 'Recycle King', '012-3456791', '33 Trash Ave, Shah Alam', 'user', TRUE, NOW() - INTERVAL 35 DAY),
(14, 'WasteWizard', 'wizard@smartwaste.com', 'fake_hash_12345', 'Waste Wizard', '012-3456792', '44 Magic Street, PJ', 'user', TRUE, NOW() - INTERVAL 30 DAY),
(15, 'EcoChampion', 'champion@smartwaste.com', 'fake_hash_12345', 'Eco Champion', '012-3456793', '55 Victory Road, KL', 'user', TRUE, NOW() - INTERVAL 25 DAY),
(16, 'GreenMachine', 'machine@smartwaste.com', 'fake_hash_12345', 'Green Machine', '012-3456794', '66 Tech Park, Cyberjaya', 'user', TRUE, NOW() - INTERVAL 20 DAY),
(17, 'PlanetProtector', 'protector@smartwaste.com', 'fake_hash_12345', 'Planet Protector', '012-3456795', '77 Shield Street, PJ', 'user', TRUE, NOW() - INTERVAL 15 DAY),
(18, 'EcoSaver', 'saver@smartwaste.com', 'fake_hash_12345', 'Eco Saver', '012-3456796', '88 Save Lane, KL', 'user', TRUE, NOW() - INTERVAL 10 DAY),
(19, 'GreenRanger', 'ranger@smartwaste.com', 'fake_hash_12345', 'Green Ranger', '012-3456797', '99 Forest Road, Shah Alam', 'user', TRUE, NOW() - INTERVAL 5 DAY),
(20, 'EcoNinja', 'ninja@smartwaste.com', 'fake_hash_12345', 'Eco Ninja', '012-3456798', '111 Shadow Street, PJ', 'user', TRUE, NOW() - INTERVAL 3 DAY);

-- ======================================================
-- USER REWARDS (20个用户的积分)
-- ======================================================
INSERT INTO user_rewards (user_id, total_points, carbon_saved, items_scanned, current_streak, longest_streak, last_scan_date, level) VALUES
(1, 850, 4.20, 15, 3, 7, CURDATE() - INTERVAL 1 DAY, 'Bronze'),
(2, 12500, 45.50, 180, 12, 30, CURDATE() - INTERVAL 1 DAY, 'Gold'),
(3, 9800, 32.20, 145, 8, 25, CURDATE() - INTERVAL 2 DAY, 'Gold'),
(4, 2840, 12.50, 47, 5, 12, CURDATE() - INTERVAL 3 DAY, 'Silver'),
(5, 2100, 9.80, 35, 4, 10, CURDATE() - INTERVAL 1 DAY, 'Silver'),
(6, 1650, 7.20, 28, 3, 8, CURDATE() - INTERVAL 4 DAY, 'Bronze'),
(7, 4200, 15.30, 62, 6, 15, CURDATE() - INTERVAL 2 DAY, 'Silver'),
(8, 3100, 11.80, 41, 4, 11, CURDATE() - INTERVAL 1 DAY, 'Silver'),
(9, 7500, 28.50, 98, 9, 20, CURDATE() - INTERVAL 3 DAY, 'Gold'),
(10, 5600, 19.40, 73, 7, 18, CURDATE() - INTERVAL 2 DAY, 'Gold'),
(11, 3200, 12.10, 44, 5, 13, CURDATE() - INTERVAL 1 DAY, 'Silver'),
(12, 1800, 8.00, 30, 3, 9, CURDATE() - INTERVAL 3 DAY, 'Bronze'),
(13, 4500, 16.80, 68, 6, 16, CURDATE() - INTERVAL 2 DAY, 'Silver'),
(14, 2300, 10.20, 38, 4, 10, CURDATE() - INTERVAL 4 DAY, 'Silver'),
(15, 6800, 25.40, 88, 8, 22, CURDATE() - INTERVAL 1 DAY, 'Gold'),
(16, 1500, 6.50, 25, 2, 6, CURDATE() - INTERVAL 5 DAY, 'Bronze'),
(17, 5200, 18.60, 78, 7, 19, CURDATE() - INTERVAL 2 DAY, 'Gold'),
(18, 2800, 11.20, 42, 4, 12, CURDATE() - INTERVAL 3 DAY, 'Silver'),
(19, 3800, 14.00, 55, 5, 14, CURDATE() - INTERVAL 1 DAY, 'Silver'),
(20, 1200, 5.80, 20, 2, 5, CURDATE() - INTERVAL 6 DAY, 'Bronze');

-- ======================================================
-- RECYCLING CENTERS (15个回收中心)
-- ======================================================
INSERT INTO recycling_centers (name, address, city, state, latitude, longitude, phone, operating_hours, accepted_materials, rating) VALUES
('Green Hub Recycling', '123 Jalan Hijau, Taman Desa', 'Kuala Lumpur', 'KL', 3.1390, 101.6869, '03-1234567', 'Mon-Sat: 9AM - 6PM', 'Plastic, Paper, Metal, Glass', 4.5),
('EcoWaste Center', '45 Jalan Merdeka, Section 14', 'Petaling Jaya', 'Selangor', 3.1077, 101.6067, '03-2345678', 'Mon-Fri: 8AM - 8PM, Sat: 9AM - 5PM', 'E-Waste, Plastic, Metal', 4.2),
('Smart Recycling Point', '78 Jalan Teknologi, Cyber 12', 'Cyberjaya', 'Selangor', 2.9356, 101.6544, '03-3456789', 'Daily: 7AM - 10PM', 'All materials accepted', 4.8),
('Recycle Hub KL', '15 Jalan Sultan Ismail', 'Kuala Lumpur', 'KL', 3.1570, 101.7020, '03-4567890', 'Mon-Sat: 10AM - 7PM', 'Paper, Cardboard, Plastic', 4.0),
('Green Earth Recycling', '88 Jalan SS2/72', 'Petaling Jaya', 'Selangor', 3.1140, 101.6220, '03-5678901', 'Mon-Fri: 9AM - 5PM', 'Glass, Metal, E-Waste', 4.3),
('Zero Waste Centre', '5 Jalan Sustainability, Taman Tun', 'Kuala Lumpur', 'KL', 3.1700, 101.6800, '03-6789012', 'Mon-Sat: 8AM - 8PM', 'All materials', 4.7),
('Eco Hub Bangsar', '22 Jalan Telawi, Bangsar', 'Kuala Lumpur', 'KL', 3.1300, 101.6700, '03-7890123', 'Daily: 9AM - 9PM', 'Plastic, Metal, Glass', 4.1),
('Recycling Center Damansara', '55 Jalan Damansara, Damansara', 'Petaling Jaya', 'Selangor', 3.1500, 101.6100, '03-8901234', 'Mon-Sat: 10AM - 8PM', 'Paper, Plastic, E-Waste', 4.4),
('Green Point Cheras', '88 Jalan Cheras, Cheras', 'Kuala Lumpur', 'KL', 3.1000, 101.7400, '03-9012345', 'Daily: 8AM - 10PM', 'All materials', 4.6),
('Eco Recycling Ampang', '123 Jalan Ampang, Ampang', 'Ampang', 'Selangor', 3.1600, 101.7600, '03-0123456', 'Mon-Sat: 9AM - 7PM', 'Metal, Glass, Plastic', 4.0),
('Green Lane Recycling', '45 Jalan Green Lane, Green Lane', 'Petaling Jaya', 'Selangor', 3.0800, 101.5900, '03-1234560', 'Mon-Fri: 8AM - 6PM', 'Paper, Cardboard', 3.8),
('Sunway Recycling Hub', '99 Jalan Sunway, Sunway', 'Petaling Jaya', 'Selangor', 3.0700, 101.6000, '03-2345670', 'Daily: 10AM - 10PM', 'All materials', 4.9),
('Puchong Recycling Point', '77 Jalan Puchong, Puchong', 'Puchong', 'Selangor', 3.0400, 101.6200, '03-3456780', 'Mon-Sat: 9AM - 8PM', 'Plastic, Metal, Glass', 4.2),
('Shah Alam Recycling', '66 Jalan Alam, Shah Alam', 'Shah Alam', 'Selangor', 3.0600, 101.5500, '03-4567890', 'Mon-Fri: 9AM - 5PM', 'E-Waste, Paper', 3.9),
('KL Eco Centre', '33 Jalan Tun Razak, KL', 'Kuala Lumpur', 'KL', 3.1600, 101.6900, '03-5678900', 'Daily: 7AM - 11PM', 'All materials', 4.5);

-- ======================================================
-- MATERIAL PRICES (25种材料)
-- ======================================================
INSERT INTO material_prices (material_name, category, price_per_kg, change_percent, is_up) VALUES
('Aluminum Cans', 'metal', 3.20, 4.9, TRUE),
('PET Plastic', 'plastic', 1.80, -2.7, FALSE),
('HDPE Plastic', 'plastic', 2.10, 1.5, TRUE),
('Cardboard', 'paper', 0.90, 12.5, TRUE),
('Newspaper', 'paper', 0.70, 0.0, TRUE),
('Glass Bottles', 'glass', 0.60, 0.0, TRUE),
('E-Waste (PCB)', 'e_waste', 12.50, 6.8, TRUE),
('E-Waste (Battery)', 'e_waste', 8.00, -5.0, FALSE),
('Steel Scrap', 'metal', 2.50, 3.2, TRUE),
('Copper Wire', 'metal', 25.00, 8.5, TRUE),
('Paper (Mixed)', 'paper', 0.50, -1.2, FALSE),
('Plastic (Mixed)', 'plastic', 1.20, 2.0, TRUE),
('Glass (Mixed)', 'glass', 0.40, 0.0, TRUE),
('Organic Waste', 'organic', 0.30, 0.0, TRUE),
('Textile', 'other', 1.00, -0.5, FALSE),
('Aluminum (Pure)', 'metal', 5.00, 3.5, TRUE),
('Brass Scrap', 'metal', 4.50, 2.1, TRUE),
('Iron Scrap', 'metal', 1.80, -1.0, FALSE),
('PVC Plastic', 'plastic', 1.50, 2.5, TRUE),
('Polystyrene', 'plastic', 1.00, -0.5, FALSE),
('Carton Box', 'paper', 0.80, 5.0, TRUE),
('Magazine', 'paper', 0.60, 0.0, TRUE),
('Wine Bottles', 'glass', 0.70, 2.0, TRUE),
('LED E-Waste', 'e_waste', 15.00, 10.0, TRUE),
('Plastic Bottles', 'plastic', 1.60, -1.5, FALSE);

-- ======================================================
-- COLLECTION POINTS (15个收集点)
-- ======================================================
INSERT INTO collection_points (name, address, latitude, longitude, capacity, current_load, status, schedule, contact_phone) VALUES
('Taman Desa Collection Point', '123 Jalan Hijau, Taman Desa', 3.1390, 101.6869, 200, 45, 'available', 'Mon-Sat: 8AM - 8PM', '012-3456789'),
('Section 14 Drop-off', '45 Jalan Merdeka, PJ', 3.1077, 101.6067, 150, 120, 'available', 'Mon-Fri: 7AM - 7PM', '012-3456790'),
('Cyberjaya Recycling Hub', '78 Jalan Teknologi, Cyberjaya', 2.9356, 101.6544, 300, 280, 'full', 'Daily: 6AM - 10PM', '012-3456791'),
('KL Sentral Collection', 'KL Sentral Station', 3.1340, 101.6860, 100, 30, 'available', 'Daily: 7AM - 11PM', '012-3456792'),
('SS2 Community Point', '88 Jalan SS2/72, PJ', 3.1140, 101.6220, 120, 85, 'maintenance', 'Mon-Sat: 9AM - 6PM', '012-3456793'),
('Bangsar Collection Center', '22 Jalan Telawi, Bangsar', 3.1300, 101.6700, 180, 60, 'available', 'Daily: 8AM - 9PM', '012-3456794'),
('Ampang Recycling Point', '15 Jalan Ampang, KL', 3.1500, 101.7100, 250, 150, 'available', 'Mon-Sat: 9AM - 7PM', '012-3456795'),
('Damansara Collection', '55 Jalan Damansara, PJ', 3.1500, 101.6100, 160, 90, 'available', 'Mon-Fri: 8AM - 8PM', '012-3456796'),
('Cheras Recycling Hub', '88 Jalan Cheras, KL', 3.1000, 101.7400, 220, 200, 'full', 'Daily: 7AM - 10PM', '012-3456797'),
('Sunway Collection Point', '99 Jalan Sunway, PJ', 3.0700, 101.6000, 300, 180, 'available', 'Daily: 10AM - 10PM', '012-3456798'),
('Puchong Drop-off', '77 Jalan Puchong, Puchong', 3.0400, 101.6200, 140, 70, 'available', 'Mon-Sat: 9AM - 8PM', '012-3456799'),
('Shah Alam Collection', '66 Jalan Alam, Shah Alam', 3.0600, 101.5500, 200, 160, 'available', 'Mon-Fri: 9AM - 5PM', '012-3456700'),
('KL Eco Centre', '33 Jalan Tun Razak, KL', 3.1600, 101.6900, 250, 130, 'available', 'Daily: 7AM - 11PM', '012-3456701'),
('Mid Valley Recycling', 'Mid Valley City, KL', 3.1200, 101.6800, 100, 45, 'available', 'Daily: 10AM - 10PM', '012-3456702'),
('Mont Kiara Collection', '66 Jalan Kiara, Mont Kiara', 3.1700, 101.6500, 130, 60, 'available', 'Mon-Sat: 8AM - 8PM', '012-3456703');

-- ======================================================
-- SCAN HISTORY (50+条扫描记录)
-- ======================================================
INSERT INTO scan_history (user_id, material_type, material_category, weight, points_earned, scan_type, status, scanned_at) VALUES
-- demo 的扫描 (10条)
(1, 'PET Bottle', 'plastic', 0.50, 25, 'barcode', 'verified', NOW() - INTERVAL 2 HOUR),
(1, 'Aluminum Can', 'metal', 0.30, 30, 'barcode', 'verified', NOW() - INTERVAL 5 HOUR),
(1, 'Cardboard Box', 'paper', 2.00, 15, 'manual', 'verified', NOW() - INTERVAL 1 DAY),
(1, 'Glass Bottle', 'glass', 0.80, 20, 'barcode', 'pending', NOW() - INTERVAL 3 HOUR),
(1, 'Newspaper', 'paper', 1.50, 10, 'manual', 'verified', NOW() - INTERVAL 2 DAY),
(1, 'Aluminum Can', 'metal', 0.25, 30, 'barcode', 'verified', NOW() - INTERVAL 4 DAY),
(1, 'PET Bottle', 'plastic', 0.60, 25, 'barcode', 'verified', NOW() - INTERVAL 5 DAY),
(1, 'Cardboard Box', 'paper', 3.00, 15, 'manual', 'pending', NOW() - INTERVAL 6 DAY),
(1, 'Glass Bottle', 'glass', 0.50, 20, 'barcode', 'verified', NOW() - INTERVAL 7 DAY),
(1, 'E-Waste (PCB)', 'e_waste', 0.30, 50, 'image', 'verified', NOW() - INTERVAL 8 DAY),

-- 其他用户的扫描 (40+条)
(2, 'Glass Bottle', 'glass', 0.80, 20, 'barcode', 'pending', NOW() - INTERVAL 3 HOUR),
(3, 'E-Waste (PCB)', 'e_waste', 1.20, 50, 'image', 'verified', NOW() - INTERVAL 4 HOUR),
(4, 'PET Bottle', 'plastic', 0.60, 25, 'barcode', 'pending', NOW() - INTERVAL 6 HOUR),
(5, 'Aluminum Can', 'metal', 0.40, 30, 'barcode', 'verified', NOW() - INTERVAL 1 DAY),
(6, 'Newspaper', 'paper', 1.50, 10, 'manual', 'verified', NOW() - INTERVAL 2 DAY),
(2, 'PET Bottle', 'plastic', 0.70, 25, 'barcode', 'verified', NOW() - INTERVAL 2 DAY),
(3, 'Cardboard Box', 'paper', 3.00, 15, 'manual', 'verified', NOW() - INTERVAL 3 DAY),
(7, 'Aluminum Can', 'metal', 0.50, 30, 'barcode', 'verified', NOW() - INTERVAL 4 DAY),
(8, 'Glass Bottle', 'glass', 1.00, 20, 'barcode', 'pending', NOW() - INTERVAL 5 DAY),
(9, 'E-Waste (Battery)', 'e_waste', 0.80, 40, 'image', 'verified', NOW() - INTERVAL 6 DAY),
(10, 'PET Plastic', 'plastic', 2.00, 40, 'barcode', 'verified', NOW() - INTERVAL 7 DAY),
(2, 'Cardboard', 'paper', 5.00, 25, 'manual', 'verified', NOW() - INTERVAL 8 DAY),
(3, 'Aluminum Can', 'metal', 1.00, 60, 'barcode', 'verified', NOW() - INTERVAL 9 DAY),
(4, 'Glass Bottle', 'glass', 0.50, 10, 'barcode', 'pending', NOW() - INTERVAL 10 DAY),
(11, 'PET Bottle', 'plastic', 0.40, 25, 'barcode', 'verified', NOW() - INTERVAL 11 DAY),
(12, 'Newspaper', 'paper', 2.00, 10, 'manual', 'verified', NOW() - INTERVAL 12 DAY),
(13, 'Aluminum Can', 'metal', 0.60, 30, 'barcode', 'verified', NOW() - INTERVAL 13 DAY),
(14, 'E-Waste (PCB)', 'e_waste', 0.50, 50, 'image', 'pending', NOW() - INTERVAL 14 DAY),
(15, 'Cardboard Box', 'paper', 4.00, 15, 'manual', 'verified', NOW() - INTERVAL 15 DAY),
(16, 'PET Plastic', 'plastic', 1.50, 40, 'barcode', 'verified', NOW() - INTERVAL 16 DAY),
(17, 'Glass Bottle', 'glass', 0.70, 20, 'barcode', 'pending', NOW() - INTERVAL 17 DAY),
(18, 'Aluminum Can', 'metal', 0.35, 30, 'barcode', 'verified', NOW() - INTERVAL 18 DAY),
(19, 'Newspaper', 'paper', 1.80, 10, 'manual', 'verified', NOW() - INTERVAL 19 DAY),
(20, 'E-Waste (Battery)', 'e_waste', 0.60, 40, 'image', 'verified', NOW() - INTERVAL 20 DAY),
(2, 'PET Bottle', 'plastic', 0.90, 25, 'barcode', 'verified', NOW() - INTERVAL 21 DAY),
(3, 'Cardboard Box', 'paper', 2.50, 15, 'manual', 'pending', NOW() - INTERVAL 22 DAY),
(5, 'Aluminum Can', 'metal', 0.45, 30, 'barcode', 'verified', NOW() - INTERVAL 23 DAY),
(7, 'Glass Bottle', 'glass', 0.60, 20, 'barcode', 'verified', NOW() - INTERVAL 24 DAY),
(9, 'PET Plastic', 'plastic', 1.20, 40, 'barcode', 'pending', NOW() - INTERVAL 25 DAY),
(11, 'E-Waste (PCB)', 'e_waste', 0.40, 50, 'image', 'verified', NOW() - INTERVAL 26 DAY),
(13, 'Cardboard Box', 'paper', 3.50, 15, 'manual', 'verified', NOW() - INTERVAL 27 DAY),
(15, 'Aluminum Can', 'metal', 0.55, 30, 'barcode', 'verified', NOW() - INTERVAL 28 DAY),
(17, 'PET Bottle', 'plastic', 0.45, 25, 'barcode', 'pending', NOW() - INTERVAL 29 DAY),
(19, 'Newspaper', 'paper', 2.20, 10, 'manual', 'verified', NOW() - INTERVAL 30 DAY);

-- ======================================================
-- TRANSACTIONS (30+条交易记录)
-- ======================================================
INSERT INTO transactions (user_id, center_id, material_id, quantity, total_price, points_earned, transaction_type, status, transaction_date) VALUES
-- demo 的交易 (5条)
(1, 1, 1, 5.00, 16.00, 80, 'sell', 'completed', NOW() - INTERVAL 1 DAY),
(1, 2, 2, 3.00, 5.40, 48, 'sell', 'pending', NOW() - INTERVAL 3 HOUR),
(1, 3, 7, 0.50, 6.25, 60, 'sell', 'completed', NOW() - INTERVAL 5 DAY),
(1, 4, 4, 8.00, 7.20, 40, 'sell', 'completed', NOW() - INTERVAL 10 DAY),
(1, 5, 5, 6.00, 4.20, 30, 'sell', 'pending', NOW() - INTERVAL 15 DAY),

-- 其他用户的交易 (25+条)
(2, 1, 1, 5.00, 16.00, 80, 'sell', 'completed', NOW() - INTERVAL 2 DAY),
(3, 2, 2, 8.00, 14.40, 100, 'sell', 'completed', NOW() - INTERVAL 3 DAY),
(4, 1, 4, 10.00, 9.00, 60, 'sell', 'pending', NOW() - INTERVAL 4 DAY),
(5, 3, 7, 2.00, 25.00, 120, 'sell', 'completed', NOW() - INTERVAL 5 DAY),
(6, 4, 2, 3.00, 5.40, 40, 'sell', 'pending', NOW() - INTERVAL 6 DAY),
(7, 1, 3, 4.00, 8.40, 50, 'sell', 'completed', NOW() - INTERVAL 7 DAY),
(8, 2, 5, 6.00, 4.20, 30, 'sell', 'completed', NOW() - INTERVAL 8 DAY),
(9, 3, 1, 2.00, 6.40, 32, 'sell', 'pending', NOW() - INTERVAL 9 DAY),
(10, 1, 4, 8.00, 7.20, 48, 'sell', 'completed', NOW() - INTERVAL 10 DAY),
(2, 5, 6, 4.00, 2.40, 20, 'sell', 'completed', NOW() - INTERVAL 11 DAY),
(3, 4, 8, 1.00, 8.00, 80, 'sell', 'completed', NOW() - INTERVAL 12 DAY),
(11, 2, 2, 5.00, 9.00, 60, 'sell', 'completed', NOW() - INTERVAL 13 DAY),
(12, 3, 3, 6.00, 12.60, 75, 'sell', 'pending', NOW() - INTERVAL 14 DAY),
(13, 1, 5, 4.00, 2.80, 20, 'sell', 'completed', NOW() - INTERVAL 15 DAY),
(14, 4, 7, 1.50, 18.75, 90, 'sell', 'completed', NOW() - INTERVAL 16 DAY),
(15, 5, 1, 7.00, 22.40, 112, 'sell', 'pending', NOW() - INTERVAL 17 DAY),
(16, 2, 4, 9.00, 8.10, 45, 'sell', 'completed', NOW() - INTERVAL 18 DAY),
(17, 3, 6, 5.00, 3.00, 25, 'sell', 'completed', NOW() - INTERVAL 19 DAY),
(18, 1, 2, 4.00, 7.20, 48, 'sell', 'pending', NOW() - INTERVAL 20 DAY),
(19, 4, 8, 2.00, 16.00, 160, 'sell', 'completed', NOW() - INTERVAL 21 DAY),
(20, 5, 3, 3.00, 6.30, 38, 'sell', 'completed', NOW() - INTERVAL 22 DAY);

-- ======================================================
-- NOTIFICATIONS (30条通知)
-- ======================================================
INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES
-- demo 的通知 (8条)
(1, '🎉 Welcome to SmartWaste!', 'Start recycling today and earn rewards!', 'success', FALSE, NOW() - INTERVAL 30 DAY),
(1, '💡 Daily Tip', 'Did you know? Recycling one aluminum can saves enough energy to run a TV for 3 hours!', 'info', FALSE, NOW() - INTERVAL 29 DAY),
(1, '🏆 Achievement Unlocked!', 'You have completed your 10th scan! Keep going!', 'success', TRUE, NOW() - INTERVAL 28 DAY),
(1, '📈 Price Alert', 'Aluminum prices have gone up by 4.9%! Great time to recycle!', 'info', FALSE, NOW() - INTERVAL 27 DAY),
(1, '🎯 Weekly Challenge', 'Recycle 5kg of plastic this week to earn 50 bonus points!', 'warning', FALSE, NOW() - INTERVAL 26 DAY),
(1, '🌟 Level Up!', 'You are now a Silver level recycler!', 'success', TRUE, NOW() - INTERVAL 25 DAY),
(1, '📍 New Center Alert', 'A new recycling center has opened near your area!', 'info', FALSE, NOW() - INTERVAL 24 DAY),
(1, '💪 Keep Going!', 'You have recycled 15 items so far! Amazing work!', 'success', FALSE, NOW() - INTERVAL 23 DAY),

-- 其他用户的通知 (22条)
(2, '🎉 Milestone Achieved!', 'You have reached 10,000 points! Keep up the great work!', 'success', FALSE, NOW() - INTERVAL 22 DAY),
(3, 'New Recycling Center Added', 'A new recycling center has opened near your area! Check it out.', 'info', FALSE, NOW() - INTERVAL 21 DAY),
(4, 'Price Update', 'Aluminum prices have increased by 5%!', 'info', TRUE, NOW() - INTERVAL 20 DAY),
(2, 'Weekly Challenge', 'This week: Recycle 5kg of plastic to earn 100 bonus points!', 'warning', FALSE, NOW() - INTERVAL 19 DAY),
(5, '🎉 Milestone Achieved!', 'You have recycled 50 items! Great job!', 'success', FALSE, NOW() - INTERVAL 18 DAY),
(3, '💡 Did you know?', 'Recycling paper saves trees and reduces water pollution!', 'info', TRUE, NOW() - INTERVAL 17 DAY),
(7, '🏆 Level Up!', 'You are now a Gold level recycler!', 'success', FALSE, NOW() - INTERVAL 16 DAY),
(9, '🎯 Challenge Completed', 'You completed the weekly challenge! +50 bonus points!', 'success', TRUE, NOW() - INTERVAL 15 DAY),
(10, '📍 New Center Alert', 'A new recycling center has opened at Sunway!', 'info', FALSE, NOW() - INTERVAL 14 DAY),
(2, '🌟 Top Recycler', 'You are in the top 3 recyclers this month!', 'success', FALSE, NOW() - INTERVAL 13 DAY),
(11, '💪 Keep Going!', 'You have recycled 44 items so far! Amazing!', 'success', FALSE, NOW() - INTERVAL 12 DAY),
(12, '📈 Price Alert', 'Copper prices have increased by 8.5%!', 'info', TRUE, NOW() - INTERVAL 11 DAY),
(13, '🎉 Milestone Achieved!', 'You have reached 5,000 points!', 'success', FALSE, NOW() - INTERVAL 10 DAY),
(15, '💡 Daily Tip', 'Reduce, Reuse, Recycle - in that order!', 'info', FALSE, NOW() - INTERVAL 9 DAY),
(17, '🏆 Achievement Unlocked!', 'You have recycled 80 items!', 'success', TRUE, NOW() - INTERVAL 8 DAY),
(14, '📍 New Center Alert', 'New recycling point at Puchong!', 'info', FALSE, NOW() - INTERVAL 7 DAY),
(19, '🎯 Weekly Challenge', 'Recycle 10kg of paper this week!', 'warning', FALSE, NOW() - INTERVAL 6 DAY),
(6, '🌟 Level Up!', 'You are now a Silver level recycler!', 'success', FALSE, NOW() - INTERVAL 5 DAY),
(18, '📈 Price Update', 'E-Waste prices have increased by 6.8%!', 'info', TRUE, NOW() - INTERVAL 4 DAY),
(20, '💪 Keep Going!', 'You have recycled 20 items so far!', 'success', FALSE, NOW() - INTERVAL 3 DAY),
(8, '🎉 Welcome!', 'Welcome to SmartWaste! Start recycling today!', 'success', FALSE, NOW() - INTERVAL 2 DAY),
(16, '💡 Did you know?', 'Recycling plastic saves petroleum resources!', 'info', TRUE, NOW() - INTERVAL 1 DAY);

-- ======================================================
-- SYSTEM LOGS (30条系统日志)
-- ======================================================
INSERT INTO system_logs (user_id, action, details, ip_address, created_at) VALUES
(1, 'login', 'Demo user logged in', '192.168.1.1', NOW() - INTERVAL 30 DAY),
(1, 'scan', 'Scanned PET bottle - earned 25 points', '192.168.1.1', NOW() - INTERVAL 29 DAY),
(1, 'transaction', 'Completed sell: 5kg Aluminum - RM 16.00', '192.168.1.1', NOW() - INTERVAL 28 DAY),
(2, 'login', 'EcoWarrior logged in', '192.168.1.2', NOW() - INTERVAL 27 DAY),
(2, 'scan', 'Scanned Glass Bottle - earned 20 points', '192.168.1.2', NOW() - INTERVAL 26 DAY),
(3, 'transaction', 'Sold 8kg PET plastic - earned 100 points', '192.168.1.3', NOW() - INTERVAL 25 DAY),
(4, 'register', 'User RecyclePro registered', '192.168.1.4', NOW() - INTERVAL 24 DAY),
(1, 'scan', 'Scanned Cardboard Box - earned 15 points', '192.168.1.1', NOW() - INTERVAL 23 DAY),
(5, 'login', 'TrashHunter logged in', '192.168.1.5', NOW() - INTERVAL 22 DAY),
(6, 'scan', 'Scanned Newspaper - earned 10 points', '192.168.1.6', NOW() - INTERVAL 21 DAY),
(7, 'login', 'GreenGuru logged in', '192.168.1.7', NOW() - INTERVAL 20 DAY),
(8, 'transaction', 'Sold 6kg Newspaper - earned 30 points', '192.168.1.8', NOW() - INTERVAL 19 DAY),
(9, 'scan', 'Scanned E-Waste - earned 40 points', '192.168.1.9', NOW() - INTERVAL 18 DAY),
(10, 'login', 'PlanetSaver logged in', '192.168.1.10', NOW() - INTERVAL 17 DAY),
(11, 'register', 'User GreenThumb registered', '192.168.1.11', NOW() - INTERVAL 16 DAY),
(12, 'scan', 'Scanned PET Bottle - earned 25 points', '192.168.1.12', NOW() - INTERVAL 15 DAY),
(13, 'transaction', 'Sold 4kg Plastic - earned 50 points', '192.168.1.13', NOW() - INTERVAL 14 DAY),
(14, 'login', 'WasteWizard logged in', '192.168.1.14', NOW() - INTERVAL 13 DAY),
(15, 'scan', 'Scanned Cardboard Box - earned 15 points', '192.168.1.15', NOW() - INTERVAL 12 DAY),
(16, 'register', 'User GreenMachine registered', '192.168.1.16', NOW() - INTERVAL 11 DAY),
(17, 'transaction', 'Sold 7kg Aluminum - earned 112 points', '192.168.1.17', NOW() - INTERVAL 10 DAY),
(18, 'login', 'EcoSaver logged in', '192.168.1.18', NOW() - INTERVAL 9 DAY),
(19, 'scan', 'Scanned Aluminum Can - earned 30 points', '192.168.1.19', NOW() - INTERVAL 8 DAY),
(20, 'transaction', 'Sold 3kg HDPE Plastic - earned 38 points', '192.168.1.20', NOW() - INTERVAL 7 DAY),
(1, 'login', 'Demo user logged in', '192.168.1.1', NOW() - INTERVAL 6 DAY),
(1, 'scan', 'Scanned Aluminum Can - earned 30 points', '192.168.1.1', NOW() - INTERVAL 5 DAY),
(1, 'transaction', 'Pending sell: 3kg PET Plastic', '192.168.1.1', NOW() - INTERVAL 4 DAY),
(2, 'scan', 'Scanned PET Bottle - earned 25 points', '192.168.1.2', NOW() - INTERVAL 3 DAY),
(3, 'login', 'GreenMaster logged in', '192.168.1.3', NOW() - INTERVAL 2 DAY),
(1, 'scan', 'Scanned PET Bottle - earned 25 points', '192.168.1.1', NOW() - INTERVAL 1 DAY);

-- ======================================================
-- RESET AUTO_INCREMENT
-- ======================================================
ALTER TABLE users AUTO_INCREMENT = 100;
ALTER TABLE user_rewards AUTO_INCREMENT = 100;
ALTER TABLE scan_history AUTO_INCREMENT = 100;
ALTER TABLE recycling_centers AUTO_INCREMENT = 100;
ALTER TABLE material_prices AUTO_INCREMENT = 100;
ALTER TABLE transactions AUTO_INCREMENT = 100;
ALTER TABLE collection_points AUTO_INCREMENT = 100;
ALTER TABLE notifications AUTO_INCREMENT = 100;
ALTER TABLE system_logs AUTO_INCREMENT = 100;

-- ======================================================
-- FINAL - DISPLAY LOGIN INFO
-- ======================================================
SELECT '========================================' AS '';
SELECT '      ✅ SMARTWASTE DATABASE READY' AS '';
SELECT '========================================' AS '';
SELECT '🔑 LOGIN CREDENTIALS:' AS '';
SELECT '   Username: demo' AS '';
SELECT '   Password: demo123' AS '';
SELECT '========================================' AS '';
SELECT '📊 DATA SUMMARY:' AS '';
SELECT '   👤 Users: 20' AS '';
SELECT '   ⭐ Rewards: 20' AS '';
SELECT '   📋 Scan History: 50+' AS '';
SELECT '   🏪 Recycling Centers: 15' AS '';
SELECT '   💰 Material Prices: 25' AS '';
SELECT '   💳 Transactions: 30+' AS '';
SELECT '   📍 Collection Points: 15' AS '';
SELECT '   🔔 Notifications: 30' AS '';
SELECT '   📝 System Logs: 30' AS '';
SELECT '========================================' AS '';

-- ======================================================
-- PAYMENT METHODS (Bank Account / TNG)
-- ======================================================
CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    payment_type ENUM('bank', 'tng', 'grabpay') NOT NULL,
    account_name VARCHAR(100),
    account_number VARCHAR(50),
    bank_name VARCHAR(100),
    tng_phone VARCHAR(20),
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ======================================================
-- PAYMENT TRANSACTIONS
-- ======================================================
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    drop_off_id INT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    weight_kg DECIMAL(8, 2) NOT NULL,
    material_type VARCHAR(50),
    payment_method_id INT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    transaction_reference VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (drop_off_id) REFERENCES drop_offs(id) ON DELETE SET NULL,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL
);

-- ======================================================
-- NOTIFICATIONS TABLE (Enhanced)
-- ======================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'payment') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    link VARCHAR(255),
    icon VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ======================================================
-- SAMPLE NOTIFICATIONS
-- ======================================================
INSERT INTO notifications (user_id, title, message, type, icon, created_at) VALUES
(1, '💰 Payment Received!', 'Green Hub Recycling has transferred RM 16.00 for your 5kg Aluminum Cans', 'payment', 'fa-money-bill-wave', NOW() - INTERVAL 1 DAY),
(1, '💰 Payment Received!', 'EcoWaste Center has transferred RM 5.40 for your 3kg PET Plastic', 'payment', 'fa-money-bill-wave', NOW() - INTERVAL 3 HOUR),
(1, '💰 Payment Received!', 'Smart Recycling Point has transferred RM 6.25 for your 0.5kg E-Waste', 'payment', 'fa-money-bill-wave', NOW() - INTERVAL 5 DAY),
(1, '🏆 Achievement Unlocked!', 'You have completed your 10th recycle! Keep going!', 'success', 'fa-trophy', NOW() - INTERVAL 2 DAY),
(1, '💡 Daily Tip', 'Did you know? Recycling one aluminum can saves enough energy to run a TV for 3 hours!', 'info', 'fa-lightbulb', NOW() - INTERVAL 1 DAY);