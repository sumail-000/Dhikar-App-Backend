<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupKhitmaProgress;
use App\Models\InviteToken;
use App\Models\KhitmaAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class GroupController extends Controller
{
    // GET /api/groups
    public function index(Request $request)
    {
        $user = $request->user();

        $groups = Group::query()
            ->where('creator_id', $user->id)
            ->orWhereIn('id', function ($q) use ($user) {
                $q->select('group_id')
                    ->from('group_members')
                    ->where('user_id', $user->id);
            })
            ->withCount('members')
            ->orderByDesc('id')
            ->get();

        $data = $groups->map(function (Group $g) {
            $summary = null;
            if ($g->type === 'khitma') {
                $completed = KhitmaAssignment::where('group_id', $g->id)
                    ->where('status', 'completed')
                    ->count();
                $summary = [
                    'completed_juz' => $completed,
                    'total_juz' => 30,
                ];
            }
            return [
                'id' => $g->id,
                'name' => $g->name,
                'type' => $g->type,
                'creator_id' => $g->creator_id,
                'is_public' => (bool) $g->is_public,
                'auto_assign_enabled' => (bool) $g->auto_assign_enabled,
                'members_target' => $g->members_target,
                'members_count' => $g->members_count,
                'days_to_complete' => $g->days_to_complete,
                'start_date' => optional($g->start_date)->toDateString(),
                'summary' => $summary,
            ];
        });

        return response()->json(['ok' => true, 'groups' => $data]);
    }

    // POST /api/groups
    public function store(Request $request)
    {
        $user = $request->user();
        $v = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:khitma,dhikr',
            'days_to_complete' => 'nullable|integer|min:1|max:255',
            'start_date' => 'nullable|date',
            'members_target' => 'nullable|integer|min:1|max:100000',
            'is_public' => 'nullable|boolean',
            'auto_assign_enabled' => 'nullable|boolean',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }
        $data = $v->validated();
        $type = $data['type'] ?? 'khitma';

        $group = null;
        DB::transaction(function () use ($user, $data, $type, &$group) {
            $group = Group::create([
                'name' => $data['name'],
                'type' => $type,
                'creator_id' => $user->id,
                'members_target' => $data['members_target'] ?? null,
                'is_public' => array_key_exists('is_public', $data) ? (bool)$data['is_public'] : true,
                'auto_assign_enabled' => array_key_exists('auto_assign_enabled', $data) ? (bool)$data['auto_assign_enabled'] : false,
                'days_to_complete' => $data['days_to_complete'] ?? null,
                'start_date' => $data['start_date'] ?? null,
            ]);

            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 'admin',
            ]);

            if ($type === 'khitma') {
                // Seed 1..30 assignments as unassigned
                for ($j = 1; $j <= 30; $j++) {
                    KhitmaAssignment::create([
                        'group_id' => $group->id,
                        'user_id' => null,
                        'juz_number' => $j,
                        'status' => 'unassigned',
                        'pages_read' => null,
                    ]);
                }
            }
        });

        return response()->json(['ok' => true, 'group' => [
            'id' => $group->id,
            'name' => $group->name,
            'type' => $group->type,
            'creator_id' => $group->creator_id,
            'is_public' => (bool) $group->is_public,
            'members_target' => $group->members_target,
            'days_to_complete' => $group->days_to_complete,
            'start_date' => optional($group->start_date)->toDateString(),
        ]], 201);
    }

    // GET /api/groups/{id}
    public function show(Request $request, int $id)
    {
        $user = $request->user();
        $group = Group::with(['members.user'])->findOrFail($id);

        if (!$this->isMember($group, $user->id) && $group->creator_id !== $user->id) {
            if (!$group->is_public) {
                return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
            }
            // Allow read-only access for public groups to non-members
        }

        $members = $group->members->map(function (GroupMember $gm) {
            $user = $gm->user;
            return [
                'id' => $gm->user_id,
                'username' => optional($user)->username,
                'email' => optional($user)->email,
                'avatar_url' => ($user && $user->avatar_path) ? url('storage/'.$user->avatar_path) : null,
                'role' => $gm->role,
            ];
        })->values();

        $summary = null;
        if ($group->type === 'khitma') {
            $completed = KhitmaAssignment::where('group_id', $group->id)
                ->where('status', 'completed')
                ->count();
            $summary = [
                'completed_juz' => $completed,
                'total_juz' => 30,
            ];
        }

        return response()->json([
            'ok' => true,
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'type' => $group->type,
                'creator_id' => $group->creator_id,
                'is_public' => (bool) $group->is_public,
                'auto_assign_enabled' => (bool) $group->auto_assign_enabled,
                'members_target' => $group->members_target,
                'members_count' => $group->members()->count(),
                'days_to_complete' => $group->days_to_complete,
                'start_date' => optional($group->start_date)->toDateString(),
                'members' => $members,
                'summary' => $summary,
            ],
        ]);
    }

    // GET /api/groups/{id}/invite
    public function getInvite(Request $request, int $id)
    {
        $user = $request->user();
        $group = Group::findOrFail($id);
        if (!$group->isAdmin($user)) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        // Return the most recent invite token or create a permanent one if none exists
        $existing = InviteToken::where('group_id', $group->id)
            ->orderByDesc('id')
            ->first();

        if (!$existing) {
            $existing = InviteToken::generateForGroup($group, null); // no expiry
        }

        return response()->json([
            'ok' => true,
            'invite' => [
                'token' => $existing->token,
                'expires_at' => optional($existing->expires_at)->toDateTimeString(),
            ],
        ]);
    }

    // POST /api/groups/join
    public function join(Request $request)
    {
        $user = $request->user();
        $v = Validator::make($request->all(), [
            'token' => 'required|string'
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }
        $token = $v->validated()['token'];

        // Normalize token: trim spaces and uppercase
        $token = strtoupper(str_replace([' ', '\t', '\n', '\r'], '', $token));

        $invite = InviteToken::where('token', $token)->first();
        if (!$invite) {
            return response()->json(['ok' => false, 'error' => 'Invalid token'], 404);
        }
        if ($invite->expires_at && $invite->expires_at->isPast()) {
            return response()->json(['ok' => false, 'error' => 'Token expired'], 410);
        }

        $already = GroupMember::where('group_id', $invite->group_id)
            ->where('user_id', $user->id)
            ->exists();
        if ($already) {
            return response()->json(['ok' => true, 'message' => 'Already joined']);
        }

        $group = Group::findOrFail($invite->group_id);

        DB::transaction(function () use ($group, $user) {
            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 'member',
            ]);

            // Auto-assign on join if enabled and type=khitma
            if ($group->type === 'khitma' && $group->auto_assign_enabled) {
                $memberIds = GroupMember::where('group_id', $group->id)
                    ->orderBy('id')
                    ->pluck('user_id')
                    ->values()
                    ->all();
                $slots = $group->members_target ? min(max((int) $group->members_target, 1), 30) : max(count($memberIds), 1);
                // Creator first ordering
                $ordered = [];
                if (in_array($group->creator_id, $memberIds, true)) $ordered[] = $group->creator_id;
                foreach ($memberIds as $uid) if ($uid !== $group->creator_id) $ordered[] = $uid;

                // Chunking (even-only)
                $base = intdiv(30, $slots);
                $start = 1;
                for ($s = 0; $s < $slots; $s++) {
                    if ($base <= 0) { break; }
                    $count = $base;
                    $end = $start + $count - 1;
                    $uid = ($s < count($ordered)) ? $ordered[$s] : null;
                    for ($j = $start; $j <= $end; $j++) {
                        KhitmaAssignment::updateOrCreate(
                            ['group_id' => $group->id, 'juz_number' => $j],
                            ['user_id' => $uid, 'status' => $uid ? 'assigned' : 'unassigned']
                        );
                    }
                    $start = $end + 1;
                }
                for ($j = $start; $j <= 30; $j++) {
                    KhitmaAssignment::updateOrCreate(
                        ['group_id' => $group->id, 'juz_number' => $j],
                        ['user_id' => null, 'status' => 'unassigned']
                    );
                }
            }
        });

        return response()->json(['ok' => true, 'group_id' => $invite->group_id]);
    }

    // POST /api/groups/{id}/join - join public group without token
    public function joinPublic(Request $request, int $id)
    {
        $user = $request->user();
        $group = Group::findOrFail($id);
        if (!$group->is_public) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $already = GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->exists();
        if ($already) {
            return response()->json(['ok' => true, 'message' => 'Already joined']);
        }

        DB::transaction(function () use ($group, $user) {
            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 'member',
            ]);

            if ($group->type === 'khitma' && $group->auto_assign_enabled) {
                $memberIds = GroupMember::where('group_id', $group->id)
                    ->orderBy('id')
                    ->pluck('user_id')
                    ->values()
                    ->all();
                $slots = $group->members_target ? min(max((int) $group->members_target, 1), 30) : max(count($memberIds), 1);
                $ordered = [];
                if (in_array($group->creator_id, $memberIds, true)) $ordered[] = $group->creator_id;
                foreach ($memberIds as $uid) if ($uid !== $group->creator_id) $ordered[] = $uid;
                $base = intdiv(30, $slots);
                $start = 1;
                for ($s = 0; $s < $slots; $s++) {
                    if ($base <= 0) { break; }
                    $count = $base;
                    $end = $start + $count - 1;
                    $uid = ($s < count($ordered)) ? $ordered[$s] : null;
                    for ($j = $start; $j <= $end; $j++) {
                        KhitmaAssignment::updateOrCreate(
                            ['group_id' => $group->id, 'juz_number' => $j],
                            ['user_id' => $uid, 'status' => $uid ? 'assigned' : 'unassigned']
                        );
                    }
                    $start = $end + 1;
                }
                for ($j = $start; $j <= 30; $j++) {
                    KhitmaAssignment::updateOrCreate(
                        ['group_id' => $group->id, 'juz_number' => $j],
                        ['user_id' => null, 'status' => 'unassigned']
                    );
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    // POST /api/groups/{id}/leave
    public function leave(Request $request, int $id)
    {
        $user = $request->user();
        $group = Group::with('members')->findOrFail($id);

        $member = GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->first();
        if (!$member && $group->creator_id !== $user->id) {
            return response()->json(['ok' => false, 'error' => 'Not a member'], 403);
        }

        // Prevent last admin from leaving
        $adminCount = GroupMember::where('group_id', $group->id)
            ->where('role', 'admin')->count();
        $isAdmin = $group->creator_id === $user->id || ($member && $member->role === 'admin');
        if ($isAdmin && $adminCount <= 1) {
            return response()->json(['ok' => false, 'error' => 'Cannot leave as the only admin'], 400);
        }

        if ($member) {
            $member->delete();
        } else {
            // creator leaving - for now, disallow (transfer ownership feature later)
            return response()->json(['ok' => false, 'error' => 'Creator cannot leave. Transfer ownership first.'], 400);
        }

        return response()->json(['ok' => true]);
    }

    // DELETE /api/groups/{id}/members/{userId}
    public function removeMember(Request $request, int $id, int $userId)
    {
        $user = $request->user();
        $group = Group::findOrFail($id);
        if (!$group->isAdmin($user)) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $member = GroupMember::where('group_id', $group->id)
            ->where('user_id', $userId)
            ->first();
        if (!$member) {
            return response()->json(['ok' => false, 'error' => 'Not a member'], 404);
        }

        // Prevent removing the last admin
        if ($member->role === 'admin') {
            $adminCount = GroupMember::where('group_id', $group->id)
                ->where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return response()->json(['ok' => false, 'error' => 'Cannot remove the only admin'], 400);
            }
        }

        $member->delete();
        return response()->json(['ok' => true]);
    }

    // DELETE /api/groups/{id}
    public function destroy(Request $request, int $id)
    {
        $user = $request->user();
        $group = Group::findOrFail($id);
        if (!$group->isAdmin($user)) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        DB::transaction(function () use ($group) {
            $group->delete(); // cascade deletes members/invites/assignments
        });

        return response()->json(['ok' => true]);
    }

    // PATCH /api/groups/{id}
    public function update(Request $request, int $id)
    {
        $user = $request->user();
        $group = Group::findOrFail($id);
        if (!$group->isAdmin($user)) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'is_public' => ['required', 'boolean'],
        ]);

        $group->is_public = (bool) $data['is_public'];
        $group->save();

        return response()->json([
            'ok' => true,
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'type' => $group->type,
                'creator_id' => $group->creator_id,
                'is_public' => (bool) $group->is_public,
                'auto_assign_enabled' => (bool) $group->auto_assign_enabled,
                'members_target' => $group->members_target,
                'days_to_complete' => $group->days_to_complete,
                'start_date' => optional($group->start_date)->toDateString(),
            ],
        ]);
    }

    // POST /api/groups/{id}/khitma/auto-assign
    public function autoAssign(Request $request, int $id)
    {
        $user = $request->user();
        $group = Group::with('members')->findOrFail($id);
        if ($group->type !== 'khitma') {
            return response()->json(['ok' => false, 'error' => 'Invalid group type'], 400);
        }
        if (!$group->isAdmin($user)) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        // Determine target slots: prefer members_target if set (capped at 30), otherwise current member count
        $memberIds = GroupMember::where('group_id', $group->id)
            ->orderBy('id') // join order
            ->pluck('user_id')
            ->values()
            ->all();

        if (empty($memberIds)) {
            return response()->json(['ok' => false, 'error' => 'No members to assign'], 400);
        }

        $slots = $group->members_target ? min(max((int) $group->members_target, 1), 30) : max(count($memberIds), 1);

        DB::transaction(function () use ($group, $memberIds, $slots) {
            // Enable auto assign flag
            $group->auto_assign_enabled = true;
            $group->save();

            // Build ordered users: creator first, then by join order
            $ordered = [];
            if (in_array($group->creator_id, $memberIds, true)) {
                $ordered[] = $group->creator_id;
            }
            foreach ($memberIds as $uid) {
                if ($uid !== $group->creator_id) $ordered[] = $uid;
            }

            // Chunk Juz 1..30 into contiguous segments per slot (even-only); leftover Juz remain unassigned
            $base = intdiv(30, $slots);
            $start = 1;
            for ($s = 0; $s < $slots; $s++) {
                if ($base <= 0) { break; }
                $count = $base;
                $end = $start + $count - 1;
                $uid = ($s < count($ordered)) ? $ordered[$s] : null;
                for ($j = $start; $j <= $end; $j++) {
                    KhitmaAssignment::updateOrCreate(
                        ['group_id' => $group->id, 'juz_number' => $j],
                        ['user_id' => $uid, 'status' => $uid ? 'assigned' : 'unassigned']
                    );
                }
                $start = $end + 1;
            }
            // Mark remaining Juz as unassigned
            for ($j = $start; $j <= 30; $j++) {
                KhitmaAssignment::updateOrCreate(
                    ['group_id' => $group->id, 'juz_number' => $j],
                    ['user_id' => null, 'status' => 'unassigned']
                );
            }
        });

        return response()->json(['ok' => true]);
    }

    // POST /api/groups/{id}/khitma/manual-assign
    public function manualAssign(Request $request, int $id)
    {
        $user = $request->user();
        $group = Group::findOrFail($id);
        if ($group->type !== 'khitma') {
            return response()->json(['ok' => false, 'error' => 'Invalid group type'], 400);
        }
        if (!$group->isAdmin($user)) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $v = Validator::make($request->all(), [
            'assignments' => 'required|array|min:1',
            'assignments.*.user_id' => 'required|integer|exists:users,id',
            'assignments.*.juz_numbers' => 'required|array|min:1',
            'assignments.*.juz_numbers.*' => 'integer|min:1|max:30',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }
        $payload = $v->validated();

        // Flatten all juz_numbers and ensure uniqueness across payload
        $allJuz = [];
        foreach ($payload['assignments'] as $a) {
            foreach ($a['juz_numbers'] as $jn) {
                $allJuz[] = $jn;
            }
        }
        if (count($allJuz) !== count(array_unique($allJuz))) {
            return response()->json(['ok' => false, 'error' => 'Duplicate Juz numbers in payload'], 422);
        }

        DB::transaction(function () use ($group, $payload) {
            // Disable future auto-assign recalculations once manual customization is applied
            $group->auto_assign_enabled = false;
            $group->save();

            foreach ($payload['assignments'] as $a) {
                $uid = (int) $a['user_id'];
                foreach ($a['juz_numbers'] as $jn) {
                    KhitmaAssignment::updateOrCreate(
                        ['group_id' => $group->id, 'juz_number' => (int) $jn],
                        ['user_id' => $uid, 'status' => 'assigned']
                    );
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    // GET /api/groups/{id}/khitma/assignments
    public function assignments(Request $request, int $id)
    {
        $user = $request->user();
        $group = Group::findOrFail($id);
        if (!$this->isMember($group, $user->id) && $group->creator_id !== $user->id) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $rows = KhitmaAssignment::with('user')
            ->where('group_id', $group->id)
            ->orderBy('juz_number')
            ->get();

        $data = $rows->map(function (KhitmaAssignment $ka) {
            return [
                'juz_number' => $ka->juz_number,
                'status' => $ka->status,
                'pages_read' => $ka->pages_read,
                'user' => $ka->user ? [
                    'id' => $ka->user->id,
                    'username' => $ka->user->username,
                ] : null,
            ];
        });

        return response()->json(['ok' => true, 'assignments' => $data]);
    }

    // PATCH /api/groups/{id}/khitma/assignment
    public function updateAssignment(Request $request, int $id)
    {
        $user = $request->user();
        $group = Group::findOrFail($id);
        if ($group->type !== 'khitma') {
            return response()->json(['ok' => false, 'error' => 'Invalid group type'], 400);
        }

        $v = Validator::make($request->all(), [
            'juz_number' => 'required|integer|min:1|max:30',
            'status' => 'nullable|in:assigned,completed,unassigned',
            'pages_read' => 'nullable|integer|min:0',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }
        $data = $v->validated();

        $ka = KhitmaAssignment::where('group_id', $group->id)
            ->where('juz_number', $data['juz_number'])
            ->first();
        if (!$ka) {
            return response()->json(['ok' => false, 'error' => 'Assignment not found'], 404);
        }

        $isAdmin = $group->isAdmin($user);
        $isOwner = $ka->user_id === $user->id;
        if (!$isAdmin && !$isOwner) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        if (isset($data['status'])) {
            if (!$isAdmin && $data['status'] === 'unassigned') {
                return response()->json(['ok' => false, 'error' => 'Only admin can unassign'], 403);
            }
            $ka->status = $data['status'];
        }
        if (array_key_exists('pages_read', $data)) {
            $ka->pages_read = $data['pages_read'];
        }
        $ka->save();

        return response()->json(['ok' => true]);
    }

    // GET /api/groups/explore - list all public groups (for discovery)
    public function explore(Request $request)
    {
        $user = $request->user();
        $groups = Group::query()
            ->where('is_public', true)
            ->withCount('members')
            ->orderByDesc('id')
            ->get();

        $data = $groups->map(function (Group $g) {
            return [
                'id' => $g->id,
                'name' => $g->name,
                'type' => $g->type,
                'creator_id' => $g->creator_id,
                'is_public' => (bool) $g->is_public,
                'members_target' => $g->members_target,
                'members_count' => $g->members_count,
                'days_to_complete' => $g->days_to_complete,
                'start_date' => optional($g->start_date)->toDateString(),
            ];
        });

        return response()->json(['ok' => true, 'groups' => $data]);
    }

    private function isMember(Group $group, int $userId): bool
    {
        if ($group->creator_id === $userId) return true;
        return GroupMember::where('group_id', $group->id)
            ->where('user_id', $userId)
            ->exists();
    }

    // GET /api/khitma/juz-pages
    // Returns page ranges per Juz based on Uthmanic Hafs text (604 pages total expected)
    public function juzPages(Request $request)
    {
        // No auth role requirement beyond being logged-in (same as other khitma endpoints)
        // Static Mushaf page ranges per Juz (aligned with client JSON mapping)
        $ranges = [
            1 => [1,21], 2 => [22,41], 3 => [42,62], 4 => [63,82], 5 => [83,102], 6 => [103,122],
            7 => [123,142], 8 => [143,162], 9 => [163,182], 10 => [183,202], 11 => [203,222], 12 => [223,242],
            13 => [243,262], 14 => [263,282], 15 => [283,302], 16 => [303,322], 17 => [323,342], 18 => [343,362],
            19 => [363,382], 20 => [383,402], 21 => [403,422], 22 => [423,442], 23 => [443,462], 24 => [463,482],
            25 => [483,502], 26 => [503,522], 27 => [523,542], 28 => [543,562], 29 => [563,582], 30 => [583,604],
        ];
        $data = collect(range(1,30))->map(function ($juz) use ($ranges) {
            [$start,$end] = $ranges[$juz];
            return [
                'juz' => $juz,
                'page_start' => $start,
                'page_end' => $end,
                'pages' => max($end - $start + 1, 0),
            ];
        });

        return response()->json([
            'ok' => true,
            'total_pages' => 604,
            'juz_pages' => $data,
        ]);
    }


    // POST /api/groups/{id}/khitma/progress
    public function saveKhitmaProgress(Request $request, int $id)
    {
        $user = $request->user();
        $group = Group::findOrFail($id);
        
        if ($group->type !== 'khitma') {
            return response()->json(['ok' => false, 'error' => 'Invalid group type'], 400);
        }
        
        if (!$this->isMember($group, $user->id) && $group->creator_id !== $user->id) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }
        
        $v = Validator::make($request->all(), [
            'juzz_read' => 'required|integer|min:1|max:30',
            'surah_read' => 'required|integer|min:1|max:114',
            'page_read' => 'required|integer|min:1|max:604',
            'start_verse' => 'nullable|integer|min:1',
            'end_verse' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);
        
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }
        
        $data = $v->validated();
        
        DB::transaction(function () use ($group, $user, $data) {
            // Save the progress entry
            GroupKhitmaProgress::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'reading_date' => now()->toDateString(),
                'juzz_read' => $data['juzz_read'],
                'surah_read' => $data['surah_read'],
                'page_read' => $data['page_read'],
                'start_verse' => $data['start_verse'] ?? null,
                'end_verse' => $data['end_verse'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            
            // Update the corresponding khitma assignment progress and status based on distinct pages read
            $assignment = KhitmaAssignment::where('group_id', $group->id)
                ->where('user_id', $user->id)
                ->where('juz_number', $data['juzz_read'])
                ->first();

            if ($assignment) {
                // Count distinct pages read by this user for this Juz within the group
                $pagesReadCount = GroupKhitmaProgress::where('group_id', $group->id)
                    ->where('user_id', $user->id)
                    ->where('juzz_read', $data['juzz_read'])
                    ->distinct()
                    ->count('page_read');

                // Determine total pages for this Juz using static Mushaf page ranges
                $ranges = [
                    1 => [1,21], 2 => [22,41], 3 => [42,62], 4 => [63,82], 5 => [83,102], 6 => [103,122],
                    7 => [123,142], 8 => [143,162], 9 => [163,182], 10 => [183,202], 11 => [203,222], 12 => [223,242],
                    13 => [243,262], 14 => [263,282], 15 => [283,302], 16 => [303,322], 17 => [323,342], 18 => [343,362],
                    19 => [363,382], 20 => [383,402], 21 => [403,422], 22 => [423,442], 23 => [443,462], 24 => [463,482],
                    25 => [483,502], 26 => [503,522], 27 => [523,542], 28 => [543,562], 29 => [563,582], 30 => [583,604],
                ];
                $range = $ranges[(int)$data['juzz_read']] ?? [0, -1];
                $totalPagesInJuz = max($range[1] - $range[0] + 1, 0);

                // Persist pages_read on the assignment
                $assignment->pages_read = $pagesReadCount;

                // Update status: completed if all pages read, otherwise keep as assigned
                if ($totalPagesInJuz > 0 && $pagesReadCount >= $totalPagesInJuz) {
                    $assignment->status = 'completed';
                } else {
                    // Ensure it is at least marked as assigned when progress exists
                    if ($pagesReadCount > 0 && $assignment->status === 'unassigned') {
                        $assignment->status = 'assigned';
                    }
                }

                $assignment->save();
            }
        });
        
        return response()->json([
            'ok' => true,
            'message' => 'Progress saved successfully',
            'progress' => [
                'group_id' => $group->id,
                'juzz_read' => $data['juzz_read'],
                'surah_read' => $data['surah_read'],
                'page_read' => $data['page_read'],
                'reading_date' => now()->toDateString(),
            ]
        ]);
    }
    
    // GET /api/groups/{id}/khitma/progress
    public function getKhitmaProgress(Request $request, int $id)
    {
        $user = $request->user();
        $group = Group::findOrFail($id);
        
        if ($group->type !== 'khitma') {
            return response()->json(['ok' => false, 'error' => 'Invalid group type'], 400);
        }
        
        if (!$this->isMember($group, $user->id) && $group->creator_id !== $user->id) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }
        
        $progressSummary = GroupKhitmaProgress::getGroupProgressSummary($group->id);
        
        return response()->json([
            'ok' => true,
            'group_progress' => $progressSummary
        ]);
    }
    
    // GET /api/user/group-khitma-stats
    public function getUserGroupKhitmaStats(Request $request)
    {
        $user = $request->user();
        $stats = GroupKhitmaProgress::getUserTotalGroupProgress($user->id);
        
        return response()->json([
            'ok' => true,
            'stats' => $stats
        ]);
    }
}
