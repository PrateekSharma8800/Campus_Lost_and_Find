<?php
declare(strict_types=1);

final class HomeController extends Controller
{
    public function index(): void
    {
        $this->view('home/index', ['title' => 'Campus Lost & Found', 'latestItems' => Item::latest(8)]);
    }
}
