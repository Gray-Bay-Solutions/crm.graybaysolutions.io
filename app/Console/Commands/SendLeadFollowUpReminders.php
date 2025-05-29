<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\StageRepository;
use App\Mail\LeadFollowUpReminder;

class SendLeadFollowUpReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:send-follow-up-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send follow-up reminder emails to sales owners for leads that need attention';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(
        protected LeadRepository $leadRepository,
        protected StageRepository $stageRepository
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting lead follow-up reminder processing...');

        try {
            // Get the stage that represents "contact" status
            $contactStage = $this->stageRepository->findOneByField('code', 'contact');
            
            if (!$contactStage) {
                $this->error('❌ Contact stage not found. Please ensure you have a stage with code "new" or update the command.');
                return;
            }

            // Calculate date ranges for business days
            $today = Carbon::now();
            $twoDaysAgo = $this->getBusinessDaysAgo(2);

            $this->info("📅 Checking leads older than {$twoDaysAgo->format('Y-m-d')}");

            // Get leads in contact stage that were updated more than 2 business days ago
            $leads = $this->leadRepository
                ->with(['user', 'person', 'stage'])
                ->where('lead_pipeline_stage_id', $contactStage->id)
                ->where('updated_at', '<', $twoDaysAgo->endOfDay())
                ->get();

            $this->info("📊 Found {$leads->count()} leads requiring follow-up");

            // Group leads by sales owner
            $leadsByOwner = $leads->groupBy('user_id');
            
            $emailsSent = 0;
            $errors = 0;
            $totalSalesOwners = $leadsByOwner->count();

            $this->info("👥 Processing {$totalSalesOwners} sales owners");

            foreach ($leadsByOwner as $userId => $ownerLeads) {
                try {
                    $salesOwner = $ownerLeads->first()->user;
                    
                    if (!$salesOwner || !$salesOwner->email) {
                        $this->warn("⚠️  Sales owner #{$userId} has no email address");
                        continue;
                    }

                    // Categorize leads by priority
                    $highPriorityLeads = collect();
                    $lowPriorityLeads = collect();

                    foreach ($ownerLeads as $lead) {
                        $businessDaysSinceUpdate = $this->calculateBusinessDays($lead->updated_at, $today);
                        
                        // Add business days info to the lead object for template use
                        $lead->businessDaysSinceUpdate = $businessDaysSinceUpdate;
                        
                        if ($businessDaysSinceUpdate >= 5) {
                            $highPriorityLeads->push($lead);
                        } else {
                            $lowPriorityLeads->push($lead);
                        }
                    }

                    $totalLeads = $highPriorityLeads->count() + $lowPriorityLeads->count();
                    
                    $this->info("📧 Sending reminder to {$salesOwner->email} ({$totalLeads} leads: {$highPriorityLeads->count()} high priority, {$lowPriorityLeads->count()} low priority)");

                    // Comment out actual email sending for testing
                    Mail::to($salesOwner->email)->send(new LeadFollowUpReminder(
                        $salesOwner,
                        $highPriorityLeads,
                        $lowPriorityLeads
                    ));

                    $emailsSent++;
                    
                } catch (\Exception $e) {
                    $this->error("❌ Failed to send email to sales owner #{$userId}: " . $e->getMessage());
                    $errors++;
                }
            }

            $this->info("✅ Lead follow-up reminder processing completed!");
            $this->info("📈 Summary: {$emailsSent} emails sent to sales owners, {$errors} errors");

        } catch (\Exception $e) {
            $this->error('❌ An error occurred during lead follow-up processing: ' . $e->getMessage());
        }
    }

    /**
     * Get business days ago from today
     */
    private function getBusinessDaysAgo(int $days): Carbon
    {
        $date = Carbon::now();
        $businessDays = 0;
        
        while ($businessDays < $days) {
            $date->subDay();
            if ($date->isWeekday()) {
                $businessDays++;
            }
        }
        
        return $date->startOfDay();
    }

    /**
     * Calculate business days between two dates
     */
    private function calculateBusinessDays(Carbon $startDate, Carbon $endDate): int
    {
        $businessDays = 0;
        $current = $startDate->copy();
        
        while ($current->lt($endDate)) {
            if ($current->isWeekday()) {
                $businessDays++;
            }
            $current->addDay();
        }
        
        return $businessDays;
    }
} 