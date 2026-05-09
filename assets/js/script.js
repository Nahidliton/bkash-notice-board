// Logout confirmation
function confirmLogout() {
    return confirm('Are you sure you want to logout?');
}

// Star Rating System
document.addEventListener('DOMContentLoaded', function() {
    const starContainers = document.querySelectorAll('.star-rating');
    
    starContainers.forEach(container => {
        const stars = container.querySelectorAll('.star');
        const noticeId = container.getAttribute('data-notice-id');
        
        // Load saved rating from localStorage
        const savedRating = localStorage.getItem('rating_' + noticeId);
        if (savedRating) {
            highlightStars(container, parseInt(savedRating));
        }
        
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                highlightStars(container, rating);
                
                // Save rating to localStorage
                localStorage.setItem('rating_' + noticeId, rating);
                
                // Optional: Send rating to server
                sendRating(noticeId, rating);
            });
            
            star.addEventListener('mouseover', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                highlightStars(container, rating);
            });
            
            star.addEventListener('mouseout', function() {
                const currentRating = localStorage.getItem('rating_' + noticeId) || 0;
                highlightStars(container, parseInt(currentRating));
            });
        });
    });
});

function highlightStars(container, rating) {
    const stars = container.querySelectorAll('.star');
    stars.forEach((star, index) => {
        if (index < rating) {
            star.classList.add('active');
        } else {
            star.classList.remove('active');
        }
    });
}

function sendRating(noticeId, rating) {
    // Using Fetch API to send rating to server
    fetch('dashboard.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notice_id=' + noticeId + '&rating=' + rating
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Rating saved successfully');
        }
    })
    .catch(error => {
        console.error('Error saving rating:', error);
    });
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#DC3545';
                } else {
                    field.style.borderColor = '#E0E0E0';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    });
});