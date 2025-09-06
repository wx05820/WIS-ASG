const STORE_ADDRESS = "25, Jalan Kepong, Jinjang Selatan, 52000 Kuala Lumpur, Malaysia";
const STORE_COORDINATES = "3.2044498884443646,101.662665477546";

class ContactForm {
    constructor() {
        this.init();
    }

    init() {
        this.setupFormValidation();
        this.setupInputHandlers();
    }

    setupFormValidation() {
        const form = document.querySelector('.contact-form');
        if (!form) return;

        form.addEventListener('submit', (e) => {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('error');
                    isValid = false;
                } else {
                    field.classList.remove('error');
                }
            });
            
            if (isValid) {
                const submitBtn = form.querySelector('.submit-btn');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span>Sending...</span>';
                submitBtn.disabled = true;
            } else {
                e.preventDefault();
            }
        });
    }

    setupInputHandlers() {
        document.querySelectorAll('input, textarea, select').forEach(field => {
            field.addEventListener('input', function() {
                this.classList.remove('error');
            });
        });
    }
}

class LocationService {
    constructor() {
        this.init();
        this.verifyStoreLocation();
    }

    init() {
        this.setupLocationButtons();
        this.setupModalHandlers();
    }

    async verifyStoreLocation() {
        try {
            const response = await fetch(`https://api.opencagedata.com/geocode/v1/json?q=${encodeURIComponent(STORE_ADDRESS)}&key=YOUR_API_KEY&limit=1`);
            const data = await response.json();
            
            if (data.results && data.results.length > 0) {
                const result = data.results[0];
                const lat = result.geometry.lat;
                const lng = result.geometry.lng;
                
                const latDiff = Math.abs(lat - STORE_LAT);
                const lngDiff = Math.abs(lng - STORE_LNG);
                
                if (latDiff > 0.01 || lngDiff > 0.01) {
                }
            }
        } catch (error) {
        }
    }

    setupLocationButtons() {
        const locationBtns = document.querySelectorAll('.location-btn, .map-btn');
        locationBtns.forEach(btn => {
            btn.addEventListener('click', () => this.getUserLocation());
        });
    }

    setupModalHandlers() {
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('navigation-modal')) {
                this.closeNavigationModal();
            }
        });
    }

    getUserLocation() {
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by this browser. Please search for our address manually in your maps app.');
            return;
        }
        
        const buttons = document.querySelectorAll('.location-btn, .map-btn');
        buttons.forEach(btn => {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting Location...';
        });
        
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const userLat = position.coords.latitude;
                const userLng = position.coords.longitude;
                
                this.showNavigationOptions(userLat, userLng);
                this.resetButtons();
            },
            (error) => {
                let errorMessage = 'Unable to get your location. ';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        errorMessage += 'Please allow location access and try again.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMessage += 'Location information is unavailable.';
                        break;
                    case error.TIMEOUT:
                        errorMessage += 'Location request timed out.';
                        break;
                    default:
                        errorMessage += 'An unknown error occurred.';
                        break;
                }
                
                alert(errorMessage + ' Please search for "25, Jalan Kepong, Jinjang Selatan, Kuala Lumpur" in your maps app.');
                this.resetButtons();
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 300000
            }
        );
    }

    showNavigationOptions(userLat, userLng) {
        const modal = document.createElement('div');
        modal.className = 'navigation-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Choose Navigation App</h3>
                    <button class="close-modal" onclick="locationService.closeNavigationModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <p>We found your location! Choose your preferred navigation app:</p>
                    <div class="navigation-options">
                        <button class="nav-btn google-nav" onclick="locationService.navigateWithGoogle(${userLat}, ${userLng})">
                            <i class="fab fa-google"></i>
                            <span>Google Maps</span>
                            <small>Web & Mobile</small>
                        </button>
                        <button class="nav-btn waze-nav" onclick="locationService.navigateWithWaze(${userLat}, ${userLng})">
                            <i class="fas fa-car"></i>
                            <span>Waze</span>
                            <small>Community Navigation</small>
                        </button>
                        <button class="nav-btn apple-nav" onclick="locationService.navigateWithApple(${userLat}, ${userLng})">
                            <i class="fab fa-apple"></i>
                            <span>Apple Maps</span>
                            <small>iOS & macOS</small>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }

    closeNavigationModal() {
        const modal = document.querySelector('.navigation-modal');
        if (modal) {
            modal.remove();
        }
    }

    navigateWithGoogle(userLat, userLng) {
        const url = `https://www.google.com/maps/dir/${userLat},${userLng}/${STORE_COORDINATES}`;
        window.open(url, '_blank');
        this.closeNavigationModal();
    }

    navigateWithWaze(userLat, userLng) {
        const [storeLat, storeLng] = STORE_COORDINATES.split(',');
        const url = `https://waze.com/ul?ll=${storeLat},${storeLng}&navigate=yes`;
        window.open(url, '_blank');
        this.closeNavigationModal();
    }

    navigateWithApple(userLat, userLng) {
        const url = `http://maps.apple.com/?daddr=${STORE_COORDINATES}&saddr=${userLat},${userLng}`;
        window.open(url, '_blank');
        this.closeNavigationModal();
    }

    resetButtons() {
        const locationBtn = document.querySelector('.location-btn');
        const mapBtn = document.querySelector('.map-btn');
        
        if (locationBtn) {
            locationBtn.disabled = false;
            locationBtn.innerHTML = '<i class="fas fa-location-arrow"></i> Get Directions';
        }
        
        if (mapBtn) {
            mapBtn.disabled = false;
            mapBtn.innerHTML = '<i class="fas fa-location-arrow"></i> Get Directions';
        }
    }

}

