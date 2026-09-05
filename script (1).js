// ==========================================
// PAGE NAVIGATION
// ==========================================
function showPage(name) {
    document.querySelectorAll('.page').forEach(function(p) {
        p.classList.remove('active');
    });
    document.getElementById(name).classList.add('active');
    
    document.querySelectorAll('.bottom-nav button').forEach(function(b) {
        b.classList.remove('active');
    });
    var navMap = {
        'home': 0,
        'locator': 1,
        'rewards': 2,
        'profile': 3
    };
    var idx = navMap[name];
    if (idx !== undefined) {
        document.querySelectorAll('.bottom-nav button')[idx].classList.add('active');
    }
    
    if (name === 'locator' && isMapReady) {
        setTimeout(function() {
            if (map) map.invalidateSize();
        }, 500);
    }
}

// ==========================================
// MAP
// ==========================================
var map = null;
var userMarker = null;
var centerMarkers = [];
var isMapReady = false;

function initMap(centerLat, centerLng) {
    if (isMapReady) {
        setTimeout(function() {
            if (map) map.invalidateSize();
        }, 300);
        return;
    }
    
    var defaultLat = centerLat || 3.1390;
    var defaultLng = centerLng || 101.6869;
    
    var mapContainer = document.getElementById('map');
    if (!mapContainer) return;
    
    mapContainer.style.height = '220px';
    mapContainer.style.width = '100%';
    
    map = L.map('map', {
        center: [defaultLat, defaultLng],
        zoom: 13,
        zoomControl: true
    });
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);
    
    setTimeout(function() {
        if (map) map.invalidateSize();
    }, 500);
    
    isMapReady = true;
}

function addUserMarker(lat, lng) {
    if (!map) {
        initMap(lat, lng);
        setTimeout(function() {
            addUserMarker(lat, lng);
        }, 500);
        return;
    }
    
    if (userMarker) {
        map.removeLayer(userMarker);
    }
    
    var userIcon = L.divIcon({
        className: 'custom-div-icon',
        html: '<div style="background:#1f8a4a; width:18px; height:18px; border-radius:50%; border:3px solid white; box-shadow:0 0 20px rgba(31,138,74,0.5);"></div>',
        iconSize: [18, 18],
        iconAnchor: [9, 9]
    });
    
    userMarker = L.marker([lat, lng], { icon: userIcon }).addTo(map);
    userMarker.bindPopup('<strong>You are here</strong>');
    map.setView([lat, lng], 14);
    
    setTimeout(function() {
        if (map) map.invalidateSize();
    }, 300);
}

function addCenterMarkers(centers) {
    if (!map) {
        initMap(3.1390, 101.6869);
        setTimeout(function() {
            addCenterMarkers(centers);
        }, 500);
        return;
    }
    
    centerMarkers.forEach(function(marker) {
        map.removeLayer(marker);
    });
    centerMarkers = [];
    
    var centerIcon = L.divIcon({
        className: 'custom-div-icon',
        html: '<div style="background:#0f3d26; width:12px; height:12px; border-radius:50%; border:2px solid #6fcf97; box-shadow:0 0 10px rgba(0,0,0,0.2);"></div>',
        iconSize: [12, 12],
        iconAnchor: [6, 6]
    });
    
    centers.forEach(function(center) {
        var marker = L.marker([center.lat, center.lng], { icon: centerIcon }).addTo(map);
        marker.bindPopup(
            '<strong>' + center.name + '</strong><br>' +
            center.address + '<br>' +
            '📞 ' + center.phone
        );
        centerMarkers.push(marker);
    });
    
    if (centers.length > 0 && userMarker) {
        var group = L.featureGroup([userMarker].concat(centerMarkers));
        map.fitBounds(group.getBounds().pad(0.1));
    }
    
    setTimeout(function() {
        if (map) map.invalidateSize();
    }, 300);
}

