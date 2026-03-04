<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('forms.user_index') }}" class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Create New Form
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                <div class="flex items-center gap-3 mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Form Details</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Set up the basic information for your form</p>
                    </div>
                </div>

                <form action="{{ route('forms.store') }}" method="POST" x-data="{ categories: [{ name: '', description: '', percentage_start: 0, percentage_end: 100 }] }">
                    @csrf

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="lg:col-span-2">
                            <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title *</label>
                            <input type="text" name="title" id="title" value="{{ old('title') }}"
                                   class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent"
                                   required>
                            @error('title')
                            <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="lg:col-span-2">
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</label>
                            <textarea name="description" id="description" rows="3"
                                      class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent resize-none">{{ old('description') }}</textarea>
                            @error('description')
                            <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label for="visibility" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Visibility</label>
                            <select name="visibility" id="visibility"
                                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent"
                                    required>
                                <option value="public">Public</option>
                                <option value="authenticated">Authenticated Users Only</option>
                                <option value="private" selected>Private</option>
                            </select>
                            @error('visibility')
                            <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <!-- Categories Section -->
                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Form Categories</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Organize your form into logical sections</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <template x-for="(category, index) in categories" :key="index">
                                <div class="p-4 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                    <div class="mb-3">
                                        <label :for="'category_name_'+index" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Category Name</label>
                                        <input type="text" :name="'categories['+index+'][name]'" :id="'category_name_'+index" x-model="category.name"
                                               class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent"
                                               required>
                                    </div>
                                    <div class="mb-3">
                                        <label :for="'category_description_'+index" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Category Description</label>
                                        <textarea :name="'categories['+index+'][description]'" :id="'category_description_'+index" x-model="category.description" rows="2"
                                                  class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:border-transparent resize-none"></textarea>
                                    </div>
                                    <button type="button" @click="categories = categories.filter((_, i) => i !== index)"
                                            class="text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                                            x-show="categories.length > 1">
                                        Remove Category
                                    </button>
                                </div>
                            </template>
                        </div>

                        <button type="button" @click="categories.push({ name: '', description: '', percentage_start: 0, percentage_end: 100 })"
                                class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Category
                        </button>
                    </div>

                    <!-- Submit -->
                    <div class="flex justify-end mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Create Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
