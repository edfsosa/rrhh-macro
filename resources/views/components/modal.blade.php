@props([
    'id',
    'type' => 'success',
    'title' => '',
    'description' => '',
    'buttonText' => 'Aceptar'
])

@php
    $typeClass     = 'modal-' . $type;
    $iconClass     = 'modal-icon modal-icon--' . $type;
    $titleId       = $id . 'Title';
    $descId        = $id . 'Desc';
    $closeButtonId = $type === 'error' ? 'closeErrorModal' : 'closeModal';
@endphp

<div id="{{ $id }}" class="modal hidden" role="dialog" aria-modal="true"
    aria-labelledby="{{ $titleId }}" aria-describedby="{{ $descId }}">
    <div class="modal-content {{ $typeClass }}">

        <div class="{{ $iconClass }}" aria-hidden="true">
            @if ($type === 'success')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="9 12 11 14 15 10"/>
                </svg>
            @elseif ($type === 'error')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            @elseif ($type === 'warning')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="16" x2="12" y2="12"/>
                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                </svg>
            @endif
        </div>

        <h2 id="{{ $titleId }}">{{ $title }}</h2>
        <p id="{{ $descId }}">{{ $description }}</p>

        @if ($type === 'success')
        <div id="{{ $id }}Meta" class="modal-success-meta hidden">
            <span id="{{ $id }}MetaName"  class="modal-meta-name"></span>
            <span id="{{ $id }}MetaEvent" class="modal-meta-badge"></span>
            <span id="{{ $id }}MetaTime"  class="modal-meta-time"></span>
        </div>

        {{-- Aviso de marcación guardada offline (sin confirmar todavía con el servidor) --}}
        <p id="{{ $id }}QueuedNotice" class="success-queued-notice" style="display:none" role="status">
            Guardado en el dispositivo — se sincronizará cuando haya conexión.
        </p>
        @endif

        <div class="modal-actions">
            <button id="{{ $closeButtonId }}" type="button">{{ $buttonText }}</button>
        </div>

    </div>
</div>
