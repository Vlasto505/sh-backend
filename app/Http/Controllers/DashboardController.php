<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Call;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user  = $request->user();
        $roles = $user->getRoleNames()->toArray();

        $isAdmin = array_intersect($roles, ['admin', 'super_admin']);

        if ($isAdmin) {
            return $this->adminDashboard($user);
        }

        if (in_array('mentor', $roles)) {
            return $this->mentorDashboard($user);
        }

        return $this->studentDashboard($user);
    }

    private function adminDashboard($user): Response
    {
        $stats = [
            'total_users'        => User::count(),
            'active_programs'    => Program::where('is_active', true)->count(),
            'open_calls'         => Call::where('status', 'open')->count(),
            'pending_apps'       => Application::where('status', 'submitted')->count(),
            'total_apps'         => Application::count(),
            'approved_apps'      => Application::where('status', 'approved')->count(),
        ];

        $recent_applications = Application::with(['user:id,name,email', 'call:id,title'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'public_id', 'title', 'status', 'user_id', 'call_id', 'submitted_at', 'created_at']);

        $recent_users = User::orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'name', 'email', 'account_type', 'is_active', 'created_at']);

        return Inertia::render('Dashboard', [
            'dashboardType'       => 'admin',
            'stats'               => $stats,
            'recentApplications'  => $recent_applications,
            'recentUsers'         => $recent_users,
        ]);
    }

    private function mentorDashboard($user): Response
    {
        $mentorships = $user->mentorships()
            ->with(['application:id,public_id,title,status,user_id', 'application.user:id,name,email'])
            ->where('status', 'active')
            ->get();

        return Inertia::render('Dashboard', [
            'dashboardType' => 'mentor',
            'mentorships'   => $mentorships,
        ]);
    }

    private function studentDashboard($user): Response
    {
        $myApplications = $user->applications()
            ->with(['call:id,title,status', 'call.program:id,title,type'])
            ->orderByDesc('created_at')
            ->get(['id', 'public_id', 'title', 'status', 'call_id', 'submitted_at', 'created_at']);

        $openCalls = Call::where('status', 'open')
            ->with(['program:id,title,type'])
            ->whereDate('closes_at', '>=', now())
            ->orderBy('closes_at')
            ->limit(5)
            ->get(['id', 'title', 'description', 'program_id', 'closes_at', 'min_team_size', 'max_team_size']);

        $team = $user->teams()->with(['leader:id,name', 'members:id,name'])->first();

        return Inertia::render('Dashboard', [
            'dashboardType'  => 'student',
            'myApplications' => $myApplications,
            'openCalls'      => $openCalls,
            'team'           => $team,
        ]);
    }
}
