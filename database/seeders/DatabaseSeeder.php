<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\ApplicationStatus;
use App\Enums\CallStatus;
use App\Models\Application;
use App\Models\Call;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class]);

        // Admin user
        $admin = User::factory()->create([
            'name'         => 'NTI Admin',
            'email'        => 'admin@nti.sk',
            'password'     => bcrypt('password'),
            'account_type' => AccountType::Admin,
            'is_active'    => true,
        ]);
        $admin->assignRole('admin');

        // Super admin
        $superAdmin = User::factory()->create([
            'name'         => 'Super Admin',
            'email'        => 'superadmin@nti.sk',
            'password'     => bcrypt('password'),
            'account_type' => AccountType::Admin,
            'is_active'    => true,
        ]);
        $superAdmin->assignRole('super_admin');

        // Demo student
        $student = User::factory()->create([
            'name'         => 'Jan Student',
            'email'        => 'student@nti.sk',
            'password'     => bcrypt('password'),
            'account_type' => AccountType::Student,
            'is_active'    => true,
        ]);
        $student->assignRole('student');

        // Demo mentor
        $mentor = User::factory()->create([
            'name'         => 'Maria Mentor',
            'email'        => 'mentor@nti.sk',
            'password'     => bcrypt('password'),
            'account_type' => AccountType::Mentor,
            'is_active'    => true,
        ]);
        $mentor->assignRole('mentor');

        // Programs
        $programA = Program::create([
            'slug'       => 'program-a-2026',
            'title'      => 'Program A – Vlastný nápad',
            'description' => 'Grantová inkubácia pre vlastný inovatívny nápad smerujúci k startupu alebo produktu.',
            'type'       => 'program_a',
            'is_active'  => true,
            'starts_at'  => '2026-01-01',
            'ends_at'    => '2026-12-31',
            'created_by' => $admin->id,
        ]);

        $programB = Program::create([
            'slug'       => 'program-b-2026',
            'title'      => 'Program B – Firemné zadanie',
            'description' => 'Spárovanie firemných zadaní so študentskými tímami.',
            'type'       => 'program_b',
            'is_active'  => true,
            'starts_at'  => '2026-01-01',
            'ends_at'    => '2026-12-31',
            'created_by' => $admin->id,
        ]);

        // Open call for Program A
        $call = Call::create([
            'program_id'    => $programA->id,
            'slug'          => 'vyzva-jar-2026',
            'title'         => 'Jarná výzva 2026',
            'description'   => 'Otvorená výzva pre inovatívne projekty na jar 2026.',
            'status'        => CallStatus::Open,
            'opens_at'      => now()->subWeek(),
            'closes_at'     => now()->addMonths(2),
            'min_team_size' => 1,
            'max_team_size' => 5,
            'created_by'    => $admin->id,
        ]);

        // Demo application
        Application::create([
            'public_id'         => (string) Str::ulid(),
            'call_id'           => $call->id,
            'user_id'           => $student->id,
            'status'            => ApplicationStatus::Draft,
            'title'             => 'Demo projekt – AI asistent pre školy',
            'description'       => 'Návrh AI asistenta pre zefektívnenie výučby na základných školách.',
            'problem_statement' => 'Učitelia trávia príliš veľa času administratívou namiesto výučby.',
            'proposed_solution' => 'Inteligentný asistent automatizuje tvorbu testov, zadaní a hodnotenie.',
        ]);
    }
}
