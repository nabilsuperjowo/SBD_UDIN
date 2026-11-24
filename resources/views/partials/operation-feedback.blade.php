@if($errors->any())
    <div id="operation-feedback" hidden data-kind="error" data-title="Data belum disimpan" data-message="{{ $errors->first() }}" data-note="Periksa kembali isian formulir, lalu coba lagi."></div>
@elseif(session('success'))
    @php($feedbackDeleted = session('feedback_kind') === 'deleted')
    <div id="operation-feedback" hidden data-kind="{{ $feedbackDeleted ? 'deleted' : 'success' }}" data-title="{{ $feedbackDeleted ? 'Reservasi berhasil dihapus' : 'Berhasil!' }}" data-message="{{ session('success') }}" data-note="{{ $feedbackDeleted ? 'Riwayat transaksi tetap tersimpan di database.' : '' }}"></div>
@endif
