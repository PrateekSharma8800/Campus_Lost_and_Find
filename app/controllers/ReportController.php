<?php
/**
 * Report an item (/reports/new?item=ID) or a user (/reports/new?user=ID),
 * and list your own reports (/my-reports).
 *
 * Rules:
 *  - You can't report your own item or yourself.
 *  - An item can be reported once per user.
 *  - A user can only be reported by someone who exchanged messages with
 *    them (stops random targeting), and only once while a report is open.
 */

declare(strict_types=1);

final class ReportController extends Controller
{
    public function create(): void
    {
        $target = $this->loadTarget($this->query('item'), $this->query('user'));
        $this->renderForm($target, [], []);
    }

    public function store(): void
    {
        $target = $this->loadTarget($this->input('item_id'), $this->input('user_id'));
        $reasons = $target['item'] ? Report::ITEM_REASONS : Report::USER_REASONS;

        $data = ['reason' => $this->input('reason'), 'details' => $this->input('details')];
        $validator = Validator::make($data, [
            'reason'  => ['required', 'in:' . implode(',', array_keys($reasons))],
            'details' => ['required', 'min:10', 'max:1000'],
        ], ['reason' => 'reason', 'details' => 'Description']);

        if ($validator->fails()) {
            $this->renderForm($target, $data, $validator->errors(), 422);
            return;
        }

        Report::create(Auth::id(), $target['item']['id'] ?? null, (int) $target['user']['id'], $data['reason'], $data['details']);
        Session::flash('success', 'Thank you. Your report was sent to the CampusFind team for review.');
        redirect('/my-reports');
    }

    public function mine(): void
    {
        $this->view('reports/mine', ['title' => 'My reports', 'reports' => Report::forReporter(Auth::id())]);
    }

    // ------------------------------------------------------------------

    private function renderForm(array $target, array $old, array $errors, int $status = 200): void
    {
        $this->view('reports/form', [
            'title'   => 'Report ' . ($target['item'] ? 'item' : 'user'),
            'target'  => $target,
            'reasons' => $target['item'] ? Report::ITEM_REASONS : Report::USER_REASONS,
            'old'     => $old,
            'errors'  => $errors,
        ], $status);
    }

    /**
     * Work out what is being reported and check it is allowed.
     * Returns ['item' => ?array, 'user' => array (the person responsible)].
     */
    private function loadTarget(string $itemId, string $userId): array
    {
        $myId = Auth::id();

        if ($itemId !== '') {
            $item = ctype_digit($itemId) ? Item::find((int) $itemId) : null;
            if ($item === null || !$item['is_approved']) {
                abort(404);
            }
            if ((int) $item['user_id'] === $myId) {
                abort(403, 'You cannot report your own post.');
            }
            $this->stopIfDuplicate((int) $item['id'], null, '/items/' . $item['id']);
            return ['item' => $item, 'user' => User::findById((int) $item['user_id'])];
        }

        $user = ctype_digit($userId) ? User::findById((int) $userId) : null;
        if ($user === null) {
            abort(404);
        }
        if ((int) $user['id'] === $myId) {
            abort(403, 'You cannot report yourself.');
        }
        if (!$this->haveTalked($myId, (int) $user['id'])) {
            abort(403, 'You can only report users you have exchanged messages with.');
        }
        $this->stopIfDuplicate(null, (int) $user['id'], '/messages');
        return ['item' => null, 'user' => $user];
    }

    private function stopIfDuplicate(?int $itemId, ?int $userId, string $backTo): void
    {
        if (Report::alreadyReported(Auth::id(), $itemId, $userId)) {
            Session::flash('info', 'You have already reported this. Our team is looking into it.');
            redirect($backTo);
        }
    }

    private function haveTalked(int $userA, int $userB): bool
    {
        $stmt = db()->prepare('SELECT 1 FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) LIMIT 1');
        $stmt->execute([$userA, $userB, $userB, $userA]);
        return (bool) $stmt->fetchColumn();
    }
}