class ContactAdmin {
    constructor() {
        this.init();
    }

    init() {
        this.setupModalHandlers();
    }

    setupModalHandlers() {
        window.onclick = (event) => {
            const replyModal = document.getElementById('replyModal');
            const statusModal = document.getElementById('statusModal');
            
            if (event.target === replyModal) {
                this.closeReplyModal();
            }
            if (event.target === statusModal) {
                this.closeStatusModal();
            }
        };
    }

    openReplyModal(messageId) {
        document.getElementById('reply_message_id').value = messageId;
        document.getElementById('replyModal').style.display = 'block';
    }

    closeReplyModal() {
        document.getElementById('replyModal').style.display = 'none';
        document.getElementById('reply_message').value = '';
        document.getElementById('is_internal').checked = false;
    }

    openStatusModal(messageId, currentStatus, currentPriority, assignedTo) {
        document.getElementById('status_message_id').value = messageId;
        document.getElementById('status').value = currentStatus;
        document.getElementById('priority').value = currentPriority;
        document.getElementById('assigned_to').value = assignedTo || '';
        document.getElementById('statusModal').style.display = 'block';
    }

    closeStatusModal() {
        document.getElementById('statusModal').style.display = 'none';
    }

    viewReplies(messageId) {
        window.open('contact_replies.php?id=' + messageId, '_blank');
    }
}

class PriorityDashboard {
    constructor() {
        this.init();
    }

    init() {
    }

    runEscalation() {
        if (confirm('Run priority escalation check? This will analyze all messages and escalate if needed.')) {
            fetch('priority_escalation.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Escalation completed! ${data.escalated_count} messages were escalated.`);
                        location.reload();
                    } else {
                        alert('Escalation failed. Please try again.');
                    }
                })
                .catch(error => {
                    alert('Error running escalation. Please try again.');
                });
        }
    }
}

function getUserLocation() {
    if (window.locationService) {
        window.locationService.getUserLocation();
    }
}

function showNavigationOptions(userLat, userLng) {
    if (window.locationService) {
        window.locationService.showNavigationOptions(userLat, userLng);
    }
}

function closeNavigationModal() {
    if (window.locationService) {
        window.locationService.closeNavigationModal();
    }
}

function navigateWithGoogle(userLat, userLng) {
    if (window.locationService) {
        window.locationService.navigateWithGoogle(userLat, userLng);
    }
}

function navigateWithWaze(userLat, userLng) {
    if (window.locationService) {
        window.locationService.navigateWithWaze(userLat, userLng);
    }
}

function navigateWithApple(userLat, userLng) {
    if (window.locationService) {
        window.locationService.navigateWithApple(userLat, userLng);
    }
}

function resetButtons() {
    if (window.locationService) {
        window.locationService.resetButtons();
    }
}

function openReplyModal(messageId) {
    if (window.contactAdmin) {
        window.contactAdmin.openReplyModal(messageId);
    }
}

function closeReplyModal() {
    if (window.contactAdmin) {
        window.contactAdmin.closeReplyModal();
    }
}

function openStatusModal(messageId, currentStatus, currentPriority, assignedTo) {
    if (window.contactAdmin) {
        window.contactAdmin.openStatusModal(messageId, currentStatus, currentPriority, assignedTo);
    }
}

function closeStatusModal() {
    if (window.contactAdmin) {
        window.contactAdmin.closeStatusModal();
    }
}

function viewReplies(messageId) {
    if (window.contactAdmin) {
        window.contactAdmin.viewReplies(messageId);
    }
}

function runEscalation() {
    if (window.priorityDashboard) {
        window.priorityDashboard.runEscalation();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    window.contactForm = new ContactForm();
    window.locationService = new LocationService();
    
    if (document.querySelector('.admin-container')) {
        window.contactAdmin = new ContactAdmin();
    }
    
    if (document.querySelector('.priority-dashboard')) {
        window.priorityDashboard = new PriorityDashboard();
    }
});

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        ContactForm,
        LocationService,
        ContactAdmin,
        PriorityDashboard
    };
}