// ==========================================
// LOCATION
// ==========================================
function getLocation() {
    var locBanner = document.getElementById('locBanner');
    var locText = document.getElementById('locText');
    var locDot = document.getElementById('locDot');
    var locCoords = document.getElementById('locCoords');
    var locBtn = document.getElementById('locBtn');
    var nearestBox = document.getElementById('nearestBox');

    locBanner.className = 'banner waiting';
    locBanner.innerHTML = '📍 <strong>Requesting location...</strong> Please allow when prompted.';
    locBtn.disabled = true;

    if (!navigator.geolocation) {
        locText.textContent = '❌ Location not supported';
        locDot.className = 'dot error';
        locBanner.className = 'banner error';
        locBanner.innerHTML = '❌ Location not supported.';
        locBtn.disabled = false;
        return;
    }

    locText.textContent = '⏳ Getting location...';
    locDot.className = 'dot waiting';
    nearestBox.style.display = 'none';

    navigator.geolocation.getCurrentPosition(
        function(pos) {
            var lat = pos.coords.latitude;
            var lng = pos.coords.longitude;
            locText.textContent = '✅ Location found!';
            locDot.className = 'dot success';
            locCoords.textContent = '📍 Lat: ' + lat.toFixed(6) + ', Lng: ' + lng.toFixed(6);
            locBtn.disabled = false;
            locBanner.className = 'banner success';
            locBanner.innerHTML = '✅ Location found!';
            
            if (!isMapReady) {
                initMap(lat, lng);
            }
            
            addUserMarker(lat, lng);
            calculateDistances(lat, lng);
            showToast('📍 Location found');
        },
        function(err) {
            locBtn.disabled = false;
            if (err.code === err.PERMISSION_DENIED) {
                locText.textContent = '❌ Location DENIED. Click 🔒 in address bar and allow location.';
                locDot.className = 'dot error';
                locBanner.className = 'banner error';
                locBanner.innerHTML = '❌ Location DENIED. Click 🔒 in address bar and allow location.';
            } else {
                locText.textContent = '❌ Error: ' + err.message;
                locDot.className = 'dot error';
                locBanner.className = 'banner error';
                locBanner.innerHTML = '❌ Error: ' + err.message;
            }
            
            if (!isMapReady) {
                initMap(3.1390, 101.6869);
            }
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
}

function calculateDistances(userLat, userLng) {
    if (centerData.length === 0) {
        document.getElementById('locText').textContent = '⚠️ No centers';
        return;
    }

    var distances = [];
    var centersWithCoords = [];

    centerData.forEach(function(center) {
        var dist = haversineDistance(userLat, userLng, center.lat, center.lng);
        var distDisplay = document.getElementById('dist_' + center.id);
        if (distDisplay) {
            var distText = dist < 1 ? (dist * 1000).toFixed(0) + ' m' : dist.toFixed(1) + ' km';
            distDisplay.innerHTML = '📍 ' + distText;
            distDisplay.style.color = '#1f8a4a';
            distDisplay.style.fontWeight = '600';
        }
        distances.push({
            id: center.id,
            name: center.name,
            lat: center.lat,
            lng: center.lng,
            address: center.address,
            phone: center.phone,
            distance: dist,
            distanceText: dist < 1 ? (dist * 1000).toFixed(0) + ' m' : dist.toFixed(1) + ' km'
        });
        
        centersWithCoords.push({
            id: center.id,
            name: center.name,
            lat: center.lat,
            lng: center.lng,
            address: center.address,
            phone: center.phone
        });
    });

    addCenterMarkers(centersWithCoords);

    distances.sort(function(a, b) {
        return a.distance - b.distance;
    });

    if (distances.length > 0 && distances[0].distance < 50) {
        var nearest = distances[0];
        document.getElementById('nearestName').textContent = nearest.name;
        document.getElementById('nearestAddr').textContent = '📍 ' + nearest.address;
        document.getElementById('nearestDist').textContent = nearest.distanceText;
        document.getElementById('nearestBox').style.display = 'block';
        document.getElementById('locText').textContent = '✅ Nearest center found!';
    }

    document.getElementById('centerCount').textContent = centerData.length + ' centers';
    sortCentersByDistance(distances);
}

function haversineDistance(lat1, lon1, lat2, lon2) {
    var R = 6371;
    var dLat = (lat2 - lat1) * Math.PI / 180;
    var dLon = (lon2 - lon1) * Math.PI / 180;
    var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLon/2) * Math.sin(dLon/2);
    var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

function sortCentersByDistance(distances) {
    var list = document.getElementById('centerList');
    var items = Array.from(list.querySelectorAll('.item'));

    items.forEach(function(item) {
        var lat = parseFloat(item.dataset.lat);
        var lng = parseFloat(item.dataset.lng);
        var found = distances.find(function(d) {
            return Math.abs(d.lat - lat) < 0.0001 && Math.abs(d.lng - lng) < 0.0001;
        });
        item.dataset.distance = found ? found.distance : 9999;
    });

    items.sort(function(a, b) {
        return parseFloat(a.dataset.distance) - parseFloat(b.dataset.distance);
    });

    items.forEach(function(item) {
        list.appendChild(item);
    });
}

// ==========================================
// REWARDS / REDEEM
// ==========================================
function redeemReward(rewardId, pointsRequired, link) {
    if (pointsRequired > rewardPoints) {
        showToast('❌ Not enough points! You have ' + rewardPoints + ' points');
        return;
    }
    
    if (!confirm('Redeem this reward for ' + pointsRequired + ' points?\n\nYou will be redirected to the partner site.')) return;
    
    showToast('⏳ Processing redemption...');
    
    fetch('redeem.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=redeem&reward_id=' + rewardId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            var message = '🎉 Redeemed ' + data.partner_name + '!\n\n';
            message += 'Code: ' + data.redemption_code + '\n';
            message += 'Discount: ' + data.discount + '\n';
            message += 'Points Spent: ' + data.points_spent + '\n';
            message += 'Expires: ' + data.expires_at;
            
            alert(message);
            
            rewardPoints -= pointsRequired;
            var pointsElement = document.querySelector('.reward-total-amount');
            if (pointsElement) {
                pointsElement.textContent = rewardPoints.toLocaleString();
            }
            
            // Open partner link in new tab
            if (link && link !== '#') {
                window.open(link, '_blank');
            }
            
            setTimeout(function() {
                location.reload();
            }, 2000);
        } else {
            showToast('❌ ' + (data.error || 'Unable to redeem'));
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        showToast('❌ Error redeeming reward. Please try again.');
    });
}
// ==========================================
// TOAST
// ==========================================
function showToast(message) {
    var toast = document.getElementById('toast');
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(toast._timeout);
    toast._timeout = setTimeout(function() {
        toast.classList.remove('show');
    }, 3000);
}

// ==========================================
// GROUP PICKUP FUNCTIONS
// ==========================================

function createGroupPickup() {
    showCreateGroupModal(3.1390, 101.6869);
}

function showCreateGroupModal(lat, lng) {
    if (document.querySelector('.modal')) {
        return;
    }
    
    var modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content" style="background:#fff;border-radius:20px;padding:20px;max-width:400px;margin:20px auto;max-height:80vh;overflow-y:auto;">
            <h3 style="margin-bottom:15px;">🚚 Create Group Pickup</h3>
            <p style="font-size:0.8rem;color:#5f7f6b;margin-bottom:15px;">
                Invite others to share delivery costs! Everyone saves money.
            </p>
            <form id="createGroupForm">
                <div style="margin-bottom:10px;">
                    <label style="font-size:0.75rem;font-weight:600;display:block;margin-bottom:4px;">Select Recycling Center</label>
                    <select name="center_id" required style="width:100%;padding:10px;border-radius:10px;border:2px solid #eaf3ec;font-family:inherit;">
                        <option value="">Loading centers...</option>
                    </select>
                </div>
                
                <div style="margin-bottom:10px;">
                    <label style="font-size:0.75rem;font-weight:600;display:block;margin-bottom:4px;">Your Address</label>
                    <input type="text" name="address" placeholder="Enter your full address" required style="width:100%;padding:10px;border-radius:10px;border:2px solid #eaf3ec;font-family:inherit;">
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                    <div>
                        <label style="font-size:0.75rem;font-weight:600;display:block;margin-bottom:4px;">Pickup Date</label>
                        <input type="date" name="pickup_date" required style="width:100%;padding:10px;border-radius:10px;border:2px solid #eaf3ec;">
                    </div>
                    <div>
                        <label style="font-size:0.75rem;font-weight:600;display:block;margin-bottom:4px;">Pickup Time</label>
                        <input type="time" name="pickup_time" required style="width:100%;padding:10px;border-radius:10px;border:2px solid #eaf3ec;">
                    </div>
                </div>
                
                <div style="margin-bottom:10px;">
                    <label style="font-size:0.75rem;font-weight:600;display:block;margin-bottom:4px;">Max Participants</label>
                    <select name="max_participants" style="width:100%;padding:10px;border-radius:10px;border:2px solid #eaf3ec;font-family:inherit;">
                        <option value="2">2 people</option>
                        <option value="3" selected>3 people</option>
                        <option value="4">4 people</option>
                        <option value="5">5 people</option>
                        <option value="6">6 people</option>
                    </select>
                </div>
                
                <div style="margin-bottom:10px;">
                    <label style="font-size:0.75rem;font-weight:600;display:block;margin-bottom:4px;">Meeting Point (Optional)</label>
                    <input type="text" name="meeting_point" placeholder="e.g., In front of 7-Eleven" style="width:100%;padding:10px;border-radius:10px;border:2px solid #eaf3ec;">
                </div>
                
                <div style="margin-bottom:15px;">
                    <label style="font-size:0.75rem;font-weight:600;display:block;margin-bottom:4px;">Notes (Optional)</label>
                    <textarea name="notes" placeholder="Any special instructions..." style="width:100%;padding:10px;border-radius:10px;border:2px solid #eaf3ec;font-family:inherit;min-height:60px;"></textarea>
                </div>
                
                <button type="submit" class="btn btn-green" style="width:100%;padding:12px;font-size:1rem;justify-content:center;">
                    <i class="fas fa-plus"></i> Create Group
                </button>
                <button type="button" onclick="this.closest('.modal').remove()" class="btn btn-gray" style="width:100%;margin-top:8px;padding:12px;font-size:1rem;justify-content:center;">
                    Cancel
                </button>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
    
    var centerSelect = modal.querySelector('select[name="center_id"]');
    fetch('get_centers.php')
        .then(response => response.json())
        .then(centers => {
            if (centers && centers.length > 0) {
                centerSelect.innerHTML = centers.map(c => 
                    `<option value="${c.id}">${c.name} (${c.address})</option>`
                ).join('');
            } else {
                centerSelect.innerHTML = '<option value="">No centers available</option>';
            }
        })
        .catch(() => {
            centerSelect.innerHTML = '<option value="">Error loading centers</option>';
        });
    
    var dateInput = modal.querySelector('input[name="pickup_date"]');
    var tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    dateInput.value = tomorrow.toISOString().split('T')[0];
    
    modal.querySelector('#createGroupForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        
        var submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Creating...';
        
        fetch('create_group_pickup.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('✅ Group created! Share the link with others.');
                modal.remove();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('❌ ' + (data.error || 'Failed to create group'));
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-plus"></i> Create Group';
            }
        })
        .catch(() => {
            showToast('❌ Error creating group');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-plus"></i> Create Group';
        });
    });
}

