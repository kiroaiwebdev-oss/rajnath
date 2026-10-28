@props(['name', 'label', 'options', 'value' => null, 'hint' => null, 'id' => null])
@php
    $id ??= 'f-'.str_replace(['.', '[', ']'], '-', $name);
    $err = $errors->first($name);
    $current = old($name, $value);
@endphp
<div>
    <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $name }}" @if ($err) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
            {{ $attributes->merge(['class' => 'input']) }}>
        @foreach ($options as $v => $text)
            <option value="{{ $v }}" @selected((string) $current === (string) $v)>{{ $text }}</option>
        @endforeach
    </select>
    @if ($err)<p id="{{ $id }}-error" class="field-error">{{ $err }}</p>@elseif ($hint)<p id="{{ $id }}-hint" class="field-hint">{{ $hint }}</p>@endif
</div>
