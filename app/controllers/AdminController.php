<?php
declare(strict_types=1);

final class AdminController extends Controller
{
    public function index(): void
    {
        $items = (int) db()->query('SELECT COUNT(*) FROM items')->fetchColumn();
        $this->view('admin/index', ['title' => 'Admin panel', 'users' => User::roleCounts(), 'items' => $items]);
    }
}
