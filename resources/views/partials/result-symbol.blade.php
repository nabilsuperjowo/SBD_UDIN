<svg class="result-symbol" @isset($iconName) data-confirm-icon="{{ $iconName }}" @endisset @if($hidden ?? false) hidden @endif viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <circle class="result-ring" cx="12" cy="12" r="10"/>
    @if($state === 'success')
        <path class="result-mark" d="m7.5 12 3 3 6-6"/>
    @else
        <path class="result-mark" d="m8.5 8.5 7 7"/>
        <path class="result-mark result-mark-second" d="m15.5 8.5-7 7"/>
    @endif
</svg>
