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

    // ------------------------------------------------------------------
    // Reports
    // ------------------------------------------------------------------

    /** GET /admin/reports?status=open|resolved|dismissed|all */
    public function reports(): void
    {
        $status = $this->query('status');
        $status = in_array($status, ['open', 'resolved', 'dismissed', 'all'], true) ? $status : 'open';
        $this->view('admin/reports', [
            'title'   => 'Review reports',
            'status'  => $status,
            'reports' => Report::forAdmin($status === 'all' ? '' : $status),
        ]);
    }

    /** GET /admin/reports/{id} - investigate one report */
    public function showReport(string $id): void
    {
        $report = $this->findReportOr404($id);
        $this->view('admin/report', ['title' => 'Report #' . $report['id'], 'report' => $report]);
    }

    /** POST /admin/reports/{id}/status  (status = resolved | dismissed | open) */
    public function updateReportStatus(string $id): void
    {
        $report = $this->findReportOr404($id);
        $status = $this->input('status');
        if (!in_array($status, ['resolved', 'dismissed', 'open'], true)) {
            abort(400);
        }
        Report::setStatus((int) $report['id'], $status, Auth::id());

        if ($status !== 'open') {
            Notification::create((int) $report['reporter_id'], 'report_update',
                'Your report was reviewed and ' . $status . '. Thank you for keeping CampusFind safe.', '/my-reports');
        }
        Session::flash('success', 'Report #' . $report['id'] . ' marked as ' . Report::STATUS_LABELS[$status] . '.');
        redirect('/admin/reports/' . $report['id']);
    }

    private function findReportOr404(string $id): array
    {
        $report = ctype_digit($id) ? Report::findForAdmin((int) $id) : null;
        if ($report === null) {
            abort(404);
        }
        return $report;
    }

    // ------------------------------------------------------------------
    // Items
    // ------------------------------------------------------------------

    /** GET /admin/items?q=&type=&status=&visibility=&page= */
    public function items(): void
    {
        $filters = [
            'q'          => mb_substr($this->query('q'), 0, 100),
            'type'       => in_array($this->query('type'), ['lost', 'found'], true) ? $this->query('type') : '',
            'status'     => in_array($this->query('status'), Item::STATUSES, true) ? $this->query('status') : '',
            'visibility' => in_array($this->query('visibility'), ['visible', 'hidden'], true) ? $this->query('visibility') : '',
            'page'       => max(1, (int) $this->query('page')),
        ];
        $result = Item::adminSearch($filters);
        $this->view('admin/items', [
            'title'      => 'Manage items',
            'filters'    => $filters,
            'items'      => $result['items'],
            'total'      => $result['total'],
            'totalPages' => max(1, (int) ceil($result['total'] / 20)),
        ]);
    }

    /** POST /admin/items/{id}/visibility  (approved = 1 shows, 0 hides) */
    public function updateItemVisibility(string $id): void
    {
        $item = $this->findItemOr404($id);
        $show = $this->input('approved') === '1';
        Item::setApproved((int) $item['id'], $show);
        if (!$show) {
            Notification::create((int) $item['user_id'], 'item_moderated',
                'Your post "' . $item['title'] . '" was hidden by an admin.', '/items/' . $item['id']);
        }
        Session::flash('success', '"' . $item['title'] . '" is now ' . ($show ? 'visible to everyone.' : 'hidden from the public.'));
        redirect(safe_redirect_path($this->input('return_to'), '/admin/items'));
    }

    /** POST /admin/items/{id}/delete */
    public function deleteItem(string $id): void
    {
        $item = $this->findItemOr404($id);
        Item::delete((int) $item['id']);
        Notification::create((int) $item['user_id'], 'item_moderated',
            'Your post "' . $item['title'] . '" was removed by an admin.', '/my-items');
        Session::flash('success', '"' . $item['title'] . '" was deleted.');
        redirect(safe_redirect_path($this->input('return_to'), '/admin/items'));
    }

    private function findItemOr404(string $id): array
    {
        $item = ctype_digit($id) ? Item::find((int) $id) : null;
        if ($item === null) {
            abort(404);
        }
        return $item;
    }

    // ------------------------------------------------------------------
    // Users
    // ------------------------------------------------------------------

    /** GET /admin/users?q=&status=&page= */
    public function users(): void
    {
        $filters = [
            'q'      => mb_substr($this->query('q'), 0, 100),
            'status' => in_array($this->query('status'), ['active', 'inactive'], true) ? $this->query('status') : '',
            'page'   => max(1, (int) $this->query('page')),
        ];
        $result = User::adminSearch($filters['q'], $filters['status'], $filters['page']);
        $this->view('admin/users', [
            'title'      => 'Manage users',
            'filters'    => $filters,
            'users'      => $result['users'],
            'total'      => $result['total'],
            'totalPages' => max(1, (int) ceil($result['total'] / 20)),
        ]);
    }

    /** POST /admin/users/{id}/status  (active = 1 or 0) */
    public function updateUserStatus(string $id): void
    {
        $user = ctype_digit($id) ? User::findById((int) $id) : null;
        if ($user === null) {
            abort(404);
        }
        // Safety rules: an admin can't lock themselves (or another admin) out.
        if ((int) $user['id'] === Auth::id() || $user['role'] === 'admin') {
            Session::flash('danger', 'Admin accounts cannot be deactivated from the panel.');
            redirect('/admin/users');
        }

        $activate = $this->input('active') === '1';
        User::setActive((int) $user['id'], $activate);
        Session::flash('success', $user['name'] . ($activate ? ' can log in again.' : ' has been deactivated and signed out.'));
        redirect(safe_redirect_path($this->input('return_to'), '/admin/users'));
    }
}
