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
