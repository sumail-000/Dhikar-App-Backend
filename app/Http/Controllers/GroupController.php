<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
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

        $existing = InviteToken::where('group_id', $group->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();

        if (!$existing) {
            $expires = now()->addDays(7);
            $existing = InviteToken::generateForGroup($group, $expires);
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

        GroupMember::create([
            'group_id' => $invite->group_id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        return response()->json(['ok' => true]);
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

        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

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

        $memberIds = GroupMember::where('group_id', $group->id)
            ->orderBy('id')
            ->pluck('user_id')
            ->values()
            ->all();
        if (empty($memberIds)) {
            return response()->json(['ok' => false, 'error' => 'No members to assign'], 400);
        }

        DB::transaction(function () use ($group, $memberIds) {
            $idx = 0;
            for ($j = 1; $j <= 30; $j++) {
                $uid = $memberIds[$idx % count($memberIds)];
                KhitmaAssignment::updateOrCreate(
                    ['group_id' => $group->id, 'juz_number' => $j],
                    ['user_id' => $uid, 'status' => 'assigned']
                );
                $idx++;
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
}
