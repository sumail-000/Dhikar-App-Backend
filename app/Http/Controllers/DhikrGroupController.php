<?php

namespace App\Http\Controllers;

use App\Models\DhikrGroup;
use App\Models\DhikrGroupMember;
use App\Models\DhikrInviteToken;
use App\Models\User;
use App\Traits\PersonalizedReminderTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DhikrGroupController extends Controller
{
    use PersonalizedReminderTrait;
    // GET /api/dhikr-groups
    public function index(Request $request)
    {
        $user = $request->user();

        $groups = DhikrGroup::query()
            ->where('creator_id', $user->id)
            ->orWhereIn('id', function ($q) use ($user) {
                $q->select('dhikr_group_id')
                  ->from('dhikr_group_members')
                  ->where('user_id', $user->id);
            })
            ->withCount('members')
            ->orderByDesc('id')
            ->get();

        $data = $groups->map(function (DhikrGroup $g) {
            return [
                'id' => $g->id,
                'name' => $g->name,
                'type' => 'dhikr',
                'creator_id' => $g->creator_id,
                'is_public' => (bool) $g->is_public,
                'members_target' => $g->members_target,
                'members_count' => $g->members_count,
'days_to_complete' => $g->days_to_complete,
                'dhikr_target' => $g->dhikr_target,
                'dhikr_count' => $g->dhikr_count,
                'dhikr_title' => $g->dhikr_title,
                'dhikr_title_arabic' => $g->dhikr_title_arabic,
            ];
        });

        return response()->json(['ok' => true, 'groups' => $data]);
    }

    // POST /api/dhikr-groups
    public function store(Request $request)
    {
        $user = $request->user();
        $v = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'days_to_complete' => 'nullable|integer|min:1|max:255',
            'members_target' => 'nullable|integer|min:1|max:100000',
            'dhikr_target' => 'nullable|integer|min:1|max:1000000000',
'is_public' => 'nullable|boolean',
            'dhikr_title' => 'nullable|string|max:255',
            'dhikr_title_arabic' => 'nullable|string|max:255',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }
        $data = $v->validated();

        $group = null;
        DB::transaction(function () use ($user, $data, &$group) {
            $group = DhikrGroup::create([
                'name' => $data['name'],
                'creator_id' => $user->id,
                'days_to_complete' => $data['days_to_complete'] ?? null,
                'members_target' => $data['members_target'] ?? null,
'is_public' => array_key_exists('is_public', $data) ? (bool)$data['is_public'] : true,
                'dhikr_target' => $data['dhikr_target'] ?? null,
                'dhikr_count' => 0,
                'dhikr_title' => $data['dhikr_title'] ?? null,
                'dhikr_title_arabic' => $data['dhikr_title_arabic'] ?? null,
            ]);

            DhikrGroupMember::create([
                'dhikr_group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 'admin',
            ]);
        });

        return response()->json(['ok' => true, 'group' => [
            'id' => $group->id,
            'name' => $group->name,
            'type' => 'dhikr',
            'creator_id' => $group->creator_id,
            'is_public' => (bool) $group->is_public,
            'members_target' => $group->members_target,
            'members_count' => $group->members()->count(),
            'days_to_complete' => $group->days_to_complete,
            'dhikr_target' => $group->dhikr_target,
            'dhikr_count' => $group->dhikr_count,
            'dhikr_title' => $group->dhikr_title,
            'dhikr_title_arabic' => $group->dhikr_title_arabic,
        ]], 201);
    }

    // GET /api/dhikr-groups/{id}
    public function show(Request $request, int $id)
    {
        $user = $request->user();
        $group = DhikrGroup::with(['members.user'])->findOrFail($id);

        if (!$this->isMember($group, $user->id) && $group->creator_id !== $user->id) {
            if (!$group->is_public) {
                return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
            }
            // Allow read-only access for public groups to non-members
        }

        $members = $group->members->map(function (DhikrGroupMember $gm) {
            $user = $gm->user;
            return [
                'id' => $gm->user_id,
                'username' => optional($user)->username,
                'email' => optional($user)->email,
                'avatar_url' => ($user && $user->avatar_path) ? url('storage/'.$user->avatar_path) : null,
                'role' => $gm->role,
                'dhikr_contribution' => (int) ($gm->dhikr_contribution ?? 0),
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'type' => 'dhikr',
                'creator_id' => $group->creator_id,
                'is_public' => (bool) $group->is_public,
                'members_target' => $group->members_target,
                'members_count' => $group->members()->count(),
                'days_to_complete' => $group->days_to_complete,
'members' => $members,
                'summary' => null,
                'dhikr_target' => $group->dhikr_target,
                'dhikr_count' => $group->dhikr_count,
                'dhikr_title' => $group->dhikr_title,
                'dhikr_title_arabic' => $group->dhikr_title_arabic,
            ],
        ]);
    }

    // GET /api/dhikr-groups/{id}/invite
    public function getInvite(Request $request, int $id)
    {
        $user = $request->user();
        $group = DhikrGroup::findOrFail($id);
        if (!$group->isAdmin($user)) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $existing = DhikrInviteToken::where('dhikr_group_id', $group->id)
            ->orderByDesc('id')
            ->first();
        if (!$existing) {
            $existing = DhikrInviteToken::generateForGroup($group, null);
        }

        return response()->json([
            'ok' => true,
            'invite' => [
                'token' => $existing->token,
                'expires_at' => optional($existing->expires_at)->toDateTimeString(),
            ],
        ]);
    }

    // POST /api/dhikr-groups/join
    public function join(Request $request)
    {
        $user = $request->user();
        $v = Validator::make($request->all(), [
            'token' => 'required|string'
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }
        $token = strtoupper(str_replace([' ', "\t", "\n", "\r"], '', $v->validated()['token']));

        $invite = DhikrInviteToken::where('token', $token)->first();
        if (!$invite) return response()->json(['ok' => false, 'error' => 'Invalid token'], 404);
        if ($invite->expires_at && $invite->expires_at->isPast()) {
            return response()->json(['ok' => false, 'error' => 'Token expired'], 410);
        }
        $group = DhikrGroup::findOrFail($invite->dhikr_group_id);

        $already = DhikrGroupMember::where('dhikr_group_id', $group->id)
            ->where('user_id', $user->id)->exists();
        if ($already) return response()->json(['ok' => true, 'message' => 'Already joined']);

        DhikrGroupMember::create([
            'dhikr_group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        return response()->json(['ok' => true, 'group_id' => $group->id]);
    }

    // POST /api/dhikr-groups/{id}/join
    public function joinPublic(Request $request, int $id)
    {
        $user = $request->user();
        $group = DhikrGroup::findOrFail($id);
        if (!$group->is_public) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $already = DhikrGroupMember::where('dhikr_group_id', $group->id)
            ->where('user_id', $user->id)->exists();
        if ($already) return response()->json(['ok' => true, 'message' => 'Already joined']);

        DhikrGroupMember::create([
            'dhikr_group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        return response()->json(['ok' => true]);
    }

    // POST /api/dhikr-groups/{id}/leave
    public function leave(Request $request, int $id)
    {
        $user = $request->user();
        $group = DhikrGroup::with('members')->findOrFail($id);

        $member = DhikrGroupMember::where('dhikr_group_id', $group->id)
            ->where('user_id', $user->id)->first();
        if (!$member && $group->creator_id !== $user->id) {
            return response()->json(['ok' => false, 'error' => 'Not a member'], 403);
        }

        $adminCount = DhikrGroupMember::where('dhikr_group_id', $group->id)
            ->where('role', 'admin')->count();
        $isAdmin = $group->creator_id === $user->id || ($member && $member->role === 'admin');
        if ($isAdmin && $adminCount <= 1) {
            return response()->json(['ok' => false, 'error' => 'Cannot leave as the only admin'], 400);
        }

        if ($member) {
            $member->delete();
        } else {
            // creator leaving - disallow for now (transfer ownership later)
            return response()->json(['ok' => false, 'error' => 'Creator cannot leave. Transfer ownership first.'], 400);
        }

        return response()->json(['ok' => true]);
    }

    // DELETE /api/dhikr-groups/{id}/members/{userId}
    public function removeMember(Request $request, int $id, int $userId)
    {
        $user = $request->user();
        $group = DhikrGroup::findOrFail($id);
        if (!$group->isAdmin($user)) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $member = DhikrGroupMember::where('dhikr_group_id', $group->id)
            ->where('user_id', $userId)->first();
        if (!$member) return response()->json(['ok' => false, 'error' => 'Not a member'], 404);

        if ($member->role === 'admin') {
            $adminCount = DhikrGroupMember::where('dhikr_group_id', $group->id)
                ->where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return response()->json(['ok' => false, 'error' => 'Cannot remove the only admin'], 400);
            }
        }

        $member->delete();
        return response()->json(['ok' => true]);
    }

    // DELETE /api/dhikr-groups/{id}
    public function destroy(Request $request, int $id)
    {
        $user = $request->user();
        $group = DhikrGroup::findOrFail($id);
        if (!$group->isAdmin($user)) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        DB::transaction(function () use ($group) {
            $group->delete();
        });

        return response()->json(['ok' => true]);
    }

    // PATCH /api/dhikr-groups/{id}
    public function update(Request $request, int $id)
    {
        $user = $request->user();
        $group = DhikrGroup::findOrFail($id);
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
                'type' => 'dhikr',
                'creator_id' => $group->creator_id,
                'is_public' => (bool) $group->is_public,
                'members_target' => $group->members_target,
                'members_count' => $group->members()->count(),
                'days_to_complete' => $group->days_to_complete,
            ],
        ]);
    }

    // POST /api/dhikr-groups/{id}/progress
    public function saveProgress(Request $request, int $id)
    {
        $user = $request->user();
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:1000000000'],
        ]);
        $inc = (int) $data['count'];

        $updated = null;
        DB::transaction(function () use ($id, $user, $inc, &$updated) {
            $group = DhikrGroup::lockForUpdate()->findOrFail($id);

            $isMember = DhikrGroupMember::where('dhikr_group_id', $group->id)
                ->where('user_id', $user->id)
                ->exists();
            if (!$isMember && $group->creator_id !== $user->id) {
                abort(response()->json(['ok' => false, 'error' => 'Forbidden'], 403));
            }

            $current = (int) ($group->dhikr_count ?? 0);
            $target = $group->dhikr_target; // may be null
            $new = $current + $inc;
            if (!is_null($target) && $new > $target) {
                $new = $target;
            }
            $group->dhikr_count = $new;
            $group->save();

            // Log daily contribution for streaks
            DB::table('dhikr_group_daily_contributions')->updateOrInsert(
                [
                    'dhikr_group_id' => $group->id,
                    'user_id' => $user->id,
                    'contribution_date' => now()->toDateString(),
                ],
                [
                    'count' => DB::raw('GREATEST(COALESCE(count,0) + '.(int)$inc.', 0)'),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            // Increment member contribution
            $member = DhikrGroupMember::where('dhikr_group_id', $group->id)
                ->where('user_id', $user->id)
                ->first();
            if ($member) {
                $member->dhikr_contribution = (int)($member->dhikr_contribution ?? 0) + $inc;
                // clamp to group target if set
                if (!is_null($group->dhikr_target) && $member->dhikr_contribution > $group->dhikr_target) {
                    $member->dhikr_contribution = $group->dhikr_target;
                }
                $member->save();
            }

            $updated = $group;
        });

        return response()->json([
            'ok' => true,
            'group' => [
                'id' => $updated->id,
                'dhikr_target' => $updated->dhikr_target,
                'dhikr_count' => $updated->dhikr_count,
            ],
        ]);
    }

    // GET /api/dhikr-groups/{id}/progress
    public function getProgress(Request $request, int $id)
    {
        $user = $request->user();
        $group = DhikrGroup::findOrFail($id);

        if (!$group->is_public) {
            $isMember = DhikrGroupMember::where('dhikr_group_id', $group->id)
                ->where('user_id', $user->id)
                ->exists();
            if (!$isMember && $group->creator_id !== $user->id) {
                return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
            }
        }

        return response()->json([
            'ok' => true,
            'group' => [
                'id' => $group->id,
                'dhikr_target' => $group->dhikr_target,
                'dhikr_count' => $group->dhikr_count,
            ],
        ]);
    }

    // GET /api/dhikr-groups/explore
    public function explore(Request $request)
    {
        $groups = DhikrGroup::query()
            ->where('is_public', true)
            ->withCount('members')
            ->orderByDesc('id')
            ->get();

        $data = $groups->map(function (DhikrGroup $g) {
            return [
                'id' => $g->id,
                'name' => $g->name,
                'type' => 'dhikr',
                'creator_id' => $g->creator_id,
                'is_public' => (bool) $g->is_public,
                'members_target' => $g->members_target,
                'members_count' => $g->members_count,
                'days_to_complete' => $g->days_to_complete,
                'dhikr_title' => $g->dhikr_title,
                'dhikr_title_arabic' => $g->dhikr_title_arabic,
            ];
        });

        return response()->json(['ok' => true, 'groups' => $data]);
    }

    private function isMember(DhikrGroup $group, int $userId): bool
    {
        if ($group->creator_id === $userId) return true;
        return DhikrGroupMember::where('dhikr_group_id', $group->id)
            ->where('user_id', $userId)
            ->exists();
    }

    // POST /api/dhikr-groups/{id}/reminders - send reminder to dhikr group members (admin only)
    public function sendReminder(Request $request, int $id)
    {
        $user = $request->user();
        $group = DhikrGroup::findOrFail($id);
        if (!$group->isAdmin($user)) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $payload = $request->validate([
            'message' => ['nullable','string','max:500'],
        ]);
        $message = $payload['message'] ?? 'Reminder: Kindly contribute to the group dhikr goal.';

        $memberIds = DhikrGroupMember::where('dhikr_group_id', $group->id)
            ->pluck('user_id')->values()->all();

        // Filter by user preferences (allow_group_notifications)
        $eligibleUserIds = array_values(array_filter($memberIds, function ($uid) {
            return \App\Models\UserNotificationPreference::allowsGroup((int) $uid);
        }));

        $tokens = \App\Models\DeviceToken::whereIn('user_id', $eligibleUserIds)
            ->pluck('device_token')->values()->all();

        foreach ($memberIds as $uid) {
            \App\Models\AppNotification::create([
                'user_id' => $uid,
                'type' => 'dhikr_group_reminder',
                'title' => 'Dhikr Group Reminder',
                'body' => $message,
                'data' => [
                    'dhikr_group_id' => $group->id,
                    'group_name' => $group->name,
                ],
            ]);
        }

        if (!empty($tokens)) {
            \App\Jobs\SendPushNotification::dispatch(
                $tokens,
                'Dhikr Group Reminder',
                $message,
                ['dhikr_group_id' => $group->id, 'type' => 'dhikr_group_reminder']
            );
        }

        return response()->json(['ok' => true]);
    }

    // POST /api/dhikr-groups/{id}/reminders/member - send reminder to a single member (admin only)
    public function sendMemberReminder(Request $request, int $id)
    {
        $user = $request->user();
        $group = DhikrGroup::findOrFail($id);
        if (!$group->isAdmin($user)) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $payload = $request->validate([
            'user_id' => ['required','integer','exists:users,id'],
            'message' => ['nullable','string','max:500'],
        ]);
        $targetUserId = (int) $payload['user_id'];
        $customMessage = $payload['message'] ?? null;

        // Ensure target is member of the group
        $isMember = DhikrGroupMember::where('dhikr_group_id', $group->id)->where('user_id', $targetUserId)->exists();
        if (!$isMember && $group->creator_id !== $targetUserId) {
            return response()->json(['ok' => false, 'error' => 'User is not a member of this group'], 422);
        }

        // Get target user safely
        $targetUser = $this->getUserSafely($targetUserId);
        if (!$targetUser) {
            return response()->json(['ok' => false, 'error' => 'Target user not found'], 404);
        }

        // Generate personalized reminder message
        try {
            $reminderData = $this->generateDhikrReminderMessage($targetUser, $group, $customMessage);
            $message = $reminderData['message'];
            $title = $reminderData['title'];
        } catch (\Exception $e) {
            \Log::error('Error generating personalized dhikr reminder message: ' . $e->getMessage());
            // Fallback to simple message if personalization fails
            $firstName = $this->getFirstName($targetUser->username ?? 'Friend');
            $message = "Salam {$firstName}! Please check your dhikr group participation.";
            $title = "Dhikr Group Reminder - {$group->name}";
        }

        // Create in-app notification with personalized message
        try {
            \App\Models\AppNotification::create([
                'user_id' => $targetUserId,
                'type' => 'dhikr_group_reminder',
                'title' => $title,
                'body' => $message,
                'data' => [
                    'dhikr_group_id' => $group->id,
                    'group_name' => $group->name,
                    'target_user_id' => $targetUserId,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Error creating dhikr in-app notification: ' . $e->getMessage());
            return response()->json(['ok' => false, 'error' => 'Failed to create notification'], 500);
        }

        // Push to user's devices (respect preferences)
        try {
            if (!\App\Models\UserNotificationPreference::allowsGroup($targetUserId)) {
                $tokens = [];
            } else {
                $tokens = \App\Models\DeviceToken::where('user_id', $targetUserId)->pluck('device_token')->values()->all();
            }
            if (!empty($tokens)) {
                \App\Jobs\SendPushNotification::dispatch(
                    $tokens,
                    $title,
                    $message,
                    ['dhikr_group_id' => $group->id, 'type' => 'dhikr_group_reminder', 'target_user_id' => $targetUserId]
                );
                \Log::info("Personalized Dhikr reminder sent to user {$targetUserId} in group {$group->id}");
            } else {
                \Log::warning("No device tokens found for user {$targetUserId}");
            }
        } catch (\Exception $e) {
            \Log::error('Error dispatching dhikr push notification: ' . $e->getMessage());
            // Don't fail the request if push notification fails
        }

        return response()->json(['ok' => true, 'message' => 'Personalized dhikr reminder sent successfully']);
    }
}
