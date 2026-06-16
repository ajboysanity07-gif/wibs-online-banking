<?php

namespace App\Console\Commands;

use App\Services\LoanRequests\LoanWorkflowProductionSupportService;
use Illuminate\Console\Command;

class LoanWorkflowSendRemindersCommand extends Command
{
    protected $signature = 'loan-workflow:send-reminders';

    protected $description = 'Queue due member reminder notifications for the loan workflow.';

    public function handle(
        LoanWorkflowProductionSupportService $supportService,
    ): int {
        $result = $supportService->sendDueReminders();

        $this->info(sprintf(
            'Reminder scan complete. Requested: %d. Queued: %d.',
            (int) ($result['requested'] ?? 0),
            (int) ($result['queued'] ?? 0),
        ));

        return self::SUCCESS;
    }
}
