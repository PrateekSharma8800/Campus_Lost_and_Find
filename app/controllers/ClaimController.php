<?php
/**
 * Claims: request a found item, and approve/reject requests on your items.
 */

declare(strict_types=1);

final class ClaimController extends Controller
{
    /** POST /items/{id}/claim */
    public function store(string $id): void
    {
        $item = ctype_digit($id) ? Item::find((int) $id) : null;
        if ($item === null || !$item['is_approved']) {
            abort(404);
        }
        $itemId = (int) $item['id'];

        // Business rules: only open FOUND items, not your own, one claim per person.
        if ((int) $item['user_id'] === Auth::id()) {
            abort(403, 'You cannot claim an item you posted.');
        }
        if ($item['type'] !== 'found' || $item['status'] !== 'open') {
            Session::flash('warning', 'This item is not open for claims.');
            redirect('/items/' . $itemId);
        }
        if (Claim::byUserForItem(Auth::id(), $itemId) !== null) {
            Session::flash('info', 'You have already claimed this item.');
            redirect('/items/' . $itemId);
        }

        $data = ['message' => $this->input('message'), 'proof_answer' => $this->input('proof_answer')];
        $validator = Validator::make($data, [
            'message'      => ['required', 'min:10', 'max:1000'],
            'proof_answer' => ['required', 'min:3', 'max:255'],
        ], ['proof_answer' => 'Identifying detail']);

        if ($validator->fails()) {
            $this->view('items/show', [
                'title' => $item['title'], 'item' => $item, 'images' => ItemImage::forItem($itemId),
                'isOwner' => false, 'claims' => [], 'myClaim' => null, 'canClaim' => true,
                'errors' => $validator->errors(), 'old' => $data,
            ], 422);
            return;
        }

        Claim::create($itemId, Auth::id(), $data['message'], $data['proof_answer']);
        Notification::create((int) $item['user_id'], 'claim_received',
            Auth::user()['name'] . ' claimed your found item "' . $item['title'] . '".', '/items/' . $itemId);

        Session::flash('success', 'Your claim was sent. The finder will review it and you will see the decision here.');
        redirect('/items/' . $itemId);
    }

    /** POST /claims/{id}/approve */
    public function approve(string $id): void
    {
        [$claim, $item] = $this->findReviewableClaim($id);
        if ($item['status'] !== 'open') {
            Session::flash('warning', 'This item already has an approved claim.');
            redirect('/items/' . $item['id']);
        }
        Claim::approve($claim, Auth::id());
        Notification::create((int) $claim['claimant_id'], 'claim_approved',
            'Your claim for "' . $item['title'] . '" was approved!', '/items/' . $item['id']);

        Session::flash('success', 'Claim approved. Arrange the hand-over, then mark the item as returned.');
        redirect('/items/' . $item['id']);
    }

    /** POST /claims/{id}/reject */
    public function reject(string $id): void
    {
        [$claim, $item] = $this->findReviewableClaim($id);
        Claim::reject($claim, Auth::id());
        Notification::create((int) $claim['claimant_id'], 'claim_rejected',
            'Your claim for "' . $item['title'] . '" was not approved.', '/items/' . $item['id']);

        Session::flash('info', 'Claim rejected.');
        redirect('/items/' . $item['id']);
    }

    /** GET /my-claims */
    public function mine(): void
    {
        $this->view('claims/mine', ['title' => 'My claims', 'claims' => Claim::forUser(Auth::id())]);
    }

    /** A pending claim on an item the current user posted (admins may review any). */
    private function findReviewableClaim(string $id): array
    {
        $claim = ctype_digit($id) ? Claim::find((int) $id) : null;
        if ($claim === null) {
            abort(404);
        }
        $item = Item::find((int) $claim['item_id']);
        if ((int) $item['user_id'] !== Auth::id() && !Auth::isAdmin()) {
            abort(403, 'Only the person who posted this item can review its claims.');
        }
        if ($claim['status'] !== 'pending') {
            Session::flash('info', 'This claim has already been reviewed.');
            redirect('/items/' . $item['id']);
        }
        return [$claim, $item];
    }
}
