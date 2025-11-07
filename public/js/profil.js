document.addEventListener('DOMContentLoaded', () => {
    const items = document.querySelectorAll('.card-item');
    const input = document.getElementById('profile_settings_imageCollection');

    items.forEach(item => {
        item.addEventListener('click', () => {
            items.forEach(i => i.classList.remove('selected'));
            item.classList.add('selected');
            input.value = item.dataset.path;
        });
    });
});