// CampusFind front-end helpers. Loaded as an external file (no inline JS,
// so the Content-Security-Policy can forbid inline scripts entirely).
(function () {
    'use strict';

    // Show / hide password buttons: <button data-toggle-password="inputId">
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-toggle-password'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            var icon = btn.querySelector('i');
            if (icon) icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });

    // Prevent double submission and show a loading spinner: <form data-once>
    document.querySelectorAll('form[data-once]').forEach(function (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Please wait…';
            });
        });
    });

    // Browse filters: submit the search form as soon as a dropdown changes.
    document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () { select.form.submit(); });
    });

    // Post item form: show the "secret detail" box only for found items.
    var typeRadios = document.querySelectorAll('[data-type-toggle]');
    typeRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            var isFound = radio.value === 'found';
            var foundBox = document.querySelector('[data-found-only]');
            var dateLabel = document.querySelector('[data-date-label]');
            if (foundBox) foundBox.classList.toggle('d-none', !isFound);
            if (dateLabel) dateLabel.textContent = isFound ? 'Date found' : 'Date lost';
        });
    });

    // Photo picker: preview chosen photos and support drag & drop.
    var photoInput = document.querySelector('[data-photo-input]');
    var preview = document.querySelector('[data-photo-preview]');
    var zone = document.querySelector('[data-upload-zone]');
    if (photoInput && preview && zone) {
        var showPreviews = function () {
            preview.innerHTML = '';
            Array.prototype.slice.call(photoInput.files).forEach(function (file) {
                var thumb = document.createElement('div');
                thumb.className = 'thumb';
                var img = document.createElement('img');
                img.alt = file.name;
                img.src = URL.createObjectURL(file);   // local preview, nothing is uploaded yet
                thumb.appendChild(img);
                preview.appendChild(thumb);
            });
        };
        photoInput.addEventListener('change', showPreviews);

        ['dragenter', 'dragover'].forEach(function (name) {
            zone.addEventListener(name, function (event) { event.preventDefault(); zone.classList.add('dragover'); });
        });
        ['dragleave', 'drop'].forEach(function (name) {
            zone.addEventListener(name, function () { zone.classList.remove('dragover'); });
        });
        zone.addEventListener('drop', function (event) {
            event.preventDefault();
            photoInput.files = event.dataTransfer.files;
            showPreviews();
        });
    }

    // Item gallery: clicking a thumbnail shows that photo large.
    var mainPhoto = document.querySelector('[data-gallery-main]');
    document.querySelectorAll('[data-gallery-thumb]').forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            mainPhoto.src = thumb.getAttribute('data-gallery-thumb');
            document.querySelectorAll('[data-gallery-thumb]').forEach(function (t) { t.classList.remove('active'); });
            thumb.classList.add('active');
        });
    });
})();
