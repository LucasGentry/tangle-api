<?php

namespace App\Console\Commands;

use App\Models\CollaborationRequest;
use Illuminate\Console\Command;

class CheckCollaborationDeadlines extends Command
{
    protected $signature = 'collaborations:check-deadlines';
    protected $description = 'Check and auto-close expired collaboration requests';

    public function handle()
    {
        $this->info('Checking collaboration deadlines...');

        $count = 0;
        CollaborationRequest::where('status', CollaborationRequest::STATUS_OPEN)
            ->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->chunk(100, function ($collaborations) use (&$count) {
                foreach ($collaborations as $collaboration) {
                    if ($collaboration->checkAndAutoClose()) {
                        $count++;
                    }
                }
            });

        $this->info("Auto-closed {$count} expired collaboration requests.");
    }
} 