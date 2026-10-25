<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('facility_requests', 'cancellation_status')) {
                $table->string('cancellation_status', 24)->nullable()->index();
                $table->foreignId('cancellation_requested_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancellation_requested_at')->nullable();
                $table->foreignId('cancellation_reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancellation_reviewed_at')->nullable();
                $table->text('cancellation_notes')->nullable();
            }
            if (! Schema::hasColumn('facility_requests', 'igp_paid_by_id')) {
                $table->foreignId('igp_paid_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('igp_paid_at')->nullable();
                $table->string('igp_refund_status', 24)->nullable();
                $table->string('igp_refund_reference', 100)->nullable();
                $table->text('igp_refund_notes')->nullable();
                $table->foreignId('igp_refunded_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('igp_refunded_at')->nullable();
            }
        });

        Schema::table('request_change_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('request_change_requests', 'change_type')) {
                $table->string('change_type', 32)->nullable();
                $table->json('proposed_schedule')->nullable();
                $table->json('affected_user_ids')->nullable();
                $table->json('responses')->nullable();
                $table->timestamp('response_deadline_at')->nullable();
                $table->boolean('memorandum_priority')->default(false);
            }
        });

        Schema::table('revision_histories', function (Blueprint $table): void {
            if (! Schema::hasColumn('revision_histories', 'affected_user_ids')) {
                $table->json('affected_user_ids')->nullable();
                $table->json('responses')->nullable();
                $table->boolean('memorandum_priority')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('request_change_requests')) {
            Schema::table('request_change_requests', function (Blueprint $table): void {
                $columns = ['change_type', 'proposed_schedule', 'affected_user_ids', 'responses', 'response_deadline_at', 'memorandum_priority'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('request_change_requests', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('revision_histories')) {
            Schema::table('revision_histories', function (Blueprint $table): void {
                foreach (['affected_user_ids', 'responses', 'memorandum_priority'] as $column) {
                    if (Schema::hasColumn('revision_histories', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::table('facility_requests', function (Blueprint $table): void {
            foreach (['cancellation_requested_by_id', 'cancellation_reviewed_by_id', 'igp_paid_by_id', 'igp_refunded_by_id'] as $column) {
                if (Schema::hasColumn('facility_requests', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            $columns = ['cancellation_status', 'cancellation_requested_at', 'cancellation_reviewed_at', 'cancellation_notes', 'igp_paid_at', 'igp_refund_status', 'igp_refund_reference', 'igp_refund_notes', 'igp_refunded_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('facility_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