function joinGroup(groupId) {
    if (!confirm('Join this group pickup? You can share delivery costs with others.')) return;
    
    var address = prompt('Enter your pickup address:', '');
    if (!address) return;
    
    var weight = prompt('Estimated weight (kg):', '1');
    if (!weight) return;
    
    fetch('join_group_pickup.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `group_id=${groupId}&address=${encodeURIComponent(address)}&weight=${weight}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('✅ Joined group! You\'ll be notified of pickup details.');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('❌ ' + data.error);
        }
    })
    .catch(() => {
        showToast('❌ Error joining group');
    });
}

function viewGroupDetails(groupId) {
    fetch('group_details.php?id=' + groupId)
        .then(response => response.json())
        .then(data => {
            if (!data) {
                showToast('❌ Unable to load group details');
                return;
            }
            
            var modal = document.createElement('div');
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="background:#fff;border-radius:20px;padding:20px;max-width:400px;margin:20px auto;">
                    <h3 style="margin-bottom:10px;">🚚 Group Details</h3>
                    <div style="background:#f5faf7;padding:12px;border-radius:10px;margin-bottom:10px;">
                        <div><strong>🏢 Center:</strong> ${data.center_name}</div>
                        <div><strong>📍 Address:</strong> ${data.center_address}</div>
                        <div><strong>🕐 Pickup:</strong> ${new Date(data.pickup_date).toLocaleDateString()} at ${data.pickup_time}</div>
                        <div><strong>👥 Participants:</strong> ${data.current_participants}/${data.max_participants}</div>
                        <div><strong>💰 Cost per person:</strong> ${data.cost_per_person || 'TBD'}</div>
                    </div>
                    <div style="margin-bottom:10px;">
                        <strong>Participants:</strong>
                        <ul style="list-style:none;padding:0;margin-top:5px;">
                            ${data.participants.map(p => 
                                `<li style="padding:4px 0;border-bottom:1px solid #eaf3ec;font-size:0.85rem;">
                                    ${p.username} ${p.role === 'creator' ? '👑' : '👤'}
                                    ${p.status === 'accepted' ? '✅' : '⏳'}
                                </li>`
                            ).join('')}
                        </ul>
                    </div>
                    ${data.meeting_point ? `<div style="background:#fff3cd;padding:8px;border-radius:8px;font-size:0.8rem;margin-bottom:10px;">📍 Meeting Point: ${data.meeting_point}</div>` : ''}
                    <button onclick="this.closest('.modal').remove()" class="btn btn-green" style="width:100%;padding:12px;font-size:1rem;justify-content:center;">Close</button>
                </div>
            `;
            document.body.appendChild(modal);
        })
        .catch(() => {
            showToast('❌ Error loading group details');
        });
}

function manageGroup(groupId) {
    var options = [
        'Cancel Group',
        'Remove Participant',
        'Confirm Pickup',
        'View Participants'
    ];
    
    var choice = prompt('Manage Group:\n' + options.map((o, i) => `${i+1}. ${o}`).join('\n'));
    if (!choice) return;
    
    switch(choice) {
        case '1':
            if (confirm('Cancel this group pickup?')) {
                fetch('cancel_group.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `group_id=${groupId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('✅ Group cancelled');
                        location.reload();
                    } else {
                        showToast('❌ ' + data.error);
                    }
                });
            }
            break;
        default:
            showToast('Feature coming soon!');
    }
}

