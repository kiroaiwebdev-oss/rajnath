@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'id' => null])
@php
    $id ??= 'f-'.str_replace(['.', '[', ']'], '-', $name);
    $err = $errors->first($name);
@endphp
<div>
    <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
           @if ($err) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
           {{ $attributes->merge(['class' => 'input']) }}>
    @if ($err)
        <p id="{{ $id }}-error" class="field-error">{{ $err }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="field-hint">{{ $hint }}</p>
    @endif
</div>
