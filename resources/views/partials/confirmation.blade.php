<dialog class="confirm-modal" id="action-confirmation" role="alertdialog" aria-labelledby="confirmation-title" aria-describedby="confirmation-description confirmation-note">
    <div class="confirm-heading">
        <div class="confirm-icon" aria-hidden="true">
            <svg data-confirm-icon="delete" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M9 6V4h6v2M5 6l1 14h12l1-14M10 10v6M14 10v6"/></svg>
            <svg data-confirm-icon="payment" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" hidden><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M3 9h18M8 14l3 3 5-5"/></svg>
            @include('partials.result-symbol', ['state' => 'success', 'iconName' => 'success', 'hidden' => true])
            @include('partials.result-symbol', ['state' => 'error', 'iconName' => 'error', 'hidden' => true])
        </div>
        <button class="confirm-close" type="button" data-confirm-cancel aria-label="Tutup konfirmasi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
    </div>
    <p class="confirm-eyebrow">GALAXY FUTSAL CENTRE</p>
    <h2 id="confirmation-title">Hapus reservasi ini?</h2>
    <p class="confirm-description" id="confirmation-description"></p>
    <p class="confirm-note" id="confirmation-note"></p>
    <div class="confirm-actions">
        <button class="button ghost" type="button" data-confirm-cancel autofocus>Batal</button>
        <button class="button danger" type="button" id="confirmation-accept">Hapus Reservasi</button>
    </div>
</dialog>
