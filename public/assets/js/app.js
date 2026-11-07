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
                // Icon-only buttons (like the chat send button) just show the spinner.
                button.innerHTML = button.textContent.trim() === ''
                    ? '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>'
                    : '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Please wait…';
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

    // ---------- Chat ----------
    var chatBody = document.querySelector('[data-chat-body]');
    if (chatBody) {
        chatBody.scrollTop = chatBody.scrollHeight;   // start at the newest message
    }

    // Show message times in the reader's own time zone.
    document.querySelectorAll('time[data-local-time]').forEach(function (time) {
        var date = new Date(time.getAttribute('datetime'));
        if (!isNaN(date)) {
            time.textContent = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
    });

    var chatInput = document.querySelector('[data-chat-input]');
    var chatForm = document.querySelector('[data-chat-form]');
    var charCount = document.querySelector('[data-char-count]');
    if (chatInput && chatForm) {
        var resize = function () {
            chatInput.style.height = 'auto';
            chatInput.style.height = Math.min(chatInput.scrollHeight, 160) + 'px';   // grow up to ~6 lines
            if (charCount) charCount.textContent = chatInput.value.length;
        };
        chatInput.addEventListener('input', resize);
        resize();

        // Enter sends the message, Shift+Enter adds a new line.
        chatInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                if (chatInput.value.trim() !== '') {
                    chatForm.requestSubmit();
                }
            }
        });
    }

    // Confirmation dialog: <form data-confirm="Deactivate this user?"> asks before submitting.
    var confirmModalElement = document.getElementById('confirmModal');
    if (confirmModalElement && window.bootstrap) {
        var confirmModal = new bootstrap.Modal(confirmModalElement);
        var formToSubmit = null;
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (form.dataset.confirmed === 'yes') return;   // already confirmed: let it submit
                event.preventDefault();
                formToSubmit = form;
                confirmModalElement.querySelector('[data-confirm-text]').textContent = form.getAttribute('data-confirm');
                confirmModal.show();
            });
        });
        confirmModalElement.querySelector('[data-confirm-ok]').addEventListener('click', function () {
            if (!formToSubmit) return;
            formToSubmit.dataset.confirmed = 'yes';
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>';
            formToSubmit.requestSubmit();
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

// ---------------------------------------------------------------------
// Motion: navbar on scroll, scroll reveal, scroll-lit text, hero tilt,
// button ripples, stat count-up. Everything is skipped for visitors who
// ask the system for reduced motion, and content is visible without JS.
// (CSSOM style changes are allowed by the CSP; inline <style>/style="" are not.)
// ---------------------------------------------------------------------
(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var navbar = document.querySelector('.app-navbar');
    var tiltEl = document.querySelector('[data-tilt]');
    var lightBlocks = [];

    // Click ripple on every button (pure decoration).
    document.addEventListener('pointerdown', function (event) {
        if (reduceMotion) return;
        var btn = event.target.closest('.btn');
        if (!btn || btn.disabled || btn.classList.contains('btn-link')) return;
        var rect = btn.getBoundingClientRect();
        var dot = document.createElement('span');
        dot.className = 'btn-ripple';
        dot.style.left = (event.clientX - rect.left) + 'px';
        dot.style.top = (event.clientY - rect.top) + 'px';
        btn.appendChild(dot);
        dot.addEventListener('animationend', function () { dot.remove(); });
    });

    // Step cards: glow follows the pointer.
    document.querySelectorAll('[data-glow]').forEach(function (card) {
        card.addEventListener('pointermove', function (event) {
            var rect = card.getBoundingClientRect();
            card.style.setProperty('--mx', (event.clientX - rect.left) + 'px');
            card.style.setProperty('--my', (event.clientY - rect.top) + 'px');
        });
    });

    // Split scroll-lit paragraphs into word spans (text nodes only, so nested
    // spans like .accent keep their styling).
    document.querySelectorAll('[data-scroll-light]').forEach(function (block) {
        var walker = document.createTreeWalker(block, NodeFilter.SHOW_TEXT);
        var textNodes = [];
        while (walker.nextNode()) textNodes.push(walker.currentNode);
        textNodes.forEach(function (node) {
            var fragment = document.createDocumentFragment();
            node.textContent.split(/(\s+)/).forEach(function (part) {
                if (part === '') return;
                if (/^\s+$/.test(part)) { fragment.appendChild(document.createTextNode(' ')); return; }
                var word = document.createElement('span');
                word.className = 'sl-word';
                word.textContent = part;
                fragment.appendChild(word);
            });
            node.parentNode.replaceChild(fragment, node);
        });
        lightBlocks.push({ el: block, words: block.querySelectorAll('.sl-word') });
    });

    // Count numbers up from 0 when they appear (admin + dashboard stat cards).
    function countUp(el) {
        var target = parseInt(el.textContent.replace(/[^\d]/g, ''), 10);
        if (!target || el.dataset.counted) return;
        el.dataset.counted = '1';
        var start = null;
        var step = function (time) {
            if (start === null) start = time;
            var t = Math.min(1, (time - start) / 1100);
            el.textContent = Math.round(target * (1 - Math.pow(1 - t, 3))).toLocaleString();
            if (t < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }

    // Scroll reveal: blocks below the fold fade up when they enter the viewport.
    if (!reduceMotion && 'IntersectionObserver' in window) {
        var selector = 'main .page-header, main .cf-card, main .card, main section > h2, main .eyebrow, ' +
            'main .scroll-light-section, main .hero-marquee, main .empty-state, main .admin-head';
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var target = entry.target;
                target.classList.add('is-visible');
                target.querySelectorAll('.stat-value').forEach(countUp);
                observer.unobserve(target);
                // Hand transforms back to the element's own hover effects once revealed.
                setTimeout(function () { target.classList.remove('reveal'); }, 1300);
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

        var seen = [];
        document.querySelectorAll(selector).forEach(function (el) {
            if (el.closest('.landing-hero .hero-stage') || el.closest('.modal')) return;
            if (seen.some(function (parent) { return parent.contains(el); })) return;  // only outermost blocks
            seen.push(el);
            var rect = el.getBoundingClientRect();
            if (rect.top < window.innerHeight * 0.92) {
                // Already on screen: no hiding (avoids a flash), but still animate charts and numbers.
                el.classList.add('is-visible');
                el.querySelectorAll('.stat-value').forEach(countUp);
                return;
            }
            el.classList.add('reveal');
            // Stagger siblings in the same row (cards in a grid).
            var col = el.parentElement;
            if (col && col.parentElement && col.parentElement.classList.contains('row')) {
                var index = Array.prototype.indexOf.call(col.parentElement.children, col);
                el.style.setProperty('--reveal-delay', Math.min(index % 4, 3) * 0.08 + 's');
            }
            observer.observe(el);
        });
    } else {
        document.querySelectorAll('.stat-value').forEach(function (el) { el.dataset.counted = '1'; });
    }

    // One rAF-throttled scroll handler for navbar, tilt and lit text.
    var ticking = false;
    function onScroll() {
        var y = window.scrollY;
        var vh = window.innerHeight;
        if (navbar) navbar.classList.toggle('scrolled', y > 12);

        if (tiltEl && !reduceMotion) {
            var tilt = Math.max(0, 1 - y / (vh * 0.55));
            tiltEl.style.setProperty('--tilt', tilt.toFixed(3));
        }

        lightBlocks.forEach(function (block) {
            var rect = block.el.getBoundingClientRect();
            // 0 when the paragraph's top reaches 85% of the screen, 1 when its bottom reaches 45%.
            var progress = (vh * 0.85 - rect.top) / (rect.height + vh * 0.4);
            progress = reduceMotion ? 1 : Math.max(0, Math.min(1, progress));
            var lit = Math.round(progress * block.words.length);
            block.words.forEach(function (word, i) { word.classList.toggle('lit', i < lit); });
        });
        ticking = false;
    }
    window.addEventListener('scroll', function () {
        if (!ticking) { ticking = true; requestAnimationFrame(onScroll); }
    }, { passive: true });
    window.addEventListener('resize', onScroll);
    onScroll();
})();
