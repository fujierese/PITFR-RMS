<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'additional_roles')) {
                $table->json('additional_roles')->nullable();
            }
        });

        Schema::table('facility_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('facility_requests', 'supply_office_review_status')) {
                $table->string('supply_office_review_status', 24)->nullable()->index();
            }
            if (! Schema::hasColumn('facility_requests', 'supply_office_reviewed_by_id')) {
                $table->foreignId('supply_office_reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('facility_requests', 'supply_office_reviewed_at')) {
                $table->timestamp('supply_office_reviewed_at')->nullable();
            }
            if (! Schema::hasColumn('facility_requests', 'supply_office_review_notes')) {
                $table->text('supply_office_review_notes')->nullable();
            }
            if (! Schema::hasColumn('facility_requests', 'urgent_document_status')) {
                $table->string('urgent_document_status', 24)->nullable();
            }
            if (! Schema::hasColumn('facility_requests', 'igp_receipt_number')) {
                $table->string('igp_receipt_number', 100)->nullable();
            }
            if (! Schema::hasColumn('facility_requests', 'igp_payment_status')) {
                $table->string('igp_payment_status', 24)->nullable();
            }
        });

        if (Schema::hasTable('revision_histories')) {
            Schema::table('revision_histories', function (Blueprint $table): void {
                if (! Schema::hasColumn('revision_histories', 'response_deadline_at')) {
                    $table->timestamp('response_deadline_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('revision_histories') && Schema::hasColumn('revision_histories', 'response_deadline_at')) {
            Schema::table('revision_histories', fn (Blueprint $table) => $table->dropColumn('response_deadline_at'));
        }

        Schema::table('facility_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('facility_requests', 'supply_office_reviewed_by_id')) {
                $table->dropConstrainedForeignId('supply_office_reviewed_by_id');
            }
            $columns = array_filter([
                'supply_office_review_status',
                'supply_office_reviewed_at',
                'supply_office_review_notes',
                'urgent_document_status',
                'igp_receipt_number',
                'igp_payment_status',
            ], fn (string $column): bool => Schema::hasColumn('facility_requests', $column));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        if (Schema::hasColumn('users', 'additional_roles')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('additional_roles'));
        }
    }
};
