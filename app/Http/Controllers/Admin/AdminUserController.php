<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    /**
     * Get paginated list of users
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,suspended'],
        ]);

        $query = User::query();

        // Search functionality
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Status filter
        if (!empty($validated['status'])) {
            $query->where('users.status', $validated['status']);
        }

        // Add last activity (from user_daily_activity table)
        $query->leftJoin('user_daily_activity', function ($join) {
            $join->on('users.id', '=', 'user_daily_activity.user_id')
                 ->whereRaw('user_daily_activity.activity_date = (
                     SELECT MAX(activity_date) 
                     FROM user_daily_activity AS uda 
                     WHERE uda.user_id = users.id
                 )');
        });

        // Base columns
        $query->select([
            'users.*',
            'user_daily_activity.activity_date as last_activity_date',
            'user_daily_activity.first_opened_at as last_activity_time',
        ]);

        // Compute group-related counts via subqueries (memberships and created groups across khitma and dhikr)
        // IMPORTANT: addSelect AFTER select so we don't overwrite selected columns
        $query->addSelect([
            // memberships
            'group_memberships_count' => DB::raw('(
                SELECT COUNT(*) FROM group_members gm WHERE gm.user_id = users.id
            )'),
            'dhikr_memberships_count' => DB::raw('(
                SELECT COUNT(*) FROM dhikr_group_members dgm WHERE dgm.user_id = users.id
            )'),
            // created/owned
            'groups_created_count' => DB::raw('(
                SELECT COUNT(*) FROM groups g WHERE g.creator_id = users.id
            )'),
            'dhikr_groups_created_count' => DB::raw('(
                SELECT COUNT(*) FROM dhikr_groups dg WHERE dg.creator_id = users.id
            )'),
        ]);

        $perPage = $validated['per_page'] ?? 15;
        $users = $query->orderByDesc('users.created_at')->paginate($perPage);

        // Map items to include status, groups_created and groups_joined breakdown and avatar_url
        // Use robust per-user counts to avoid issues with DB driver not surfacing subselect aliases
        $items = collect($users->items())->map(function ($u) {
            $arr = is_array($u) ? $u : $u->toArray();
            $userId = (int)($arr['id'] ?? 0);

            // Compute joined and created counts directly
            $joined = (int) DB::table('group_members')->where('user_id', $userId)->count()
                    + (int) DB::table('dhikr_group_members')->where('user_id', $userId)->count();
            $created = (int) DB::table('groups')->where('creator_id', $userId)->count()
                     + (int) DB::table('dhikr_groups')->where('creator_id', $userId)->count();

            $arr['groups_joined'] = $joined;
            $arr['groups_created'] = $created;
            $arr['groups_count'] = $joined + $created;

            // Ensure status is exposed
            $arr['status'] = $arr['status'] ?? 'active';

            $arr['avatar_url'] = !empty($arr['avatar_path']) ? url('storage/' . $arr['avatar_path']) : null;
            return $arr;
        })->values();

        return response()->json([
            'ok' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $users->currentPage(),
                'total_pages' => $users->lastPage(),
                'total_items' => $users->total(),
                'items_per_page' => $users->perPage(),
            ],
        ]);
    }

    /**
     * Get specific user details
     */
    public function show(Request $request, int $id)
    {
        $user = User::with(['groups.group'])
            ->withCount('groups')
            ->findOrFail($id);

        // Compute joined (membership) counts
        $joinedKhitma = $user->groups()->whereHas('group', function ($q) {
            $q->where('type', 'khitma');
        })->count();
        $joinedDhikr = $user->groups()->whereHas('group', function ($q) {
            $q->where('type', 'dhikr');
        })->count();
        $joinedTotal = $joinedKhitma + $joinedDhikr;

        // Compute created counts
        $createdKhitma = DB::table('groups')->where('creator_id', $user->id)->count();
        $createdDhikr = DB::table('dhikr_groups')->where('creator_id', $user->id)->count();
        $createdTotal = $createdKhitma + $createdDhikr;

        // Get user statistics (total + breakdown)
        $stats = [
            'total_groups' => $joinedTotal + $createdTotal,
            'groups_joined_total' => $joinedTotal,
            'groups_created_total' => $createdTotal,
            'khitma_groups' => $joinedKhitma,
            'dhikr_groups' => $joinedDhikr,
        ];

        // Get recent activity
        $recentActivity = DB::table('user_daily_activity')
            ->where('user_id', $user->id)
            ->orderByDesc('activity_date')
            ->limit(30)
            ->get();

        return response()->json([
            'ok' => true,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'avatar_url' => $user->avatar_path ? url('storage/' . $user->avatar_path) : null,
                'created_at' => $user->created_at->toISOString(),
                'status' => $user->status ?? 'active',
                'stats' => $stats,
                'recent_activity' => $recentActivity,
            ],
        ]);
    }

    /**
     * Suspend user (placeholder - would need to add status column)
     */
    public function suspend(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        // Update status and revoke tokens
        $user->update(['status' => 'suspended']);
        $user->tokens()->delete();
        $user->refresh();

        return response()->json([
            'ok' => true,
            'message' => 'User suspended',
            'user' => [
                'id' => $user->id,
                'status' => $user->status,
            ],
        ]);
    }

    /**
     * Activate user (placeholder)
     */
    public function activate(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $user->update(['status' => 'active']);
        $user->refresh();

        return response()->json([
            'ok' => true,
            'message' => 'User activated',
            'user' => [
                'id' => $user->id,
                'status' => $user->status,
            ],
        ]);
    }

    /**
     * Delete user (with proper cleanup)
     */
    public function destroy(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        
        try {
            DB::beginTransaction();

            // 1) Revoke all API tokens (Sanctum)
            $user->tokens()->delete();

            // 2) Remove any DB-backed sessions for this user (no FK constraint on sessions.user_id)
            DB::table('sessions')->where('user_id', $user->id)->delete();

            // 3) Delete the user and rely on database FK cascades / null-on-delete
            // All related tables with proper constraints will be cleaned automatically.
            $user->delete();

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'User deleted; related data cleaned up via FK cascades',
                'deleted_user' => [
                    'id' => $id,
                    'username' => $user->username,
                    'email' => $user->email,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'ok' => false,
                'error' => 'Failed to delete user: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user statistics for dashboard
     */
    public function statistics(Request $request)
    {
        $totalUsers = User::count();
        $newUsersToday = User::whereDate('created_at', today())->count();
        $newUsersThisWeek = User::whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ])->count();
        $newUsersThisMonth = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Active users (users who opened app in last 7 days)
        $activeUsers = DB::table('user_daily_activity')
            ->where('activity_date', '>=', now()->subDays(7)->toDateString())
            ->distinct('user_id')
            ->count();

        return response()->json([
            'ok' => true,
            'statistics' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'new_users_today' => $newUsersToday,
                'new_users_week' => $newUsersThisWeek,
                'new_users_month' => $newUsersThisMonth,
            ],
        ]);
    }
}