<?php
declare(strict_types=1);

final class DashboardController extends Controller
{
    public function index(): void
    {
        $userId = Auth::id();
        $this->view('dashboard/index', [
            'title'          => 'Dashboard',
            'user'           => Auth::user(),
            'stats'          => Item::statsForUser($userId),
            'claimsMade'     => Claim::countForUser($userId),
            'pendingReviews' => Claim::pendingForOwner($userId),
            'recentItems'    => array_slice(Item::forUser($userId), 0, 5),
        ]);
    }
}
