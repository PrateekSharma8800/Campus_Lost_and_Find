<?php /** Shared confirmation dialog. Any <form data-confirm="Question?"> opens it before submitting (see app.js). */ ?>
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-body p-4 text-center">
                <span class="icon-bubble amber mb-3"><i class="bi bi-exclamation-triangle"></i></span>
                <h2 class="h5" id="confirmTitle">Are you sure?</h2>
                <p class="text-muted small mb-4" data-confirm-text></p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" data-confirm-ok>Yes, continue</button>
                </div>
            </div>
        </div>
    </div>
</div>
