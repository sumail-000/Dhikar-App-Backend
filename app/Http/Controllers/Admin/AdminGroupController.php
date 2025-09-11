<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\DhikrGroup;
use App\Models\KhitmaAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminGroupController extends Controller
{
    // GET /api/admin/groups/{id}
    public function showKhitma(int $id)
    {
        $g = Group::with(['members.user'])->findOrFail($id);
        $creator = $g->creator;

        $members = $g->members->map(function ($gm) {
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
        if ($g->type === 'khitma') {
            $completed = KhitmaAssignment::where('group_id', $g->id)
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
                'id' => $g->id,
                'name' => $g->name,
                'type' => $g->type,
                'creator' => $creator ? [
                    'id' => $creator->id,
                    'username' => $creator->username,
                    'email' => $creator->email,
                    'avatar_url' => $creator->avatar_path ? url('storage/'.$creator->avatar_path) : null,
                ] : null,
                'is_public' => (bool) $g->is_public,
                'auto_assign_enabled' => (bool) $g->auto_assign_enabled,
                'members_target' => $g->members_target,
                'members_count' => $g->members()->count(),
                'days_to_complete' => $g->days_to_complete,
                'start_date' => optional($g->start_date)->toDateString(),
                'created_at' => optional($g->created_at)->toDateTimeString(),
                'members' => $members,
                'summary' => $summary,
            ],
        ]);
    }

    // GET /api/admin/groups (khitma)
    public function indexKhitma(Request $request)
    {
        $groups = Group::query()
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
                'created_at' => optional($g->created_at)->toDateTimeString(),
                'summary' => $summary,
            ];
        })->values();

        return response()->json(['ok' => true, 'data' => $data]);
    }

    // GET /api/admin/dhikr-groups/{id}
    public function showDhikr(int $id)
    {
        $g = DhikrGroup::with(['members.user'])->findOrFail($id);
        $creator = $g->creator;

        $members = $g->members->map(function ($gm) {
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
                'id' => $g->id,
                'name' => $g->name,
                'type' => 'dhikr',
                'creator' => $creator ? [
                    'id' => $creator->id,
                    'username' => $creator->username,
                    'email' => $creator->email,
                    'avatar_url' => $creator->avatar_path ? url('storage/'.$creator->avatar_path) : null,
                ] : null,
                'is_public' => (bool) $g->is_public,
                'members_target' => $g->members_target,
                'members_count' => $g->members()->count(),
                'days_to_complete' => $g->days_to_complete,
                'created_at' => optional($g->created_at)->toDateTimeString(),
                'members' => $members,
                'summary' => null,
                'dhikr_target' => $g->dhikr_target,
                'dhikr_count' => $g->dhikr_count,
                'dhikr_title' => $g->dhikr_title,
                'dhikr_title_arabic' => $g->dhikr_title_arabic,
            ],
        ]);
    }

    // GET /api/admin/dhikr-groups
    public function indexDhikr(Request $request)
    {
        $groups = DhikrGroup::query()
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
                'created_at' => optional($g->created_at)->toDateTimeString(),
                'dhikr_target' => $g->dhikr_target,
                'dhikr_count' => $g->dhikr_count,
                'dhikr_title' => $g->dhikr_title,
                'dhikr_title_arabic' => $g->dhikr_title_arabic,
            ];
        })->values();

        return response()->json(['ok' => true, 'data' => $data]);
    }

    // POST /api/admin/groups/{id}/disable
    public function disableKhitma(int $id)
    {
        $g = Group::findOrFail($id);
        $g->is_public = false;
        $g->save();
        return response()->json(['ok' => true, 'message' => 'Group disabled']);
    }

    // POST /api/admin/groups/{id}/enable
    public function enableKhitma(int $id)
    {
        $g = Group::findOrFail($id);
        $g->is_public = true;
        $g->save();
        return response()->json(['ok' => true, 'message' => 'Group enabled']);
    }

    // DELETE /api/admin/groups/{id}
    public function deleteKhitma(int $id)
    {
        DB::transaction(function () use ($id) {
            $g = Group::findOrFail($id);
            $g->delete();
        });
        return response()->json(['ok' => true, 'message' => 'Group deleted']);
    }

    // POST /api/admin/dhikr-groups/{id}/disable
    public function disableDhikr(int $id)
    {
        $g = DhikrGroup::findOrFail($id);
        $g->is_public = false;
        $g->save();
        return response()->json(['ok' => true, 'message' => 'Dhikr group disabled']);
    }

    // POST /api/admin/dhikr-groups/{id}/enable
    public function enableDhikr(int $id)
    {
        $g = DhikrGroup::findOrFail($id);
        $g->is_public = true;
        $g->save();
        return response()->json(['ok' => true, 'message' => 'Dhikr group enabled']);
    }

    // DELETE /api/admin/dhikr-groups/{id}
    public function deleteDhikr(int $id)
    {
        DB::transaction(function () use ($id) {
            $g = DhikrGroup::findOrFail($id);
            $g->delete();
        });
        return response()->json(['ok' => true, 'message' => 'Dhikr group deleted']);
    }
}

