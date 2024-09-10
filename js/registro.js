document.addEventListener('DOMContentLoaded', function() {
    const closeModal = document.querySelector('.error-modal .close');
    const errorModal = document.querySelector('.error-modal');

    if (closeModal) {
        closeModal.addEventListener('click', function() {
            errorModal.style.display = 'none';
        });
    }

    window.addEventListener('click', function(event) {
        if (event.target === errorModal) {
            errorModal.style.display = 'none';
        }
    });
});
