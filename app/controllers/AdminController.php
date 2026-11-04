<?php
/**
 * Admin panel. Every route using this controller has the 'admin' middleware,
 * so only logged-in admins reach these methods.
 */

declare(strict_types=1);

final class AdminController extends Controller
{
    /** GET /admin - overview with stats, charts and recent activity */
    public function index(): void
    {
        $this->view('admin/index', [
            'title'      => 'Admin overview',
            'totals'     => AdminStats::totals(),
            'perDay'     => AdminStats::itemsPerDay(14),
            'byCategory' => AdminStats::itemsByCategory(),
            'byStatus'   => AdminStats::itemsByStatus(),
            'activity'   => AdminStats::recentActivity(8),
        ]);
    }
}
