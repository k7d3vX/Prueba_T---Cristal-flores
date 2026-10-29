@props(['level' => 'warn'])
{{-- Alerta con severidad: ícono + texto, nunca solo color. --}}
<div class="banner {{ $level === 'high' ? 'bad' : 'warn' }}" role="alert">
    <strong>{{ $level === 'high' ? '⚠ Atención' : '⚠ Aviso' }}:</strong> {{ $slot }}
</div>
