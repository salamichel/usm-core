<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Models\EmailLog;

class EmailLogController extends BaseAdminController
{
    public function index(array $params): void
    {
        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) {
            $page = 1;
        }
        
        $limit = 20;
        
        $filters = [
            'status'     => $_GET['status'] ?? null,
            'email_type' => $_GET['email_type'] ?? null,
            'search'     => $_GET['search'] ?? null,
        ];
        
        // Clean empty values from filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        $logs = EmailLog::getPaginated($page, $limit, $filters);
        $total = EmailLog::count($filters);
        $totalPages = (int)ceil($total / $limit);
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        
        $stats = EmailLog::getStats();
        $emailTypes = EmailLog::getDistinctEmailTypes();

        View::render('admin/email_logs/index.twig', [
            'logs'         => $logs,
            'stats'        => $stats,
            'email_types'  => $emailTypes,
            'filters'      => $filters,
            'page'         => $page,
            'total_pages'  => $totalPages,
            'total_items'  => $total,
            'types_map'    => EmailLog::TYPES_MAP
        ]);
    }
}
