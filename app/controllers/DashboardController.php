<?php
declare(strict_types=1);

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->view('dashboard/index', ['title' => 'Dashboard', 'user' => Auth::user()]);
    }
}
