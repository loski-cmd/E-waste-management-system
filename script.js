// General JavaScript functions
document.addEventListener('DOMContentLoaded', function() {
    // Set minimum date for pickup scheduling to today
    const scheduledDateInput = document.getElementById('scheduled_date');
    if (scheduledDateInput) {
        const today = new Date();
        const minDate = today.toISOString().slice(0, 16);
        scheduledDateInput.min = minDate;
    }
    
    // Handle success message fade out
    const successAlert = document.getElementById('success-alert');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.opacity = '0';
            setTimeout(() => {
                successAlert.style.display = 'none';
            }, 500);
        }, 4500);
    }

    // Initialize all modal dialogs
    const modals = document.querySelectorAll('dialog');
    modals.forEach(modal => {
        // Close buttons inside modals
        const closeButtons = modal.querySelectorAll('[data-dismiss="modal"]');
        closeButtons.forEach(button => {
            button.addEventListener('click', () => modal.close());
        });
    });
});

// Modal handling functions
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const openModal = document.querySelector('dialog[open]');
        if (openModal) {
            openModal.close();
        }
    }
});

document.addEventListener('click', function(e) {
    const dialog = document.querySelector('dialog[open]');
    if (dialog && e.target === dialog) {
        dialog.close();
    }
});

// Enhanced logout confirmation
function confirmLogout() {
    const isConfirmed = confirm('Are you sure you want to logout?');
    if (isConfirmed) {
        // If collector, update status to active before logout
        if (window.location.pathname.includes('collectorDashboard.php')) {
            fetch('updateCollectorStatus.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ status: 'active' })
            }).then(() => {
                window.location.href = 'logout.php';
            });
            return false;
        }
        return true;
    }
    return false;
}

// Helper function to show modal
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.showModal();
    }
}

// Helper function to close modal
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.close();
    }
}

// Form validation helper
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (form) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = 'var(--error)';
                isValid = false;
            } else {
                field.style.borderColor = '';
            }
        });
        
        return isValid;
    }
    return false;
}