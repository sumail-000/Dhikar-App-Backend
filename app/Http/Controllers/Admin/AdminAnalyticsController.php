<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Group;
use App\Models\DhikrGroup;
use App\Models\MotivationalVerse;
use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    /**
     * Get dashboard metrics
     */
    public function dashboard(Request $request)
    {
        // User metrics
        $totalUsers = User::count();
        $activeUsers = DB::table('user_daily_activity')
            ->where('activity_date', '>=', now()->subDays(7)->toDateString())
            ->distinct('user_id')
            ->count();
        $newUsersToday = User::whereDate('created_at', today())->count();

        // Group metrics
        $totalGroups = Group::count() + DhikrGroup::count();
        
        // Simplified active groups calculation - groups with recent activity
        $activeGroupIds = DB::table('group_members')
            ->join('user_daily_activity', 'group_members.user_id', '=', 'user_daily_activity.user_id')
            ->where('user_daily_activity.activity_date', '>=', now()->subDays(7)->toDateString())
            ->distinct('group_members.group_id')
            ->pluck('group_members.group_id');
            
        $activeDhikrGroupIds = DB::table('dhikr_group_members')
            ->join('user_daily_activity', 'dhikr_group_members.user_id', '=', 'user_daily_activity.user_id')
            ->where('user_daily_activity.activity_date', '>=', now()->subDays(7)->toDateString())
            ->distinct('dhikr_group_members.dhikr_group_id')
            ->pluck('dhikr_group_members.dhikr_group_id');
            
        $activeGroups = $activeGroupIds->count() + $activeDhikrGroupIds->count();

        // Verse metrics
        $totalVerses = MotivationalVerse::count();
        $activeVerses = MotivationalVerse::where('is_active', true)->count();

        // Notification metrics
        $notificationsSentToday = AppNotification::whereDate('created_at', today())->count();

        return response()->json([
            'ok' => true,
            'metrics' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'new_users_today' => $newUsersToday,
                'total_groups' => $totalGroups,
                'active_groups' => $activeGroups,
                'total_verses' => $totalVerses,
                'active_verses' => $activeVerses,
                'notifications_sent_today' => $notificationsSentToday,
            ],
        ]);
    }

    /**
     * Get user analytics
     */
    public function users(Request $request)
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $days = $validated['days'] ?? 30;
        $startDate = now()->subDays($days)->toDateString();

        // Daily user registrations
        $dailyRegistrations = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Daily active users
        $dailyActiveUsers = DB::table('user_daily_activity')
            ->selectRaw('activity_date as date, COUNT(DISTINCT user_id) as count')
            ->where('activity_date', '>=', $startDate)
            ->groupBy('activity_date')
            ->orderBy('activity_date')
            ->get();

        return response()->json([
            'ok' => true,
            'analytics' => [
                'daily_registrations' => $dailyRegistrations,
                'daily_active_users' => $dailyActiveUsers,
            ],
        ]);
    }

    /**
     * Get group analytics
     */
    public function groups(Request $request)
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $days = $validated['days'] ?? 30;
        $startDate = now()->subDays($days)->toDateString();

        // Daily group creations
        $dailyKhitmaGroups = Group::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', $startDate)
            ->where('type', 'khitma')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $dailyDhikrGroups = DhikrGroup::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Group completion rates (for Khitma groups)
        $khitmaCompletionStats = DB::table('groups')
            ->leftJoin('khitma_assignments', 'groups.id', '=', 'khitma_assignments.group_id')
            ->selectRaw('
                groups.id,
                groups.name,
                COUNT(khitma_assignments.id) as total_assignments,
                SUM(CASE WHEN khitma_assignments.status = "completed" THEN 1 ELSE 0 END) as completed_assignments
            ')
            ->where('groups.type', 'khitma')
            ->groupBy('groups.id', 'groups.name')
            ->having('total_assignments', '>', 0)
            ->get()
            ->map(function ($group) {
                $group->completion_rate = $group->total_assignments > 0 
                    ? round(($group->completed_assignments / $group->total_assignments) * 100, 2)
                    : 0;
                return $group;
            });

        return response()->json([
            'ok' => true,
            'analytics' => [
                'daily_khitma_groups' => $dailyKhitmaGroups,
                'daily_dhikr_groups' => $dailyDhikrGroups,
                'khitma_completion_stats' => $khitmaCompletionStats,
            ],
        ]);
    }

    /**
     * Get activity analytics
     */
    public function activity(Request $request)
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $days = $validated['days'] ?? 30;
        $startDate = now()->subDays($days)->toDateString();

        // Daily activity data
        $dailyActivity = DB::table('user_daily_activity')
            ->selectRaw('
                activity_date as date,
                COUNT(DISTINCT user_id) as active_users,
                SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) as app_opens,
                SUM(CASE WHEN reading = 1 THEN 1 ELSE 0 END) as reading_sessions
            ')
            ->where('activity_date', '>=', $startDate)
            ->groupBy('activity_date')
            ->orderBy('activity_date')
            ->get();

        // Notification engagement
        $notificationStats = AppNotification::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as sent,
                SUM(CASE WHEN read_at IS NOT NULL THEN 1 ELSE 0 END) as read
            ')
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($stat) {
                $stat->read_rate = $stat->sent > 0 ? round(($stat->read / $stat->sent) * 100, 2) : 0;
                return $stat;
            });

        return response()->json([
            'ok' => true,
            'analytics' => [
                'daily_activity' => $dailyActivity,
                'notification_stats' => $notificationStats,
            ],
        ]);
    }

    /**
     * Get most used verses
     */
    public function mostUsedVerses(Request $request)
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $limit = $validated['limit'] ?? 5;

        // Get verses with usage count from motivational_verse_user table
        $mostUsedVerses = DB::table('motivational_verses')
            ->leftJoin('motivational_verse_user', 'motivational_verses.id', '=', 'motivational_verse_user.motivational_verse_id')
            ->selectRaw('
                motivational_verses.id,
                motivational_verses.surah_name,
                motivational_verses.surah_number,
                motivational_verses.ayah_number,
                COUNT(motivational_verse_user.id) as usage_count
            ')
            ->where('motivational_verses.is_active', true)
            ->groupBy('motivational_verses.id', 'motivational_verses.surah_name', 'motivational_verses.surah_number', 'motivational_verses.ayah_number')
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get()
            ->map(function ($verse) {
                $verse->verse_reference = $verse->surah_name . ' ' . $verse->surah_number . ':' . $verse->ayah_number;
                return $verse;
            });

        return response()->json([
            'ok' => true,
            'verses' => $mostUsedVerses,
        ]);
    }

    /**
     * Get group distribution for dashboard
     */
    public function groupDistribution(Request $request)
    {
        // Count active Khitma groups
        $activeKhitmaGroups = Group::where('type', 'khitma')->count();
        
        // Count active Dhikr groups
        $activeDhikrGroups = DhikrGroup::count();
        
        // Calculate inactive groups (groups with no recent activity)
        $totalGroups = $activeKhitmaGroups + $activeDhikrGroups;
        
        // Get groups with recent member activity (last 30 days)
        $recentKhitmaActivity = DB::table('group_members')
            ->join('user_daily_activity', 'group_members.user_id', '=', 'user_daily_activity.user_id')
            ->where('user_daily_activity.activity_date', '>=', now()->subDays(30)->toDateString())
            ->distinct('group_members.group_id')
            ->count();
            
        $recentDhikrActivity = DB::table('dhikr_group_members')
            ->join('user_daily_activity', 'dhikr_group_members.user_id', '=', 'user_daily_activity.user_id')
            ->where('user_daily_activity.activity_date', '>=', now()->subDays(30)->toDateString())
            ->distinct('dhikr_group_members.dhikr_group_id')
            ->count();

        $activeGroupsWithActivity = $recentKhitmaActivity + $recentDhikrActivity;
        $inactiveGroups = max(0, $totalGroups - $activeGroupsWithActivity);

        return response()->json([
            'ok' => true,
            'distribution' => [
                [
                    'type' => 'Khitma Groups',
                    'count' => $activeKhitmaGroups,
                    'color' => '#3B82F6'
                ],
                [
                    'type' => 'Dhikr Groups', 
                    'count' => $activeDhikrGroups,
                    'color' => '#10B981'
                ],
                [
                    'type' => 'Inactive Groups',
                    'count' => $inactiveGroups,
                    'color' => '#F59E0B'
                ]
            ],
        ]);
    }
}