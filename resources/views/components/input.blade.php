@props([
    'label',
    'value' => '',
    'name' => null,
    'type' => 'text',
    'autocomplete' => 'off',
    'placeholder' => null,
    'readonly' => false,
])

@php
    $fieldName = $name ?: 'field_'.substr(md5($label), 0, 8);
    $fieldId = 'field-'.str_replace('_', '-', $fieldName);
@endphp

<div class="form-field">
    <label for="{{ $fieldId }}">{{ $label }}</label>
    <input
        id="{{ $fieldId }}"
        name="{{ $fieldName }}"
        type="{{ $type }}"
        value="{{ $value }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        autocomplete="{{ $autocomplete }}"
        @readonly($readonly)
        {{ $attributes }}
    >
</div>