// ==========================================
// INIT
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    showPage('home');
    setTimeout(function() {
        initMap(3.1390, 101.6869);
    }, 300);
});

// ==========================================
// PAYMENT METHODS
// ==========================================
// ==========================================
// PAYMENT METHODS
// ==========================================
function addPaymentMethod() {
    // Check if modal already exists
    if (document.querySelector('.modal')) {
        return;
    }
    
    var modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content" style="background:#fff;border-radius:20px;padding:20px;max-width:400px;margin:20px auto;max-height:80vh;overflow-y:auto;">
            <h3 style="margin-bottom:15px;color:#0f3d26;">💳 Add Payment Method</h3>
            <p style="font-size:0.8rem;color:#5f7f6b;margin-bottom:15px;">Add your bank account or Touch 'n Go to receive payments</p>
            <form id="paymentForm">
                <div style="margin-bottom:10px;">
                    <label style="font-size:0.75rem;font-weight:600;display:block;margin-bottom:4px;color:#1a3a2a;">Payment Type</label>
                    <select name="payment_type" required style="width:100%;padding:10px;border-radius:10px;border:2px solid #eaf3ec;font-family:inherit;font-size:0.9rem;">
                        <option value="bank">🏦 Bank Account</option>
                        <option value="tng">📱 Touch 'n Go</option>
                        <option value="grabpay">🟢 GrabPay</option>
                    </select>
                </div>
                <div id="bankFields">
                    <div style="margin-bottom:10px;">
                        <label style="font-size:0.75rem;font-weight:600;display:block;margin-bottom:4px;color:#1a3a2a;">Bank Name</label>
                        <input type="text" name="bank_name" placeholder="e.g., Maybank, CIMB" style="width:100%;padding:10px;border-radius:10px;border:2px solid #eaf3ec;font-family:inherit;font-size:0.9rem;">
                    </div>
                    <div style="margin-bottom:10px;">
                        <label style="font-size:0.75rem;font-weight:600;display:block;margin-bottom:4px;color:#1a3a2a;">Account Name</label>
                        <input type="text" name="account_name" placeholder="Full name on account" style="width:100%;padding:10px;border-radius:10px;border:2px solid #eaf3ec;font-family:inherit;font-size:0.9rem;">
                    </div>
                    <div style="margin-bottom:10px;">
                        <label style="font-size:0.75rem;font-weight:600;display:block;margin-bottom:4px;color:#1a3a2a;">Account Number</label>
                        <input type="text" name="account_number" placeholder="Account number" style="width:100%;padding:10px;border-radius:10px;border:2px solid #eaf3ec;font-family:inherit;font-size:0.9rem;">
                    </div>
                </div>
                <div id="tngFields" style="display:none;">
                    <div style="margin-bottom:10px;">
                        <label style="font-size:0.75rem;font-weight:600;display:block;margin-bottom:4px;color:#1a3a2a;">Touch 'n Go Phone Number</label>
                        <input type="tel" name="tng_phone" placeholder="e.g., 012-3456789" style="width:100%;padding:10px;border-radius:10px;border:2px solid #eaf3ec;font-family:inherit;font-size:0.9rem;">
                    </div>
                </div>
                <div style="margin-bottom:15px;">
                    <label style="font-size:0.8rem;display:flex;align-items:center;gap:8px;color:#1a3a2a;cursor:pointer;">
                        <input type="checkbox" name="is_default" value="1"> Set as default payment method
                    </label>
                </div>
                <button type="submit" class="btn btn-green" style="width:100%;padding:12px;font-size:1rem;justify-content:center;">
                    <i class="fas fa-plus"></i> Add Payment Method
                </button>
                <button type="button" onclick="this.closest('.modal').remove()" class="btn btn-gray" style="width:100%;margin-top:8px;padding:12px;font-size:1rem;justify-content:center;">
                    Cancel
                </button>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Toggle fields based on payment type
    modal.querySelector('select[name="payment_type"]').addEventListener('change', function() {
        var bankFields = modal.querySelector('#bankFields');
        var tngFields = modal.querySelector('#tngFields');
        if (this.value === 'bank') {
            bankFields.style.display = 'block';
            tngFields.style.display = 'none';
        } else if (this.value === 'tng' || this.value === 'grabpay') {
            bankFields.style.display = 'none';
            tngFields.style.display = 'block';
        }
    });
    
    modal.querySelector('#paymentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        
        var submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Adding...';
        
        fetch('add_payment_method.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('✅ Payment method added successfully!');
                modal.remove();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('❌ ' + (data.error || 'Failed to add payment method'));
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-plus"></i> Add Payment Method';
            }
        })
        .catch(() => {
            showToast('❌ Error adding payment method');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-plus"></i> Add Payment Method';
        });
    });
}

function setDefaultPayment(methodId) {
    if (!confirm('Set this as your default payment method?')) return;
    
    fetch('set_default_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'method_id=' + methodId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('✅ Default payment method updated!');
            location.reload();
        } else {
            showToast('❌ ' + data.error);
        }
    });
}

function removePayment(methodId) {
    if (!confirm('Remove this payment method?')) return;
    
    fetch('remove_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'method_id=' + methodId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('✅ Payment method removed');
            location.reload();
        } else {
            showToast('❌ ' + data.error);
        }
    });
}

function markRead(notificationId) {
    fetch('mark_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}