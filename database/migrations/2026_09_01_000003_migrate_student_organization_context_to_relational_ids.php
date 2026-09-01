<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_organizations')) {
            return;
        }

        Schema::table('student_organizations', function (Blueprint $table): void {
            if (! Schema::hasColumn('student_organizations', 'college_id')) {
                $table->foreignId('college_id')->nullable()->after('name')->constrained('colleges')->nullOnDelete();
            }

            if (! Schema::hasColumn('student_organizations', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('college_id')->constrained('departments')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('student_organizations', 'college_program')) {
            $this->migrateCollegeValues();
        }

        if (Schema::hasColumn('student_organizations', 'department')) {
            $this->migrateDepartmentValues();
        }

        Schema::table('student_organizations', function (Blueprint $table): void {
            if (Schema::hasColumn('student_organizations', 'college_program')) {
                $table->dropColumn('college_program');
            }

            if (Schema::hasColumn('student_organizations', 'department')) {
                $table->dropColumn('department');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('student_organizations')) {
            return;
        }

        if (! Schema::hasColumn('student_organizations', 'college_program')) {
            Schema::table('student_organizations', function (Blueprint $table): void {
                $table->string('college_program', 191)->nullable()->after('name');
            });
        }

        if (! Schema::hasColumn('student_organizations', 'department')) {
            Schema::table('student_organizations', function (Blueprint $table): void {
                $table->string('department', 191)->nullable()->after('college_program');
            });
        }

        foreach (DB::table('student_organizations')->get() as $organization) {
            $college = $organization->college_id ? DB::table('colleges')->where('id', $organization->college_id)->value('name') : null;
            $department = $organization->department_id ? DB::table('departments')->where('id', $organization->department_id)->value('name') : null;

            DB::table('student_organizations')
                ->where('id', $organization->id)
                ->update([
                    'college_program' => $college,
                    'department' => $department,
                ]);
        }
    }

    protected function migrateCollegeValues(): void
    {
        $organizations = DB::table('student_organizations')
            ->whereNotNull('college_program')
            ->get();

        foreach ($organizations as $organization) {
            $legacyValue = trim((string) $organization->college_program);
            if ($legacyValue === '') {
                continue;
            }

            $college = DB::table('colleges')
                ->where(function ($query) use ($legacyValue): void {
                    $value = mb_strtolower($legacyValue);
                    $query->whereRaw('LOWER(name) = ?', [$value])
                        ->orWhereRaw('LOWER(abbreviation) = ?', [$value])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%' . $value . '%']);
                })
                ->first();

            if (! $college) {
                continue;
            }

            DB::table('student_organizations')
                ->where('id', $organization->id)
                ->update(['college_id' => $college->id]);
        }
    }

    protected function migrateDepartmentValues(): void
    {
        $organizations = DB::table('student_organizations')
            ->whereNotNull('department')
            ->get();

        foreach ($organizations as $organization) {
            $legacyValue = trim((string) $organization->department);
            if ($legacyValue === '') {
                continue;
            }

            $query = DB::table('departments')->where(function ($departmentQuery) use ($legacyValue): void {
                $value = mb_strtolower($legacyValue);
                $departmentQuery->whereRaw('LOWER(name) = ?', [$value])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%' . $value . '%'])
                    ->orWhereRaw('LOWER(description) LIKE ?', ['%' . $value . '%']);
            });

            if (! empty($organization->college_id)) {
                $query->where('college_id', $organization->college_id);
            }

            $department = $query->first();

            if (! $department) {
                continue;
            }

            DB::table('student_organizations')
                ->where('id', $organization->id)
                ->update(['department_id' => $department->id]);
        }
    }
};
