<div x-data="{ 
    showQuickAdd: null,
    draggedField: null,
    showFieldTypeMenu: false 
}" class="space-y-6">
    
    <!-- Toolbar -->
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="p-4">
            <!-- Stats Row -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-2">
                        <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center">
                            <svg class="w-5 h-5 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $fieldStats['categories'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Sections</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center">
                            <svg class="w-5 h-5 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $fieldStats['total'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Fields</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $fieldStats['required'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Required</p>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex items-center gap-2">
                    <button wire:click="openAddCategoryPanel" 
                            class="inline-flex items-center gap-2 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-sm font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Section
                    </button>
                    <button wire:click="openAddFieldPanel"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-sm font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Field
                    </button>
                </div>
            </div>
            
            <!-- Search and Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" 
                           wire:model.live.debounce.300ms="searchQuery"
                           placeholder="Search fields..." 
                           class="w-full pl-10 pr-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                </div>
                
                @if($searchQuery || $activeFieldTypeFilter)
                    <button wire:click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        Clear filters
                    </button>
                @endif
                
                <div class="flex items-center gap-1">
                    <button wire:click="expandAllCategories" 
                            class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                            title="Expand all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                        </svg>
                    </button>
                    <button wire:click="collapseAllCategories" 
                            class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                            title="Collapse all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Structure - Categories with Drag & Drop -->
    <div class="space-y-4" 
         wire:sortable="updateCategoryOrder" 
         wire:sortable.options="{ animation: 150, handle: '.category-drag-handle' }">
        @forelse($filteredCategories as $index => $category)
            <div wire:sortable.item="{{ $category['id'] }}" wire:key="category-{{ $category['id'] }}"
                 class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md">
                
                <!-- Category Header -->
                <div class="flex items-center gap-3 p-4 bg-gradient-to-r from-gray-50 to-white dark:from-gray-800 dark:to-gray-750 border-b border-gray-200 dark:border-gray-700">
                    <!-- Drag Handle -->
                    <div wire:sortable.handle class="category-drag-handle cursor-grab active:cursor-grabbing p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM14 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM14 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM14 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
                        </svg>
                    </div>
                    
                    <!-- Collapse Toggle -->
                    <button wire:click="toggleCategoryCollapse({{ $category['id'] }})" 
                            class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        <svg class="w-5 h-5 text-gray-500 transition-transform duration-200 {{ in_array($category['id'], $collapsedCategories) ? '' : 'rotate-90' }}" 
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    
                    <!-- Category Info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white truncate">{{ $category['name'] }}</h3>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                {{ count($category['fields']) }} {{ Str::plural('field', count($category['fields'])) }}
                            </span>
                        </div>
                        @if($category['description'])
                            <p class="text-sm text-gray-500 dark:text-gray-400 truncate mt-0.5">{{ $category['description'] }}</p>
                        @endif
                    </div>
                    
                    <!-- Category Actions -->
                    <div class="flex items-center gap-1">
                        <button wire:click="openAddFieldPanel({{ $category['id'] }})" 
                                class="p-2 text-sky-600 hover:text-sky-700 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/20 rounded-lg transition-colors"
                                title="Add field to this section">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </button>
                        <button wire:click="editCategory({{ $category['id'] }})" 
                                class="p-2 text-sky-600 hover:text-sky-700 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/20 rounded-lg transition-colors"
                                title="Edit section">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button wire:click="duplicateCategory({{ $category['id'] }})" 
                                class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                title="Duplicate section">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        </button>
                        <button wire:click="confirmDeleteCategory({{ $category['id'] }})" 
                                class="p-2 text-red-500 hover:text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                title="Delete section">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Category Fields -->
                @if(!in_array($category['id'], $collapsedCategories))
                    <div class="p-4 space-y-2" 
                         wire:sortable-group="updateFieldOrder"
                         wire:sortable-group.item-group="{{ $category['id'] }}"
                         wire:sortable-group.options="{ animation: 150, group: 'fields' }">
                        
                        @forelse($category['fields'] as $fieldIndex => $field)
                            <div wire:sortable-group.item="{{ $field['id'] }}" wire:key="field-{{ $field['id'] }}"
                                 class="group flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-transparent hover:border-gray-200 dark:hover:border-gray-600 transition-all">
                                
                                <!-- Field Drag Handle -->
                                <div wire:sortable-group.handle class="cursor-grab active:cursor-grabbing p-1 rounded opacity-0 group-hover:opacity-100 hover:bg-gray-200 dark:hover:bg-gray-600 transition-all">
                                    <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM14 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM14 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM14 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
                                    </svg>
                                </div>
                                
                                <!-- Field Type Icon -->
                                <div class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center
                                    @if($field['type'] === 'header') bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400
                                    @elseif($field['type'] === 'description') bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400
                                    @elseif($field['type'] === 'text') bg-gray-100 dark:bg-gray-600 text-gray-600 dark:text-gray-300
                                    @elseif($field['type'] === 'textarea') bg-gray-100 dark:bg-gray-600 text-gray-600 dark:text-gray-300
                                    @elseif($field['type'] === 'select') bg-cyan-100 dark:bg-cyan-900/30 text-cyan-600 dark:text-cyan-400
                                    @elseif($field['type'] === 'checkbox') bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400
                                    @elseif($field['type'] === 'radio') bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400
                                    @elseif($field['type'] === 'file') bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400
                                    @else bg-gray-100 dark:bg-gray-600 text-gray-600 dark:text-gray-300
                                    @endif">
                                    @if($field['type'] === 'header')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                                    @elseif($field['type'] === 'description')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                                    @elseif($field['type'] === 'text')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                    @elseif($field['type'] === 'textarea')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                    @elseif($field['type'] === 'select')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                                    @elseif($field['type'] === 'checkbox')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    @elseif($field['type'] === 'radio')
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                                    @elseif($field['type'] === 'file')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    @else
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                                    @endif
                                </div>
                                
                                <!-- Field Content -->
                                <div class="flex-1 min-w-0">
                                    @if($field['type'] === 'header')
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ $field['content'] }}</p>
                                        <p class="text-xs text-purple-600 dark:text-purple-400">Section Header</p>
                                    @elseif($field['type'] === 'description')
                                        <p class="text-gray-700 dark:text-gray-300 text-sm line-clamp-1">{{ $field['content'] }}</p>
                                        <p class="text-xs text-blue-600 dark:text-blue-400">Description Text</p>
                                    @else
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $field['label'] }}</p>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-xs text-gray-500 dark:text-gray-400 capitalize">{{ $fieldTypes[$field['type']]['label'] ?? $field['type'] }}</span>
                                            @if($field['required'])
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">Required</span>
                                            @endif
                                            @if($field['char_limit'])
                                                <span class="text-xs text-gray-400">Max {{ $field['char_limit'] }} chars</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                
                                <!-- Field Actions -->
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button wire:click="editField({{ $field['id'] }})" 
                                            class="p-1.5 text-sky-600 hover:text-sky-700 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/20 rounded transition-colors"
                                            title="Edit field">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button wire:click="duplicateField({{ $field['id'] }})" 
                                            class="p-1.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded transition-colors"
                                            title="Duplicate field">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                    <button wire:click="confirmDeleteField({{ $field['id'] }})" 
                                            class="p-1.5 text-red-500 hover:text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors"
                                            title="Delete field">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @empty
                            <!-- Empty State for Category -->
                            <div class="flex flex-col items-center justify-center py-8 text-center">
                                <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mb-3">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">No fields in this section yet</p>
                                <button wire:click="openAddFieldPanel({{ $category['id'] }})"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/20 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Add your first field
                                </button>
                            </div>
                        @endforelse
                        
                        <!-- Quick Add Field Row -->
                        @if(count($category['fields']) > 0)
                            <div x-data="{ showMenu: false }" class="relative">
                                <button @click="showMenu = !showMenu" 
                                        class="w-full flex items-center justify-center gap-2 py-2 text-sm text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 border-2 border-dashed border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Quick add field
                                </button>
                                
                                <!-- Quick Add Menu -->
                                <div x-show="showMenu"
                                     x-cloak
                                     @click.away="showMenu = false"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="transform opacity-0 scale-95"
                                     x-transition:enter-end="transform opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="transform opacity-100 scale-100"
                                     x-transition:leave-end="transform opacity-0 scale-95"
                                     class="absolute bottom-full left-0 right-0 mb-2 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-3 z-20">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Field Types</p>
                                    <div class="grid grid-cols-4 gap-2">
                                        @foreach($fieldTypes as $type => $config)
                                            <button wire:click="quickAddField('{{ $type }}', {{ $category['id'] }})" 
                                                    @click="showMenu = false"
                                                    class="flex flex-col items-center gap-1 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                                <div class="w-8 h-8 rounded-lg flex items-center justify-center
                                                    @if($type === 'header') bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400
                                                    @elseif($type === 'description') bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400
                                                    @elseif($type === 'select') bg-cyan-100 dark:bg-cyan-900/30 text-cyan-600 dark:text-cyan-400
                                                    @elseif($type === 'checkbox') bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400
                                                    @elseif($type === 'radio') bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400
                                                    @elseif($type === 'file') bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400
                                                    @else bg-gray-100 dark:bg-gray-600 text-gray-600 dark:text-gray-300
                                                    @endif">
                                                    @if($type === 'header')
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                                                    @elseif($type === 'description')
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                                                    @elseif($type === 'text')
                                                        <span class="text-xs font-bold">Aa</span>
                                                    @elseif($type === 'textarea')
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                                    @elseif($type === 'select')
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                                                    @elseif($type === 'checkbox')
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    @elseif($type === 'radio')
                                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                                                    @elseif($type === 'file')
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                                    @endif
                                                </div>
                                                <span class="text-xs text-gray-600 dark:text-gray-300">{{ $config['label'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <!-- Empty State - No Categories -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                <div class="w-16 h-16 rounded-full bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Start building your form</h3>
                <p class="text-gray-500 dark:text-gray-400 mb-6 max-w-md mx-auto">
                    Create sections to organize your form fields. Each section can contain multiple fields that users will fill out.
                </p>
                <button wire:click="openAddCategoryPanel" 
                        class="inline-flex items-center gap-2 px-6 py-3 bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Create your first section
                </button>
            </div>
        @endforelse
    </div>
    <!-- Delete Section Confirmation Modal -->
    @if($confirmingCategoryDeletion)
        <div class="fixed inset-0 z-50 overflow-y-auto"
             x-data="{ init() { document.body.style.overflow = 'hidden' }, destroy() { document.body.style.overflow = '' } }"
             @keydown.escape.window="$wire.set('confirmingCategoryDeletion', false)">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity z-40" wire:click="$set('confirmingCategoryDeletion', false)"></div>
                <div class="relative z-50 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full p-6">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete Section</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">This will permanently delete this section and all its fields. This action cannot be undone.</p>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3 justify-end">
                        <button wire:click="$set('confirmingCategoryDeletion', false)" class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">Cancel</button>
                        <button wire:click="deleteCategory" class="px-4 py-2.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">Delete Section</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Delete Field Confirmation Modal -->
    @if($confirmingFieldDeletion)
        <div class="fixed inset-0 z-50 overflow-y-auto"
             x-data="{ init() { document.body.style.overflow = 'hidden' }, destroy() { document.body.style.overflow = '' } }"
             @keydown.escape.window="$wire.set('confirmingFieldDeletion', false)">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity z-40" wire:click="$set('confirmingFieldDeletion', false)"></div>
                <div class="relative z-50 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full p-6">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete Field</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">This will permanently delete this field. This action cannot be undone.</p>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3 justify-end">
                        <button wire:click="$set('confirmingFieldDeletion', false)" class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">Cancel</button>
                        <button wire:click="deleteField" class="px-4 py-2.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">Delete Field</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Edit Section Modal -->
    @if($editingCategory)
        <div class="fixed inset-0 z-50 overflow-y-auto"
             x-data="{ init() { document.body.style.overflow = 'hidden' }, destroy() { document.body.style.overflow = '' } }"
             @keydown.escape.window="$wire.set('editingCategory', false)">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity z-40" wire:click="$set('editingCategory', false)"></div>
                <div class="relative z-50 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-lg w-full">
                    <form wire:submit.prevent="updateCategory">
                        <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Section</h3>
                            </div>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label for="edit-category-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Section Name *</label>
                                <input type="text" id="edit-category-name" wire:model.defer="categoryBeingEdited.name"
                                       class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                                @error('categoryBeingEdited.name') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="edit-category-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                <textarea id="edit-category-description" wire:model.defer="categoryBeingEdited.description" rows="3"
                                          class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent resize-none"></textarea>
                                @error('categoryBeingEdited.description') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 rounded-b-2xl flex gap-3 justify-end">
                            <button type="button" wire:click="$set('editingCategory', false)" class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2.5 text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 rounded-lg transition-colors">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
    <!-- Edit Field Slide-out Panel -->
    @if($editingField)
        <div class="fixed inset-0 z-50 overflow-hidden"
             x-data="{ init() { document.body.style.overflow = 'hidden' }, destroy() { document.body.style.overflow = '' } }"
             @keydown.escape.window="$wire.set('editingField', false)">
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity z-40" wire:click="$set('editingField', false)"></div>
                <div class="fixed inset-y-0 right-0 pl-10 max-w-full flex z-50">
                    <div class="w-screen max-w-md">
                        <div class="h-full flex flex-col bg-white dark:bg-gray-800 shadow-2xl">
                            <!-- Header -->
                            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </div>
                                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Field</h2>
                                    </div>
                                    <button wire:click="$set('editingField', false)" class="p-2 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Form -->
                            <form wire:submit.prevent="updateField" class="flex-1 flex flex-col">
                                <div class="flex-1 px-6 py-5 space-y-5 overflow-y-auto">
                                    <!-- Field Type -->
                                    <div>
                                        <label for="edit-field-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Field Type</label>
                                        <select id="edit-field-type" wire:model.live="fieldBeingEdited.type"
                                                class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                                            @foreach($fieldTypes as $type => $config)
                                                <option value="{{ $type }}">{{ $config['label'] }}</option>
                                            @endforeach
                                        </select>
                                        @error('fieldBeingEdited.type') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                    </div>

                                    @if(in_array($fieldBeingEdited['type'], ['header', 'description']))
                                        <div>
                                            <label for="edit-field-content" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Content *</label>
                                            @if($fieldBeingEdited['type'] === 'description')
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Supports **bold**, *italic*, and bullet points</p>
                                            @endif
                                            <textarea id="edit-field-content" wire:model.live="fieldBeingEdited.content" rows="4"
                                                      class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent resize-none"></textarea>
                                            @error('fieldBeingEdited.content') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                            
                                            @if($fieldBeingEdited['type'] === 'description' && !empty($fieldBeingEdited['content']))
                                                <div class="mt-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Preview:</p>
                                                    <div class="prose prose-sm dark:prose-invert max-w-none">
                                                        {!! \App\Helpers\MarkdownHelper::toHtml($fieldBeingEdited['content']) !!}
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <div>
                                            <label for="edit-field-label" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Field Label *</label>
                                            <input type="text" id="edit-field-label" wire:model.defer="fieldBeingEdited.label"
                                                   class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                                            @error('fieldBeingEdited.label') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                        </div>

                                        @if(in_array($fieldBeingEdited['type'], ['select', 'checkbox', 'radio']))
                                            <div>
                                                <label for="edit-field-options" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Options *</label>
                                                <input type="text" id="edit-field-options" wire:model.defer="fieldBeingEdited.options"
                                                       class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Separate options with commas</p>
                                                @error('fieldBeingEdited.options') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                            </div>
                                        @endif

                                        @if(in_array($fieldBeingEdited['type'], ['text', 'textarea']))
                                            <div>
                                                <label for="edit-field-char-limit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Character Limit</label>
                                                <input type="number" id="edit-field-char-limit" wire:model.defer="fieldBeingEdited.char_limit"
                                                       min="1" placeholder="No limit"
                                                       class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                                                @error('fieldBeingEdited.char_limit') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                            </div>
                                        @endif

                                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                            <input type="checkbox" id="edit-field-required" wire:model.defer="fieldBeingEdited.required"
                                                   class="w-4 h-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500">
                                            <label for="edit-field-required" class="flex-1">
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Required field</span>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Users must fill this field to submit</p>
                                            </label>
                                        </div>

                                        <!-- Conditional Visibility -->
                                        <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 space-y-3">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Conditional Visibility</label>
                                            <select wire:model.live="fieldBeingEdited.depends_on_field_id"
                                                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent text-sm">
                                                <option value="">Always visible</option>
                                                @foreach($form->fields()->where('id', '!=', $fieldBeingEdited['id'] ?? 0)->whereIn('type', ['select', 'radio', 'checkbox'])->orderBy('order')->get() as $parentField)
                                                    <option value="{{ $parentField->id }}">{{ $parentField->label }}</option>
                                                @endforeach
                                            </select>
                                            @if(!empty($fieldBeingEdited['depends_on_field_id']))
                                                <input type="text" wire:model.defer="fieldBeingEdited.depends_on_value"
                                                       placeholder="Show when value equals..."
                                                       class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent text-sm">
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                
                                <!-- Footer -->
                                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                    <div class="flex gap-3">
                                        <button type="button" wire:click="$set('editingField', false)"
                                                class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                            Cancel
                                        </button>
                                        <button type="submit"
                                                class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 rounded-lg transition-colors">
                                            Save Changes
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Add Section Slide-out Panel -->
    @if($showAddCategoryPanel)
        <div class="fixed inset-0 z-50 overflow-hidden"
             x-data="{ init() { document.body.style.overflow = 'hidden' }, destroy() { document.body.style.overflow = '' } }"
             @keydown.escape.window="$wire.closeAddCategoryPanel()">
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity z-40" wire:click="closeAddCategoryPanel"></div>
                <div class="fixed inset-y-0 right-0 pl-10 max-w-full flex z-50">
                    <div class="w-screen max-w-md">
                        <div class="h-full flex flex-col bg-white dark:bg-gray-800 shadow-2xl">
                            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                            </svg>
                                        </div>
                                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add New Section</h2>
                                    </div>
                                    <button wire:click="closeAddCategoryPanel" class="p-2 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Sections help organize your form into logical groups.</p>
                            </div>
                            
                            <form wire:submit.prevent="addCategory" class="flex-1 flex flex-col">
                                <div class="flex-1 px-6 py-5 space-y-5 overflow-y-auto">
                                    <div>
                                        <label for="new-category-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Section Name *</label>
                                        <input type="text" id="new-category-name" wire:model.defer="newCategory.name"
                                               placeholder="e.g., Personal Information"
                                               class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                                        @error('newCategory.name') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                    </div>
                                    
                                    <div>
                                        <label for="new-category-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description (optional)</label>
                                        <textarea id="new-category-description" wire:model.defer="newCategory.description" rows="3"
                                                  placeholder="Brief description of what this section contains..."
                                                  class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent resize-none"></textarea>
                                        @error('newCategory.description') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                
                                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                    <div class="flex gap-3">
                                        <button type="button" wire:click="closeAddCategoryPanel"
                                                class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                            Cancel
                                        </button>
                                        <button type="submit"
                                                class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 rounded-lg transition-colors">
                                            Add Section
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Add Field Slide-out Panel -->
    @if($showAddFieldPanel)
        <div class="fixed inset-0 z-50 overflow-hidden"
             x-data="{ init() { document.body.style.overflow = 'hidden' }, destroy() { document.body.style.overflow = '' } }"
             @keydown.escape.window="$wire.closeAddFieldPanel()">
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity z-40" wire:click="closeAddFieldPanel"></div>
                <div class="fixed inset-y-0 right-0 pl-10 max-w-full flex z-50">
                    <div class="w-screen max-w-lg">
                        <div class="h-full flex flex-col bg-white dark:bg-gray-800 shadow-2xl">
                            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                        </div>
                                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add New Field</h2>
                                    </div>
                                    <button wire:click="closeAddFieldPanel" class="p-2 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <form wire:submit.prevent="addField" class="flex-1 flex flex-col">
                                <div class="flex-1 px-6 py-5 space-y-5 overflow-y-auto">
                                    <!-- Section Selection -->
                                    <div>
                                        <label for="field-category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Section *</label>
                                        <select id="field-category" wire:model.live="newField.category_id"
                                                class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                                            <option value="">Select a section</option>
                                            @foreach($categories as $category)
                                                <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                                            @endforeach
                                        </select>
                                        @error('newField.category_id') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Field Type Selection -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Field Type *</label>
                                        <div class="grid grid-cols-4 gap-2">
                                            @foreach($fieldTypes as $type => $config)
                                                <button type="button" wire:click="$set('newField.type', '{{ $type }}')"
                                                        class="flex flex-col items-center gap-1.5 p-3 rounded-lg border-2 transition-all text-center
                                                            {{ $newField['type'] === $type
                                                                ? 'border-sky-500 bg-sky-50 dark:bg-sky-900/20'
                                                                : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500' }}">
                                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center
                                                        {{ $type === 'header' ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400' : '' }}
                                                        {{ $type === 'description' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400' : '' }}
                                                        {{ $type === 'text' ? 'bg-gray-100 dark:bg-gray-600 text-gray-600 dark:text-gray-300' : '' }}
                                                        {{ $type === 'textarea' ? 'bg-gray-100 dark:bg-gray-600 text-gray-600 dark:text-gray-300' : '' }}
                                                        {{ $type === 'select' ? 'bg-cyan-100 dark:bg-cyan-900/30 text-cyan-600 dark:text-cyan-400' : '' }}
                                                        {{ $type === 'checkbox' ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400' : '' }}
                                                        {{ $type === 'radio' ? 'bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400' : '' }}
                                                        {{ $type === 'file' ? 'bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400' : '' }}">
                                                        @if($type === 'header')
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                                                        @elseif($type === 'description')
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                                                        @elseif($type === 'text')
                                                            <span class="text-xs font-bold">Aa</span>
                                                        @elseif($type === 'textarea')
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                                        @elseif($type === 'select')
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                                                        @elseif($type === 'checkbox')
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        @elseif($type === 'radio')
                                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                                                        @elseif($type === 'file')
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                                        @endif
                                                    </div>
                                                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $config['label'] }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                        @error('newField.type') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Conditional Fields -->
                                    @if($newField['type'])
                                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700 space-y-4">
                                            @if(in_array($newField['type'], ['header', 'description']))
                                                <div>
                                                    <label for="field-content" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                        {{ $newField['type'] === 'header' ? 'Header Text' : 'Description Text' }} *
                                                    </label>
                                                    @if($newField['type'] === 'description')
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Supports **bold**, *italic*, and bullet points</p>
                                                    @endif
                                                    <textarea id="field-content" wire:model.live="newField.content" rows="3"
                                                              class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent resize-none"></textarea>
                                                    @error('newField.content') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                                    
                                                    @if($newField['type'] === 'description' && $newField['content'])
                                                        <div class="mt-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                                                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Preview:</p>
                                                            <div class="prose prose-sm dark:prose-invert max-w-none">
                                                                {!! \App\Helpers\MarkdownHelper::toHtml($newField['content']) !!}
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            @else
                                                <div>
                                                    <label for="field-label" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Field Label *</label>
                                                    <input type="text" id="field-label" wire:model.live="newField.label"
                                                           placeholder="e.g., Email Address"
                                                           class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                                                    @error('newField.label') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                                </div>

                                                @if(in_array($newField['type'], ['select', 'checkbox', 'radio']))
                                                    <div>
                                                        <label for="field-options" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Options *</label>
                                                        <input type="text" id="field-options" wire:model.live="newField.options"
                                                               placeholder="Option 1, Option 2, Option 3"
                                                               class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Separate options with commas</p>
                                                        @error('newField.options') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                                    </div>
                                                @endif

                                                @if(in_array($newField['type'], ['text', 'textarea']))
                                                    <div>
                                                        <label for="field-char-limit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Character Limit</label>
                                                        <input type="number" id="field-char-limit" wire:model.live="newField.char_limit"
                                                               min="1" placeholder="No limit"
                                                               class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent">
                                                        @error('newField.char_limit') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                                    </div>
                                                @endif

                                                <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                                    <input type="checkbox" id="field-required" wire:model.live="newField.required"
                                                           class="w-4 h-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500">
                                                    <label for="field-required" class="flex-1">
                                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Required field</span>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">Users must fill this field to submit</p>
                                                    </label>
                                                </div>

                                                <!-- Conditional Visibility -->
                                                <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 space-y-3">
                                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Conditional Visibility</label>
                                                    <select wire:model.live="newField.depends_on_field_id"
                                                            class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent text-sm">
                                                        <option value="">Always visible</option>
                                                        @foreach($form->fields()->whereIn('type', ['select', 'radio', 'checkbox'])->orderBy('order')->get() as $parentField)
                                                            <option value="{{ $parentField->id }}">{{ $parentField->label }}</option>
                                                        @endforeach
                                                    </select>
                                                    @if(!empty($newField['depends_on_field_id']))
                                                        <input type="text" wire:model.live="newField.depends_on_value"
                                                               placeholder="Show when value equals..."
                                                               class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent text-sm">
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                
                                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                    <div class="flex gap-3">
                                        <button type="button" wire:click="closeAddFieldPanel"
                                                class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                            Cancel
                                        </button>
                                        <button type="submit"
                                                class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 rounded-lg transition-colors">
                                            Add Field
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>


