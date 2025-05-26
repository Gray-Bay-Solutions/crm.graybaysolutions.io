<v-bulk-upload>
    <button
        type="button"
        class="secondary-button"
    >
        @lang('Upload Leads')
    </button>
</v-bulk-upload>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="bulk-upload-template"
    >
        <div>
            <button
                type="button"
                class="secondary-button"
                @click="$refs.bulkUploadModal.open()"
            >
                @lang('Upload Leads')
            </button>

            <x-admin::form
                v-slot="{ meta, values, errors, handleSubmit }"
                as="div"
                ref="modalForm"
            >
                <form 
                    @submit="handleSubmit($event, upload)"
                    enctype="multipart/form-data"
                    ref="bulkUploadForm"
                >
                    <x-admin::modal ref="bulkUploadModal">
                        <!-- Modal Header -->
                        <x-slot:header>
                            <p class="text-lg font-bold text-gray-800 dark:text-white">
                                @lang('Bulk Upload Leads')
                            </p>
                        </x-slot>

                        <!-- Modal Content -->
                        <x-slot:content>
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    @lang('Excel/CSV File')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="file"
                                    id="file"
                                    name="file"
                                    rules="required"
                                    :label="trans('Excel/CSV File')"
                                    ::disabled="isLoading"
                                    ref="file"
                                    accept=".xlsx,.xls,.csv"
                                />

                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                    Upload Excel or CSV file with columns: phone, first_name, last_name, company, email, industry_category
                                </p>

                                <x-admin::form.control-group.error control-name="file" />
                            </x-admin::form.control-group>

                            <!-- Organization Address Information -->
                            <div class="mt-4 border-t pt-4">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                    @lang('Default Organization Address (applied to all leads)')
                                </h4>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label>
                                            @lang('Country')
                                        </x-admin::form.control-group.label>

                                        <x-admin::form.control-group.control
                                            type="select"
                                            name="country"
                                            id="country"
                                            v-model="address.country"
                                            ::disabled="isLoading"
                                        >
                                            <option value="">@lang('Select Country')</option>
                                            <option value="US">United States</option>
                                            <option value="CA">Canada</option>
                                            <option value="GB">United Kingdom</option>
                                            <option value="AU">Australia</option>
                                            <option value="DE">Germany</option>
                                            <option value="FR">France</option>
                                            <option value="IN">India</option>
                                        </x-admin::form.control-group.control>
                                    </x-admin::form.control-group>

                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label>
                                            @lang('State/Province')
                                        </x-admin::form.control-group.label>

                                        <x-admin::form.control-group.control
                                            type="text"
                                            name="state"
                                            id="state"
                                            v-model="address.state"
                                            ::disabled="isLoading"
                                            :placeholder="trans('State/Province')"
                                        />
                                    </x-admin::form.control-group>
                                </div>
                            </div>
                        </x-slot>

                        <!-- Modal Footer -->
                        <x-slot:footer>
                            <x-admin::button
                                button-type="submit"
                                class="primary-button justify-center"
                                :title="trans('Upload')"
                                ::loading="isLoading"
                                ::disabled="isLoading"
                            />
                        </x-slot>
                    </x-admin::modal>
                </form>
            </x-admin::form>
        </div>
    </script>

    <script type="module">
        app.component('v-bulk-upload', {
            template: '#bulk-upload-template',

            data() {
                return {
                    isLoading: false,
                    address: {
                        country: '',
                        state: ''
                    }
                };
            },

            methods: {
                upload(params, { resetForm, setErrors }) {
                    const selectedFile = this.$refs.file?.files[0];

                    if (!selectedFile) {
                        this.$emitter.emit('add-flash', { type: 'error', message: "Please select a file" });
                        return;
                    }

                    // Client-side file validation
                    const allowedExtensions = ['xlsx', 'xls', 'csv'];
                    const fileName = selectedFile.name.toLowerCase();
                    const fileExtension = fileName.split('.').pop();
                    
                    if (!allowedExtensions.includes(fileExtension)) {
                        this.$emitter.emit('add-flash', { type: 'error', message: "Only Excel (.xlsx, .xls) and CSV files are allowed." });
                        return;
                    }

                    // Check file size (10MB max)
                    const maxSize = 10 * 1024 * 1024; // 10MB in bytes
                    if (selectedFile.size > maxSize) {
                        this.$emitter.emit('add-flash', { type: 'error', message: "File size must be less than 10MB." });
                        return;
                    }

                    this.isLoading = true;

                    const formData = new FormData();
                    formData.append('file', selectedFile);
                    formData.append('_method', 'post');
                    formData.append('country', this.address.country);
                    formData.append('state', this.address.state);

                    this.sendRequest(formData, resetForm);
                },

                sendRequest(formData, resetForm) {
                    this.$axios.post("{{ route('admin.leads.bulk_upload') }}", formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data',
                        }
                    })
                    .then(response => {
                        this.isLoading = false;
                        
                        if (response.data.success) {
                            // Build success message
                            let message = `Successfully imported ${response.data.total_processed} leads`;
                            
                            if (response.data.created_count > 0) {
                                message = `Successfully created ${response.data.created_count} leads`;
                            }
                            
                            // Add duplicate info only if there are actual duplicates
                            if (response.data.duplicate_count > 0) {
                                message += `. ${response.data.duplicate_count} duplicate(s) were skipped`;
                            }
                            
                            // Add error info if there are errors
                            if (response.data.error_count > 0) {
                                message += `. ${response.data.error_count} row(s) had errors`;
                            }
                            
                            this.$emitter.emit('add-flash', { type: 'success', message: message });
                            
                            // Close modal and reset form
                            this.isModalOpen = false;
                            resetForm();
                            this.resetForm();
                            
                            // Refresh page to show new leads
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            this.$emitter.emit('add-flash', { type: 'error', message: response.data.message });
                        }
                    })
                    .catch(error => {
                        this.isLoading = false;
                        console.error('Upload error:', error);
                        
                        let errorMessage = 'An error occurred during upload.';
                        
                        if (error.response) {
                            // Server responded with error status
                            if (error.response.status === 422 && error.response.data.errors) {
                                // Validation errors
                                const validationErrors = Object.values(error.response.data.errors).flat();
                                errorMessage = validationErrors.join(', ');
                            } else if (error.response.data.message) {
                                errorMessage = error.response.data.message;
                            } else {
                                errorMessage = `Server error (${error.response.status}): ${error.response.statusText}`;
                            }
                        } else if (error.request) {
                            // Network error
                            errorMessage = 'Network error. Please check your connection and try again.';
                        }
                        
                        this.$emitter.emit('add-flash', { type: 'error', message: errorMessage });
                    });
                },

                resetForm() {
                    // Reset file input
                    if (this.$refs.file) {
                        this.$refs.file.value = '';
                    }
                    
                    // Reset address data
                    this.address = {
                        country: '',
                        state: ''
                    };
                    
                    // Reset loading state
                    this.isLoading = false;
                }
            },
        });
    </script>
@endPushOnce 