<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.leads.view.title', ['title' => $lead->title])
    </x-slot>

    <!-- Content -->
    <div class="relative flex gap-4 max-lg:flex-wrap">
        <!-- Left Panel -->
        {!! view_render_event('admin.leads.view.left.before', ['lead' => $lead]) !!}

        <div class="max-lg:min-w-full max-lg:max-w-full [&>div:last-child]:border-b-0 lg:sticky lg:top-[73px] flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <!-- Lead Information -->
            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <!-- Breadcrumb's -->
                <div class="flex items-center justify-between">
                    <x-admin::breadcrumbs
                        name="leads.view"
                        :entity="$lead"
                    />
                </div>

                <div class="mb-2">
                    @if (($days = $lead->rotten_days) > 0)
                        @php
                            $lead->tags->prepend([
                                'name'  => '<span class="icon-rotten text-base"></span>' . trans('admin::app.leads.view.rotten-days', ['days' => $days]),
                                'color' => '#FEE2E2'
                            ]);
                        @endphp
                    @endif

                    {!! view_render_event('admin.leads.view.tags.before', ['lead' => $lead]) !!}

                    <!-- Tags -->
                    <x-admin::tags
                        :attach-endpoint="route('admin.leads.tags.attach', $lead->id)"
                        :detach-endpoint="route('admin.leads.tags.detach', $lead->id)"
                        :added-tags="$lead->tags"
                    />

                    {!! view_render_event('admin.leads.view.tags.after', ['lead' => $lead]) !!}
                </div>


                {!! view_render_event('admin.leads.view.title.before', ['lead' => $lead]) !!}

                <!-- Title -->
                <h3 class="text-lg font-bold dark:text-white">
                    {{ $lead->title }}
                </h1>

                {!! view_render_event('admin.leads.view.title.after', ['lead' => $lead]) !!}

                <!-- Activity Actions -->
                <div class="flex flex-wrap gap-2">
                    {!! view_render_event('admin.leads.view.actions.before', ['lead' => $lead]) !!}

                    @if (bouncer()->hasPermission('mail.compose'))
                        <!-- Mail Activity Action -->
                        <x-admin::activities.actions.mail
                            :entity="$lead"
                            entity-control-name="lead_id"
                        />
                    @endif

                    @if (bouncer()->hasPermission('activities.create'))
                        <!-- File Activity Action -->
                        <x-admin::activities.actions.file
                            :entity="$lead"
                            entity-control-name="lead_id"
                        />

                        <!-- Note Activity Action -->
                        <x-admin::activities.actions.note
                            :entity="$lead"
                            entity-control-name="lead_id"
                        />

                        <!-- Activity Action -->
                        <x-admin::activities.actions.activity
                            :entity="$lead"
                            entity-control-name="lead_id"
                        />
                    @endif

                    {!! view_render_event('admin.leads.view.actions.after', ['lead' => $lead]) !!}
                </div>
            </div>
            
            <!-- Lead Attributes -->
            @include ('admin::leads.view.attributes')

            <!-- Contact Person -->
            @include ('admin::leads.view.person')

            <!-- Organization -->
            @include ('admin::leads.view.organization')
        </div>

        {!! view_render_event('admin.leads.view.left.after', ['lead' => $lead]) !!}

        {!! view_render_event('admin.leads.view.right.before', ['lead' => $lead]) !!}
        
        <!-- Right Panel -->
        <div class="flex w-full flex-col gap-4 rounded-lg">
            <!-- Stages Navigation -->
            @include ('admin::leads.view.stages')

            <!-- Activities -->
            {!! view_render_event('admin.leads.view.activities.before', ['lead' => $lead]) !!}

            <x-admin::activities
                :endpoint="route('admin.leads.activities.index', $lead->id)"
                :email-detach-endpoint="route('admin.leads.emails.detach', $lead->id)"
                :lead="$lead"
                :extra-types="[
                    ['name' => 'description', 'label' => trans('admin::app.leads.view.tabs.description')],
                    ['name' => 'products', 'label' => trans('admin::app.leads.view.tabs.products')],
                    ['name' => 'quotes', 'label' => trans('admin::app.leads.view.tabs.quotes')],
                    ['name' => 'github', 'label' => 'GitHub Assets'],
                ]"
            >
                <!-- Products -->
                <x-slot:products>
                    @include ('admin::leads.view.products')
                </x-slot>

                <!-- Quotes -->
                <x-slot:quotes>
                    @include ('admin::leads.view.quotes')
                </x-slot>

                <!-- Description -->
                <x-slot:description>
                    <div class="p-4 dark:text-white">
                        {{ $lead->description }}
                    </div>
                </x-slot>

                <!-- GitHub Assets -->
                <x-slot:github>
                    <v-lead-github-assets></v-lead-github-assets>
                </x-slot>
            </x-admin::activities>

            {!! view_render_event('admin.leads.view.activities.after', ['lead' => $lead]) !!}
        </div>

        {!! view_render_event('admin.leads.view.right.after', ['lead' => $lead]) !!}
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-lead-github-assets-template"
        >
            <div>
                <!-- Header with Add Assets Button -->
                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                    <button 
                        type="button"
                        class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-500 dark:hover:bg-blue-600"
                        @click="$refs.addAssetsModal.open()"
                    >
                        <span class="icon-plus text-sm mr-1"></span>
                        Add Assets
                    </button>
                </div>
                
                <!-- Content -->
                <div class="p-4">
                    <div v-if="hasAssets" class="space-y-4">
                        <!-- GitHub Repository -->
                        <div v-if="githubUrl" class="flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-900 dark:bg-gray-700">
                                <span class="icon-github text-white text-xl"></span>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900 dark:text-white">GitHub Repository</h4>
                                <a :href="githubUrl" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm break-all">
                                    @{{ githubUrl }}
                                </a>
                            </div>
                            <a :href="githubUrl" target="_blank" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                                <span class="icon-external-link text-lg"></span>
                            </a>
                        </div>

                        <!-- Live Website -->
                        <div v-if="vercelUrl" class="flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-black dark:bg-gray-700">
                                <span class="icon-website text-white text-xl"></span>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900 dark:text-white">Live Website</h4>
                                <a :href="vercelUrl" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm break-all">
                                    @{{ vercelUrl }}
                                </a>
                            </div>
                            <a :href="vercelUrl" target="_blank" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                                <span class="icon-external-link text-lg"></span>
                            </a>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-8">
                        <div class="flex justify-center mb-4">
                            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                <span class="icon-github text-gray-400 text-2xl"></span>
                            </div>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No GitHub Assets</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-4">Click "Add Assets" to add GitHub repository and live site links.</p>
                        <button 
                            type="button"
                            class="secondary-button"
                            @click="$refs.addAssetsModal.open()"
                        >
                            Add Assets
                        </button>
                    </div>
                </div>

                <!-- Add Assets Modal -->
                <form @submit.prevent="updateAssets">
                    <x-admin::modal ref="addAssetsModal">
                        <x-slot:header>
                            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                                Add GitHub Assets
                            </h2>
                        </x-slot>

                        <x-slot:content>
                            <div class="space-y-4">
                                <div class="form-group">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        GitHub Repository URL
                                    </label>

                                    <input
                                        type="url"
                                        name="github_repo_url"
                                        v-model="assets.github_repo_url"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                                        placeholder="https://github.com/username/repository"
                                    />
                                </div>

                                <div class="form-group">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Live Website URL
                                    </label>

                                    <input
                                        type="url"
                                        name="vercel_url"
                                        v-model="assets.vercel_url"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                                        placeholder="https://your-website.vercel.app"
                                    />
                                </div>
                            </div>
                        </x-slot>

                        <x-slot:footer>
                            <div class="flex gap-x-2.5 items-center">
                                <button
                                    type="submit"
                                    class="primary-button"
                                    :disabled="isLoading"
                                >
                                    <span v-if="isLoading" class="icon-spinner animate-spin text-sm mr-1"></span>
                                    @{{ isLoading ? 'Saving...' : 'Save Assets' }}
                                </button>
                            </div>
                        </x-slot>
                    </x-admin::modal>
                </form>
            </div>
        </script>

        <script type="module">
            app.component('v-lead-github-assets', {
                template: '#v-lead-github-assets-template',

                data() {
                    return {
                        isLoading: false,
                        assets: {
                            github_repo_url: '{{ $lead->github_repo_url ?? '' }}',
                            vercel_url: '{{ $lead->vercel_url ?? '' }}'
                        }
                    }
                },

                computed: {
                    hasAssets() {
                        return this.githubUrl || this.vercelUrl;
                    },

                    githubUrl() {
                        return this.assets.github_repo_url;
                    },

                    vercelUrl() {
                        return this.assets.vercel_url;
                    }
                },

                methods: {
                    updateAssets() {
                        this.isLoading = true;

                        this.$axios.put("{{ route('admin.leads.update', $lead->id) }}", {
                            github_repo_url: this.assets.github_repo_url,
                            vercel_url: this.assets.vercel_url,
                            _method: 'PUT'
                        })
                        .then(response => {
                            this.$refs.addAssetsModal.close();
                            
                            this.$emitter.emit('add-flash', { type: 'success', message: 'GitHub assets updated successfully!' });
                        })
                        .catch(error => {
                            console.error('Error updating GitHub assets:', error);
                            
                            let errorMessage = 'Something went wrong!';
                            
                            if (error.response && error.response.data && error.response.data.message) {
                                errorMessage = error.response.data.message;
                            } else if (error.response && error.response.data && error.response.data.errors) {
                                const errors = error.response.data.errors;
                                const firstError = Object.values(errors)[0];
                                if (Array.isArray(firstError) && firstError.length > 0) {
                                    errorMessage = firstError[0];
                                }
                            }
                            
                            this.$emitter.emit('add-flash', { type: 'error', message: errorMessage });
                        })
                        .finally(() => {
                            this.isLoading = false;
                        });
                    }
                }
            });
        </script>
    @endPushOnce    
</x-admin::layouts>