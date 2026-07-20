<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Api\CronController;
use App\Core\View;
use App\Models\ScheduledJob;
use App\Models\ScheduledJobLog;
use App\Models\SiteConfig;
use App\Services\Validator;

class ScheduledJobController extends BaseAdminController
{
    public function index(array|int $params = []): void
    {
        $jobs = ScheduledJob::getAll(50);
        $logs = ScheduledJobLog::getRecentLogs(30);
        $counts = ScheduledJob::getCountsByStatus();
        $lastExecTimestamp = (int)(SiteConfig::get('last_cron_execution', '0'));
        $lastExecutionDate = $lastExecTimestamp > 0 ? date('d/m/Y à H:i:s', $lastExecTimestamp) : 'Jamais';

        View::render('admin/scheduled_jobs/index.twig', [
            'jobs' => $jobs,
            'logs' => $logs,
            'counts' => $counts,
            'last_execution' => $lastExecutionDate,
            'actions_map' => ScheduledJob::ACTIONS_MAP,
            'frequencies_map' => ScheduledJob::FREQUENCIES_MAP,
            'default_execute_at' => date('Y-m-d\TH:i', strtotime('+5 minutes'))
        ]);
    }

    public function store(array|int $params = []): void
    {
        $this->requirePost('/admin/scheduled-jobs');

        $action = trim($_POST['action'] ?? '');
        $frequency = trim($_POST['frequency'] ?? 'once');
        $executeAt = trim($_POST['execute_at'] ?? '');
        $endAt = trim($_POST['end_at'] ?? '');
        $payloadRaw = trim($_POST['payload'] ?? '');

        $v = Validator::make([
            'action' => $action,
            'execute_at' => $executeAt,
        ])
        ->required('action', 'Veuillez sélectionner un type d\'action.')
        ->required('execute_at', 'La date et l\'heure de début d\'exécution sont obligatoires.');

        if ($v->fails()) {
            View::flash('error', $v->firstError());
            $this->redirect('/admin/scheduled-jobs');
        }

        $payload = null;
        if (!empty($payloadRaw)) {
            $decoded = json_decode($payloadRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                View::flash('error', 'Le format JSON des paramètres est invalide.');
                $this->redirect('/admin/scheduled-jobs');
            }
            $payload = $decoded;
        }

        $executeAtFormatted = date('Y-m-d H:i:s', strtotime($executeAt));
        $endAtFormatted = !empty($endAt) ? date('Y-m-d H:i:s', strtotime($endAt)) : null;

        ScheduledJob::create($action, $payload, $executeAtFormatted, $frequency, $endAtFormatted);

        View::flash('success', 'Nouvelle tâche planifiée enregistrée avec succès.');
        $this->redirect('/admin/scheduled-jobs');
    }

    public function forceRun(array|int $params = []): void
    {
        $this->requirePost('/admin/scheduled-jobs');

        $cronApi = new CronController();
        $executedCount = $cronApi->runPendingJobs(10);

        SiteConfig::set('last_cron_execution', (string)time());

        View::flash('success', "Exécution manuelle terminée. Tâche(s) traitée(s) : {$executedCount}.");
        $this->redirect('/admin/scheduled-jobs');
    }

    public function delete(array|int $params): void
    {
        $this->requirePost('/admin/scheduled-jobs');

        $id = is_array($params) ? (int)($params['id'] ?? 0) : (int)$params;

        $job = ScheduledJob::find($id);
        if ($job) {
            ScheduledJob::delete($id);
            View::flash('success', 'Tâche planifiée supprimée.');
        } else {
            View::flash('error', 'Tâche introuvable.');
        }

        $this->redirect('/admin/scheduled-jobs');
    }
}
