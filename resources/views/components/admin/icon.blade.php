@props(['name', 'class' => 'h-5 w-5'])

@switch($name)
    @case('dashboard')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <path d="M4 13h7V4H4v9Zm9 7h7v-9h-7v9Zm0-16v5h7V4h-7ZM4 20h7v-5H4v5Z" />
        </svg>
        @break
    @case('products')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <path d="M4 7.5 12 4l8 3.5L12 11 4 7.5Z" />
            <path d="M4 7.5V16.5L12 20l8-3.5V7.5" />
            <path d="M12 11v9" />
        </svg>
        @break
    @case('categories')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <path d="M4 6h7v5H4zM13 6h7v5h-7zM4 13h7v5H4zM13 13h7v5h-7z" />
        </svg>
        @break
    @case('inventory')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <path d="M4 8h16v10H4z" />
            <path d="M8 8V5h8v3" />
            <path d="M9 13h6" />
        </svg>
        @break
    @case('stock-in')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <path d="M12 19V5" />
            <path d="m7 10 5-5 5 5" />
            <path d="M5 20h14" />
        </svg>
        @break
    @case('upload')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <path d="M12 16V4" />
            <path d="m7 9 5-5 5 5" />
            <path d="M4 20h16" />
        </svg>
        @break
    @case('pos')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <path d="M5 6h14v12H5z" />
            <path d="M8 10h8M8 14h4" />
        </svg>
        @break
    @case('orders')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <path d="M7 4h10l2 3v13H5V7l2-3Z" />
            <path d="M9 11h6M9 15h4" />
        </svg>
        @break
    @case('customers')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <path d="M16 19a4 4 0 0 0-8 0" />
            <path d="M12 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
            <path d="M20 18a3 3 0 0 0-3-3" />
            <path d="M17 9a2.5 2.5 0 1 0-2.5-2.5" />
        </svg>
        @break
    @case('reports')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <path d="M5 20V8m7 12V4m7 16v-8" stroke-linecap="round" />
        </svg>
        @break
    @case('spark')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <path d="m12 3 1.8 4.7L18.5 9l-4.7 1.3L12 15l-1.8-4.7L5.5 9l4.7-1.3L12 3Z" />
            <path d="M19 15.5 20 18l2.5 1-2.5 1L19 22.5 18 20l-2.5-1 2.5-1 1-2.5Z" />
        </svg>
        @break
    @case('globe')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <circle cx="12" cy="12" r="8" />
            <path d="M4 12h16M12 4a13.5 13.5 0 0 1 0 16M12 4a13.5 13.5 0 0 0 0 16" />
        </svg>
        @break
    @case('clock')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <circle cx="12" cy="12" r="8" />
            <path d="M12 8v4l3 2" stroke-linecap="round" />
        </svg>
        @break
    @case('check-circle')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <circle cx="12" cy="12" r="8" />
            <path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @break
    @case('alert')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <path d="M12 5 4.5 18h15L12 5Z" stroke-linejoin="round" />
            <path d="M12 10v4M12 17h.01" stroke-linecap="round" />
        </svg>
        @break
    @case('slash-circle')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <circle cx="12" cy="12" r="8" />
            <path d="m8 16 8-8" stroke-linecap="round" />
        </svg>
        @break
    @case('trend')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <path d="M4 16 9 11l3 3 7-7" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M15 7h4v4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @break
    @default
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <circle cx="12" cy="12" r="8" />
        </svg>
@endswitch
