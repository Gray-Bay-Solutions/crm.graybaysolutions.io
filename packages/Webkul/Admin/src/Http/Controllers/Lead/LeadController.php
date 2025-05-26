<?php

namespace Webkul\Admin\Http\Controllers\Lead;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Prettus\Repository\Criteria\RequestCriteria;
use Webkul\Admin\DataGrids\Lead\LeadDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\LeadForm;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\Admin\Http\Resources\LeadResource;
use Webkul\Admin\Http\Resources\StageResource;
use Webkul\Admin\Imports\LeadsImport;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Lead\Helpers\MagicAI;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\ProductRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Repositories\StageRepository;
use Webkul\Lead\Repositories\TypeRepository;
use Webkul\Lead\Services\MagicAIService;
use Webkul\Tag\Repositories\TagRepository;
use Webkul\User\Repositories\UserRepository;

class LeadController extends Controller
{
    /**
     * Const variable for supported types.
     */
    const SUPPORTED_TYPES = 'pdf,bmp,jpeg,jpg,png,webp';

    /**
     * Property to store skipped duplicates during bulk upload.
     */
    protected $skippedDuplicates = [];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected UserRepository $userRepository,
        protected AttributeRepository $attributeRepository,
        protected SourceRepository $sourceRepository,
        protected TypeRepository $typeRepository,
        protected PipelineRepository $pipelineRepository,
        protected StageRepository $stageRepository,
        protected LeadRepository $leadRepository,
        protected ProductRepository $productRepository,
        protected PersonRepository $personRepository,
        protected OrganizationRepository $organizationRepository,
        protected TagRepository $tagRepository
    ) {
        request()->request->add(['entity_type' => 'leads']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(LeadDataGrid::class)->process();
        }

        if (request('pipeline_id')) {
            $pipeline = $this->pipelineRepository->find(request('pipeline_id'));
        } else {
            $pipeline = $this->pipelineRepository->getDefaultPipeline();
        }

        return view('admin::leads.index', [
            'pipeline' => $pipeline,
            'columns'  => $this->getKanbanColumns(),
        ]);
    }

    /**
     * Returns a listing of the resource.
     */
    public function get(): JsonResponse
    {
        if (request()->query('pipeline_id')) {
            $pipeline = $this->pipelineRepository->find(request()->query('pipeline_id'));
        } else {
            $pipeline = $this->pipelineRepository->getDefaultPipeline();
        }

        if ($stageId = request()->query('pipeline_stage_id')) {
            $stages = $pipeline->stages->where('id', request()->query('pipeline_stage_id'));
        } else {
            $stages = $pipeline->stages;
        }

        foreach ($stages as $stage) {
            /**
             * We have to create a new instance of the lead repository every time, which is
             * why we're not using the injected one.
             */
            $query = app(LeadRepository::class)
                ->pushCriteria(app(RequestCriteria::class))
                ->where([
                    'lead_pipeline_id'       => $pipeline->id,
                    'lead_pipeline_stage_id' => $stage->id,
                ]);

            if ($userIds = bouncer()->getAuthorizedUserIds()) {
                $query->whereIn('leads.user_id', $userIds);
            }

            $stage->lead_value = (clone $query)->sum('lead_value');

            $data[$stage->sort_order] = (new StageResource($stage))->jsonSerialize();

            $data[$stage->sort_order]['leads'] = [
                'data' => LeadResource::collection($paginator = $query->with([
                    'tags',
                    'type',
                    'source',
                    'user',
                    'person',
                    'person.organization',
                    'pipeline',
                    'pipeline.stages',
                    'stage',
                    'attribute_values',
                ])->paginate(10)),

                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'from'         => $paginator->firstItem(),
                    'last_page'    => $paginator->lastPage(),
                    'per_page'     => $paginator->perPage(),
                    'to'           => $paginator->lastItem(),
                    'total'        => $paginator->total(),
                ],
            ];
        }

        return response()->json($data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin::leads.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LeadForm $request): RedirectResponse
    {
        Event::dispatch('lead.create.before');

        $data = $request->all();

        $data['status'] = 1;

        if (isset($data['lead_pipeline_stage_id'])) {
            $stage = $this->stageRepository->findOrFail($data['lead_pipeline_stage_id']);

            $data['lead_pipeline_id'] = $stage->lead_pipeline_id;
        } else {
            $pipeline = $this->pipelineRepository->getDefaultPipeline();

            $stage = $pipeline->stages()->first();

            $data['lead_pipeline_id'] = $pipeline->id;

            $data['lead_pipeline_stage_id'] = $stage->id;
        }

        if (in_array($stage->code, ['won', 'lost'])) {
            $data['closed_at'] = Carbon::now();
        }

        $lead = $this->leadRepository->create($data);

        Event::dispatch('lead.create.after', $lead);

        session()->flash('success', trans('admin::app.leads.create-success'));

        return redirect()->route('admin.leads.index', $data['lead_pipeline_id']);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $lead = $this->leadRepository->findOrFail($id);

        return view('admin::leads.edit', compact('lead'));
    }

    /**
     * Display a resource.
     */
    public function view(int $id)
    {
        $lead = $this->leadRepository->findOrFail($id);

        $userIds = bouncer()->getAuthorizedUserIds();

        if (
            $userIds
            && ! in_array($lead->user_id, $userIds)
        ) {
            return redirect()->route('admin.leads.index');
        }

        return view('admin::leads.view', compact('lead'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LeadForm $request, int $id): RedirectResponse|JsonResponse
    {
        Event::dispatch('lead.update.before', $id);

        $data = $request->all();

        if (isset($data['lead_pipeline_stage_id'])) {
            $stage = $this->stageRepository->findOrFail($data['lead_pipeline_stage_id']);

            $data['lead_pipeline_id'] = $stage->lead_pipeline_id;
        } else {
            $pipeline = $this->pipelineRepository->getDefaultPipeline();

            $stage = $pipeline->stages()->first();

            $data['lead_pipeline_id'] = $pipeline->id;

            $data['lead_pipeline_stage_id'] = $stage->id;
        }

        $lead = $this->leadRepository->update($data, $id);

        Event::dispatch('lead.update.after', $lead);

        if (request()->ajax()) {
            return response()->json([
                'message' => trans('admin::app.leads.update-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.leads.update-success'));

        if (request()->has('closed_at')) {
            return redirect()->back();
        } else {
            return redirect()->route('admin.leads.index', $data['lead_pipeline_id']);
        }
    }

    /**
     * Update the lead attributes.
     */
    public function updateAttributes(int $id)
    {
        $data = request()->all();

        $attributes = $this->attributeRepository->findWhere([
            'entity_type' => 'leads',
            ['code', 'NOTIN', ['title', 'description']],
        ]);

        Event::dispatch('lead.update.before', $id);

        $lead = $this->leadRepository->update($data, $id, $attributes);

        Event::dispatch('lead.update.after', $lead);

        return response()->json([
            'message' => trans('admin::app.leads.update-success'),
        ]);
    }

    /**
     * Update the lead stage.
     */
    public function updateStage(int $id)
    {
        $this->validate(request(), [
            'lead_pipeline_stage_id' => 'required',
        ]);

        $lead = $this->leadRepository->findOrFail($id);

        $stage = $lead->pipeline->stages()
            ->where('id', request()->input('lead_pipeline_stage_id'))
            ->firstOrFail();

        Event::dispatch('lead.update.before', $id);

        $lead = $this->leadRepository->update(
            [
                'entity_type'            => 'leads',
                'lead_pipeline_stage_id' => $stage->id,
            ],
            $id,
            ['lead_pipeline_stage_id']
        );

        Event::dispatch('lead.update.after', $lead);

        return response()->json([
            'message' => trans('admin::app.leads.update-success'),
        ]);
    }

    /**
     * Search person results.
     */
    public function search(): AnonymousResourceCollection
    {
        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $results = $this->leadRepository
                ->pushCriteria(app(RequestCriteria::class))
                ->findWhereIn('user_id', $userIds);
        } else {
            $results = $this->leadRepository
                ->pushCriteria(app(RequestCriteria::class))
                ->all();
        }

        return LeadResource::collection($results);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->leadRepository->findOrFail($id);

        try {
            Event::dispatch('lead.delete.before', $id);

            $this->leadRepository->delete($id);

            Event::dispatch('lead.delete.after', $id);

            return response()->json([
                'message' => trans('admin::app.leads.destroy-success'),
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.leads.destroy-failed'),
            ], 400);
        }
    }

    /**
     * Mass update the specified resources.
     */
    public function massUpdate(MassUpdateRequest $massUpdateRequest): JsonResponse
    {
        $leads = $this->leadRepository->findWhereIn('id', $massUpdateRequest->input('indices'));

        try {
            foreach ($leads as $lead) {
                Event::dispatch('lead.update.before', $lead->id);

                $lead = $this->leadRepository->find($lead->id);

                $lead?->update(['lead_pipeline_stage_id' => $massUpdateRequest->input('value')]);

                Event::dispatch('lead.update.before', $lead->id);
            }

            return response()->json([
                'message' => trans('admin::app.leads.update-success'),
            ]);
        } catch (\Exception $th) {
            return response()->json([
                'message' => trans('admin::app.leads.update-failed'),
            ], 400);
        }
    }

    /**
     * Mass delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $leads = $this->leadRepository->findWhereIn('id', $massDestroyRequest->input('indices'));

        try {
            foreach ($leads as $lead) {
                Event::dispatch('lead.delete.before', $lead->id);

                $this->leadRepository->delete($lead->id);

                Event::dispatch('lead.delete.after', $lead->id);
            }

            return response()->json([
                'message' => trans('admin::app.leads.destroy-success'),
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.leads.destroy-failed'),
            ]);
        }
    }

    /**
     * Attach product to lead.
     */
    public function addProduct(int $leadId): JsonResponse
    {
        $product = $this->productRepository->updateOrCreate(
            [
                'lead_id'    => $leadId,
                'product_id' => request()->input('product_id'),
            ],
            array_merge(
                request()->all(),
                [
                    'lead_id' => $leadId,
                    'amount'  => request()->input('price') * request()->input('quantity'),
                ],
            )
        );

        return response()->json([
            'data'    => $product,
            'message' => trans('admin::app.leads.update-success'),
        ]);
    }

    /**
     * Remove product attached to lead.
     */
    public function removeProduct(int $id): JsonResponse
    {
        try {
            Event::dispatch('lead.product.delete.before', $id);

            $this->productRepository->deleteWhere([
                'lead_id'    => $id,
                'product_id' => request()->input('product_id'),
            ]);

            Event::dispatch('lead.product.delete.after', $id);

            return response()->json([
                'message' => trans('admin::app.leads.destroy-success'),
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.leads.destroy-failed'),
            ]);
        }
    }

    /**
     * Kanban lookup.
     */
    public function kanbanLookup()
    {
        $params = $this->validate(request(), [
            'column'      => ['required'],
            'search'      => ['required', 'min:2'],
        ]);

        /**
         * Finding the first column from the collection.
         */
        $column = collect($this->getKanbanColumns())->where('index', $params['column'])->firstOrFail();

        /**
         * Fetching on the basis of column options.
         */
        return app($column['filterable_options']['repository'])
            ->select([$column['filterable_options']['column']['label'].' as label', $column['filterable_options']['column']['value'].' as value'])
            ->where($column['filterable_options']['column']['label'], 'LIKE', '%'.$params['search'].'%')
            ->get()
            ->map
            ->only('label', 'value');
    }

    /**
     * Get columns for the kanban view.
     */
    private function getKanbanColumns(): array
    {
        return [
            [
                'index'                 => 'id',
                'label'                 => trans('admin::app.leads.index.kanban.columns.id'),
                'type'                  => 'integer',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_type'       => null,
                'filterable_options'    => [],
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
            ],
            [
                'index'                 => 'lead_value',
                'label'                 => trans('admin::app.leads.index.kanban.columns.lead-value'),
                'type'                  => 'string',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_type'       => null,
                'filterable_options'    => [],
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
            ],
            [
                'index'                 => 'user_id',
                'label'                 => trans('admin::app.leads.index.kanban.columns.sales-person'),
                'type'                  => 'string',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_type'       => 'searchable_dropdown',
                'filterable_options'    => [
                    'repository' => UserRepository::class,
                    'column'     => [
                        'label' => 'name',
                        'value' => 'id',
                    ],
                ],
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
            ],
            [
                'index'                 => 'person.id',
                'label'                 => trans('admin::app.leads.index.kanban.columns.contact-person'),
                'type'                  => 'string',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_options'    => [],
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
                'filterable_type'       => 'searchable_dropdown',
                'filterable_options'    => [
                    'repository' => PersonRepository::class,
                    'column'     => [
                        'label' => 'name',
                        'value' => 'id',
                    ],
                ],
            ],
            [
                'index'                 => 'lead_type_id',
                'label'                 => trans('admin::app.leads.index.kanban.columns.lead-type'),
                'type'                  => 'string',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_type'       => 'dropdown',
                'filterable_options'    => $this->typeRepository->all(['name as label', 'id as value'])->toArray(),
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
            ],
            [
                'index'                 => 'lead_source_id',
                'label'                 => trans('admin::app.leads.index.kanban.columns.source'),
                'type'                  => 'string',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_type'       => 'dropdown',
                'filterable_options'    => $this->sourceRepository->all(['name as label', 'id as value'])->toArray(),
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
            ],
            [
                'index'                 => 'tags.name',
                'label'                 => trans('admin::app.leads.index.kanban.columns.tags'),
                'type'                  => 'string',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_options'    => [],
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
                'filterable_type'       => 'searchable_dropdown',
                'filterable_options'    => [
                    'repository' => TagRepository::class,
                    'column'     => [
                        'label' => 'name',
                        'value' => 'name',
                    ],
                ],
            ],
        ];
    }

    /**
     * Create lead with specified AI.
     */
    public function createByAI()
    {
        $leadData = [];

        $errorMessages = [];

        foreach (request()->file('files') as $file) {
            $lead = $this->processFile($file);

            if (
                isset($lead['status'])
                && $lead['status'] === 'error'
            ) {
                $errorMessages[] = $lead['message'];
            } else {
                $leadData[] = $lead;
            }
        }

        if (isset($errorMessages[0]['code'])) {
            return response()->json(MagicAI::errorHandler($errorMessages[0]['message']));
        }

        if (
            empty($leadData)
            && ! empty($errorMessages)
        ) {
            return response()->json(MagicAI::errorHandler(implode(', ', $errorMessages)), 400);
        }

        if (empty($leadData)) {
            return response()->json(MagicAI::errorHandler(trans('admin::app.leads.no-valid-files')), 400);
        }

        return response()->json([
            'message' => trans('admin::app.leads.create-success'),
            'leads'   => $this->createLeads($leadData),
        ]);
    }

    /**
     * Bulk upload leads from Excel/CSV file.
     */
    public function bulkUpload()
    {
        // Ensure entity_type is set globally for this request
        request()->request->add(['entity_type' => 'leads']);
        
        $this->validate(request(), [
            'file' => 'required|file|mimes:xlsx,csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv,text/plain|max:10240',
        ]);

        try {
            $file = request()->file('file');
            
            // Additional file extension check
            $allowedExtensions = ['xlsx', 'xls', 'csv'];
            $fileExtension = strtolower($file->getClientOriginalExtension());
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Excel (.xlsx, .xls) and CSV files are allowed.',
                ], 400);
            }
            
            $leads = $this->processExcelFile($file);
            
            if (empty($leads)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid lead data found in the file. Please check that your file has the required columns: phone, first_name, last_name, company, email, industry_category',
                ], 400);
            }
            
            // Get address data from request
            $addressData = [
                'country' => request()->input('country', ''),
                'state' => request()->input('state', ''),
            ];
            
            $result = $this->createBulkLeads($leads, $addressData);

            $message = trans('admin::app.leads.create-success') . ' (' . $result['created_count'] . ' leads imported)';
            
            if ($result['duplicate_count'] > 0) {
                $message .= '. ' . $result['duplicate_count'] . ' duplicate(s) skipped.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'leads'   => $result['leads'],
                'duplicate_count' => $result['duplicate_count'],
                'error_count' => $result['error_count'],
                'total_processed' => $result['total_processed'],
                'duplicate_details' => $result['duplicate_details'],
                'error_details' => $result['error_details']
            ]);
        } catch (\Exception $e) {
            \Log::error('Bulk upload error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Process file.
     *
     * @param  mixed  $file
     */
    private function processFile($file)
    {
        $validator = Validator::make(
            ['file' => $file],
            ['file' => 'required|extensions:'.str_replace(' ', '', self::SUPPORTED_TYPES)]
        );

        if ($validator->fails()) {
            return MagicAI::errorHandler($validator->errors()->first());
        }

        $base64Pdf = base64_encode(file_get_contents($file->getRealPath()));

        $extractedData = MagicAIService::extractDataFromFile($base64Pdf);

        $lead = MagicAI::mapAIDataToLead($extractedData);

        return $lead;
    }

    /**
     * Create multiple leads.
     */
    private function createLeads($rawLeads): array
    {
        $leads = [];

        foreach ($rawLeads as $rawLead) {
            Event::dispatch('lead.create.before');

            foreach ($rawLead['person']['emails'] as $email) {
                $person = $this->personRepository
                    ->whereJsonContains('emails', [['value' => $email['value']]])
                    ->first();

                if ($person) {
                    $rawLead['person']['id'] = $person->id;

                    break;
                }
            }

            $pipeline = $this->pipelineRepository->getDefaultPipeline();

            $stage = $pipeline->stages()->first();

            $lead = $this->leadRepository->create(array_merge($rawLead, [
                'lead_pipeline_id'       => $pipeline->id,
                'lead_pipeline_stage_id' => $stage->id,
            ]));

            Event::dispatch('lead.create.after', $lead);

            $leads[] = $lead;
        }

        return $leads;
    }

    /**
     * Process Excel/CSV file and extract lead data.
     */
    private function processExcelFile($file): array
    {
        try {
            $import = new LeadsImport();
            Excel::import($import, $file);
            
            return $import->getLeads();
        } catch (\Exception $e) {
            throw new \Exception('Error processing file: ' . $e->getMessage());
        }
    }

    /**
     * Create multiple leads from bulk upload.
     */
    private function createBulkLeads($rawLeads, $addressData): array
    {
        $leads = [];
        $skippedDuplicates = [];
        $skippedErrors = [];
        
        // Get required default data with safety checks
        $defaultSource = $this->sourceRepository->first();
        $defaultType = $this->typeRepository->first();
        $pipeline = $this->pipelineRepository->getDefaultPipeline();
        $stage = $pipeline ? $pipeline->stages()->first() : null;

        // Safety checks for required data
        if (!$defaultSource) {
            throw new \Exception('No lead source found. Please create at least one lead source.');
        }
        if (!$defaultType) {
            throw new \Exception('No lead type found. Please create at least one lead type.');
        }
        if (!$pipeline) {
            throw new \Exception('No pipeline found. Please create at least one pipeline.');
        }
        if (!$stage) {
            throw new \Exception('No pipeline stage found. Please create at least one stage in the pipeline.');
        }

        foreach ($rawLeads as $index => $rawLead) {
            try {
                // Validate required data
                if (empty($rawLead['person']['name']) && empty($rawLead['title'])) {
                    $skippedErrors[] = [
                        'row' => $index + 1,
                        'name' => 'Unknown',
                        'reason' => 'Missing required name/title data'
                    ];
                    continue;
                }

                // Check for duplicates based on email and phone
                $isDuplicate = false;
                $duplicateReason = '';
                $existingPerson = null;
                
                // Check email duplicate first
                if (!empty($rawLead['person']['emails'][0]['value'])) {
                    $existingPerson = $this->personRepository
                        ->whereJsonContains('emails', [['value' => $rawLead['person']['emails'][0]['value']]])
                        ->first();
                    
                    if ($existingPerson) {
                        $isDuplicate = true;
                        $duplicateReason = 'email: ' . $rawLead['person']['emails'][0]['value'];
                    }
                }
                
                // Check phone duplicate if no email duplicate found
                if (!$isDuplicate && !empty($rawLead['person']['contact_numbers'][0]['value'])) {
                    $existingPerson = $this->personRepository
                        ->whereJsonContains('contact_numbers', [['value' => $rawLead['person']['contact_numbers'][0]['value']]])
                        ->first();
                    
                    if ($existingPerson) {
                        $isDuplicate = true;
                        $duplicateReason = 'phone: ' . $rawLead['person']['contact_numbers'][0]['value'];
                    }
                }
                
                // Skip if duplicate found
                if ($isDuplicate) {
                    $skippedDuplicates[] = [
                        'row' => $index + 1,
                        'name' => $rawLead['person']['name'],
                        'reason' => $duplicateReason
                    ];
                    continue;
                }

                // Create or find organization first
                $organizationId = null;
                if (!empty($rawLead['person']['organization']['name'])) {
                    $organizationId = $this->createOrFindOrganization(
                        $rawLead['person']['organization']['name'], 
                        $addressData
                    );
                }

                // Prepare lead data with proper entity_type and structure
                $leadData = [
                    'title' => $rawLead['title'],
                    'description' => $rawLead['description'],
                    'lead_value' => $rawLead['lead_value'],
                    'user_id' => null, // No sales owner assigned
                    'entity_type' => 'leads',
                    'lead_source_id' => $defaultSource->id,
                    'lead_type_id' => $defaultType->id,
                    'lead_pipeline_id' => $pipeline->id,
                    'lead_pipeline_stage_id' => $stage->id,
                    'person' => [
                        'name' => $rawLead['person']['name'],
                        'emails' => $rawLead['person']['emails'],
                        'contact_numbers' => $rawLead['person']['contact_numbers'],
                        'organization_id' => $organizationId, // Set organization_id directly
                        'organization' => $organizationId ? ['id' => $organizationId] : null,
                    ]
                ];

                // Set entity_type in request for this specific creation
                request()->merge(['entity_type' => 'leads']);

                Event::dispatch('lead.create.before');

                // Create the lead
                $lead = $this->leadRepository->create($leadData);

                Event::dispatch('lead.create.after', $lead);

                // Attach industry category as tag if available
                if (!empty($rawLead['industry_category'])) {
                    $tagId = $this->createOrFindTag($rawLead['industry_category']);
                    
                    if ($tagId && !$lead->tags->contains($tagId)) {
                        $lead->tags()->attach($tagId);
                        
                        \Log::info('Tag attached to lead', [
                            'lead_id' => $lead->id,
                            'tag_id' => $tagId,
                            'tag_name' => $rawLead['industry_category']
                        ]);
                    }
                }

                // Ensure person-organization relationship is established
                if ($organizationId && $lead->person) {
                    // Update the person to ensure organization_id is set
                    $this->personRepository->update([
                        'organization_id' => $organizationId
                    ], $lead->person->id);
                    
                    \Log::info('Person linked to organization', [
                        'person_id' => $lead->person->id,
                        'organization_id' => $organizationId
                    ]);
                }

                $leads[] = $lead;

                \Log::info('Lead created successfully', [
                    'lead_id' => $lead->id,
                    'person_name' => $rawLead['person']['name'],
                    'organization_id' => $organizationId
                ]);

            } catch (\Exception $e) {
                \Log::error('Failed to create lead at row ' . ($index + 1), [
                    'error' => $e->getMessage(),
                    'lead_data' => $rawLead,
                    'trace' => $e->getTraceAsString()
                ]);
                
                $skippedErrors[] = [
                    'row' => $index + 1,
                    'name' => $rawLead['person']['name'] ?? 'Unknown',
                    'reason' => 'creation error: ' . $e->getMessage()
                ];
                continue;
            }
        }

        return [
            'leads' => $leads,
            'created_count' => count($leads),
            'duplicate_count' => count($skippedDuplicates),
            'error_count' => count($skippedErrors),
            'total_processed' => count($rawLeads),
            'duplicate_details' => $skippedDuplicates,
            'error_details' => $skippedErrors
        ];
    }

    /**
     * Create or find organization and return its ID
     */
    private function createOrFindOrganization($organizationName, $addressData): ?int
    {
        try {
            // Check if organization already exists
            $organization = $this->organizationRepository->where('name', $organizationName)->first();
            
            if ($organization) {
                \Log::info('Using existing organization', ['id' => $organization->id, 'name' => $organizationName]);
                return $organization->id;
            }

            // Create new organization with address data
            $organizationData = [
                'name' => $organizationName,
                'entity_type' => 'organizations'
            ];

            // Add address data if provided
            if (!empty($addressData['country']) || !empty($addressData['state'])) {
                $address = [];
                if (!empty($addressData['country'])) $address['country'] = $addressData['country'];
                if (!empty($addressData['state'])) $address['state'] = $addressData['state'];
                $organizationData['address'] = $address;
            }

            // Temporarily set entity_type for organization creation
            $originalEntityType = request()->get('entity_type');
            request()->merge(['entity_type' => 'organizations']);

            $organization = $this->organizationRepository->create($organizationData);

            // Restore original entity_type
            request()->merge(['entity_type' => $originalEntityType]);

            \Log::info('Created new organization', [
                'id' => $organization->id, 
                'name' => $organizationName,
                'address' => $organizationData['address'] ?? null
            ]);
            
            return $organization->id;
        } catch (\Exception $e) {
            \Log::error('Failed to create/find organization', [
                'name' => $organizationName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Create or find tag by name and return its ID
     */
    private function createOrFindTag($tagName): ?int
    {
        try {
            // Skip if tag name is empty
            if (empty($tagName)) {
                return null;
            }

            // Check if tag already exists
            $tag = $this->tagRepository->where('name', $tagName)->first();
            
            if ($tag) {
                \Log::info('Using existing tag', ['id' => $tag->id, 'name' => $tagName]);
                return $tag->id;
            }

            // Create new tag with random color
            $colors = [
                '#DC2626', // aquarelle-red
                '#EA580C', // crushed-cashew
                '#D97706', // beeswax
                '#CA8A04', // lemon-chiffon
                '#65A30D', // snow-flurry
                '#16A34A', // honeydew
            ];

            $tag = $this->tagRepository->create([
                'name' => $tagName,
                'color' => $colors[array_rand($colors)],
                'user_id' => auth()->guard('user')->user()->id ?? 1, // Fallback to user ID 1 if no authenticated user
            ]);

            \Log::info('Created new tag', [
                'id' => $tag->id, 
                'name' => $tagName,
                'color' => $tag->color
            ]);
            
            return $tag->id;
        } catch (\Exception $e) {
            \Log::error('Failed to create/find tag', [
                'name' => $tagName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
