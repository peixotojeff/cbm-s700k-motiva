@props(['campo'])
{{-- Erro de validação junto ao campo; o input referencia este id com aria-describedby="erro-<campo>". --}}
@error($campo)
    <p id="erro-{{ $campo }}" class="mt-1 flex items-start gap-1 text-sm font-medium text-red-700">
        <x-icone nome="alerta" class="mt-0.5 h-4 w-4" />{{ $message }}
    </p>
@enderror
