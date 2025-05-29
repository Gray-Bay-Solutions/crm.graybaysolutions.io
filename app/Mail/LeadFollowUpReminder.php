<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Webkul\User\Contracts\User;

class LeadFollowUpReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        public User $salesOwner,
        public Collection $highPriorityLeads,
        public Collection $lowPriorityLeads
    ) {}

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $totalLeads = $this->highPriorityLeads->count() + $this->lowPriorityLeads->count();
        $highCount = $this->highPriorityLeads->count();
        $lowCount = $this->lowPriorityLeads->count();
        
        $subject = "Daily Call List: {$totalLeads} Leads Ready for Follow-up";
        
        if ($highCount > 0) {
            $subject .= " ({$highCount} High Priority)";
        }

        return $this
            ->subject($subject)
            ->view('emails.lead-follow-up-reminder')
            ->with([
                'salesOwner' => $this->salesOwner,
                'highPriorityLeads' => $this->highPriorityLeads,
                'lowPriorityLeads' => $this->lowPriorityLeads,
                'totalLeads' => $totalLeads,
                'highCount' => $highCount,
                'lowCount' => $lowCount,
            ]);
    }
} 