@props([
    'label' => '',
    'value' => 0,
    'colSpan' => 1,
])

<div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-100 dark:border-gray-600" style="grid-column: span {{ $colSpan }}">
    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $label }}</div>
    <div class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ $value }}</div>
</div>
